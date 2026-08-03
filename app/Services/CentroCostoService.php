<?php

namespace App\Services;

use App\Models\CentroCosto;
use App\Models\MovimientoContable;
use App\Models\Store;
use App\Models\TipoComprobante;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CentroCostoService
{
    public function listar(Store $store, array $filtros = [], int $perPage = 30): LengthAwarePaginator
    {
        $q = CentroCosto::query()
            ->deStore($store)
            ->with(['padre:id,codigo,nombre', 'hijos:id,parent_id,codigo,nombre,activo'])
            ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy('codigo');

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $q->where(function ($qq) use ($search) {
                $qq->where('nombre', 'like', '%'.$search.'%')
                    ->orWhere('codigo', 'like', $search.'%');
            });
        }

        if (isset($filtros['activo']) && $filtros['activo'] !== '' && $filtros['activo'] !== null) {
            $q->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN));
        }

        if (($filtros['nivel'] ?? '') === 'centro') {
            $q->centros();
        } elseif (($filtros['nivel'] ?? '') === 'subcentro') {
            $q->subcentros();
        }

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * Centros activos con sus subcentros activos (para selects en cascada de asientos).
     *
     * @return Collection<int, array{id: int, codigo: string, nombre: string, subcentros: list<array{id: int, codigo: string, nombre: string}>}>
     */
    public function opcionesParaAsiento(Store $store): Collection
    {
        return CentroCosto::query()
            ->deStore($store)
            ->centros()
            ->activos()
            ->with(['hijos' => fn ($q) => $q->activos()->orderBy('codigo')])
            ->orderBy('codigo')
            ->get()
            ->map(function (CentroCosto $centro) {
                return [
                    'id' => $centro->id,
                    'codigo' => $centro->codigo,
                    'nombre' => $centro->nombre,
                    'subcentros' => $centro->hijos->map(fn (CentroCosto $sub) => [
                        'id' => $sub->id,
                        'codigo' => $sub->codigo,
                        'nombre' => $sub->nombre,
                    ])->values()->all(),
                ];
            })
            ->filter(fn (array $c) => $c['subcentros'] !== [])
            ->values();
    }

    public function crearCentro(Store $store, array $data): CentroCosto
    {
        $codigo = $this->normalizarCodigo($data['codigo'] ?? null);
        $nombre = $this->normalizarNombre($data['nombre'] ?? null);
        $this->assertCodigoUnico($store, $codigo);

        return DB::transaction(function () use ($store, $codigo, $nombre, $data) {
            $centro = CentroCosto::create([
                'store_id' => $store->id,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
                'parent_id' => null,
            ]);

            CentroCosto::create([
                'store_id' => $store->id,
                'codigo' => $this->codigoSubcentroDefault($store, $codigo),
                'nombre' => 'General',
                'activo' => true,
                'parent_id' => $centro->id,
            ]);

            return $centro->fresh(['hijos']);
        });
    }

    public function crearSubcentro(Store $store, array $data): CentroCosto
    {
        $padreId = (int) ($data['parent_id'] ?? 0);
        $padre = CentroCosto::query()
            ->deStore($store)
            ->centros()
            ->whereKey($padreId)
            ->first();

        if (! $padre) {
            throw new Exception('Debes seleccionar un centro de costo válido.');
        }

        $codigo = $this->normalizarCodigo($data['codigo'] ?? null);
        $nombre = $this->normalizarNombre($data['nombre'] ?? null);
        $this->assertCodigoUnico($store, $codigo);

        return CentroCosto::create([
            'store_id' => $store->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
            'parent_id' => $padre->id,
        ]);
    }

    public function actualizar(Store $store, CentroCosto $centroCosto, array $data): CentroCosto
    {
        $this->validarPertenencia($store, $centroCosto);

        $codigo = $this->normalizarCodigo($data['codigo'] ?? $centroCosto->codigo);
        $nombre = $this->normalizarNombre($data['nombre'] ?? $centroCosto->nombre);
        $this->assertCodigoUnico($store, $codigo, $centroCosto);

        if ($centroCosto->esCentro() && array_key_exists('parent_id', $data) && $data['parent_id']) {
            throw new Exception('Un centro de costo no puede convertirse en subcentro desde esta pantalla.');
        }

        if ($centroCosto->esSubcentro()) {
            $padreId = (int) ($data['parent_id'] ?? $centroCosto->parent_id);
            $padre = CentroCosto::query()
                ->deStore($store)
                ->centros()
                ->whereKey($padreId)
                ->first();
            if (! $padre) {
                throw new Exception('El centro padre no es válido.');
            }
            $centroCosto->parent_id = $padre->id;
        }

        $centroCosto->codigo = $codigo;
        $centroCosto->nombre = $nombre;
        if (array_key_exists('activo', $data)) {
            $centroCosto->activo = (bool) $data['activo'];
        }
        $centroCosto->save();

        return $centroCosto->fresh(['padre', 'hijos']);
    }

    public function inactivar(Store $store, CentroCosto $centroCosto): CentroCosto
    {
        $this->validarPertenencia($store, $centroCosto);
        $centroCosto->update(['activo' => false]);

        if ($centroCosto->esCentro()) {
            CentroCosto::query()
                ->deStore($store)
                ->where('parent_id', $centroCosto->id)
                ->update(['activo' => false]);
        }

        return $centroCosto->fresh();
    }

    public function assertSubcentroUsable(Store $store, int $centroCostoId): CentroCosto
    {
        $nodo = CentroCosto::query()
            ->deStore($store)
            ->activos()
            ->whereKey($centroCostoId)
            ->first();

        if (! $nodo || ! $nodo->esSubcentro()) {
            throw new Exception('Debes seleccionar un subcentro de costo activo de esta tienda.');
        }

        $padreActivo = CentroCosto::query()
            ->deStore($store)
            ->activos()
            ->whereKey($nodo->parent_id)
            ->exists();

        if (! $padreActivo) {
            throw new Exception('El centro de costo padre del subcentro no está activo.');
        }

        return $nodo;
    }

    /**
     * Matriz global estilo Siigo «Definir comprobantes» (por tipo, no por centro).
     *
     * @return Collection<string, Collection<int, \App\Models\TipoComprobante>>
     */
    public function matrizDefinirComprobantes(Store $store): Collection
    {
        app(TipoComprobanteService::class)->asegurarTiposPorDefecto($store);

        $ordenFamilias = array_keys(TipoComprobante::etiquetasFamiliasGrupo());

        $tipos = TipoComprobante::query()
            ->deStore($store)
            ->orderBy('familia')
            ->orderBy('codigo')
            ->get()
            ->sortBy(function (TipoComprobante $tipo) use ($ordenFamilias) {
                $pos = array_search($tipo->familia, $ordenFamilias, true);

                return sprintf('%02d-%s', $pos === false ? 99 : $pos, $tipo->codigo);
            })
            ->values();

        return $tipos->groupBy('familia');
    }

    /**
     * Guarda la configuración global de centros por tipo de comprobante.
     *
     * @param  list<array{id: int|string, maneja_centro_costos?: mixed, centro_costo_obligatorio?: mixed, centro_costo_default_id?: mixed}>  $filas
     */
    public function guardarDefinirComprobantes(Store $store, array $filas): int
    {
        $actualizados = 0;

        DB::transaction(function () use ($store, $filas, &$actualizados) {
            foreach ($filas as $fila) {
                $tipoId = (int) ($fila['id'] ?? 0);
                if ($tipoId < 1) {
                    continue;
                }

                $tipo = TipoComprobante::query()
                    ->deStore($store)
                    ->whereKey($tipoId)
                    ->lockForUpdate()
                    ->first();

                if (! $tipo) {
                    throw new Exception('Uno de los tipos de comprobante no pertenece a esta tienda.');
                }

                $maneja = filter_var($fila['maneja_centro_costos'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $obligatorio = $maneja && filter_var($fila['centro_costo_obligatorio'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $defaultId = $fila['centro_costo_default_id'] ?? null;
                $defaultId = ($defaultId === null || $defaultId === '') ? null : (int) $defaultId;

                if (! $maneja) {
                    $defaultId = null;
                    $obligatorio = false;
                } elseif ($defaultId !== null) {
                    $this->assertSubcentroUsable($store, $defaultId);
                }

                $tipo->update([
                    'maneja_centro_costos' => $maneja,
                    'centro_costo_obligatorio' => $obligatorio,
                    'centro_costo_default_id' => $defaultId,
                ]);
                $actualizados++;
            }
        });

        return $actualizados;
    }

    private function codigoSubcentroDefault(Store $store, string $codigoCentro): string
    {
        $candidato = $codigoCentro.'-01';
        if (! CentroCosto::query()->deStore($store)->where('codigo', $candidato)->exists()) {
            return $candidato;
        }

        for ($i = 1; $i <= 99; $i++) {
            $alt = $codigoCentro.'-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            if (! CentroCosto::query()->deStore($store)->where('codigo', $alt)->exists()) {
                return $alt;
            }
        }

        throw new Exception('No se pudo generar el código del subcentro por defecto.');
    }

    private function normalizarCodigo(mixed $value): string
    {
        $codigo = trim((string) $value);
        if ($codigo === '') {
            throw new Exception('El código es obligatorio.');
        }
        if (strlen($codigo) > 32) {
            throw new Exception('El código no puede superar 32 caracteres.');
        }

        return $codigo;
    }

    private function normalizarNombre(mixed $value): string
    {
        $nombre = trim((string) $value);
        if ($nombre === '') {
            throw new Exception('El nombre es obligatorio.');
        }

        return $nombre;
    }

    private function assertCodigoUnico(Store $store, string $codigo, ?CentroCosto $excepto = null): void
    {
        $dup = CentroCosto::query()
            ->deStore($store)
            ->where('codigo', $codigo)
            ->when($excepto, fn ($q) => $q->whereKeyNot($excepto->id))
            ->exists();

        if ($dup) {
            throw new Exception('Ya existe un centro/subcentro con el código '.$codigo.'.');
        }
    }

    private function validarPertenencia(Store $store, CentroCosto $centroCosto): void
    {
        if ($centroCosto->store_id !== $store->id) {
            throw new Exception('El centro de costo no pertenece a esta tienda.');
        }
    }

    public function tieneMovimientos(CentroCosto $centroCosto): bool
    {
        if (MovimientoContable::query()->where('centro_costo_id', $centroCosto->id)->exists()) {
            return true;
        }

        if ($centroCosto->esCentro()) {
            $hijosIds = CentroCosto::query()
                ->where('parent_id', $centroCosto->id)
                ->pluck('id');

            return MovimientoContable::query()->whereIn('centro_costo_id', $hijosIds)->exists();
        }

        return false;
    }
}
