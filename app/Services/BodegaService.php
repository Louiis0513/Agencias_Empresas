<?php

namespace App\Services;

use App\Models\Bodega;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BodegaService
{
    public function listar(Store $store, array $filtros = [], int $perPage = 30): LengthAwarePaginator
    {
        $q = Bodega::query()
            ->deStore($store)
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

        return $q->paginate($perPage)->withQueryString();
    }

    public function crear(Store $store, array $data): Bodega
    {
        if (! $store->maneja_bodegas) {
            throw new Exception('Activa el manejo de bodegas antes de crear una bodega.');
        }

        $codigo = $this->normalizarCodigo($data['codigo'] ?? null);
        $nombre = $this->normalizarNombre($data['nombre'] ?? null);
        $this->assertCodigoUnico($store, $codigo);

        return Bodega::create([
            'store_id' => $store->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
        ]);
    }

    public function actualizar(Store $store, Bodega $bodega, array $data): Bodega
    {
        $this->validarPertenencia($store, $bodega);

        $codigo = $this->normalizarCodigo($data['codigo'] ?? $bodega->codigo);
        $nombre = $this->normalizarNombre($data['nombre'] ?? $bodega->nombre);
        $this->assertCodigoUnico($store, $codigo, $bodega);

        $bodega->codigo = $codigo;
        $bodega->nombre = $nombre;
        if (array_key_exists('activo', $data)) {
            $bodega->activo = (bool) $data['activo'];
        }
        $bodega->save();

        return $bodega->fresh();
    }

    public function actualizarManejoBodegas(Store $store, bool $maneja): Store
    {
        if (! $maneja && $this->tiendaTieneBodegasConMovimientos($store)) {
            throw new Exception('No es posible desactivar el manejo de bodegas porque alguna bodega ya tiene movimientos.');
        }

        $store->maneja_bodegas = $maneja;
        $store->save();

        return $store->fresh();
    }

    /**
     * Stub hasta reconstruir inventario: sin movimientos asociados aún.
     */
    public function tieneMovimientos(Bodega $bodega): bool
    {
        return false;
    }

    public function tiendaTieneBodegasConMovimientos(Store $store): bool
    {
        $bodegas = Bodega::query()->deStore($store)->get(['id']);

        foreach ($bodegas as $bodega) {
            if ($this->tieneMovimientos($bodega)) {
                return true;
            }
        }

        return false;
    }

    protected function validarPertenencia(Store $store, Bodega $bodega): void
    {
        if ($bodega->store_id !== $store->id) {
            throw new Exception('La bodega no pertenece a esta tienda.');
        }
    }

    protected function normalizarCodigo(?string $codigo): string
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            throw new Exception('El código de la bodega es obligatorio.');
        }
        if (mb_strlen($codigo) > 32) {
            throw new Exception('El código no puede superar 32 caracteres.');
        }

        return $codigo;
    }

    protected function normalizarNombre(?string $nombre): string
    {
        $nombre = trim((string) $nombre);
        if ($nombre === '') {
            throw new Exception('El nombre de la bodega es obligatorio.');
        }

        return $nombre;
    }

    protected function assertCodigoUnico(Store $store, string $codigo, ?Bodega $excepto = null): void
    {
        $q = Bodega::query()
            ->deStore($store)
            ->where('codigo', $codigo);

        if ($excepto) {
            $q->whereKeyNot($excepto->id);
        }

        if ($q->exists()) {
            throw new Exception('Ya existe una bodega con el código «'.$codigo.'» en esta tienda.');
        }
    }
}
