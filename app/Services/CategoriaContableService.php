<?php

namespace App\Services;

use App\Models\CategoriaContable;
use App\Models\CuentaContable;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CategoriaContableService
{
    public function __construct(
        protected CuentaContableService $cuentaContableService,
    ) {}

    public function listar(Store $store, array $filtros = [], int $perPage = 30): LengthAwarePaginator
    {
        $q = CategoriaContable::query()
            ->deStore($store)
            ->with([
                'cuentaInventario:id,codigo,nombre',
                'cuentaCosto:id,codigo,nombre',
                'cuentaIngreso:id,codigo,nombre',
                'cuentaDevolucion:id,codigo,nombre',
            ])
            ->orderByRaw('CAST(codigo AS UNSIGNED) asc')
            ->orderBy('codigo');

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $q->where(function ($qq) use ($search) {
                $qq->where('codigo', 'like', $search.'%')
                    ->orWhere('nombre', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filtros['tipo'])) {
            $q->where('tipo', $filtros['tipo']);
        }

        if (isset($filtros['activo']) && $filtros['activo'] !== '' && $filtros['activo'] !== null) {
            $q->where('activo', (bool) $filtros['activo']);
        }

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * Auxiliares activas sugeridas por rol contable (para selects del formulario).
     *
     * @return array{
     *   inventario: Collection<int, CuentaContable>,
     *   costo: Collection<int, CuentaContable>,
     *   ingreso: Collection<int, CuentaContable>,
     *   devolucion: Collection<int, CuentaContable>
     * }
     */
    public function cuentasParaSelects(Store $store): array
    {
        $auxiliares = CuentaContable::query()
            ->deStore($store)
            ->activas()
            ->where('es_auxiliar', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'categoria', 'relacion_con']);

        return [
            'inventario' => $auxiliares->filter(
                fn (CuentaContable $c) => CuentaContable::perfilDesdeCodigo($c->codigo) === 'inventario'
                    || $c->categoria === CuentaContable::CATEGORIA_INVENTARIOS
            )->values(),
            'costo' => $auxiliares->filter(
                fn (CuentaContable $c) => CuentaContable::perfilDesdeCodigo($c->codigo) === 'costo'
                    || $c->categoria === CuentaContable::CATEGORIA_COSTO_VENTAS
            )->values(),
            'ingreso' => $auxiliares->filter(
                fn (CuentaContable $c) => CuentaContable::perfilDesdeCodigo($c->codigo) === 'ingreso'
                    || (
                        $c->categoria === CuentaContable::CATEGORIA_INGRESOS
                        && CuentaContable::perfilDesdeCodigo($c->codigo) !== 'devolucion'
                    )
            )->values(),
            'devolucion' => $auxiliares->filter(
                fn (CuentaContable $c) => CuentaContable::perfilDesdeCodigo($c->codigo) === 'devolucion'
                    || $c->relacion_con === CuentaContable::RELACION_DEVOLUCIONES_VENTAS
            )->values(),
        ];
    }

    /**
     * Categorías activas para selects de producto.
     */
    public function listarActivasParaProducto(Store $store): Collection
    {
        return CategoriaContable::query()
            ->deStore($store)
            ->activas()
            ->orderByRaw('CAST(codigo AS UNSIGNED) asc')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'tipo']);
    }

    public function sugerirCodigo(Store $store): string
    {
        $codigos = CategoriaContable::query()
            ->deStore($store)
            ->pluck('codigo');

        $max = 0;
        foreach ($codigos as $codigo) {
            if (ctype_digit((string) $codigo)) {
                $max = max($max, (int) $codigo);
            }
        }

        return (string) ($max + 1);
    }

    public function crear(Store $store, array $data): CategoriaContable
    {
        $payload = $this->normalizarYValidar($store, $data);

        return DB::transaction(function () use ($store, $payload) {
            return CategoriaContable::create([
                'store_id' => $store->id,
                ...$payload,
            ])->fresh([
                'cuentaInventario',
                'cuentaCosto',
                'cuentaIngreso',
                'cuentaDevolucion',
            ]);
        });
    }

    public function actualizar(Store $store, CategoriaContable $categoria, array $data): CategoriaContable
    {
        if ($categoria->store_id !== $store->id) {
            throw new Exception('La categoría contable no pertenece a esta tienda.');
        }

        $payload = $this->normalizarYValidar($store, $data, $categoria);

        return DB::transaction(function () use ($categoria, $payload) {
            $categoria->update($payload);

            return $categoria->fresh([
                'cuentaInventario',
                'cuentaCosto',
                'cuentaIngreso',
                'cuentaDevolucion',
            ]);
        });
    }

    /**
     * Asegura categorías base estilo Siigo: Productos y Servicios.
     * Idempotente: no duplica si ya existen por tipo.
     *
     * @return array{creadas: list<string>, omitidas: list<string>, errores: list<string>}
     */
    public function asegurarCategoriasPorDefecto(Store $store): array
    {
        $stats = [
            'creadas' => [],
            'omitidas' => [],
            'errores' => [],
        ];

        try {
            $productos = CategoriaContable::query()
                ->deStore($store)
                ->where('tipo', CategoriaContable::TIPO_PRODUCTO)
                ->orderBy('id')
                ->first();

            if ($productos) {
                $stats['omitidas'][] = 'producto:'.$productos->codigo;
            } else {
                $cuentas = $this->cuentasParaSelects($store);
                if ($cuentas['inventario']->isEmpty() || $cuentas['costo']->isEmpty()
                    || $cuentas['ingreso']->isEmpty() || $cuentas['devolucion']->isEmpty()) {
                    $stats['errores'][] = 'No hay auxiliares suficientes para crear la categoría Productos.';
                } else {
                    $cat = $this->crear($store, [
                        'nombre' => 'Productos',
                        'tipo' => CategoriaContable::TIPO_PRODUCTO,
                        'cuenta_inventario_id' => $cuentas['inventario']->first()->id,
                        'cuenta_costo_id' => $cuentas['costo']->first()->id,
                        'cuenta_ingreso_id' => $cuentas['ingreso']->first()->id,
                        'cuenta_devolucion_id' => $cuentas['devolucion']->first()->id,
                        'activo' => true,
                    ]);
                    $stats['creadas'][] = 'producto:'.$cat->codigo;
                }
            }
        } catch (Exception $e) {
            $stats['errores'][] = 'producto: '.$e->getMessage();
        }

        try {
            $servicios = CategoriaContable::query()
                ->deStore($store)
                ->where('tipo', CategoriaContable::TIPO_SERVICIO)
                ->orderBy('id')
                ->first();

            $inventario = $this->asegurarAuxiliarNombrado(
                $store,
                CuentaContable::PADRE_INVENTARIO_MERCANCIA,
                'Inventario – servicios',
                CuentaContable::CATEGORIA_INVENTARIOS,
                CuentaContable::RELACION_INVENTARIO
            );
            $costo = $this->asegurarAuxiliarNombrado(
                $store,
                CuentaContable::PADRE_COSTO_COMERCIO,
                'Costo de ventas – servicios',
                CuentaContable::CATEGORIA_COSTO_VENTAS,
                CuentaContable::RELACION_COSTO_VENTAS
            );
            $ingreso = $this->asegurarAuxiliarNombrado(
                $store,
                CuentaContable::PADRE_INGRESO_COMERCIO,
                'Ingresos – servicios',
                CuentaContable::CATEGORIA_INGRESOS,
                CuentaContable::RELACION_INGRESOS_OPERACIONALES
            );
            $devolucion = $this->asegurarAuxiliarNombrado(
                $store,
                CuentaContable::PADRE_DEVOLUCION_VENTAS,
                'Devoluciones en ventas – servicios',
                CuentaContable::CATEGORIA_INGRESOS,
                CuentaContable::RELACION_DEVOLUCIONES_VENTAS
            );

            if ($servicios) {
                $dirty = false;
                if (! $servicios->cuenta_inventario_id) {
                    $servicios->cuenta_inventario_id = $inventario->id;
                    $dirty = true;
                }
                if (! $servicios->cuenta_costo_id) {
                    $servicios->cuenta_costo_id = $costo->id;
                    $dirty = true;
                }
                if (! $servicios->cuenta_ingreso_id) {
                    $servicios->cuenta_ingreso_id = $ingreso->id;
                    $dirty = true;
                }
                if (! $servicios->cuenta_devolucion_id) {
                    $servicios->cuenta_devolucion_id = $devolucion->id;
                    $dirty = true;
                }
                if ($dirty) {
                    $servicios->save();
                    $stats['creadas'][] = 'servicio-cuentas:'.$servicios->codigo;
                } else {
                    $stats['omitidas'][] = 'servicio:'.$servicios->codigo;
                }
            } else {
                $cat = $this->crear($store, [
                    'nombre' => 'Servicios',
                    'tipo' => CategoriaContable::TIPO_SERVICIO,
                    'cuenta_inventario_id' => $inventario->id,
                    'cuenta_costo_id' => $costo->id,
                    'cuenta_ingreso_id' => $ingreso->id,
                    'cuenta_devolucion_id' => $devolucion->id,
                    'activo' => true,
                ]);
                $stats['creadas'][] = 'servicio:'.$cat->codigo;
            }
        } catch (Exception $e) {
            $stats['errores'][] = 'servicio: '.$e->getMessage();
        }

        return $stats;
    }

    /**
     * Busca auxiliar por nombre bajo el padre; si no existe, la crea.
     */
    private function asegurarAuxiliarNombrado(
        Store $store,
        string $codigoPadre,
        string $nombre,
        string $categoria,
        string $relacionCon
    ): CuentaContable {
        $padre = CuentaContable::query()
            ->deStore($store)
            ->where('codigo', $codigoPadre)
            ->where('es_auxiliar', false)
            ->first();

        if (! $padre) {
            throw new Exception(
                "No existe la subcuenta {$codigoPadre} en el plan de cuentas. Importa el PUC base primero."
            );
        }

        $existente = CuentaContable::query()
            ->deStore($store)
            ->where('cuenta_padre_id', $padre->id)
            ->where('es_auxiliar', true)
            ->where('nombre', $nombre)
            ->orderBy('codigo')
            ->first();

        if ($existente) {
            return $existente;
        }

        return $this->cuentaContableService->crearAuxiliar($store, [
            'cuenta_padre_id' => $padre->id,
            'nombre' => $nombre,
            'categoria' => $categoria,
            'relacion_con' => $relacionCon,
            'maneja_vencimientos' => CuentaContable::MANEJA_VENCIMIENTOS_NO,
            'nivel_agrupacion' => CuentaContable::NIVEL_TRANSACCIONAL,
            'activo' => true,
            'diferencia_fiscal' => false,
        ]);
    }

    /**
     * @return array{
     *   codigo: string,
     *   nombre: string,
     *   tipo: string,
     *   cuenta_inventario_id: ?int,
     *   cuenta_costo_id: ?int,
     *   cuenta_ingreso_id: int,
     *   cuenta_devolucion_id: int,
     *   activo: bool
     * }
     */
    private function normalizarYValidar(Store $store, array $data, ?CategoriaContable $existente = null): array
    {
        $tipo = trim((string) ($data['tipo'] ?? ''));
        if (! in_array($tipo, CategoriaContable::TIPOS, true)) {
            throw new Exception('El tipo debe ser producto o servicio.');
        }

        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new Exception('El nombre de la categoría es obligatorio.');
        }

        $codigo = trim((string) ($data['codigo'] ?? ''));
        if ($codigo === '') {
            $codigo = $this->sugerirCodigo($store);
        }

        $dup = CategoriaContable::query()
            ->deStore($store)
            ->where('codigo', $codigo)
            ->when($existente, fn ($q) => $q->whereKeyNot($existente->id))
            ->exists();

        if ($dup) {
            throw new Exception('Ya existe una categoría contable con el código '.$codigo.'.');
        }

        $ingresoId = $this->nullableId($data['cuenta_ingreso_id'] ?? null);
        $devolucionId = $this->nullableId($data['cuenta_devolucion_id'] ?? null);
        $inventarioId = $this->nullableId($data['cuenta_inventario_id'] ?? null);
        $costoId = $this->nullableId($data['cuenta_costo_id'] ?? null);

        if (! $inventarioId) {
            throw new Exception('Debes seleccionar la cuenta de inventarios.');
        }
        if (! $costoId) {
            throw new Exception('Debes seleccionar la cuenta de costo de ventas.');
        }
        if (! $ingresoId) {
            throw new Exception('Debes seleccionar la cuenta de ingreso.');
        }
        if (! $devolucionId) {
            throw new Exception('Debes seleccionar la cuenta de devoluciones.');
        }

        $this->assertCuentaRol($store, $inventarioId, 'inventario');
        $this->assertCuentaRol($store, $costoId, 'costo');
        $this->assertCuentaRol($store, $ingresoId, 'ingreso');
        $this->assertCuentaRol($store, $devolucionId, 'devolucion');

        return [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'cuenta_inventario_id' => $inventarioId,
            'cuenta_costo_id' => $costoId,
            'cuenta_ingreso_id' => $ingresoId,
            'cuenta_devolucion_id' => $devolucionId,
            'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
        ];
    }

    private function assertCuentaRol(Store $store, int $cuentaId, string $rolEsperado): void
    {
        $cuenta = CuentaContable::query()
            ->deStore($store)
            ->whereKey($cuentaId)
            ->first();

        if (! $cuenta) {
            throw new Exception('La cuenta contable seleccionada no existe en esta tienda.');
        }

        if (! $cuenta->activo) {
            throw new Exception('La cuenta '.$cuenta->codigo.' está inactiva.');
        }

        if (! $cuenta->es_auxiliar) {
            throw new Exception('Debes usar una cuenta auxiliar (transaccional), no una cuenta base. Código: '.$cuenta->codigo);
        }

        $perfil = CuentaContable::perfilDesdeCodigo($cuenta->codigo);

        $ok = match ($rolEsperado) {
            'inventario' => $perfil === 'inventario'
                || $cuenta->categoria === CuentaContable::CATEGORIA_INVENTARIOS,
            'costo' => $perfil === 'costo'
                || $cuenta->categoria === CuentaContable::CATEGORIA_COSTO_VENTAS,
            'ingreso' => $perfil === 'ingreso'
                || (
                    $cuenta->categoria === CuentaContable::CATEGORIA_INGRESOS
                    && $perfil !== 'devolucion'
                ),
            'devolucion' => $perfil === 'devolucion'
                || $cuenta->relacion_con === CuentaContable::RELACION_DEVOLUCIONES_VENTAS,
            default => false,
        };

        if (! $ok) {
            $labels = [
                'inventario' => 'inventarios (14…)',
                'costo' => 'costo de ventas (61…/62…)',
                'ingreso' => 'ingresos (4…)',
                'devolucion' => 'devoluciones (4175…)',
            ];
            throw new Exception(
                'La cuenta '.$cuenta->codigo.' no es válida como cuenta de '.($labels[$rolEsperado] ?? $rolEsperado).'.'
            );
        }
    }

    private function nullableId(mixed $value): ?int
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        return (int) $value;
    }
}
