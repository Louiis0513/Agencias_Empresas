<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Tercero;

/** Adaptador temporal para callers legacy de proveedores. */
class ProveedorService
{
    public function __construct(private readonly TerceroService $terceroService) {}

    public function crearProveedor(Store $store, array $data): Tercero
    {
        return $this->terceroService->crear($store, $this->mapPayload($data));
    }

    public function actualizarProveedor(Store $store, int $proveedorId, array $data): Tercero
    {
        $tercero = $this->terceroService->obtener($store, $proveedorId);
        $payload = $this->mapPayload($data);
        $payload['roles'] = $tercero->roles->where('activo', true)->pluck('rol')->push(Tercero::ROL_PROVEEDOR)->unique()->values()->all();

        return $this->terceroService->actualizar($store, $tercero, $payload);
    }

    public function eliminarProveedor(Store $store, int $proveedorId): bool
    {
        $this->terceroService->eliminar($store, $this->terceroService->obtener($store, $proveedorId));

        return true;
    }

    public function listarProveedores(Store $store, array $filtros = [])
    {
        $filtros['rol'] = Tercero::ROL_PROVEEDOR;
        if (array_key_exists('estado', $filtros)) {
            $filtros['activo'] = $filtros['estado'];
        }

        return $this->terceroService->listar($store, $filtros, (int) ($filtros['per_page'] ?? 10));
    }

    private function mapPayload(array $data): array
    {
        return [
            ...$data,
            'nombre' => $data['nombre'] ?? $data['name'] ?? '',
            'numero_identificacion' => $data['numero_identificacion'] ?? $data['nit'] ?? null,
            'telefono' => $data['telefono'] ?? $data['numero_celular'] ?? $data['phone'] ?? null,
            'direccion' => $data['direccion'] ?? $data['address'] ?? null,
            'activo' => $data['activo'] ?? $data['estado'] ?? true,
            'roles' => [Tercero::ROL_PROVEEDOR],
            'productos' => $data['productos'] ?? $data['producto_ids'] ?? [],
            'proveedor' => $data['proveedor'] ?? [],
        ];
    }
}
