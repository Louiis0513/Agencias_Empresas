<?php

namespace App\Services;

use App\Models\CuentaContable;
use App\Models\Impuesto;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ImpuestoService
{
    public function listar(Store $store, array $filtros = [], int $perPage = 30): LengthAwarePaginator
    {
        $q = Impuesto::query()
            ->deStore($store)
            ->with([
                'cuentaVentas:id,codigo,nombre',
                'cuentaCompras:id,codigo,nombre',
                'cuentaDevolucionVentas:id,codigo,nombre',
                'cuentaDevolucionCompras:id,codigo,nombre',
            ])
            ->orderBy('codigo');

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $q->where(function ($qq) use ($search) {
                $qq->where('nombre', 'like', '%'.$search.'%')
                    ->orWhere('codigo', 'like', $search.'%')
                    ->orWhere('tipo', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filtros['tipo'])) {
            $q->where('tipo', $filtros['tipo']);
        }

        if (isset($filtros['en_uso']) && $filtros['en_uso'] !== '' && $filtros['en_uso'] !== null) {
            $q->where('en_uso', filter_var($filtros['en_uso'], FILTER_VALIDATE_BOOLEAN));
        }

        return $q->paginate($perPage)->withQueryString();
    }

    /** @return Collection<int, CuentaContable> */
    public function cuentasDisponibles(Store $store): Collection
    {
        return CuentaContable::query()
            ->deStore($store)
            ->activas()
            ->transaccionales()
            ->where('es_auxiliar', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);
    }

    public function siguienteCodigo(Store $store): int
    {
        $max = (int) Impuesto::query()
            ->deStore($store)
            ->max('codigo');

        return $max + 1;
    }

    public function crear(Store $store, array $data): Impuesto
    {
        $payload = $this->normalizarYValidar($store, $data);

        return Impuesto::create([
            'store_id' => $store->id,
            ...$payload,
        ]);
    }

    public function actualizar(Store $store, Impuesto $impuesto, array $data): Impuesto
    {
        $this->validarPertenencia($store, $impuesto);
        $payload = $this->normalizarYValidar($store, $data, $impuesto);
        $impuesto->update($payload);

        return $impuesto->fresh([
            'cuentaVentas',
            'cuentaCompras',
            'cuentaDevolucionVentas',
            'cuentaDevolucionCompras',
        ]);
    }

    /**
     * @return array{
     *   en_uso: bool,
     *   codigo: int,
     *   nombre: string,
     *   tipo: string,
     *   por_valor: bool,
     *   tarifa: string,
     *   cuenta_ventas_id: int,
     *   cuenta_compras_id: int,
     *   cuenta_devolucion_ventas_id: int,
     *   cuenta_devolucion_compras_id: int
     * }
     */
    private function normalizarYValidar(Store $store, array $data, ?Impuesto $existente = null): array
    {
        $tipo = trim((string) ($data['tipo'] ?? ''));
        if (! in_array($tipo, Impuesto::TIPOS, true)) {
            throw new Exception('El tipo de impuesto no es válido.');
        }

        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new Exception('El nombre del impuesto es obligatorio.');
        }

        $codigoRaw = $data['codigo'] ?? null;
        $codigo = ($codigoRaw === null || $codigoRaw === '')
            ? $this->siguienteCodigo($store)
            : (int) $codigoRaw;

        if ($codigo < 1) {
            throw new Exception('El código debe ser un entero mayor o igual a 1.');
        }

        $dup = Impuesto::query()
            ->deStore($store)
            ->where('codigo', $codigo)
            ->when($existente, fn ($q) => $q->whereKeyNot($existente->id))
            ->exists();

        if ($dup) {
            throw new Exception('Ya existe un impuesto con el código '.$codigo.'.');
        }

        $tarifa = round((float) ($data['tarifa'] ?? 0), 4);
        if ($tarifa < 0) {
            throw new Exception('La tarifa no puede ser negativa.');
        }

        $ventasId = $this->requireId($data['cuenta_ventas_id'] ?? null, 'cuenta de ventas');
        $comprasId = $this->requireId($data['cuenta_compras_id'] ?? null, 'cuenta de compras');
        $devVentasId = $this->requireId($data['cuenta_devolucion_ventas_id'] ?? null, 'cuenta de devolución de ventas');
        $devComprasId = $this->requireId($data['cuenta_devolucion_compras_id'] ?? null, 'cuenta de devolución de compras');

        $this->assertCuentaAuxiliar($store, $ventasId, 'ventas');
        $this->assertCuentaAuxiliar($store, $comprasId, 'compras');
        $this->assertCuentaAuxiliar($store, $devVentasId, 'devolución de ventas');
        $this->assertCuentaAuxiliar($store, $devComprasId, 'devolución de compras');

        return [
            'en_uso' => array_key_exists('en_uso', $data) ? (bool) $data['en_uso'] : true,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'por_valor' => array_key_exists('por_valor', $data) ? (bool) $data['por_valor'] : false,
            'tarifa' => number_format($tarifa, 4, '.', ''),
            'cuenta_ventas_id' => $ventasId,
            'cuenta_compras_id' => $comprasId,
            'cuenta_devolucion_ventas_id' => $devVentasId,
            'cuenta_devolucion_compras_id' => $devComprasId,
        ];
    }

    private function requireId(mixed $value, string $label): int
    {
        if ($value === null || $value === '') {
            throw new Exception('Debes seleccionar la '.$label.'.');
        }

        return (int) $value;
    }

    private function assertCuentaAuxiliar(Store $store, int $cuentaId, string $rol): void
    {
        $cuenta = CuentaContable::query()
            ->deStore($store)
            ->whereKey($cuentaId)
            ->first();

        if (
            ! $cuenta
            || ! $cuenta->activo
            || ! $cuenta->es_auxiliar
            || ! $cuenta->esTransaccional()
        ) {
            throw new Exception(
                'La cuenta de '.$rol.' debe ser auxiliar, transaccional, activa y pertenecer a esta tienda.'
            );
        }
    }

    private function validarPertenencia(Store $store, Impuesto $impuesto): void
    {
        if ($impuesto->store_id !== $store->id) {
            throw new Exception('El impuesto no pertenece a esta tienda.');
        }
    }
}
