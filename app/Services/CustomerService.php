<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\User;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Exception;

class CustomerService
{
    /**
     * Crea un cliente y lo vincula automáticamente a un usuario si existe
     */
    public function createCustomer(Store $store, array $data): Customer
    {
        // 1. Validar Unicidad de Negocio
        $this->validarDuplicados($store, $data);

        return DB::transaction(function () use ($store, $data) {
            
            // 2. Vincular con Usuario Global (Si el email ya está registrado en la plataforma)
            $usuarioExistente = null;
            if (!empty($data['email'])) {
                $usuarioExistente = $this->buscarUsuarioGlobal($data['email']);
            }
            
            return Customer::create([
                'store_id'       => $store->id,
                'user_id'        => $usuarioExistente ? $usuarioExistente->id : null,
                'name'           => $data['name'],
                'email'          => $data['email'] ?? null,
                'phone'          => $data['phone'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'address'        => $data['address'] ?? null,
            ]);
        });
    }

    /**
     * Actualiza un cliente existente
     */
    public function updateCustomer(Store $store, int $customerId, array $data): Customer
    {
        $cliente = Customer::where('id', $customerId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        // 1. Validar Unicidad (excluyendo al cliente actual)
        $this->validarDuplicados($store, $data, $cliente->id);

        return DB::transaction(function () use ($cliente, $data) {
            
            // 2. Vincular con Usuario Global si:
            //    - El email cambió, O
            //    - El cliente no tenía user_id previo y ahora tenemos un email
            $emailActual = $data['email'] ?? $cliente->email;
            
            if (!empty($emailActual)) {
                $debeBuscarUsuario = false;
                
                // Si el email cambió, buscar nuevo usuario
                if (isset($data['email']) && $data['email'] !== $cliente->email) {
                    $debeBuscarUsuario = true;
                }
                // Si no tenía user_id y ahora tenemos email, intentar vincular
                elseif (empty($cliente->user_id)) {
                    $debeBuscarUsuario = true;
                }
                
                if ($debeBuscarUsuario) {
                    $usuarioExistente = $this->buscarUsuarioGlobal($emailActual);
                    $data['user_id'] = $usuarioExistente ? $usuarioExistente->id : null;
                }
            }

            $cliente->update($data);

            return $cliente->fresh(['user']);
        });
    }

    /**
     * Elimina un cliente
     */
    public function deleteCustomer(Store $store, int $customerId): bool
    {
        $cliente = Customer::where('id', $customerId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        // AQUÍ FUTURO: Validar si el cliente tiene Facturas PENDIENTES o DEUDA.
        // Si tiene deuda, lanzar Exception. Por ahora, permitimos borrar.
        
        return DB::transaction(function () use ($cliente) {
            $cliente->delete();
            return true;
        });
    }

    /**
     * Obtiene todos los clientes de una tienda con paginación
     */
    public function getStoreCustomers(Store $store, array $filtros = [])
    {
        $query = Customer::deTienda($store->id)
            ->with(['user:id,name,email']);

        // Búsqueda
        if (isset($filtros['search']) && !empty($filtros['search'])) {
            $query->buscar($filtros['search']);
        }

        $perPage = $filtros['per_page'] ?? 10;

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Obtiene todos los clientes de una tienda sin paginación (para dropdowns)
     */
    public function getAllStoreCustomers(Store $store)
    {
        return Customer::deTienda($store->id)
            ->orderBy('name')
            ->get();
    }

    /**
     * Vincula automáticamente todos los customers existentes que tengan el mismo email
     * cuando un usuario se registra en el sistema.
     * 
     * @param User $user Usuario recién registrado
     * @return int Número de customers vinculados
     */
    public function vincularCustomersExistentes(User $user): int
    {
        if (empty($user->email)) {
            return 0;
        }

        return DB::transaction(function () use ($user) {
            // Buscar todos los customers que tengan el mismo email pero no estén vinculados
            $customers = Customer::where('email', $user->email)
                ->whereNull('user_id')
                ->get();

            // Vincular cada customer al usuario
            foreach ($customers as $customer) {
                $customer->user_id = $user->id;
                $customer->save();
            }

            return $customers->count();
        });
    }

    /**
     * Desvincula todos los customers de un usuario cuando cambia su email.
     * Esto es necesario porque el email es la clave de vinculación.
     * 
     * @param User $user Usuario cuyo email cambió
     * @param string $oldEmail Email anterior del usuario
     * @return int Número de customers desvinculados
     */
    public function desvincularCustomersPorCambioEmail(User $user, string $oldEmail): int
    {
        return DB::transaction(function () use ($user, $oldEmail) {
            // Buscar todos los customers vinculados a este usuario que tengan el email anterior
            $customers = Customer::where('user_id', $user->id)
                ->where('email', $oldEmail)
                ->get();

            // Desvincular cada customer
            foreach ($customers as $customer) {
                $customer->user_id = null;
                $customer->save();
            }

            return $customers->count();
        });
    }

    // --- HELPERS PRIVADOS ---

    private function validarDuplicados(Store $store, array $data, ?int $ignorarId = null): void
    {
        // Validar que al menos documento o email estén presentes para validar duplicados
        $tieneDocumento = !empty($data['document_number']);
        $tieneEmail = !empty($data['email']);
        
        if (!$tieneDocumento && !$tieneEmail) {
            // Si no hay datos para validar, no hacemos nada (puede ser una actualización parcial)
            return;
        }

        // Validar Documento
        if ($tieneDocumento) {
            $query = Customer::where('store_id', $store->id)
                ->where('document_number', $data['document_number']);
            
            if ($ignorarId) {
                $query->where('id', '!=', $ignorarId);
            }

            if ($query->exists()) {
                throw new Exception("Ya existe un cliente con el documento '{$data['document_number']}'.");
            }
        }

        // Validar Email
        if ($tieneEmail) {
            $query = Customer::where('store_id', $store->id)
                ->where('email', $data['email']);
            
            if ($ignorarId) {
                $query->where('id', '!=', $ignorarId);
            }

            if ($query->exists()) {
                throw new Exception("El correo '{$data['email']}' ya está registrado para otro cliente.");
            }
        }
    }

    private function buscarUsuarioGlobal(string $email): ?User
    {
        return User::where('email', $email)->first();
    }
}
