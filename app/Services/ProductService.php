<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    /**
     * Listado de productos/servicios con búsqueda y filtros (estilo Siigo).
     *
     * Filtros soportados:
     * - search: nombre, codigo, codigo_barras
     * - tipo: producto|servicio
     * - categoria_contable_id
     * - estado / is_active: 1|0|activo|inactivo
     * - es_inventariable: 1|0
     * - stock: aceptado pero ignorado hasta reconstruir inventario
     *
     * @param  array{
     *     search?: ?string,
     *     tipo?: ?string,
     *     categoria_contable_id?: int|string|null,
     *     estado?: int|string|null,
     *     is_active?: int|string|null,
     *     es_inventariable?: int|string|null,
     *     stock?: ?string
     * }  $filtros
     */
    public function listar(Store $store, array $filtros = [], int $perPage = 10): LengthAwarePaginator
    {
        $q = Product::query()
            ->deStore($store)
            ->with([
                'impuestoCargo:id,nombre,tarifa,tipo',
                'precios' => fn ($pq) => $pq->with(['listaPrecio:id,numero,nombre,activo,store_id']),
            ])
            ->orderBy('nombre');

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $q->where(function ($qq) use ($search) {
                $qq->where('nombre', 'like', '%'.$search.'%')
                    ->orWhere('codigo', 'like', '%'.$search.'%')
                    ->orWhere('codigo_barras', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filtros['tipo']) && in_array($filtros['tipo'], Product::TIPOS, true)) {
            $q->where('tipo', $filtros['tipo']);
        }

        if (! empty($filtros['categoria_contable_id'])) {
            $q->where('categoria_contable_id', (int) $filtros['categoria_contable_id']);
        }

        $estado = $filtros['estado'] ?? $filtros['is_active'] ?? null;
        if ($estado !== null && $estado !== '') {
            $activo = match (true) {
                in_array((string) $estado, ['1', 'activo', 'activos', 'true'], true) => true,
                in_array((string) $estado, ['0', 'inactivo', 'inactivos', 'false'], true) => false,
                default => filter_var($estado, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            };
            if ($activo !== null) {
                $q->where('is_active', $activo);
            }
        }

        if (isset($filtros['es_inventariable']) && $filtros['es_inventariable'] !== '' && $filtros['es_inventariable'] !== null) {
            $q->where(
                'es_inventariable',
                filter_var($filtros['es_inventariable'], FILTER_VALIDATE_BOOLEAN)
            );
        }

        // stock: UI only por ahora — inventario demolido; no filtrar hasta reconstruir stock.

        return $q->paginate($perPage)->withQueryString();
    }
}
