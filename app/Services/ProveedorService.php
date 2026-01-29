<?php

namespace App\Services;

use App\Models\Proveedor;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

class ProveedorService
{
    public function crearProveedor(Store $store, array $data): Proveedor
    {
        return DB::transaction(function () use ($store, $data) {
            $productoIds = $data['producto_ids'] ?? [];
            unset($data['producto_ids']);

            $proveedor = Proveedor::create([
                'store_id' => $store->id,
                'nombre' => $data['nombre'],
                'numero_celular' => $data['numero_celular'] ?? null,
                'telefono' => $data['telefono'] ?? null,
                'email' => $data['email'] ?? null,
                'nit' => $data['nit'] ?? null,
                'direccion' => $data['direccion'] ?? null,
                'estado' => $data['estado'] ?? true,
            ]);

            $this->syncProductos($proveedor, $store, $productoIds);

            return $proveedor;
        });
    }

    public function actualizarProveedor(Store $store, int $proveedorId, array $data): Proveedor
    {
        $proveedor = Proveedor::where('id', $proveedorId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        return DB::transaction(function () use ($proveedor, $store, $data) {
            $productoIds = $data['producto_ids'] ?? null;
            unset($data['producto_ids']);

            $proveedor->update($data);

            if ($productoIds !== null) {
                $this->syncProductos($proveedor, $store, $productoIds);
            }

            return $proveedor->fresh();
        });
    }

    public function eliminarProveedor(Store $store, int $proveedorId): bool
    {
        $proveedor = Proveedor::where('id', $proveedorId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        return DB::transaction(function () use ($proveedor) {
            $proveedor->delete();
            return true;
        });
    }

    public function listarProveedores(Store $store, array $filtros = [])
    {
        $query = Proveedor::deTienda($store->id)->with('productos');

        if (isset($filtros['search']) && !empty($filtros['search'])) {
            $query->buscar($filtros['search']);
        }

        if (isset($filtros['estado']) && $filtros['estado'] !== null) {
            $query->where('estado', (bool) $filtros['estado']);
        }

        $perPage = $filtros['per_page'] ?? 10;

        return $query->orderBy('nombre')->paginate($perPage);
    }

    /**
     * Sincroniza los productos de un proveedor (solo productos de la misma tienda).
     */
    protected function syncProductos(Proveedor $proveedor, Store $store, array $productoIds): void
    {
        $validIds = \App\Models\Product::where('store_id', $store->id)
            ->whereIn('id', $productoIds)
            ->pluck('id')
            ->toArray();

        $proveedor->productos()->sync($validIds);
    }
}
