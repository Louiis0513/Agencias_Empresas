<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Tercero;
use App\Models\User;

/** Adaptador temporal para callers legacy de trabajadores. */
class WorkerService
{
    public function __construct(private readonly TerceroService $terceroService) {}

    public function createWorker(Store $store, array $data): Tercero
    {
        return $this->terceroService->crear($store, $this->mapPayload($data));
    }

    public function updateWorker(Tercero $worker, array $data): Tercero
    {
        $payload = $this->mapPayload($data);
        $payload['roles'] = $worker->roles->where('activo', true)->pluck('rol')->push(Tercero::ROL_TRABAJADOR)->unique()->values()->all();

        return $this->terceroService->actualizar($worker->store, $worker, $payload);
    }

    public function deleteWorker(Tercero $worker): bool
    {
        $this->terceroService->eliminar($worker->store, $worker);

        return true;
    }

    public function vincularWorkersExistentes(User $user): int
    {
        return $this->terceroService->vincularPorEmailUsuario($user);
    }

    public function listar(Store $store, array $filtros = [])
    {
        $filtros['rol'] = Tercero::ROL_TRABAJADOR;

        return $this->terceroService->listar($store, $filtros, (int) ($filtros['per_page'] ?? 15));
    }

    private function mapPayload(array $data): array
    {
        return [
            ...$data,
            'nombre' => $data['nombre'] ?? $data['name'] ?? '',
            'numero_identificacion' => $data['numero_identificacion'] ?? $data['document_number'] ?? null,
            'telefono' => $data['telefono'] ?? $data['phone'] ?? null,
            'direccion' => $data['direccion'] ?? $data['address'] ?? null,
            'roles' => [Tercero::ROL_TRABAJADOR],
            'trabajador' => [
                ...($data['trabajador'] ?? []),
                'role_id' => $data['role_id'] ?? $data['trabajador']['role_id'] ?? null,
                'cargo' => $data['cargo'] ?? $data['trabajador']['cargo'] ?? null,
                'fecha_ingreso' => $data['fecha_ingreso'] ?? $data['trabajador']['fecha_ingreso'] ?? null,
            ],
        ];
    }
}
