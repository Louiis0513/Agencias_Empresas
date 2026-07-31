<?php

namespace App\Services;

use App\Models\ComprobanteContable;
use App\Models\CuentaContable;
use App\Models\MovimientoContable;
use App\Models\Store;
use App\Models\Tercero;
use App\Models\TipoComprobante;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AsientoContableService
{
    public function __construct(
        protected TipoComprobanteService $tipoComprobanteService,
    ) {}

    public function listar(Store $store, array $filtros = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = ComprobanteContable::query()
            ->deStore($store)
            ->with(['tipoComprobante', 'creador'])
            ->withCount('movimientos');

        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (! empty($filtros['fecha_desde'])) {
            $query->whereDate('fecha', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->whereDate('fecha', '<=', $filtros['fecha_hasta']);
        }

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', '%'.$search.'%')
                    ->orWhere('descripcion', 'like', '%'.$search.'%')
                    ->orWhereHas('movimientos.tercero', fn ($t) => $t->where(
                        fn ($tercero) => $tercero
                            ->where('nombre', 'like', '%'.$search.'%')
                            ->orWhere('numero_identificacion', 'like', '%'.$search.'%')
                    ));
            });
        }

        return $query
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function libroDiario(Store $store, array $filtros = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = MovimientoContable::query()
            ->with(['comprobante.tipoComprobante', 'comprobante.tercero', 'cuentaContable', 'tercero'])
            ->where('store_id', $store->id)
            ->whereHas('comprobante', fn ($q) => $q
                ->where('store_id', $store->id)
                ->whereIn('estado', [
                    ComprobanteContable::ESTADO_CONTABILIZADO,
                    ComprobanteContable::ESTADO_REVERSADO,
                ]));

        if (! empty($filtros['fecha_desde'])) {
            $query->whereHas('comprobante', fn ($q) => $q->whereDate('fecha', '>=', $filtros['fecha_desde']));
        }

        if (! empty($filtros['fecha_hasta'])) {
            $query->whereHas('comprobante', fn ($q) => $q->whereDate('fecha', '<=', $filtros['fecha_hasta']));
        }

        if (! empty($filtros['cuenta_contable_id'])) {
            $query->where('cuenta_contable_id', (int) $filtros['cuenta_contable_id']);
        }

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $query->where(function ($q) use ($search) {
                $q->where('descripcion', 'like', '%'.$search.'%')
                    ->orWhereHas('comprobante', fn ($c) => $c->where(
                        fn ($comprobante) => $comprobante
                            ->where('numero', 'like', '%'.$search.'%')
                            ->orWhere('descripcion', 'like', '%'.$search.'%')
                    ))
                    ->orWhereHas('tercero', fn ($t) => $t->where(
                        fn ($tercero) => $tercero
                            ->where('nombre', 'like', '%'.$search.'%')
                            ->orWhere('numero_identificacion', 'like', '%'.$search.'%')
                    ));
            });
        }

        return $query
            ->orderBy(
                ComprobanteContable::select('fecha')
                    ->whereColumn('comprobantes_contables.id', 'movimientos_contables.comprobante_contable_id')
            )
            ->orderBy('comprobante_contable_id')
            ->orderBy('orden')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function obtener(Store $store, int $id): ComprobanteContable
    {
        return ComprobanteContable::query()
            ->deStore($store)
            ->with([
                'tipoComprobante',
                'tercero',
                'creador',
                'contabilizadoPor',
                'reversadoPor',
                'reversaDe',
                'reverso',
                'movimientos.cuentaContable',
                'movimientos.tercero',
            ])
            ->findOrFail($id);
    }

    /** @return Collection<int, TipoComprobante> */
    public function tiposCcDisponibles(Store $store): Collection
    {
        $this->tipoComprobanteService->asegurarTiposPorDefecto($store);

        return TipoComprobante::query()
            ->deStore($store)
            ->activas()
            ->where('familia', TipoComprobante::FAMILIA_CC)
            ->orderByRaw('CAST(codigo AS UNSIGNED) asc')
            ->orderBy('id')
            ->get();
    }

    /** @return Collection<int, CuentaContable> */
    public function cuentasDisponibles(Store $store): Collection
    {
        return CuentaContable::query()
            ->deStore($store)
            ->activas()
            ->transaccionales()
            ->where('es_auxiliar', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'clase', 'maneja_vencimientos']);
    }

    /** @return Collection<int, Tercero> */
    public function tercerosDisponibles(Store $store): Collection
    {
        return Tercero::query()
            ->deStore($store)
            ->activos()
            ->orderBy('nombre')
            ->get(['id', 'numero_identificacion', 'nombre']);
    }

    public function crearBorrador(Store $store, int $userId, array $data): ComprobanteContable
    {
        $tipo = $this->validarTipoCc($store, (int) $data['tipo_comprobante_id']);
        [$lineas, $totalDebito, $totalCredito] = $this->validarYNormalizarLineas(
            $store,
            $data['lineas'] ?? []
        );
        $terceroId = $this->validarTercero($store, $data['tercero_id'] ?? null);
        $numero = $this->resolverNumeroManual($store, $tipo, $data['numero'] ?? null);

        return DB::transaction(function () use (
            $store,
            $userId,
            $data,
            $tipo,
            $lineas,
            $totalDebito,
            $totalCredito,
            $terceroId,
            $numero
        ) {
            $comprobante = ComprobanteContable::create([
                'store_id' => $store->id,
                'tipo_comprobante_id' => $tipo->id,
                'numero' => $numero,
                'fecha' => $data['fecha'],
                'tercero_id' => $terceroId,
                'descripcion' => trim((string) $data['descripcion']),
                'estado' => ComprobanteContable::ESTADO_BORRADOR,
                'evento' => ComprobanteContable::EVENTO_MANUAL,
                'total_debito' => $totalDebito,
                'total_credito' => $totalCredito,
                'created_by' => $userId,
            ]);

            $this->guardarLineas($comprobante, $lineas);

            return $this->obtener($store, $comprobante->id);
        });
    }

    public function actualizarBorrador(
        Store $store,
        ComprobanteContable $comprobante,
        array $data
    ): ComprobanteContable {
        $this->validarPertenencia($store, $comprobante);
        $tipo = $this->validarTipoCc($store, (int) $data['tipo_comprobante_id']);
        [$lineas, $totalDebito, $totalCredito] = $this->validarYNormalizarLineas(
            $store,
            $data['lineas'] ?? []
        );
        $terceroId = $this->validarTercero($store, $data['tercero_id'] ?? null);
        $numero = $this->resolverNumeroManual($store, $tipo, $data['numero'] ?? null, $comprobante);

        return DB::transaction(function () use (
            $store,
            $comprobante,
            $data,
            $tipo,
            $lineas,
            $totalDebito,
            $totalCredito,
            $terceroId,
            $numero
        ) {
            $locked = ComprobanteContable::query()->lockForUpdate()->findOrFail($comprobante->id);
            if (! $locked->esBorrador()) {
                throw new Exception('Solo se pueden editar comprobantes en borrador.');
            }

            $locked->update([
                'tipo_comprobante_id' => $tipo->id,
                'numero' => $numero,
                'fecha' => $data['fecha'],
                'tercero_id' => $terceroId,
                'descripcion' => trim((string) $data['descripcion']),
                'total_debito' => $totalDebito,
                'total_credito' => $totalCredito,
            ]);

            $locked->movimientos()->delete();
            $this->guardarLineas($locked, $lineas);

            return $this->obtener($store, $locked->id);
        });
    }

    public function contabilizar(
        Store $store,
        ComprobanteContable $comprobante,
        int $userId
    ): ComprobanteContable {
        $this->validarPertenencia($store, $comprobante);

        return DB::transaction(function () use ($store, $comprobante, $userId) {
            $locked = ComprobanteContable::query()
                ->with(['tipoComprobante', 'movimientos'])
                ->lockForUpdate()
                ->findOrFail($comprobante->id);

            if (! $locked->esBorrador()) {
                throw new Exception('Solo se pueden contabilizar comprobantes en borrador.');
            }

            $tipo = $this->validarTipoCc($store, (int) $locked->tipo_comprobante_id);
            [, $totalDebito, $totalCredito] = $this->validarYNormalizarLineas(
                $store,
                $locked->movimientos->map(fn ($linea) => [
                    'cuenta_contable_id' => $linea->cuenta_contable_id,
                    'tercero_id' => $linea->tercero_id,
                    'detalle_contable' => $linea->detalle_contable,
                    'descripcion' => $linea->descripcion,
                    'debito' => $linea->debito,
                    'credito' => $linea->credito,
                ])->all()
            );

            $numero = $locked->numero;
            if ($tipo->numeracion_automatica) {
                $numero = $this->tipoComprobanteService->tomarSiguienteNumero($store, $tipo);
            } elseif (! $numero) {
                throw new Exception('Debes indicar el número del comprobante antes de contabilizar.');
            }

            $locked->update([
                'numero' => $numero,
                'estado' => ComprobanteContable::ESTADO_CONTABILIZADO,
                'total_debito' => $totalDebito,
                'total_credito' => $totalCredito,
                'contabilizado_by' => $userId,
                'contabilizado_at' => now(),
            ]);

            return $this->obtener($store, $locked->id);
        });
    }

    public function reversar(
        Store $store,
        ComprobanteContable $comprobante,
        int $userId
    ): ComprobanteContable {
        $this->validarPertenencia($store, $comprobante);

        return DB::transaction(function () use ($store, $comprobante, $userId) {
            $original = ComprobanteContable::query()
                ->with(['tipoComprobante', 'movimientos'])
                ->lockForUpdate()
                ->findOrFail($comprobante->id);

            if (! $original->estaContabilizado()) {
                throw new Exception('Solo se pueden reversar comprobantes contabilizados.');
            }

            if ($original->reversa_de_id !== null) {
                throw new Exception('Un comprobante de reversión no puede volver a reversarse.');
            }

            if (ComprobanteContable::query()->where('reversa_de_id', $original->id)->exists()) {
                throw new Exception('Este comprobante ya tiene una reversión.');
            }

            $tipo = $this->tipoAutomaticoParaReverso($store, $original->tipoComprobante);

            $numero = $this->tipoComprobanteService->tomarSiguienteNumero($store, $tipo);
            $reverso = ComprobanteContable::create([
                'store_id' => $store->id,
                'tipo_comprobante_id' => $tipo->id,
                'numero' => $numero,
                'fecha' => now()->toDateString(),
                'tercero_id' => $original->tercero_id,
                'descripcion' => 'Reversión de '.($original->numero ?? 'comprobante #'.$original->id)
                    .': '.$original->descripcion,
                'estado' => ComprobanteContable::ESTADO_CONTABILIZADO,
                'evento' => ComprobanteContable::EVENTO_REVERSO,
                'total_debito' => $original->total_credito,
                'total_credito' => $original->total_debito,
                'reversa_de_id' => $original->id,
                'created_by' => $userId,
                'contabilizado_by' => $userId,
                'contabilizado_at' => now(),
            ]);

            foreach ($original->movimientos as $linea) {
                $reverso->movimientos()->create([
                    'store_id' => $store->id,
                    'cuenta_contable_id' => $linea->cuenta_contable_id,
                    'tercero_id' => $linea->tercero_id,
                    'detalle_contable' => $linea->detalle_contable,
                    'descripcion' => $linea->descripcion,
                    'debito' => $linea->credito,
                    'credito' => $linea->debito,
                    'orden' => $linea->orden,
                ]);
            }

            $original->update([
                'estado' => ComprobanteContable::ESTADO_REVERSADO,
                'reversado_by' => $userId,
                'reversado_at' => now(),
            ]);

            return $this->obtener($store, $reverso->id);
        });
    }

    private function validarTipoCc(Store $store, int $tipoId): TipoComprobante
    {
        $tipo = TipoComprobante::query()
            ->deStore($store)
            ->activas()
            ->where('familia', TipoComprobante::FAMILIA_CC)
            ->find($tipoId);

        if (! $tipo) {
            throw new Exception('El tipo de comprobante CC no es válido para esta tienda.');
        }

        return $tipo;
    }

    private function validarTercero(Store $store, mixed $terceroId): ?int
    {
        if ($terceroId === null || $terceroId === '') {
            return null;
        }

        $id = (int) $terceroId;
        $existe = Tercero::query()->deStore($store)->activos()->whereKey($id)->exists();
        if (! $existe) {
            throw new Exception('El tercero no es válido para esta tienda.');
        }

        return $id;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: string, 2: string}
     */
    private function validarYNormalizarLineas(Store $store, array $lineas): array
    {
        if (count($lineas) < 2) {
            throw new Exception('El asiento debe tener al menos dos líneas.');
        }

        $cuentaIds = collect($lineas)
            ->pluck('cuenta_contable_id')
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $cuentas = CuentaContable::query()
            ->deStore($store)
            ->whereIn('id', $cuentaIds)
            ->get()
            ->keyBy('id');

        $terceroIds = collect($lineas)
            ->pluck('tercero_id')
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $tercerosValidos = Tercero::query()
            ->deStore($store)
            ->activos()
            ->whereIn('id', $terceroIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $totalDebitoCentavos = 0;
        $totalCreditoCentavos = 0;
        $normalizadas = [];

        foreach (array_values($lineas) as $index => $linea) {
            $cuentaId = (int) ($linea['cuenta_contable_id'] ?? 0);
            $cuenta = $cuentas->get($cuentaId);
            if (! $cuenta
                || ! $cuenta->activo
                || ! $cuenta->es_auxiliar
                || ! $cuenta->esTransaccional()
            ) {
                throw new Exception('La cuenta de la línea '.($index + 1)
                    .' debe ser auxiliar, transaccional, activa y pertenecer a esta tienda.');
            }

            $terceroId = $linea['tercero_id'] ?? null;
            $terceroId = ($terceroId === null || $terceroId === '') ? null : (int) $terceroId;
            if ($terceroId !== null && ! in_array($terceroId, $tercerosValidos, true)) {
                throw new Exception('El tercero de la línea '.($index + 1).' no es válido para esta tienda.');
            }

            $debito = $this->aCentavos($linea['debito'] ?? 0, $index);
            $credito = $this->aCentavos($linea['credito'] ?? 0, $index);

            if (($debito > 0 && $credito > 0) || ($debito === 0 && $credito === 0)) {
                throw new Exception('La línea '.($index + 1)
                    .' debe tener un valor solamente en débito o solamente en crédito.');
            }

            $totalDebitoCentavos += $debito;
            $totalCreditoCentavos += $credito;
            $normalizadas[] = [
                'cuenta_contable_id' => $cuentaId,
                'tercero_id' => $terceroId,
                'detalle_contable' => $this->textoNullable($linea['detalle_contable'] ?? null),
                'descripcion' => $this->textoNullable($linea['descripcion'] ?? null),
                'debito' => $this->desdeCentavos($debito),
                'credito' => $this->desdeCentavos($credito),
                'orden' => $index + 1,
            ];
        }

        if ($totalDebitoCentavos <= 0 || $totalDebitoCentavos !== $totalCreditoCentavos) {
            throw new Exception(
                'El asiento no está cuadrado. Total débito: '.$this->desdeCentavos($totalDebitoCentavos)
                .' — total crédito: '.$this->desdeCentavos($totalCreditoCentavos).'.'
            );
        }

        return [
            $normalizadas,
            $this->desdeCentavos($totalDebitoCentavos),
            $this->desdeCentavos($totalCreditoCentavos),
        ];
    }

    /** @param list<array<string, mixed>> $lineas */
    private function guardarLineas(ComprobanteContable $comprobante, array $lineas): void
    {
        foreach ($lineas as $linea) {
            $comprobante->movimientos()->create([
                'store_id' => $comprobante->store_id,
                ...$linea,
            ]);
        }
    }

    private function resolverNumeroManual(
        Store $store,
        TipoComprobante $tipo,
        mixed $numero,
        ?ComprobanteContable $actual = null
    ): ?string {
        if ($tipo->numeracion_automatica) {
            return null;
        }

        $numero = trim((string) $numero);
        if ($numero === '') {
            throw new Exception('El número es obligatorio para un tipo CC con numeración manual.');
        }

        $duplicado = ComprobanteContable::query()
            ->deStore($store)
            ->where('tipo_comprobante_id', $tipo->id)
            ->where('numero', $numero)
            ->when($actual, fn ($q) => $q->where('id', '!=', $actual->id))
            ->exists();

        if ($duplicado) {
            throw new Exception('Ya existe un comprobante con ese número para el tipo seleccionado.');
        }

        return $numero;
    }

    private function validarPertenencia(Store $store, ComprobanteContable $comprobante): void
    {
        if ((int) $comprobante->store_id !== (int) $store->id) {
            throw new Exception('El comprobante no pertenece a esta tienda.');
        }
    }

    private function tipoAutomaticoParaReverso(
        Store $store,
        TipoComprobante $tipoOriginal
    ): TipoComprobante {
        if ((int) $tipoOriginal->store_id === (int) $store->id
            && $tipoOriginal->familia === TipoComprobante::FAMILIA_CC
            && $tipoOriginal->activo
            && $tipoOriginal->numeracion_automatica
        ) {
            return $tipoOriginal;
        }

        $tipo = TipoComprobante::query()
            ->deStore($store)
            ->activas()
            ->where('familia', TipoComprobante::FAMILIA_CC)
            ->where('numeracion_automatica', true)
            ->orderBy('id')
            ->first();

        if (! $tipo) {
            throw new Exception('La reversión requiere un tipo CC activo con numeración automática.');
        }

        return $tipo;
    }

    private function aCentavos(mixed $valor, int $index): int
    {
        $normalizado = trim((string) ($valor ?? 0));
        if ($normalizado === '') {
            return 0;
        }

        if (! is_numeric($normalizado) || (float) $normalizado < 0) {
            throw new Exception('El valor de la línea '.($index + 1).' no es válido.');
        }

        return (int) round((float) $normalizado * 100);
    }

    private function desdeCentavos(int $centavos): string
    {
        return number_format($centavos / 100, 2, '.', '');
    }

    private function textoNullable(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto === '' ? null : $texto;
    }
}
