<?php

namespace App\Services;

use App\Models\User;
use App\Models\Worker;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Exception;

class WorkerService
{
    /**
     * Crea un trabajador y lo vincula automáticamente si el usuario existe
     */
    public function createWorker(Store $store, array $data): Worker
    {
        $this->validarDuplicados($store, $data);

        return DB::transaction(function () use ($store, $data) {
            $userExistente = User::where('email', $data['email'])->first();

            $worker = Worker::create([
                'store_id'        => $store->id,
                'user_id'         => $userExistente?->id,
                'role_id'         => $data['role_id'],
                'name'            => $data['name'],
                'email'           => $data['email'],
                'phone'           => $data['phone'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'address'         => $data['address'] ?? null,
            ]);

            if ($userExistente) {
                $this->asegurarEnStoreUser($store, $userExistente->id, (int) $data['role_id']);
            }

            return $worker;
        });
    }

    /**
     * Actualiza un trabajador existente
     */
    public function updateWorker(Worker $worker, array $data): Worker
    {
        $this->validarDuplicados($worker->store, $data, $worker->id);

        return DB::transaction(function () use ($worker, $data) {
            $emailActual = $data['email'] ?? $worker->email;
            $userExistente = User::where('email', $emailActual)->first();

            $worker->update([
                'user_id'         => $userExistente?->id,
                'role_id'         => $data['role_id'],
                'name'            => $data['name'],
                'email'           => $data['email'],
                'phone'           => $data['phone'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'address'         => $data['address'] ?? null,
            ]);

            if ($userExistente) {
                $this->asegurarEnStoreUser($worker->store, $userExistente->id, (int) $data['role_id']);
            } else {
                $this->quitarDeStoreUserSiEstaba($worker);
            }

            return $worker->fresh(['user', 'role']);
        });
    }

    /**
     * Elimina un trabajador y lo quita de store_user si estaba vinculado
     */
    public function deleteWorker(Worker $worker): bool
    {
        return DB::transaction(function () use ($worker) {
            $this->quitarDeStoreUserSiEstaba($worker);
            $worker->delete();
            return true;
        });
    }

    /**
     * Vincula automáticamente workers existentes cuando un usuario se registra
     */
    public function vincularWorkersExistentes(User $user): int
    {
        if (empty($user->email)) {
            return 0;
        }

        return DB::transaction(function () use ($user) {
            $workers = Worker::where('email', $user->email)
                ->whereNull('user_id')
                ->with('store')
                ->get();

            foreach ($workers as $worker) {
                $worker->user_id = $user->id;
                $worker->save();
                $this->asegurarEnStoreUser($worker->store, $user->id, $worker->role_id);
            }

            return $workers->count();
        });
    }

    private function asegurarEnStoreUser(Store $store, int $userId, int $roleId): void
    {
        $existe = DB::table('store_user')
            ->where('store_id', $store->id)
            ->where('user_id', $userId)
            ->exists();

        if (! $existe) {
            DB::table('store_user')->insert([
                'store_id'  => $store->id,
                'user_id'   => $userId,
                'role_id'   => $roleId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('store_user')
                ->where('store_id', $store->id)
                ->where('user_id', $userId)
                ->update(['role_id' => $roleId, 'updated_at' => now()]);
        }
    }

    private function quitarDeStoreUserSiEstaba(Worker $worker): void
    {
        if ($worker->user_id) {
            DB::table('store_user')
                ->where('store_id', $worker->store_id)
                ->where('user_id', $worker->user_id)
                ->delete();
        }
    }

    private function validarDuplicados(Store $store, array $data, ?int $ignorarId = null): void
    {
        $query = Worker::where('store_id', $store->id)
            ->where('email', $data['email']);

        if ($ignorarId) {
            $query->where('id', '!=', $ignorarId);
        }

        if ($query->exists()) {
            throw new Exception("Ya existe un trabajador con el correo '{$data['email']}' en esta tienda.");
        }
    }
}
