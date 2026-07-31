<?php

namespace App\Services;

use App\Models\ContratoLaboral;
use App\Models\Store;
use App\Models\Tercero;
use App\Models\TerceroClienteGymPerfil;
use App\Models\TerceroClientePerfil;
use App\Models\TerceroContacto;
use App\Models\TerceroDireccion;
use App\Models\TerceroProveedorPerfil;
use App\Models\TerceroRol;
use App\Models\TerceroTrabajadorPerfil;
use App\Models\User;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TerceroService
{
    public function listar(Store $store, array $filtros = [], int $perPage = 30): LengthAwarePaginator
    {
        $q = Tercero::query()
            ->deStore($store)
            ->with(['roles', 'perfilTrabajador.role'])
            ->orderBy('nombre');

        if (! empty($filtros['search'])) {
            $q->buscar($filtros['search']);
        }

        if (! empty($filtros['rol'])) {
            $q->conRol($filtros['rol']);
        }

        if (isset($filtros['activo']) && $filtros['activo'] !== '' && $filtros['activo'] !== null) {
            $q->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN));
        }

        return $q->paginate($perPage)->withQueryString();
    }

    public function buscarParaSelect(Store $store, string $rol, ?string $q = null, int $limit = 50): Collection
    {
        return Tercero::query()
            ->deStore($store)
            ->activos()
            ->conRol($rol)
            ->buscar($q)
            ->orderBy('nombre')
            ->limit($limit)
            ->get();
    }

    public function obtener(Store $store, int $terceroId): Tercero
    {
        $tercero = Tercero::query()
            ->deStore($store)
            ->with([
                'roles',
                'contactos',
                'direcciones',
                'perfilCliente.gym',
                'perfilProveedor',
                'perfilTrabajador.role',
                'contratosLaborales',
                'user',
            ])
            ->find($terceroId);

        if (! $tercero) {
            throw new Exception('Tercero no encontrado.');
        }

        return $tercero;
    }

    public function crear(Store $store, array $data): Tercero
    {
        $payload = $this->normalizarIdentidad($store, $data);

        return DB::transaction(function () use ($store, $payload, $data) {
            $tercero = Tercero::create([
                'store_id' => $store->id,
                ...$payload,
            ]);

            $roles = $data['roles'] ?? [];
            if ($roles === [] && ! empty($data['rol'])) {
                $roles = [$data['rol']];
            }
            $this->syncRoles($tercero, $roles);

            if ($tercero->esCliente() || in_array(Tercero::ROL_CLIENTE, $roles, true)) {
                $this->upsertPerfilCliente($tercero, $data['cliente'] ?? [], $data['gym'] ?? []);
            }
            if ($tercero->esProveedor() || in_array(Tercero::ROL_PROVEEDOR, $roles, true)) {
                $this->upsertPerfilProveedor($tercero, $data['proveedor'] ?? []);
            }
            if ($tercero->esTrabajador() || in_array(Tercero::ROL_TRABAJADOR, $roles, true)) {
                $this->upsertPerfilTrabajador($tercero, $data['trabajador'] ?? []);
                $this->syncStoreUserFromTrabajador($store, $tercero->fresh('perfilTrabajador'));
            }

            $contactos = collect($data['contactos'] ?? [])
                ->filter(fn ($contacto) => filled($contacto['nombre'] ?? null));
            foreach ($contactos as $contacto) {
                $this->agregarContacto($store, $tercero, $contacto);
            }
            if ($contactos->isEmpty()) {
                $this->seedContactoPrincipal($tercero);
            }
            if (filled($tercero->direccion)) {
                $this->seedDireccionFiscal($tercero);
            }

            if (in_array(Tercero::ROL_PROVEEDOR, $roles, true)
                && ! empty($data['productos'])
                && is_array($data['productos'])) {
                $this->syncProductos($store, $tercero, $data['productos']);
            }

            return $tercero->fresh([
                'roles',
                'contactos',
                'direcciones',
                'perfilCliente.gym',
                'perfilProveedor',
                'perfilTrabajador.role',
            ]);
        });
    }

    public function actualizar(Store $store, Tercero $tercero, array $data): Tercero
    {
        if ($tercero->store_id !== $store->id) {
            throw new Exception('El tercero no pertenece a esta tienda.');
        }

        $payload = $this->normalizarIdentidad($store, $data, $tercero);

        return DB::transaction(function () use ($store, $tercero, $payload, $data) {
            $tercero->update($payload);

            if (array_key_exists('roles', $data) || array_key_exists('rol', $data)) {
                $roles = $data['roles'] ?? [];
                if ($roles === [] && ! empty($data['rol'])) {
                    $roles = [$data['rol']];
                }
                $this->syncRoles($tercero, $roles);
            }

            $tercero->load('roles');

            if ($tercero->esCliente()) {
                $this->upsertPerfilCliente($tercero, $data['cliente'] ?? [], $data['gym'] ?? []);
            }
            if ($tercero->esProveedor()) {
                $this->upsertPerfilProveedor($tercero, $data['proveedor'] ?? []);
                if (! empty($data['productos_seleccionados_presentes'])) {
                    $this->syncProductos($store, $tercero, $data['productos'] ?? []);
                }
            }
            if ($tercero->esTrabajador()) {
                $this->upsertPerfilTrabajador($tercero, $data['trabajador'] ?? []);
                $this->syncStoreUserFromTrabajador($store, $tercero->fresh(['perfilTrabajador', 'user']));
            } else {
                $this->quitarDeStoreUserSiEraTrabajador($store, $tercero);
            }

            foreach ($data['contactos_nuevos'] ?? [] as $contacto) {
                if (filled($contacto['nombre'] ?? null)) {
                    $this->agregarContacto($store, $tercero, $contacto);
                }
            }

            return $tercero->fresh([
                'roles',
                'contactos',
                'direcciones',
                'perfilCliente.gym',
                'perfilProveedor',
                'perfilTrabajador.role',
                'contratosLaborales',
            ]);
        });
    }

    public function eliminar(Store $store, Tercero $tercero): void
    {
        if ($tercero->store_id !== $store->id) {
            throw new Exception('El tercero no pertenece a esta tienda.');
        }

        if ($tercero->numero_identificacion === Tercero::CONSUMIDOR_FINAL_DOCUMENT) {
            throw new Exception('No se puede eliminar el consumidor final.');
        }

        DB::transaction(function () use ($store, $tercero) {
            $this->quitarDeStoreUserSiEraTrabajador($store, $tercero);
            $tercero->update(['activo' => false]);
            $tercero->delete();
        });
    }

    public function asegurarConsumidorFinal(Store $store): Tercero
    {
        $existente = Tercero::query()
            ->deStore($store)
            ->where('numero_identificacion', Tercero::CONSUMIDOR_FINAL_DOCUMENT)
            ->first();

        if ($existente) {
            $this->syncRoles($existente, [Tercero::ROL_CLIENTE]);
            if (! $existente->perfilCliente) {
                $this->upsertPerfilCliente($existente, [], []);
            }

            return $existente->fresh(['roles', 'perfilCliente']);
        }

        return $this->crear($store, [
            'tipo_persona' => Tercero::TIPO_PERSONA_NATURAL,
            'tipo_identificacion' => Tercero::ID_NIT,
            'numero_identificacion' => Tercero::CONSUMIDOR_FINAL_DOCUMENT,
            'nombre' => Tercero::CONSUMIDOR_FINAL_NAME,
            'email' => Tercero::consumidorFinalEmailForStore($store->id),
            'direccion' => Tercero::CONSUMIDOR_FINAL_ADDRESS,
            'activo' => true,
            'roles' => [Tercero::ROL_CLIENTE],
        ]);
    }

    public function agregarContacto(Store $store, Tercero $tercero, array $data): TerceroContacto
    {
        $this->assertStore($store, $tercero);

        return TerceroContacto::create([
            'tercero_id' => $tercero->id,
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'cargo' => $data['cargo'] ?? null,
            'parentesco' => $data['parentesco'] ?? null,
            'tipo_contacto' => $data['tipo_contacto'] ?? null,
            'email' => $data['email'] ?? null,
            'telefono' => $data['telefono'] ?? null,
            'celular' => $data['celular'] ?? null,
            'es_principal' => (bool) ($data['es_principal'] ?? (($data['tipo_contacto'] ?? null) === 'principal')),
            'es_facturacion' => (bool) ($data['es_facturacion'] ?? (($data['tipo_contacto'] ?? null) === 'facturacion')),
            'es_cartera' => (bool) ($data['es_cartera'] ?? (($data['tipo_contacto'] ?? null) === 'cartera')),
            'es_compras' => (bool) ($data['es_compras'] ?? (($data['tipo_contacto'] ?? null) === 'compras')),
            'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
        ]);
    }

    public function actualizarContacto(Store $store, Tercero $tercero, TerceroContacto $contacto, array $data): TerceroContacto
    {
        $this->assertStore($store, $tercero);
        if ($contacto->tercero_id !== $tercero->id) {
            throw new Exception('El contacto no pertenece al tercero.');
        }

        $tipoCambio = array_key_exists('tipo_contacto', $data);
        $tipoContacto = $data['tipo_contacto'] ?? $contacto->tipo_contacto;
        $contacto->update([
            'nombre' => trim((string) ($data['nombre'] ?? $contacto->nombre)),
            'cargo' => $data['cargo'] ?? $contacto->cargo,
            'parentesco' => $data['parentesco'] ?? $contacto->parentesco,
            'tipo_contacto' => $data['tipo_contacto'] ?? $contacto->tipo_contacto,
            'email' => $data['email'] ?? $contacto->email,
            'telefono' => $data['telefono'] ?? $contacto->telefono,
            'celular' => $data['celular'] ?? $contacto->celular,
            'es_principal' => array_key_exists('es_principal', $data) ? (bool) $data['es_principal'] : ($tipoCambio ? $tipoContacto === 'principal' : $contacto->es_principal),
            'es_facturacion' => array_key_exists('es_facturacion', $data) ? (bool) $data['es_facturacion'] : ($tipoCambio ? $tipoContacto === 'facturacion' : $contacto->es_facturacion),
            'es_cartera' => array_key_exists('es_cartera', $data) ? (bool) $data['es_cartera'] : ($tipoCambio ? $tipoContacto === 'cartera' : $contacto->es_cartera),
            'es_compras' => array_key_exists('es_compras', $data) ? (bool) $data['es_compras'] : ($tipoCambio ? $tipoContacto === 'compras' : $contacto->es_compras),
            'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : $contacto->activo,
        ]);

        return $contacto->fresh();
    }

    public function eliminarContacto(Store $store, Tercero $tercero, TerceroContacto $contacto): void
    {
        $this->assertStore($store, $tercero);
        if ($contacto->tercero_id !== $tercero->id) {
            throw new Exception('El contacto no pertenece al tercero.');
        }
        $contacto->delete();
    }

    public function agregarDireccion(Store $store, Tercero $tercero, array $data): TerceroDireccion
    {
        $this->assertStore($store, $tercero);

        return TerceroDireccion::create([
            'tercero_id' => $tercero->id,
            'tipo' => $data['tipo'] ?? TerceroDireccion::TIPO_FISCAL,
            'linea' => trim((string) ($data['linea'] ?? '')),
            'ciudad' => $data['ciudad'] ?? null,
            'departamento' => $data['departamento'] ?? null,
            'pais' => $data['pais'] ?? 'Colombia',
            'es_principal' => (bool) ($data['es_principal'] ?? false),
            'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
        ]);
    }

    public function actualizarDireccion(Store $store, Tercero $tercero, TerceroDireccion $direccion, array $data): TerceroDireccion
    {
        $this->assertStore($store, $tercero);
        if ($direccion->tercero_id !== $tercero->id) {
            throw new Exception('La dirección no pertenece al tercero.');
        }

        $direccion->update([
            'tipo' => $data['tipo'] ?? $direccion->tipo,
            'linea' => trim((string) ($data['linea'] ?? $direccion->linea)),
            'ciudad' => $data['ciudad'] ?? $direccion->ciudad,
            'departamento' => $data['departamento'] ?? $direccion->departamento,
            'pais' => $data['pais'] ?? $direccion->pais,
            'es_principal' => array_key_exists('es_principal', $data) ? (bool) $data['es_principal'] : $direccion->es_principal,
            'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : $direccion->activo,
        ]);

        return $direccion->fresh();
    }

    public function eliminarDireccion(Store $store, Tercero $tercero, TerceroDireccion $direccion): void
    {
        $this->assertStore($store, $tercero);
        if ($direccion->tercero_id !== $tercero->id) {
            throw new Exception('La dirección no pertenece al tercero.');
        }
        $direccion->delete();
    }

    public function vincularPorEmailUsuario(User $user): int
    {
        if (empty($user->email)) {
            return 0;
        }

        return DB::transaction(function () use ($user) {
            $terceros = Tercero::query()
                ->where('email', $user->email)
                ->whereNull('user_id')
                ->with(['roles', 'perfilTrabajador', 'store'])
                ->get();

            foreach ($terceros as $tercero) {
                $tercero->update(['user_id' => $user->id]);
                if ($tercero->esTrabajador() && $tercero->perfilTrabajador?->role_id) {
                    $this->asegurarEnStoreUser($tercero->store, $user->id, (int) $tercero->perfilTrabajador->role_id);
                }
            }

            return $terceros->count();
        });
    }

    private function normalizarIdentidad(Store $store, array $data, ?Tercero $existente = null): array
    {
        $nombre = trim((string) ($data['nombre'] ?? $data['name'] ?? ''));
        if ($nombre === '') {
            throw new Exception('El nombre del tercero es obligatorio.');
        }

        $numero = $data['numero_identificacion'] ?? $data['document_number'] ?? $data['nit'] ?? null;
        $numero = is_string($numero) ? preg_replace('/\s+/', '', trim($numero)) : $numero;
        if ($numero === '') {
            $numero = null;
        }

        $tipoId = $data['tipo_identificacion'] ?? null;
        if ($tipoId === '') {
            $tipoId = null;
        }
        if ($numero && ! $tipoId) {
            $tipoId = Tercero::ID_CC;
        }
        if ($tipoId && ! in_array($tipoId, Tercero::TIPOS_IDENTIFICACION, true)) {
            throw new Exception('Tipo de identificación no válido.');
        }

        if ($numero) {
            $dup = Tercero::query()
                ->deStore($store)
                ->where('numero_identificacion', $numero)
                ->when($existente, fn ($q) => $q->where('id', '!=', $existente->id))
                ->exists();
            if ($dup) {
                throw new Exception('Ya existe un tercero con esa identificación en esta tienda.');
            }
        }

        $tipoPersona = $data['tipo_persona'] ?? Tercero::TIPO_PERSONA_NATURAL;
        if (! in_array($tipoPersona, Tercero::TIPOS_PERSONA, true)) {
            $tipoPersona = Tercero::TIPO_PERSONA_NATURAL;
        }

        $email = $data['email'] ?? null;
        if (is_string($email)) {
            $email = trim($email) ?: null;
        }

        return [
            'tipo_persona' => $tipoPersona,
            'tipo_identificacion' => $tipoId,
            'numero_identificacion' => $numero,
            'digito_verificacion' => $tipoId === Tercero::ID_NIT
                ? $this->calcularDigitoVerificacion($numero)
                : null,
            'nombre' => $nombre,
            'nombre_comercial' => $data['nombre_comercial'] ?? null,
            'email' => $email,
            'telefono' => $data['telefono'] ?? $data['phone'] ?? $data['numero_celular'] ?? null,
            'telefono_secundario' => $data['telefono_secundario'] ?? $data['telefono_fijo'] ?? null,
            'direccion' => $data['direccion'] ?? $data['address'] ?? null,
            'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : ($existente?->activo ?? true),
            'user_id' => $email
                ? (User::where('email', $email)->value('id') ?? $existente?->user_id)
                : ($existente?->user_id),
        ];
    }

    private function calcularDigitoVerificacion(?string $numero): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $numero);
        if ($digitos === '' || strlen($digitos) > 15) {
            return null;
        }

        $pesos = [71, 67, 59, 53, 47, 43, 41, 37, 29, 23, 19, 17, 13, 7, 3];
        $digitos = str_pad($digitos, count($pesos), '0', STR_PAD_LEFT);
        $suma = 0;

        foreach ($pesos as $indice => $peso) {
            $suma += ((int) $digitos[$indice]) * $peso;
        }

        $residuo = $suma % 11;

        return (string) ($residuo <= 1 ? $residuo : 11 - $residuo);
    }

    private function syncRoles(Tercero $tercero, array $roles): void
    {
        $roles = array_values(array_unique(array_filter($roles, fn ($r) => in_array($r, Tercero::ROLES, true))));
        if ($roles === []) {
            throw new Exception('Debes asignar al menos un rol al tercero.');
        }

        $existentes = TerceroRol::query()->where('tercero_id', $tercero->id)->get()->keyBy('rol');

        foreach ($roles as $rol) {
            if ($existentes->has($rol)) {
                $existentes[$rol]->update(['activo' => true]);
            } else {
                TerceroRol::create([
                    'tercero_id' => $tercero->id,
                    'rol' => $rol,
                    'activo' => true,
                ]);
            }
        }

        foreach ($existentes as $rol => $row) {
            if (! in_array($rol, $roles, true)) {
                $row->update(['activo' => false]);
            }
        }

        $tercero->unsetRelation('roles');
        $tercero->load('roles');
    }

    private function upsertPerfilCliente(Tercero $tercero, array $cliente, array $gym): void
    {
        $perfil = TerceroClientePerfil::query()->firstOrNew(['tercero_id' => $tercero->id]);
        $perfil->fill([
            'credito_habilitado' => (bool) ($cliente['credito_habilitado'] ?? $perfil->credito_habilitado ?? false),
            'cupo_credito' => $cliente['cupo_credito'] ?? $perfil->cupo_credito,
            'dias_plazo' => $cliente['dias_plazo'] ?? $perfil->dias_plazo,
            'bloqueado_ventas' => (bool) ($cliente['bloqueado_ventas'] ?? $perfil->bloqueado_ventas ?? false),
            'motivo_bloqueo' => $cliente['motivo_bloqueo'] ?? $perfil->motivo_bloqueo,
            'observaciones' => $cliente['observaciones'] ?? $perfil->observaciones,
        ]);
        $perfil->save();

        $gymPerfil = TerceroClienteGymPerfil::query()->firstOrNew(['tercero_cliente_perfil_id' => $perfil->id]);
        $gymPerfil->fill([
            'gender' => $gym['gender'] ?? $gymPerfil->gender,
            'blood_type' => $gym['blood_type'] ?? $gymPerfil->blood_type,
            'eps' => $gym['eps'] ?? $gymPerfil->eps,
            'birth_date' => $gym['birth_date'] ?? $gymPerfil->birth_date,
            'emergency_contact_name' => $gym['emergency_contact_name'] ?? $gymPerfil->emergency_contact_name,
            'emergency_contact_phone' => $gym['emergency_contact_phone'] ?? $gymPerfil->emergency_contact_phone,
        ]);
        $gymPerfil->save();
    }

    private function upsertPerfilProveedor(Tercero $tercero, array $proveedor): void
    {
        $perfil = TerceroProveedorPerfil::query()->firstOrNew(['tercero_id' => $tercero->id]);
        $perfil->fill([
            'plazo_pago_dias' => $proveedor['plazo_pago_dias'] ?? $perfil->plazo_pago_dias,
            'preferido' => (bool) ($proveedor['preferido'] ?? $perfil->preferido ?? false),
            'observaciones' => $proveedor['observaciones'] ?? $perfil->observaciones,
        ]);
        $perfil->save();
    }

    private function upsertPerfilTrabajador(Tercero $tercero, array $trabajador): void
    {
        $perfil = TerceroTrabajadorPerfil::query()->firstOrNew(['tercero_id' => $tercero->id]);
        $perfil->fill([
            'role_id' => $trabajador['role_id'] ?? $perfil->role_id,
            'cargo' => $trabajador['cargo'] ?? $perfil->cargo,
            'fecha_ingreso' => $trabajador['fecha_ingreso'] ?? $perfil->fecha_ingreso,
            'estado_laboral' => $trabajador['estado_laboral'] ?? $perfil->estado_laboral ?? 'activo',
        ]);
        if (! $perfil->role_id) {
            throw new Exception('El rol de permisos es obligatorio para un trabajador.');
        }
        $perfil->save();

        if (! empty($trabajador['contrato']) && is_array($trabajador['contrato'])) {
            $c = $trabajador['contrato'];
            ContratoLaboral::create([
                'tercero_id' => $tercero->id,
                'tipo_contrato' => $c['tipo_contrato'] ?? null,
                'fecha_inicio' => $c['fecha_inicio'] ?? null,
                'fecha_fin' => $c['fecha_fin'] ?? null,
                'salario_base' => $c['salario_base'] ?? null,
                'jornada' => $c['jornada'] ?? null,
                'cargo' => $c['cargo'] ?? $perfil->cargo,
                'estado' => $c['estado'] ?? 'vigente',
            ]);
        }
    }

    private function syncProductos(Store $store, Tercero $tercero, array $productoIds): void
    {
        $ids = collect($productoIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $validos = DB::table('products')
            ->where('store_id', $store->id)
            ->whereIn('id', $ids)
            ->pluck('id');

        $tercero->productos()->sync($validos->all());
    }

    private function seedContactoPrincipal(Tercero $tercero): void
    {
        if ($tercero->contactos()->exists()) {
            return;
        }
        if (! $tercero->email && ! $tercero->telefono) {
            return;
        }

        TerceroContacto::create([
            'tercero_id' => $tercero->id,
            'nombre' => $tercero->nombre,
            'tipo_contacto' => 'principal',
            'email' => $tercero->email,
            'telefono' => $tercero->telefono,
            'celular' => $tercero->telefono,
            'es_principal' => true,
            'es_facturacion' => true,
            'activo' => true,
        ]);
    }

    private function seedDireccionFiscal(Tercero $tercero): void
    {
        if ($tercero->direcciones()->exists()) {
            return;
        }

        TerceroDireccion::create([
            'tercero_id' => $tercero->id,
            'tipo' => TerceroDireccion::TIPO_FISCAL,
            'linea' => (string) $tercero->direccion,
            'pais' => 'Colombia',
            'es_principal' => true,
            'activo' => true,
        ]);
    }

    private function syncStoreUserFromTrabajador(Store $store, Tercero $tercero): void
    {
        $perfil = $tercero->perfilTrabajador;
        if (! $perfil?->role_id) {
            return;
        }

        $userId = $tercero->user_id;
        if (! $userId && $tercero->email) {
            $userId = User::where('email', $tercero->email)->value('id');
            if ($userId) {
                $tercero->update(['user_id' => $userId]);
            }
        }

        if ($userId) {
            $this->asegurarEnStoreUser($store, (int) $userId, (int) $perfil->role_id);
        }
    }

    private function quitarDeStoreUserSiEraTrabajador(Store $store, Tercero $tercero): void
    {
        if (! $tercero->user_id) {
            return;
        }

        // Solo quitar si no queda como trabajador activo
        if ($tercero->fresh('roles')?->esTrabajador()) {
            return;
        }

        DB::table('store_user')
            ->where('store_id', $store->id)
            ->where('user_id', $tercero->user_id)
            ->delete();
    }

    private function asegurarEnStoreUser(Store $store, int $userId, int $roleId): void
    {
        $existe = DB::table('store_user')
            ->where('store_id', $store->id)
            ->where('user_id', $userId)
            ->exists();

        if (! $existe) {
            DB::table('store_user')->insert([
                'store_id' => $store->id,
                'user_id' => $userId,
                'role_id' => $roleId,
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

    private function assertStore(Store $store, Tercero $tercero): void
    {
        if ($tercero->store_id !== $store->id) {
            throw new Exception('El tercero no pertenece a esta tienda.');
        }
    }
}
