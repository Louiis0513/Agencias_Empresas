<?php

namespace App\Services;

use App\Models\AccountReceivable;
use App\Models\Bolsillo;
use App\Models\ComprobanteIngreso;
use App\Models\ComprobanteIngresoAplicacion;
use App\Models\ComprobanteIngresoDestino;
use App\Models\FormaPago;
use App\Models\Invoice;
use App\Models\InvoiceDetail;
use App\Models\MovimientoBolsillo;
use App\Models\Store;
use App\Models\TipoComprobante;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ComprobanteIngresoService
{
    public function __construct(
        protected CajaService $cajaService,
        protected InvoiceService $invoiceService,
        protected StoreTimezoneService $storeTimezoneService,
        protected TipoComprobanteService $tipoComprobanteService,
        protected CentroCostoService $centroCostoService,
    ) {}

    public function siguienteNumero(Store $store): string
    {
        $count = ComprobanteIngreso::deTienda($store->id)->count();

        return 'CI-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * CxC con saldo pendiente de un tercero (para UI abono).
     *
     * @return Collection<int, AccountReceivable>
     */
    public function cuentasPendientesDelTercero(Store $store, int $terceroId): Collection
    {
        return AccountReceivable::query()
            ->deTienda($store->id)
            ->where('tercero_id', $terceroId)
            ->where('balance', '>', 0)
            ->whereIn('status', [AccountReceivable::STATUS_PENDIENTE, AccountReceivable::STATUS_PARCIAL])
            ->with(['invoice:id,number,status', 'cuotas'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();
    }

    /**
     * Filas de cuota pendientes para la UI de abono (estilo Siigo).
     *
     * @return list<array{
     *   account_receivable_id: int,
     *   account_receivable_cuota_id: int,
     *   sequence: int,
     *   due_date: ?string,
     *   pending: float,
     *   invoice_id: ?int,
     *   invoice_number: ?string,
     *   label: string
     * }>
     */
    public function cuotasPendientesDelTercero(Store $store, int $terceroId): array
    {
        $cuentas = $this->cuentasPendientesDelTercero($store, $terceroId);
        $filas = [];

        foreach ($cuentas as $ar) {
            $doc = $ar->invoice?->number
                ? 'FV '.$ar->invoice->number
                : ('CxC #'.$ar->id);

            foreach ($ar->cuotas as $cuota) {
                $pending = max(0, (float) $cuota->amount - (float) $cuota->amount_paid);
                if ($pending <= 0.009) {
                    continue;
                }
                $filas[] = [
                    'account_receivable_id' => $ar->id,
                    'account_receivable_cuota_id' => $cuota->id,
                    'sequence' => (int) $cuota->sequence,
                    'due_date' => optional($cuota->due_date)->format('Y-m-d'),
                    'pending' => round($pending, 2),
                    'invoice_id' => $ar->invoice_id,
                    'invoice_number' => $ar->invoice?->number,
                    'label' => $doc.' — Cuota '.$cuota->sequence,
                ];
            }
        }

        usort($filas, function ($a, $b) {
            $da = $a['due_date'] ?? '9999-12-31';
            $db = $b['due_date'] ?? '9999-12-31';
            if ($da === $db) {
                return $a['account_receivable_cuota_id'] <=> $b['account_receivable_cuota_id'];
            }

            return $da <=> $db;
        });

        return $filas;
    }

    /**
     * Resuelve forma de pago (cartera) → bolsillo activo de la cuenta 11.
     */
    public function resolverBolsilloDesdeFormaPago(Store $store, int $formaPagoId): Bolsillo
    {
        $forma = FormaPago::query()
            ->deStore($store)
            ->with(['cuentaContable.bolsillo'])
            ->where('id', $formaPagoId)
            ->first();

        if (! $forma || ! $forma->en_uso) {
            throw new Exception('La forma de pago no es válida o no está en uso.');
        }

        if (! in_array($forma->aplica_a, [FormaPago::APLICA_CARTERA, FormaPago::APLICA_AMBOS], true)) {
            throw new Exception('La forma de pago "'.$forma->nombre.'" no aplica a cartera / recibo de caja.');
        }

        $bolsillo = $forma->cuentaContable?->bolsillo
            ?? Bolsillo::query()
                ->deTienda($store->id)
                ->where('cuenta_contable_id', $forma->cuenta_contable_id)
                ->activos()
                ->first();

        if (! $bolsillo || ! $bolsillo->is_active) {
            throw new Exception(
                'La forma de pago "'.$forma->nombre.'" no tiene un bolsillo de caja vinculado a su cuenta contable. Usa una forma con cuenta del disponible (11…).'
            );
        }

        return $bolsillo;
    }

    /**
     * Crea un comprobante de ingreso / recibo de caja.
     * - destinos legacy: [['bolsillo_id' => int, 'amount' => float, 'reference' => ?string], ...]
     * - condiciones_pago / destinos RC: [['forma_pago_id' => int, 'amount' => float], ...]
     * - aplicaciones: [['account_receivable_id' => int, 'amount' => float, 'account_receivable_cuota_id' => ?int], ...]
     * - Si sum(aplicaciones) < total destinos → monto_anticipo = diferencia (saldo a favor documentado).
     */
    public function crearComprobante(Store $store, int $userId, array $data): ComprobanteIngreso
    {
        return DB::transaction(function () use ($store, $userId, $data) {
            $destinos = $this->normalizarDestinos($store, $data);
            $aplicaciones = $this->normalizarAplicaciones($data['aplicaciones'] ?? []);
            $invoiceId = isset($data['invoice_id']) ? (int) $data['invoice_id'] : null;
            $modo = isset($data['modo']) ? trim((string) $data['modo']) : null;
            if ($modo === '') {
                $modo = null;
            }

            $totalDestinos = array_sum(array_map(fn ($d) => (float) ($d['amount'] ?? 0), $destinos));
            if ($totalDestinos <= 0) {
                throw new Exception('Debe indicar al menos una condición de pago (o destino) con monto mayor a cero.');
            }

            $totalAplicaciones = array_sum(array_map(fn ($a) => (float) ($a['amount'] ?? 0), $aplicaciones));
            if ($totalAplicaciones - $totalDestinos > 0.01) {
                throw new Exception("La suma de abonos ({$totalAplicaciones}) no puede superar el valor recibido ({$totalDestinos}).");
            }

            // Legacy callers (sin modo RC): siguen exigiendo igualdad exacta aplicaciones ↔ destinos.
            $esFlujoRc = array_key_exists('tipo_comprobante_id', $data) || $modo !== null || ! empty($data['condiciones_pago']);
            if (! $esFlujoRc && count($aplicaciones) > 0 && abs($totalAplicaciones - $totalDestinos) > 0.01) {
                throw new Exception("La suma de aplicaciones a cuentas por cobrar ({$totalAplicaciones}) debe coincidir con el total del ingreso ({$totalDestinos}).");
            }

            $montoAnticipo = max(0, round($totalDestinos - $totalAplicaciones, 2));

            $customerId = $data['tercero_id'] ?? $data['customer_id'] ?? null;
            $customerId = $customerId !== null && $customerId !== '' ? (int) $customerId : null;

            // Inferir / forzar type y modo.
            if ($modo === ComprobanteIngreso::MODO_ANTICIPO) {
                $type = ComprobanteIngreso::TYPE_ANTICIPO;
                if (! $customerId) {
                    throw new Exception('El anticipo requiere un cliente.');
                }
                if (count($aplicaciones) > 0) {
                    throw new Exception('Un anticipo no puede aplicar a cuentas por cobrar.');
                }
                $montoAnticipo = $totalDestinos;
            } elseif ($modo === ComprobanteIngreso::MODO_ABONO) {
                $modo = ComprobanteIngreso::MODO_ABONO;
                if (count($aplicaciones) === 0) {
                    // Valor recibido sin abonos = anticipo / saldo a favor (estilo Siigo).
                    $type = ComprobanteIngreso::TYPE_ANTICIPO;
                    if (! $customerId) {
                        throw new Exception('El anticipo / saldo a favor requiere un cliente.');
                    }
                    $montoAnticipo = $totalDestinos;
                } else {
                    $type = ComprobanteIngreso::TYPE_COBRO_CUENTA;
                }
            } elseif ($invoiceId > 0) {
                $type = ComprobanteIngreso::TYPE_PAGO_FACTURA;
                $factura = Invoice::where('id', $invoiceId)->where('store_id', $store->id)->firstOrFail();
                if ($customerId === null) {
                    $customerId = $factura->tercero_id;
                }
            } elseif (count($aplicaciones) > 0) {
                $type = ComprobanteIngreso::TYPE_COBRO_CUENTA;
                $modo = $modo ?: ComprobanteIngreso::MODO_ABONO;
            } else {
                $type = ComprobanteIngreso::TYPE_INGRESO_MANUAL;
                $modo = $modo ?: ComprobanteIngreso::MODO_OTRO_INGRESO;
            }

            if ($type === ComprobanteIngreso::TYPE_COBRO_CUENTA) {
                if (count($aplicaciones) === 1 && ! $customerId) {
                    $ar = AccountReceivable::where('id', $aplicaciones[0]['account_receivable_id'])->where('store_id', $store->id)->first();
                    if ($ar) {
                        $customerId = $ar->tercero_id;
                    }
                }
                if (! $customerId) {
                    throw new Exception('El abono a deuda requiere un cliente.');
                }
                foreach ($aplicaciones as $ap) {
                    $arId = (int) ($ap['account_receivable_id'] ?? 0);
                    if ($arId <= 0) {
                        continue;
                    }
                    $ar = AccountReceivable::where('id', $arId)->where('store_id', $store->id)->first();
                    if (! $ar || (int) $ar->tercero_id !== (int) $customerId) {
                        throw new Exception('Todas las cuentas por cobrar aplicadas deben pertenecer al mismo cliente.');
                    }
                }
            }

            $tipo = $this->resolverTipoRc($store, $data['tipo_comprobante_id'] ?? null, forzarRc: $esFlujoRc);
            $centroCostoId = $this->resolverCentroCosto($store, $tipo, $data['centro_costo_id'] ?? null);

            $primeraFormaId = null;
            foreach ($destinos as $d) {
                if (! empty($d['forma_pago_id'])) {
                    $primeraFormaId = (int) $d['forma_pago_id'];
                    break;
                }
            }
            $formaPagoId = $this->resolverFormaPago($store, $data['forma_pago_id'] ?? $primeraFormaId);

            $number = $tipo
                ? $this->tipoComprobanteService->tomarSiguienteNumero($store, $tipo)
                : $this->siguienteNumero($store);

            $comprobanteData = [
                'store_id' => $store->id,
                'number' => $number,
                'total_amount' => $totalDestinos,
                'monto_anticipo' => $montoAnticipo,
                'date' => $data['date'] ?? $this->storeTimezoneService->nowForStore($store)->toDateString(),
                'notes' => $data['notes'] ?? null,
                'type' => $type,
                'modo' => $modo,
                'tipo_comprobante_id' => $tipo?->id,
                'forma_pago_id' => $formaPagoId,
                'centro_costo_id' => $centroCostoId,
                'tercero_id' => $customerId,
                'user_id' => $userId,
            ];
            if ($invoiceId > 0) {
                $comprobanteData['invoice_id'] = $invoiceId;
            }

            $comprobante = ComprobanteIngreso::create($comprobanteData);

            foreach ($destinos as $d) {
                $amount = (float) ($d['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $bolsilloId = (int) ($d['bolsillo_id'] ?? 0);
                if (! $bolsilloId) {
                    throw new Exception('Debe indicar bolsillo_id para cada destino.');
                }

                ComprobanteIngresoDestino::create([
                    'comprobante_ingreso_id' => $comprobante->id,
                    'bolsillo_id' => $bolsilloId,
                    'forma_pago_id' => isset($d['forma_pago_id']) ? (int) $d['forma_pago_id'] : null,
                    'amount' => $amount,
                    'reference' => $d['reference'] ?? null,
                ]);

                $this->cajaService->registrarMovimiento($store, $userId, [
                    'bolsillo_id' => $bolsilloId,
                    'type' => MovimientoBolsillo::TYPE_INCOME,
                    'amount' => $amount,
                    'description' => 'Recibo de caja '.$comprobante->number,
                    'comprobante_ingreso_id' => $comprobante->id,
                ]);
            }

            foreach ($aplicaciones as $ap) {
                $accountReceivableId = (int) ($ap['account_receivable_id'] ?? 0);
                $amount = (float) ($ap['amount'] ?? 0);
                $cuotaId = isset($ap['account_receivable_cuota_id']) ? (int) $ap['account_receivable_cuota_id'] : null;
                if ($accountReceivableId <= 0 || $amount <= 0) {
                    continue;
                }

                ComprobanteIngresoAplicacion::create([
                    'comprobante_ingreso_id' => $comprobante->id,
                    'account_receivable_id' => $accountReceivableId,
                    'account_receivable_cuota_id' => $cuotaId ?: null,
                    'amount' => $amount,
                ]);

                $this->aplicarCobroACuentaPorCobrar($store, $accountReceivableId, $amount, $cuotaId ?: null);
            }

            return $comprobante->load([
                'destinos.bolsillo.cuentaContable',
                'destinos.formaPago',
                'aplicaciones.accountReceivable.invoice',
                'aplicaciones.cuota',
                'invoice',
                'tipoComprobante',
                'formaPago',
                'centroCosto',
                'tercero',
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{bolsillo_id: int, forma_pago_id: ?int, amount: float, reference: ?string}>
     */
    protected function normalizarDestinos(Store $store, array $data): array
    {
        $condiciones = $data['condiciones_pago'] ?? null;
        if (is_array($condiciones) && $condiciones !== []) {
            $out = [];
            foreach ($condiciones as $c) {
                $amount = (float) ($c['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                $bolsilloId = (int) ($c['bolsillo_id'] ?? 0);
                $fpId = isset($c['forma_pago_id']) && $c['forma_pago_id'] !== '' ? (int) $c['forma_pago_id'] : null;

                // RC v1.5+: forma de pago en UI = bolsillo (disponible).
                if ($bolsilloId > 0) {
                    $bolsillo = $this->resolverBolsilloParaRc($store, $bolsilloId);
                    $out[] = [
                        'bolsillo_id' => $bolsillo->id,
                        'forma_pago_id' => $this->resolverFormaPagoDesdeBolsillo($store, $bolsillo) ?? $fpId,
                        'amount' => $amount,
                        'reference' => $c['reference'] ?? null,
                    ];

                    continue;
                }

                // Compat: condiciones con forma_pago_id del catálogo.
                if ($fpId > 0) {
                    $bolsillo = $this->resolverBolsilloDesdeFormaPago($store, $fpId);
                    $out[] = [
                        'bolsillo_id' => $bolsillo->id,
                        'forma_pago_id' => $fpId,
                        'amount' => $amount,
                        'reference' => $c['reference'] ?? null,
                    ];
                }
            }

            return $out;
        }

        $destinos = $data['destinos'] ?? [];
        $out = [];
        foreach ($destinos as $d) {
            $amount = (float) ($d['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $fpId = isset($d['forma_pago_id']) && $d['forma_pago_id'] !== '' ? (int) $d['forma_pago_id'] : null;
            $bolsilloId = isset($d['bolsillo_id']) ? (int) $d['bolsillo_id'] : 0;

            if ($fpId && ! $bolsilloId) {
                $bolsillo = $this->resolverBolsilloDesdeFormaPago($store, $fpId);
                $bolsilloId = $bolsillo->id;
            }

            if (! $bolsilloId) {
                continue;
            }

            if (! $fpId) {
                $bolsillo = Bolsillo::query()->deTienda($store->id)->where('id', $bolsilloId)->first();
                if ($bolsillo) {
                    $fpId = $this->resolverFormaPagoDesdeBolsillo($store, $bolsillo);
                }
            }

            $out[] = [
                'bolsillo_id' => $bolsilloId,
                'forma_pago_id' => $fpId,
                'amount' => $amount,
                'reference' => $d['reference'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Bolsillo activo de la tienda con cuenta contable (destino RC).
     */
    public function resolverBolsilloParaRc(Store $store, int $bolsilloId): Bolsillo
    {
        $bolsillo = Bolsillo::query()
            ->deTienda($store->id)
            ->activos()
            ->with('cuentaContable')
            ->where('id', $bolsilloId)
            ->first();

        if (! $bolsillo) {
            throw new Exception('La forma de pago (caja) no es válida o no está activa.');
        }

        if (! $bolsillo->cuenta_contable_id || ! $bolsillo->cuentaContable) {
            throw new Exception(
                'El bolsillo "'.$bolsillo->name.'" no tiene cuenta contable vinculada. Vincula una cuenta del disponible (11…).'
            );
        }

        return $bolsillo;
    }

    /**
     * Si existe forma de pago del catálogo con la misma cuenta, la asocia (impresión / futuro asiento).
     */
    protected function resolverFormaPagoDesdeBolsillo(Store $store, Bolsillo $bolsillo): ?int
    {
        if (! $bolsillo->cuenta_contable_id) {
            return null;
        }

        $forma = FormaPago::query()
            ->deStore($store)
            ->enUso()
            ->where('cuenta_contable_id', $bolsillo->cuenta_contable_id)
            ->whereIn('aplica_a', [FormaPago::APLICA_CARTERA, FormaPago::APLICA_AMBOS])
            ->orderBy('codigo')
            ->first();

        return $forma?->id;
    }

    /**
     * @param  mixed  $aplicaciones
     * @return list<array{account_receivable_id: int, account_receivable_cuota_id: ?int, amount: float}>
     */
    protected function normalizarAplicaciones(mixed $aplicaciones): array
    {
        if (! is_array($aplicaciones)) {
            return [];
        }
        $out = [];
        foreach ($aplicaciones as $a) {
            $amount = (float) ($a['amount'] ?? 0);
            $arId = (int) ($a['account_receivable_id'] ?? 0);
            if ($amount <= 0 || $arId <= 0) {
                continue;
            }
            $cuotaId = isset($a['account_receivable_cuota_id']) && $a['account_receivable_cuota_id'] !== ''
                ? (int) $a['account_receivable_cuota_id']
                : null;
            $out[] = [
                'account_receivable_id' => $arId,
                'account_receivable_cuota_id' => $cuotaId ?: null,
                'amount' => $amount,
            ];
        }

        return $out;
    }

    /**
     * @param  bool  $forzarRc  Si true y no hay id, usa tipo RC por defecto. Si false y no hay id, deja null (legacy CI-).
     */
    protected function resolverTipoRc(Store $store, mixed $tipoId, bool $forzarRc = false): ?TipoComprobante
    {
        if ($tipoId !== null && $tipoId !== '') {
            $tipo = TipoComprobante::query()
                ->deStore($store)
                ->where('id', (int) $tipoId)
                ->first();
            if (! $tipo || $tipo->familia !== TipoComprobante::FAMILIA_RC) {
                throw new Exception('El tipo de comprobante debe ser un recibo de caja (RC) activo de esta tienda.');
            }
            if (! $tipo->activo) {
                throw new Exception('El tipo de recibo de caja está inactivo.');
            }

            return $tipo;
        }

        if (! $forzarRc) {
            return null;
        }

        $this->tipoComprobanteService->asegurarTiposPorDefecto($store);
        $tipo = $this->tipoComprobanteService->tipoPorDefecto($store, TipoComprobante::FAMILIA_RC);
        if (! $tipo) {
            throw new Exception('No hay un tipo de recibo de caja (RC) configurado para esta tienda.');
        }

        return $tipo;
    }

    protected function resolverCentroCosto(Store $store, ?TipoComprobante $tipo, mixed $centroId): ?int
    {
        if (! $tipo || ! $tipo->manejaCentroCostos()) {
            return null;
        }

        $centroId = ($centroId === null || $centroId === '') ? null : (int) $centroId;
        if (! $centroId && $tipo->centro_costo_default_id) {
            $centroId = (int) $tipo->centro_costo_default_id;
        }

        if ($tipo->exigeCentroCostos() && ! $centroId) {
            throw new Exception('Este tipo de recibo de caja exige un centro de costos.');
        }

        if (! $centroId) {
            return null;
        }

        $this->centroCostoService->assertSubcentroUsable($store, $centroId);

        return $centroId;
    }

    protected function resolverFormaPago(Store $store, mixed $formaPagoId): ?int
    {
        if ($formaPagoId === null || $formaPagoId === '') {
            return null;
        }

        $forma = FormaPago::query()
            ->deStore($store)
            ->where('id', (int) $formaPagoId)
            ->first();
        if (! $forma || ! $forma->en_uso) {
            throw new Exception('La forma de pago no es válida o no está en uso.');
        }

        return $forma->id;
    }

    /**
     * Crea un comprobante de ingreso por pago de factura (tipo PAGO_FACTURA).
     *
     * @deprecated Usar crearComprobante() con invoice_id, notes y destinos en $data.
     *
     * @param  array  $payments  [ ['payment_method' => 'CASH'|'CARD'|'TRANSFER', 'amount' => float, 'bolsillo_id' => int ], ... ]
     */
    public function crearComprobantePorPagoFactura(Store $store, int $userId, Invoice $factura, array $payments): ComprobanteIngreso
    {
        $destinos = [];
        foreach ($payments as $p) {
            $amount = (float) ($p['amount'] ?? 0);
            $bolsilloId = (int) ($p['bolsillo_id'] ?? 0);
            if ($amount <= 0 || ! $bolsilloId) {
                continue;
            }
            $destinos[] = [
                'bolsillo_id' => $bolsilloId,
                'amount' => $amount,
                'reference' => null,
            ];
        }
        if (empty($destinos)) {
            throw new Exception('Debe indicar al menos un pago (bolsillo y monto mayor a cero).');
        }

        return $this->crearComprobante($store, $userId, [
            'invoice_id' => $factura->id,
            'notes' => 'Pago Factura #'.$factura->id,
            'destinos' => $destinos,
        ]);
    }

    /**
     * Reduce el balance de la cuenta por cobrar y aplica el monto a las cuotas.
     * Si $cuotaId viene, aplica primero a esa cuota; el resto (si hubiera) sigue FIFO.
     */
    protected function aplicarCobroACuentaPorCobrar(Store $store, int $accountReceivableId, float $amount, ?int $cuotaId = null): void
    {
        $account = AccountReceivable::where('id', $accountReceivableId)
            ->where('store_id', $store->id)
            ->with('cuotas')
            ->lockForUpdate()
            ->firstOrFail();

        if ($account->isPagado()) {
            throw new Exception('La cuenta por cobrar ya está saldada.');
        }

        $balance = (float) $account->balance;
        if ($amount > $balance + 0.009) {
            throw new Exception("El monto a aplicar ({$amount}) no puede ser mayor al saldo pendiente ({$balance}).");
        }

        $remaining = $amount;
        $cuotas = $account->cuotas->sortBy('due_date')->values();

        if ($cuotaId) {
            $target = $cuotas->firstWhere('id', $cuotaId);
            if (! $target || (int) $target->account_receivable_id !== (int) $account->id) {
                throw new Exception('La cuota indicada no pertenece a la cuenta por cobrar.');
            }
            $pending = (float) $target->amount - (float) $target->amount_paid;
            if ($amount > $pending + 0.009) {
                throw new Exception("El abono ({$amount}) supera el saldo de la cuota ({$pending}).");
            }
            $aplicar = min($remaining, max(0, $pending));
            if ($aplicar > 0) {
                $target->increment('amount_paid', $aplicar);
                $remaining -= $aplicar;
            }
            $cuotas = $cuotas->reject(fn ($c) => (int) $c->id === (int) $cuotaId)->values();
        }

        foreach ($cuotas as $cuota) {
            if ($remaining <= 0.009) {
                break;
            }
            $pending = (float) $cuota->amount - (float) $cuota->amount_paid;
            if ($pending <= 0) {
                continue;
            }
            $aplicar = min($remaining, $pending);
            $cuota->increment('amount_paid', $aplicar);
            $remaining -= $aplicar;
        }

        $newBalance = max(0, (float) $account->balance - $amount);
        $account->balance = $newBalance;
        $account->status = $newBalance <= 0 ? AccountReceivable::STATUS_PAGADO : AccountReceivable::STATUS_PARCIAL;
        $account->save();

        $invoice = $account->invoice;
        if ($invoice) {
            $bolsilloIds = $account->comprobanteIngresoAplicaciones()
                ->with('comprobanteIngreso.destinos')
                ->get()
                ->flatMap(fn (ComprobanteIngresoAplicacion $a) => $a->comprobanteIngreso->destinos->pluck('bolsillo_id'))
                ->unique()
                ->filter()
                ->values()
                ->all();
            $paymentMethod = $this->invoiceService->derivarMetodoPagoDesdeBolsillos($store, $bolsilloIds) ?? 'CASH';
            $invoice->update(array_filter([
                'payment_method' => $paymentMethod,
                'status' => $newBalance <= 0 ? 'PAID' : null,
            ]));
        }
    }

    public function listar(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = ComprobanteIngreso::deTienda($store->id)
            ->with(['customer', 'destinos.bolsillo', 'aplicaciones.accountReceivable.invoice'])
            ->orderByDesc('created_at');

        if (! empty($filtros['type'])) {
            $query->where('type', $filtros['type']);
        }
        if (! empty($filtros['customer_id'])) {
            $query->where('tercero_id', $filtros['customer_id']);
        }

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    /**
     * Líneas de ingreso a bolsillos para la vista Movimientos (una fila por destino).
     * No altera listar(): lectura dedicada con filtros propios.
     */
    public function listarDestinosPaginadosParaMovimientos(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = ComprobanteIngresoDestino::query()
            ->select('comprobante_ingreso_destinos.*')
            ->join('comprobantes_ingreso', 'comprobante_ingreso_destinos.comprobante_ingreso_id', '=', 'comprobantes_ingreso.id')
            ->where('comprobantes_ingreso.store_id', $store->id)
            ->whereNull('comprobantes_ingreso.reversed_at');

        $this->applyDestinosMovimientosFiltros($query, $filtros);

        $query->orderByDesc('comprobantes_ingreso.created_at')
            ->orderByDesc('comprobante_ingreso_destinos.id');

        return $query
            ->with(['comprobanteIngreso', 'bolsillo'])
            ->paginate($filtros['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * Destinos de ingreso sin paginar (exportación Excel). Mismos filtros que {@see listarDestinosPaginadosParaMovimientos}.
     *
     * @return EloquentCollection<int, ComprobanteIngresoDestino>
     */
    public function coleccionDestinosParaExportacionMovimientos(Store $store, array $filtros = []): EloquentCollection
    {
        $query = ComprobanteIngresoDestino::query()
            ->select('comprobante_ingreso_destinos.*')
            ->join('comprobantes_ingreso', 'comprobante_ingreso_destinos.comprobante_ingreso_id', '=', 'comprobantes_ingreso.id')
            ->where('comprobantes_ingreso.store_id', $store->id)
            ->whereNull('comprobantes_ingreso.reversed_at');

        $this->applyDestinosMovimientosFiltros($query, $filtros);

        $query->orderByDesc('comprobantes_ingreso.created_at')
            ->orderByDesc('comprobante_ingreso_destinos.id');

        return $query
            ->with(['comprobanteIngreso.customer', 'comprobanteIngreso.user', 'bolsillo'])
            ->get();
    }

    /**
     * Suma de ingresos por bolsillo en el período/filtros de Movimientos.
     *
     * @return \Illuminate\Support\Collection<int, object{bolsillo_name: string, total: float}>
     */
    public function totalesIngresosPorBolsilloMovimientos(Store $store, array $filtros = []): \Illuminate\Support\Collection
    {
        $query = ComprobanteIngresoDestino::query()
            ->join('comprobantes_ingreso', 'comprobante_ingreso_destinos.comprobante_ingreso_id', '=', 'comprobantes_ingreso.id')
            ->join('bolsillos', 'comprobante_ingreso_destinos.bolsillo_id', '=', 'bolsillos.id')
            ->where('comprobantes_ingreso.store_id', $store->id)
            ->where('bolsillos.store_id', $store->id)
            ->whereNull('comprobantes_ingreso.reversed_at');

        $this->applyDestinosMovimientosFiltros($query, $filtros);

        return $query
            ->selectRaw('bolsillos.name as bolsillo_name, SUM(comprobante_ingreso_destinos.amount) as total')
            ->groupBy('bolsillos.id', 'bolsillos.name')
            ->orderBy('bolsillos.name')
            ->get()
            ->map(fn ($row) => (object) [
                'bolsillo_name' => (string) $row->bolsillo_name,
                'total' => (float) $row->total,
            ]);
    }

    /**
     * Suma montos de destinos de ingreso con los mismos criterios que la tabla Movimientos (agregado en BD).
     */
    public function sumarMontosDestinosMovimientos(Store $store, array $filtros = []): float
    {
        $query = ComprobanteIngresoDestino::query()
            ->join('comprobantes_ingreso', 'comprobante_ingreso_destinos.comprobante_ingreso_id', '=', 'comprobantes_ingreso.id')
            ->where('comprobantes_ingreso.store_id', $store->id)
            ->whereNull('comprobantes_ingreso.reversed_at');

        $this->applyDestinosMovimientosFiltros($query, $filtros);

        return (float) $query->sum('comprobante_ingreso_destinos.amount');
    }

    private function applyDestinosMovimientosFiltros(Builder $query, array $filtros): void
    {
        $tz = ! empty($filtros['timezone']) ? (string) $filtros['timezone'] : (string) config('app.timezone');

        if (! empty($filtros['fecha_desde'])) {
            $start = Carbon::parse($filtros['fecha_desde'], $tz)->startOfDay()->utc();
            $query->where('comprobantes_ingreso.created_at', '>=', $start);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $end = Carbon::parse($filtros['fecha_hasta'], $tz)->endOfDay()->utc();
            $query->where('comprobantes_ingreso.created_at', '<=', $end);
        }

        $search = isset($filtros['search']) ? trim((string) $filtros['search']) : '';
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('comprobante_ingreso_destinos.reference', 'like', $term)
                    ->orWhere('comprobantes_ingreso.notes', 'like', $term)
                    ->orWhere('comprobantes_ingreso.number', 'like', $term);
            });
        }

        $bolsilloIds = array_values(array_unique(array_filter(array_map('intval', $filtros['bolsillo_ids'] ?? []))));
        if ($bolsilloIds !== []) {
            $query->whereIn('comprobante_ingreso_destinos.bolsillo_id', $bolsilloIds);
        }

        $userIds = array_values(array_unique(array_filter(array_map('intval', $filtros['empleado_user_ids'] ?? []))));
        if ($userIds !== []) {
            $query->whereIn('comprobantes_ingreso.user_id', $userIds);
        }

        if (! empty($filtros['customer_id'])) {
            $query->where('comprobantes_ingreso.tercero_id', (int) $filtros['customer_id']);
        }
    }

    public function obtener(Store $store, int $id): ComprobanteIngreso
    {
        return ComprobanteIngreso::deTienda($store->id)
            ->with([
                'customer',
                'tercero',
                'user',
                'destinos.bolsillo.cuentaContable',
                'destinos.formaPago',
                'aplicaciones.accountReceivable.invoice',
                'aplicaciones.cuota',
                'invoice.details',
                'tipoComprobante',
                'formaPago',
                'centroCosto',
            ])
            ->findOrFail($id);
    }

    /**
     * Variables compartidas entre la vista detalle y el PDF (el modelo debe venir de obtener()).
     *
     * @return array{
     *     cur: string,
     *     c: ComprobanteIngreso,
     *     customer: \App\Models\Tercero|null,
     *     dirTel: string,
     *     ciudadEmpresa: string,
     *     logoAbsPath: string|null,
     *     detalleSubtitulo: string,
     *     valorEnLetras: string,
     *     tipoEtiqueta: string,
     *     condicionPago: string,
     *     condicionPagoMonto: float,
     *     totalItems: int,
     *     detalleLineasVista: list<array{item: int, documento: string, descripcion: string, valor: float}>
     * }
     */
    public function datosVistaComprobante(Store $store, ComprobanteIngreso $comprobanteIngreso): array
    {
        $cur = $store->currency ?? 'COP';
        $c = $comprobanteIngreso;
        $customer = $c->customer ?? $c->tercero;

        $telText = '';
        if ($store->phone) {
            $telText = __('Tel.: :n', ['n' => $store->phone]);
        } elseif ($store->mobile ?? null) {
            $telText = __('Cel.: :n', ['n' => $store->mobile]);
        }
        $dirTel = trim(implode(' ', array_filter([$store->address ?? '', $telText])));

        $ciudadEmpresa = trim(implode(' - ', array_filter([
            $store->city ?? null,
            $store->country ?? null,
        ])));

        $logoAbsPath = null;
        if (filled($store->logo_path)) {
            $candidate = storage_path('app/public/'.$store->logo_path);
            if (is_file($candidate)) {
                $logoAbsPath = $candidate;
            }
        }

        $detalleLineas = collect();

        // Preferir líneas por documento/cuota (estilo Siigo: Ítem | Documento | Descripción | Valor).
        if ($c->aplicaciones->isNotEmpty() && $c->type === ComprobanteIngreso::TYPE_COBRO_CUENTA) {
            $detalleLineas = $c->aplicaciones->map(function (ComprobanteIngresoAplicacion $ap) {
                $invoice = $ap->accountReceivable?->invoice;
                $doc = $invoice
                    ? __('Factura #:id', ['id' => $invoice->id])
                    : __('CxC #:id', ['id' => $ap->account_receivable_id]);
                if ($ap->cuota) {
                    $doc .= ' - '.__('Cuota :n', ['n' => $ap->cuota->sequence]);
                }

                return (object) [
                    'documento' => $doc,
                    'descripcion' => __('Abono'),
                    'valor' => (float) $ap->amount,
                ];
            });
            if ((float) $c->monto_anticipo > 0.009) {
                $detalleLineas = $detalleLineas->push((object) [
                    'documento' => $c->number.' - '.__('Cuota :n', ['n' => 1]),
                    'descripcion' => __('Anticipo'),
                    'valor' => (float) $c->monto_anticipo,
                ]);
            }
        } elseif ($c->type === ComprobanteIngreso::TYPE_ANTICIPO) {
            $detalleLineas = collect([(object) [
                'documento' => $c->number.' - '.__('Cuota :n', ['n' => 1]),
                'descripcion' => __('Anticipo'),
                'valor' => (float) $c->total_amount,
            ]]);
        } elseif ($c->invoice_id && $c->invoice && $c->invoice->details->isNotEmpty()) {
            $docFactura = __('Factura #:id', ['id' => $c->invoice_id]);
            $detalleLineas = $c->invoice->details->map(fn (InvoiceDetail $linea) => (object) [
                'documento' => $docFactura,
                'descripcion' => $linea->receipt_description ?? format_product_name_for_receipt($linea->product_name),
                'valor' => (float) $linea->subtotal,
            ]);
        } elseif ($c->destinos->pluck('reference')->filter(fn ($r) => filled($r))->isNotEmpty()) {
            foreach ($c->destinos as $d) {
                if (filled($d->reference)) {
                    $detalleLineas->push((object) [
                        'documento' => '',
                        'descripcion' => $d->reference,
                        'valor' => (float) $d->amount,
                    ]);
                }
            }
        }

        if ($detalleLineas->isEmpty()) {
            $desc = match ($c->type) {
                ComprobanteIngreso::TYPE_PAGO_FACTURA => __('Pago asociado a factura #:id', ['id' => $c->invoice_id ?? '—']),
                ComprobanteIngreso::TYPE_COBRO_CUENTA => __('Abono a deuda / cuentas por cobrar'),
                ComprobanteIngreso::TYPE_ANTICIPO => __('Anticipo'),
                default => (filled($c->notes) ? $c->notes : __('Otro ingreso')),
            };
            $detalleLineas = collect([(object) [
                'documento' => $c->invoice_id ? __('Factura #:id', ['id' => $c->invoice_id]) : '',
                'descripcion' => $desc,
                'valor' => (float) $c->total_amount,
            ]]);
        }

        $esRc = (bool) $c->tipo_comprobante_id;
        $detalleSubtitulo = $esRc ? __('Recibo de caja') : __('Comprobante de ingreso');

        $valorEnLetras = money_to_words_es((float) $c->total_amount, $cur);

        $tipoEtiqueta = match ($c->type) {
            ComprobanteIngreso::TYPE_COBRO_CUENTA => __('Abono a deuda'),
            ComprobanteIngreso::TYPE_PAGO_FACTURA => __('Pago de factura'),
            ComprobanteIngreso::TYPE_ANTICIPO => __('Anticipo'),
            default => __('Otro ingreso'),
        };

        // Condiciones de pago = nombre del catálogo Forma de pago (Siigo); bolsillo solo como respaldo.
        $primerDestino = $c->destinos->first();
        $condicionPago = $primerDestino?->formaPago?->nombre
            ?? $c->formaPago?->nombre
            ?? $primerDestino?->bolsillo?->name
            ?? '—';

        $condicionPagoMonto = $primerDestino
            ? (float) $primerDestino->amount
            : (float) $c->total_amount;

        $detalleLineasVista = $detalleLineas->values()->map(function ($linea, int $idx) {
            if ($linea instanceof InvoiceDetail) {
                return [
                    'item' => $idx + 1,
                    'documento' => '',
                    'descripcion' => $linea->receipt_description ?? format_product_name_for_receipt($linea->product_name),
                    'valor' => (float) $linea->subtotal,
                ];
            }

            return [
                'item' => $idx + 1,
                'documento' => (string) ($linea->documento ?? ''),
                'descripcion' => (string) ($linea->descripcion ?? ''),
                'valor' => (float) ($linea->valor ?? 0),
            ];
        })->all();

        return [
            'cur' => $cur,
            'c' => $c,
            'customer' => $customer,
            'dirTel' => $dirTel,
            'ciudadEmpresa' => $ciudadEmpresa,
            'logoAbsPath' => $logoAbsPath,
            'detalleSubtitulo' => $detalleSubtitulo,
            'valorEnLetras' => $valorEnLetras,
            'tipoEtiqueta' => $tipoEtiqueta,
            'condicionPago' => $condicionPago,
            'condicionPagoMonto' => $condicionPagoMonto,
            'totalItems' => count($detalleLineasVista),
            'detalleLineasVista' => $detalleLineasVista,
        ];
    }
}
