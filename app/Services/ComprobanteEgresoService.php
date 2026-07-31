<?php

namespace App\Services;

use App\Models\AccountPayable;
use App\Models\ComprobanteEgreso;
use App\Models\ComprobanteEgresoDestino;
use App\Models\ComprobanteEgresoOrigen;
use App\Models\MovimientoBolsillo;
use App\Models\Purchase;
use App\Models\SesionCaja;
use App\Models\Store;
use App\Models\Tercero;
use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class ComprobanteEgresoService
{
    public function __construct(
        protected CajaService $cajaService,
        protected ComprobanteIngresoService $comprobanteIngresoService,
        protected StoreTimezoneService $storeTimezoneService
    ) {}

    /**
     * Genera el siguiente número consecutivo CE-XXX por tienda.
     */
    public function siguienteNumero(Store $store): string
    {
        $count = ComprobanteEgreso::deTienda($store->id)->count();

        return 'CE-'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * Crea un comprobante de egreso con múltiples destinos y orígenes.
     * Regla de Oro: Un Comprobante = Un Proveedor (o NULL para gasto directo).
     * - proveedor_id: pago de facturas del proveedor
     * - proveedor_id NULL: gastos directos (ítems libres)
     */
    public function crearComprobante(Store $store, int $userId, array $data): ComprobanteEgreso
    {
        return DB::transaction(function () use ($store, $userId, $data) {
            $proveedorId = ! empty($data['tercero_id'] ?? $data['proveedor_id'] ?? null)
                ? (int) ($data['tercero_id'] ?? $data['proveedor_id'])
                : null;
            $destinos = $data['destinos'] ?? [];
            $origenes = $data['origenes'] ?? [];

            $totalDestinos = array_sum(array_map(fn ($d) => (float) ($d['amount'] ?? 0), $destinos));
            $totalOrigenes = array_sum(array_map(fn ($o) => (float) ($o['amount'] ?? 0), $origenes));

            if ($totalDestinos <= 0 || $totalOrigenes <= 0) {
                throw new Exception('Debe indicar al menos un destino y un origen con montos mayores a cero.');
            }

            if (abs($totalDestinos - $totalOrigenes) > 0.01) {
                throw new Exception("La suma de destinos ({$totalDestinos}) debe coincidir con la suma de orígenes ({$totalOrigenes}).");
            }

            $tieneCuentasPorPagar = collect($destinos)->contains(fn ($d) => ! empty($d['account_payable_id'] ?? null));
            $beneficiaryName = $this->calcularBeneficiaryName($store, $proveedorId, $destinos, $tieneCuentasPorPagar);
            $type = $tieneCuentasPorPagar ? ComprobanteEgreso::TYPE_PAGO_CUENTA : ComprobanteEgreso::TYPE_GASTO_DIRECTO;

            $comprobante = ComprobanteEgreso::create([
                'store_id' => $store->id,
                'tercero_id' => $proveedorId,
                'number' => $this->siguienteNumero($store),
                'total_amount' => $totalDestinos,
                'payment_date' => $data['payment_date'] ?? $this->storeTimezoneService->nowForStore($store)->toDateString(),
                'notes' => $data['notes'] ?? null,
                'type' => $type,
                'beneficiary_name' => $beneficiaryName,
                'user_id' => $userId,
            ]);

            foreach ($destinos as $d) {
                $amount = (float) ($d['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                $accountPayableId = ! empty($d['account_payable_id']) ? (int) $d['account_payable_id'] : null;

                // Si tiene account_payable_id = pago a CxP (factura), aunque proveedor sea null
                if ($accountPayableId) {
                    if ($proveedorId !== null) {
                        $this->validarCuentaPerteneceAProveedor($store, $accountPayableId, $proveedorId);
                    }
                    $this->aplicarPagoACuentaPorPagar($store, $accountPayableId, $amount);

                    ComprobanteEgresoDestino::create([
                        'comprobante_egreso_id' => $comprobante->id,
                        'type' => ComprobanteEgresoDestino::TYPE_CUENTA_POR_PAGAR,
                        'account_payable_id' => $accountPayableId,
                        'concepto' => null,
                        'beneficiario' => null,
                        'amount' => $amount,
                    ]);
                } else {
                    // Gasto directo: requiere concepto
                    $concepto = trim($d['concepto'] ?? '');
                    if (! $concepto) {
                        throw new Exception('Debe indicar el concepto para cada ítem de gasto directo.');
                    }

                    ComprobanteEgresoDestino::create([
                        'comprobante_egreso_id' => $comprobante->id,
                        'type' => ComprobanteEgresoDestino::TYPE_GASTO_DIRECTO,
                        'account_payable_id' => null,
                        'concepto' => $concepto,
                        'beneficiario' => null,
                        'amount' => $amount,
                    ]);
                }
            }

            foreach ($origenes as $o) {
                $amount = (float) ($o['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }

                $bolsilloId = (int) ($o['bolsillo_id'] ?? 0);
                if (! $bolsilloId) {
                    throw new Exception('Debe indicar bolsillo_id para cada origen.');
                }

                ComprobanteEgresoOrigen::create([
                    'comprobante_egreso_id' => $comprobante->id,
                    'bolsillo_id' => $bolsilloId,
                    'amount' => $amount,
                    'reference' => $o['reference'] ?? null,
                ]);

                $descripcion = $this->descripcionMovimiento($store, $comprobante, $destinos);
                $this->cajaService->registrarMovimiento($store, $userId, [
                    'bolsillo_id' => $bolsilloId,
                    'type' => MovimientoBolsillo::TYPE_EXPENSE,
                    'amount' => $amount,
                    'description' => $descripcion,
                    'comprobante_egreso_id' => $comprobante->id,
                ]);
            }

            return $comprobante->load(['destinos.accountPayable.purchase.proveedor', 'origenes.bolsillo']);
        });
    }

    /**
     * Revierte un comprobante de egreso (usa origenes originales).
     * Usado internamente por AccountPayableService.
     */
    public function reversar(Store $store, int $comprobanteId, int $userId): void
    {
        $comprobante = ComprobanteEgreso::where('id', $comprobanteId)
            ->where('store_id', $store->id)
            ->with(['origenes'])
            ->firstOrFail();

        $origenes = $comprobante->origenes->map(fn ($o) => [
            'bolsillo_id' => $o->bolsillo_id,
            'amount' => (float) $o->amount,
            'reference' => $o->reference,
        ])->toArray();

        $this->anularComprobante($store, $comprobanteId, $userId, $origenes);
    }

    /**
     * Anula un comprobante de egreso.
     * - Registra INGRESOS en los bolsillos indicados (concepto: Reverso comprobante de egreso)
     * - Restaura saldos de CxP
     * - Marca el comprobante como revertido
     *
     * @param  array  $origenes  [['bolsillo_id' => int, 'amount' => float, 'reference' => ?string], ...]
     */
    public function anularComprobante(Store $store, int $comprobanteId, int $userId, array $origenes): void
    {
        DB::transaction(function () use ($store, $comprobanteId, $userId, $origenes) {
            $comprobante = ComprobanteEgreso::where('id', $comprobanteId)
                ->where('store_id', $store->id)
                ->with(['destinos.accountPayable.purchase', 'origenes.bolsillo'])
                ->lockForUpdate()
                ->firstOrFail();

            if ($comprobante->isReversed()) {
                throw new Exception('Este comprobante ya fue anulado.');
            }

            $movimientos = MovimientoBolsillo::where('comprobante_egreso_id', $comprobante->id)->get();
            $sesionIds = $movimientos->pluck('sesion_caja_id')->filter()->unique();
            if ($sesionIds->isNotEmpty()) {
                $hayCerrada = SesionCaja::whereIn('id', $sesionIds->toArray())->whereNotNull('closed_at')->exists();
                if ($hayCerrada) {
                    throw new Exception('No se puede anular un comprobante de egreso cuyos movimientos pertenecen a una sesión de caja ya cerrada.');
                }
            }

            $totalOrigenes = 0;
            foreach ($origenes as $o) {
                $amount = (float) ($o['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $bolsilloId = (int) ($o['bolsillo_id'] ?? 0);
                if (! $bolsilloId) {
                    throw new Exception('Debe indicar bolsillo para cada origen del reverso.');
                }
                $totalOrigenes += $amount;
            }

            $totalComprobante = (float) $comprobante->total_amount;
            if (abs($totalOrigenes - $totalComprobante) > 0.01) {
                throw new Exception("La suma de los bolsillos del reverso ({$totalOrigenes}) debe coincidir con el total del comprobante ({$totalComprobante}).");
            }

            $concepto = "Reverso comprobante de egreso {$comprobante->number}";
            $destinos = [];
            foreach ($origenes as $o) {
                $amount = (float) ($o['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $destinos[] = [
                    'bolsillo_id' => (int) $o['bolsillo_id'],
                    'amount' => $amount,
                    'reference' => $o['reference'] ?? null,
                ];
            }
            $now = $this->storeTimezoneService->nowForStore($store);

            $this->comprobanteIngresoService->crearComprobante($store, $userId, [
                'notes' => $concepto,
                'destinos' => $destinos,
                'date' => $now->toDateString(),
            ]);

            foreach ($comprobante->destinos as $destino) {
                if ($destino->isCuentaPorPagar() && $destino->account_payable_id) {
                    $this->revertirPagoACuentaPorPagar($destino->accountPayable, (float) $destino->amount);
                }
            }

            $comprobante->update([
                'reversed_at' => $now,
                'reversal_user_id' => $userId,
            ]);
        });
    }

    public function listar(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = ComprobanteEgreso::deTienda($store->id)
            ->with(['user:id,name', 'proveedor:id,nombre', 'destinos.accountPayable.purchase.proveedor'])
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        if (! empty($filtros['type'])) {
            $query->where('type', $filtros['type']);
        }
        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('payment_date', '>=', $filtros['fecha_desde']);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('payment_date', '<=', $filtros['fecha_hasta']);
        }

        return $query->paginate($filtros['per_page'] ?? 15);
    }

    /**
     * Líneas de egreso desde bolsillos para la vista Movimientos (una fila por origen).
     * No altera listar(): solo lectura dedicada con filtros propios.
     */
    public function listarOrigenesPaginadosParaMovimientos(Store $store, array $filtros = []): LengthAwarePaginator
    {
        $query = ComprobanteEgresoOrigen::query()
            ->select('comprobante_egreso_origenes.*')
            ->join('comprobantes_egreso', 'comprobante_egreso_origenes.comprobante_egreso_id', '=', 'comprobantes_egreso.id')
            ->where('comprobantes_egreso.store_id', $store->id)
            ->whereNull('comprobantes_egreso.reversed_at');

        $this->applyOrigenesMovimientosFiltros($query, $filtros);

        $query->orderByDesc('comprobantes_egreso.created_at')
            ->orderByDesc('comprobante_egreso_origenes.id');

        return $query
            ->with(['comprobanteEgreso', 'bolsillo'])
            ->paginate($filtros['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * Orígenes de egreso sin paginar (exportación Excel). Mismos filtros que {@see listarOrigenesPaginadosParaMovimientos}.
     *
     * @return EloquentCollection<int, ComprobanteEgresoOrigen>
     */
    public function coleccionOrigenesParaExportacionMovimientos(Store $store, array $filtros = []): EloquentCollection
    {
        $query = ComprobanteEgresoOrigen::query()
            ->select('comprobante_egreso_origenes.*')
            ->join('comprobantes_egreso', 'comprobante_egreso_origenes.comprobante_egreso_id', '=', 'comprobantes_egreso.id')
            ->where('comprobantes_egreso.store_id', $store->id)
            ->whereNull('comprobantes_egreso.reversed_at');

        $this->applyOrigenesMovimientosFiltros($query, $filtros);

        $query->orderByDesc('comprobantes_egreso.created_at')
            ->orderByDesc('comprobante_egreso_origenes.id');

        return $query
            ->with(['comprobanteEgreso.user', 'comprobanteEgreso.proveedor', 'bolsillo'])
            ->get();
    }

    /**
     * Suma de egresos por bolsillo en el período/filtros de Movimientos.
     *
     * @return \Illuminate\Support\Collection<int, object{bolsillo_name: string, total: float}>
     */
    public function totalesEgresosPorBolsilloMovimientos(Store $store, array $filtros = []): \Illuminate\Support\Collection
    {
        $query = ComprobanteEgresoOrigen::query()
            ->join('comprobantes_egreso', 'comprobante_egreso_origenes.comprobante_egreso_id', '=', 'comprobantes_egreso.id')
            ->join('bolsillos', 'comprobante_egreso_origenes.bolsillo_id', '=', 'bolsillos.id')
            ->where('comprobantes_egreso.store_id', $store->id)
            ->where('bolsillos.store_id', $store->id)
            ->whereNull('comprobantes_egreso.reversed_at');

        $this->applyOrigenesMovimientosFiltros($query, $filtros);

        return $query
            ->selectRaw('bolsillos.name as bolsillo_name, SUM(comprobante_egreso_origenes.amount) as total')
            ->groupBy('bolsillos.id', 'bolsillos.name')
            ->orderBy('bolsillos.name')
            ->get()
            ->map(fn ($row) => (object) [
                'bolsillo_name' => (string) $row->bolsillo_name,
                'total' => (float) $row->total,
            ]);
    }

    /**
     * Suma montos de orígenes de egreso con los mismos criterios que la tabla Movimientos (agregado en BD).
     */
    public function sumarMontosOrigenesMovimientos(Store $store, array $filtros = []): float
    {
        $query = ComprobanteEgresoOrigen::query()
            ->join('comprobantes_egreso', 'comprobante_egreso_origenes.comprobante_egreso_id', '=', 'comprobantes_egreso.id')
            ->where('comprobantes_egreso.store_id', $store->id)
            ->whereNull('comprobantes_egreso.reversed_at');

        $this->applyOrigenesMovimientosFiltros($query, $filtros);

        return (float) $query->sum('comprobante_egreso_origenes.amount');
    }

    private function applyOrigenesMovimientosFiltros(Builder $query, array $filtros): void
    {
        $tz = ! empty($filtros['timezone']) ? (string) $filtros['timezone'] : (string) config('app.timezone');

        if (! empty($filtros['fecha_desde'])) {
            $start = Carbon::parse($filtros['fecha_desde'], $tz)->startOfDay()->utc();
            $query->where('comprobantes_egreso.created_at', '>=', $start);
        }
        if (! empty($filtros['fecha_hasta'])) {
            $end = Carbon::parse($filtros['fecha_hasta'], $tz)->endOfDay()->utc();
            $query->where('comprobantes_egreso.created_at', '<=', $end);
        }

        $search = isset($filtros['search']) ? trim((string) $filtros['search']) : '';
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('comprobante_egreso_origenes.reference', 'like', $term)
                    ->orWhere('comprobantes_egreso.notes', 'like', $term)
                    ->orWhere('comprobantes_egreso.number', 'like', $term)
                    ->orWhere('comprobantes_egreso.beneficiary_name', 'like', $term);
            });
        }

        $bolsilloIds = array_values(array_unique(array_filter(array_map('intval', $filtros['bolsillo_ids'] ?? []))));
        if ($bolsilloIds !== []) {
            $query->whereIn('comprobante_egreso_origenes.bolsillo_id', $bolsilloIds);
        }

        $userIds = array_values(array_unique(array_filter(array_map('intval', $filtros['empleado_user_ids'] ?? []))));
        if ($userIds !== []) {
            $query->whereIn('comprobantes_egreso.user_id', $userIds);
        }

        if (! empty($filtros['proveedor_id'])) {
            $query->where('comprobantes_egreso.tercero_id', (int) $filtros['proveedor_id']);
        }
    }

    public function obtener(Store $store, int $comprobanteId): ComprobanteEgreso
    {
        return ComprobanteEgreso::where('id', $comprobanteId)
            ->where('store_id', $store->id)
            ->with(['user', 'proveedor', 'destinos.accountPayable.purchase.proveedor', 'origenes.bolsillo'])
            ->firstOrFail();
    }

    /**
     * Variables compartidas entre la vista detalle y el PDF (el modelo debe venir de obtener()).
     *
     * @return array{
     *     c: ComprobanteEgreso,
     *     cur: string,
     *     dirTel: string,
     *     tituloDocumento: string,
     *     tipoEtiqueta: string,
     *     detalleSubtitulo: string,
     *     valorEnLetras: string,
     *     detalleLineasVista: list<array{descripcion: string, valor: float}>,
     *     pagadoNombre: string,
     *     pagadoNit: string,
     *     pagadoDireccion: string,
     *     pagadoCiudad: string
     * }
     */
    public function datosVistaComprobanteEgreso(Store $store, ComprobanteEgreso $comprobante): array
    {
        $cur = $store->currency ?? 'COP';
        $c = $comprobante;
        $c->loadMissing('destinos.accountPayable');

        $telText = '';
        if ($store->phone) {
            $telText = __('Tel.: :n', ['n' => $store->phone]);
        } elseif ($store->mobile ?? null) {
            $telText = __('Cel.: :n', ['n' => $store->mobile]);
        }
        $dirTel = trim(implode(' ', array_filter([$store->address ?? '', $telText])));

        $tituloDocumento = match ($c->type) {
            ComprobanteEgreso::TYPE_PAGO_CUENTA => __('Comprobante de abono a CxP'),
            ComprobanteEgreso::TYPE_MIXTO => __('Comprobante de egreso (mixto)'),
            default => __('Comprobante de egreso'),
        };

        $tipoEtiqueta = match ($c->type) {
            ComprobanteEgreso::TYPE_PAGO_CUENTA => __('Pago a CxP'),
            ComprobanteEgreso::TYPE_MIXTO => __('Operación mixta'),
            default => __('Gasto directo'),
        };

        $detalleLineasVista = $c->destinos->map(function (ComprobanteEgresoDestino $d) {
            if ($d->isCuentaPorPagar() && $d->accountPayable) {
                $desc = $this->descripcionVistaCuentaPorPagar($d->accountPayable);

                return ['descripcion' => $desc, 'valor' => (float) $d->amount];
            }

            $desc = trim((string) ($d->concepto ?? ''));

            return ['descripcion' => $desc !== '' ? $desc : __('Gasto'), 'valor' => (float) $d->amount];
        })->values()->all();

        $detalleSubtitulo = filled($c->notes)
            ? __('DETALLE: :texto', ['texto' => $c->notes])
            : __('DETALLE: Egreso registrado');

        $valorEnLetras = money_to_words_es((float) $c->total_amount, $cur);

        $proveedor = $c->proveedor;
        $esGastoDirectoSinProveedor = $c->type === ComprobanteEgreso::TYPE_GASTO_DIRECTO && ! $c->tercero_id;

        if ($esGastoDirectoSinProveedor) {
            $pagadoNombre = '—';
            $pagadoNit = '—';
            $pagadoDireccion = '—';
            $pagadoCiudad = '—';
        } elseif ($c->type === ComprobanteEgreso::TYPE_PAGO_CUENTA && ! $c->tercero_id) {
            $cxpManual = $c->destinos->first(fn ($d) => $d->accountPayable && $d->accountPayable->isManual());
            if ($cxpManual && $cxpManual->accountPayable) {
                $apCred = $cxpManual->accountPayable;
                $pagadoNombre = filled($apCred->creditor_name) ? $apCred->creditor_name : (filled($c->beneficiary_name) ? $c->beneficiary_name : '—');
                $pagadoNit = filled($apCred->creditor_document) ? $apCred->creditor_document : '—';
                $pagadoDireccion = '—';
                $pagadoCiudad = '—';
            } else {
                $pagadoNombre = filled($c->beneficiary_name) ? $c->beneficiary_name : '—';
                $pagadoNit = '—';
                $pagadoDireccion = '—';
                $pagadoCiudad = '—';
            }
        } else {
            $pagadoNombre = filled($c->beneficiary_name) ? $c->beneficiary_name : ($proveedor?->nombre ?? '—');
            $pagadoNit = $proveedor?->nit ?? '—';
            $pagadoDireccion = $proveedor?->direccion ?? '—';
            $pagadoCiudad = '—';
        }

        return [
            'c' => $c,
            'cur' => $cur,
            'dirTel' => $dirTel,
            'tituloDocumento' => $tituloDocumento,
            'tipoEtiqueta' => $tipoEtiqueta,
            'detalleSubtitulo' => $detalleSubtitulo,
            'valorEnLetras' => $valorEnLetras,
            'detalleLineasVista' => $detalleLineasVista,
            'pagadoNombre' => $pagadoNombre,
            'pagadoNit' => $pagadoNit,
            'pagadoDireccion' => $pagadoDireccion,
            'pagadoCiudad' => $pagadoCiudad,
        ];
    }

    /**
     * Actualiza campos editables del comprobante (fecha, notas).
     * Los montos y destinos/orígenes no se pueden editar sin reversar.
     */
    public function actualizarComprobante(Store $store, int $comprobanteId, array $data): ComprobanteEgreso
    {
        $comprobante = $this->obtener($store, $comprobanteId);

        if ($comprobante->isReversed()) {
            throw new Exception('No se puede editar un comprobante revertido.');
        }

        $comprobante->update([
            'payment_date' => $data['payment_date'] ?? $comprobante->payment_date,
            'notes' => $data['notes'] ?? $comprobante->notes,
        ]);

        return $comprobante->load(['user', 'proveedor', 'destinos.accountPayable.purchase.proveedor', 'origenes.bolsillo']);
    }

    private function descripcionVistaCuentaPorPagar(AccountPayable $ap): string
    {
        if ($ap->isManual() || $ap->purchase_id === null) {
            $parts = array_filter([
                $ap->description ? (string) $ap->description : null,
                filled($ap->document_reference) ? __('Ref. :r', ['r' => $ap->document_reference]) : null,
                filled($ap->creditor_name) ? (string) $ap->creditor_name : null,
            ]);

            return $parts !== [] ? implode(' — ', $parts) : __('CxP manual #:id', ['id' => $ap->id]);
        }

        $purchase = $ap->purchase;
        $proveedorNombre = $purchase?->proveedor?->nombre ?? __('Proveedor');
        $purchaseId = $ap->purchase_id;

        if ($purchase !== null) {
            $doc = trim((string) ($purchase->invoice_number ?? ''));
            if ($doc !== '') {
                return __('Abono a :doc — Compra a :prov', ['doc' => $doc, 'prov' => $proveedorNombre]);
            }
        }

        if ($purchaseId) {
            return __('Abono a compra #:id · :prov', ['id' => $purchaseId, 'prov' => $proveedorNombre]);
        }

        $fechaCxP = $ap->due_date?->format('d/m/Y');
        if ($fechaCxP) {
            return __('Abono a CxP (:fecha) · :prov', ['fecha' => $fechaCxP, 'prov' => $proveedorNombre]);
        }

        return __('Abono a CxP · :prov', ['prov' => $proveedorNombre]);
    }

    private function calcularBeneficiaryName(Store $store, ?int $proveedorId, array $destinos, bool $tieneCuentasPorPagar = false): ?string
    {
        if ($proveedorId) {
            $proveedor = Tercero::find($proveedorId);

            return $proveedor?->nombre ?? 'Proveedor';
        }

        if ($tieneCuentasPorPagar) {
            foreach ($destinos as $d) {
                $apid = ! empty($d['account_payable_id']) ? (int) $d['account_payable_id'] : null;
                if ($apid) {
                    $ap = AccountPayable::where('store_id', $store->id)->find($apid);
                    if ($ap && $ap->isManual() && filled($ap->creditor_name)) {
                        return $ap->creditor_name;
                    }
                }
            }

            return 'Sin proveedor';
        }

        // Gasto directo: "Pagado a" no es una persona jurídica (el PDF muestra —).
        return null;
    }

    private function validarCuentaPerteneceAProveedor(Store $store, int $accountPayableId, int $proveedorId): void
    {
        $ap = AccountPayable::where('id', $accountPayableId)
            ->where('store_id', $store->id)
            ->with('purchase')
            ->first();

        if (! $ap) {
            throw new Exception("La CxP #{$accountPayableId} no existe en esta tienda.");
        }

        if (! $ap->purchase_id) {
            throw new Exception("La CxP #{$accountPayableId} no está vinculada a una compra con proveedor.");
        }

        if ((int) $ap->purchase->tercero_id !== $proveedorId) {
            throw new Exception("La CxP #{$accountPayableId} no pertenece al proveedor seleccionado.");
        }
    }

    private function descripcionMovimiento(Store $store, ComprobanteEgreso $comprobante, array $destinos): string
    {
        $partes = [];
        $currency = $store->currency ?? 'COP';
        foreach ($destinos as $d) {
            $amount = (float) ($d['amount'] ?? 0);
            if ($d['account_payable_id'] ?? null) {
                $apId = (int) $d['account_payable_id'];
                $ap = AccountPayable::with('purchase')->find($apId);
                if ($ap && ! $ap->purchase_id) {
                    $partes[] = 'CxP #'.$apId.': '.money($amount, $currency, false);
                } else {
                    $compraId = $ap?->purchase?->id ?? $apId;
                    $partes[] = "Compra #{$compraId}: ".money($amount, $currency, false);
                }
            } else {
                $partes[] = ($d['concepto'] ?? 'Gasto').': '.money($amount, $currency, false);
            }
        }

        return 'Comprobante '.$comprobante->number.' - '.implode(' | ', $partes);
    }

    private function aplicarPagoACuentaPorPagar(Store $store, int $accountPayableId, float $amount): void
    {
        $accountPayable = AccountPayable::where('id', $accountPayableId)
            ->where('store_id', $store->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($accountPayable->isPagado()) {
            throw new Exception("La CxP #{$accountPayableId} ya está pagada.");
        }

        if ($amount > $accountPayable->balance) {
            throw new Exception("El monto ({$amount}) excede el saldo pendiente ({$accountPayable->balance}) de la CxP.");
        }

        $nuevoBalance = $accountPayable->balance - $amount;
        $nuevoStatus = $nuevoBalance <= 0
            ? AccountPayable::STATUS_PAGADO
            : AccountPayable::STATUS_PARCIAL;

        $accountPayable->update([
            'balance' => max(0, $nuevoBalance),
            'status' => $nuevoStatus,
        ]);

        if ($nuevoStatus === AccountPayable::STATUS_PAGADO && $accountPayable->purchase_id && $accountPayable->purchase) {
            $accountPayable->purchase->update(['payment_status' => Purchase::PAYMENT_PAGADO]);
        }
    }

    private function revertirPagoACuentaPorPagar(AccountPayable $accountPayable, float $monto): void
    {
        $accountPayable = AccountPayable::where('id', $accountPayable->id)->lockForUpdate()->firstOrFail();
        $eraPagado = $accountPayable->isPagado();
        $nuevoBalance = $accountPayable->balance + $monto;
        $nuevoStatus = $nuevoBalance <= 0
            ? AccountPayable::STATUS_PAGADO
            : ($nuevoBalance >= $accountPayable->total_amount
                ? AccountPayable::STATUS_PENDIENTE
                : AccountPayable::STATUS_PARCIAL);

        $accountPayable->update([
            'balance' => $nuevoBalance,
            'status' => $nuevoStatus,
        ]);

        if ($eraPagado && $accountPayable->purchase_id && $accountPayable->purchase) {
            $accountPayable->purchase->update(['payment_status' => Purchase::PAYMENT_PENDIENTE]);
        }
    }
}
