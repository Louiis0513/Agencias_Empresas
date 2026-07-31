<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Tercero;
use App\Models\User;

/**
 * Adaptador temporal para callers legacy de clientes.
 */
class CustomerService
{
    public function __construct(private readonly TerceroService $terceroService) {}

    public function createCustomer(Store $store, array $data): Tercero
    {
        return $this->terceroService->crear($store, $this->mapPayload($data));
    }

    public function updateCustomer(Store $store, int $customerId, array $data): Tercero
    {
        $tercero = $this->terceroService->obtener($store, $customerId);
        $payload = $this->mapPayload($data);
        $payload['roles'] = $tercero->roles->where('activo', true)->pluck('rol')->push(Tercero::ROL_CLIENTE)->unique()->values()->all();

        return $this->terceroService->actualizar($store, $tercero, $payload);
    }

    public function deleteCustomer(Store $store, int $customerId): bool
    {
        $this->terceroService->eliminar($store, $this->terceroService->obtener($store, $customerId));

        return true;
    }

    public function getStoreCustomers(Store $store, array $filtros = [])
    {
        $filtros['rol'] = Tercero::ROL_CLIENTE;

        return $this->terceroService->listar($store, $filtros, (int) ($filtros['per_page'] ?? 10));
    }

    public function getAllStoreCustomers(Store $store)
    {
        return $this->terceroService->buscarParaSelect($store, Tercero::ROL_CLIENTE, null, 10000);
    }

    public function vincularCustomersExistentes(User $user): int
    {
        return $this->terceroService->vincularPorEmailUsuario($user);
    }

    public function desvincularCustomersPorCambioEmail(User $user, string $oldEmail): int
    {
        return Tercero::query()
            ->where('user_id', $user->id)
            ->where('email', $oldEmail)
            ->conRol(Tercero::ROL_CLIENTE)
            ->update(['user_id' => null]);
    }

    private function mapPayload(array $data): array
    {
        return [
            ...$data,
            'nombre' => $data['nombre'] ?? $data['name'] ?? '',
            'numero_identificacion' => $data['numero_identificacion'] ?? $data['document_number'] ?? null,
            'telefono' => $data['telefono'] ?? $data['phone'] ?? null,
            'direccion' => $data['direccion'] ?? $data['address'] ?? null,
            'roles' => [Tercero::ROL_CLIENTE],
            'gym' => [
                'gender' => $data['gender'] ?? null,
                'blood_type' => $data['blood_type'] ?? null,
                'eps' => $data['eps'] ?? null,
                'birth_date' => $data['birth_date'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            ],
        ];
    }
}
