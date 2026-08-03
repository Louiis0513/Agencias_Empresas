<?php

namespace App\Services;

use App\Models\CuentaContable;
use App\Models\FormaPago;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FormaPagoService
{
    public function __construct(
        protected CuentaContableService $cuentaContableService,
    ) {}

    public function listar(Store $store, array $filtros = [], int $perPage = 30): LengthAwarePaginator
    {
        $q = FormaPago::query()
            ->deStore($store)
            ->with(['cuentaContable:id,codigo,nombre,categoria'])
            ->orderBy('codigo');

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $q->where(function ($qq) use ($search) {
                $qq->where('nombre', 'like', '%'.$search.'%')
                    ->orWhere('codigo', 'like', $search.'%')
                    ->orWhere('medio_pago_dian', 'like', $search.'%');
            });
        }

        if (! empty($filtros['aplica_a'])) {
            $q->where('aplica_a', $filtros['aplica_a']);
        }

        if (isset($filtros['en_uso']) && $filtros['en_uso'] !== '' && $filtros['en_uso'] !== null) {
            $q->where('en_uso', filter_var($filtros['en_uso'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($filtros['es_pago_en_linea']) && $filtros['es_pago_en_linea'] !== '' && $filtros['es_pago_en_linea'] !== null) {
            $q->where('es_pago_en_linea', filter_var($filtros['es_pago_en_linea'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filtros['cuenta_contable_id'])) {
            $q->where('cuenta_contable_id', (int) $filtros['cuenta_contable_id']);
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
            ->get(['id', 'codigo', 'nombre', 'categoria']);
    }

    public function siguienteCodigo(Store $store): int
    {
        $max = (int) FormaPago::query()
            ->deStore($store)
            ->max('codigo');

        return $max + 1;
    }

    /**
     * Crea Efectivo, Transferencia y Crédito si faltan (con auxiliares bajo padres PUC).
     *
     * @return array{creadas: int, omitidas: int, errores: list<string>}
     */
    public function asegurarDefaults(Store $store): array
    {
        $stats = ['creadas' => 0, 'omitidas' => 0, 'errores' => []];

        $defaults = [
            [
                'nombre' => 'Efectivo',
                'aplica_a' => FormaPago::APLICA_AMBOS,
                'padre' => '110505',
                'nombre_cuenta' => 'Caja general',
                'medio_pago_dian' => '10',
                'es_pago_en_linea' => false,
            ],
            [
                'nombre' => 'Transferencia',
                'aplica_a' => FormaPago::APLICA_AMBOS,
                'padre' => '111005',
                'nombre_cuenta' => 'Moneda nacional',
                'medio_pago_dian' => '45',
                'es_pago_en_linea' => false,
            ],
            [
                'nombre' => 'Crédito',
                'aplica_a' => FormaPago::APLICA_CARTERA,
                'padre' => '130505',
                'nombre_cuenta' => 'Clientes nacionales',
                'medio_pago_dian' => '1',
                'es_pago_en_linea' => false,
            ],
        ];

        foreach ($defaults as $def) {
            $existe = FormaPago::query()
                ->deStore($store)
                ->where('nombre', $def['nombre'])
                ->exists();

            if ($existe) {
                $stats['omitidas']++;

                continue;
            }

            try {
                DB::transaction(function () use ($store, $def, &$stats) {
                    $cuenta = $this->asegurarAuxiliarBajoPadre(
                        $store,
                        $def['padre'],
                        $def['nombre_cuenta']
                    );

                    $this->crear($store, [
                        'en_uso' => true,
                        'nombre' => $def['nombre'],
                        'aplica_a' => $def['aplica_a'],
                        'cuenta_contable_id' => $cuenta->id,
                        'medio_pago_dian' => $def['medio_pago_dian'],
                        'es_pago_en_linea' => $def['es_pago_en_linea'],
                    ]);

                    $stats['creadas']++;
                });
            } catch (Exception $e) {
                $stats['errores'][] = $def['nombre'].': '.$e->getMessage();
            }
        }

        return $stats;
    }

    public function crear(Store $store, array $data): FormaPago
    {
        $payload = $this->normalizarYValidar($store, $data);

        return FormaPago::create([
            'store_id' => $store->id,
            ...$payload,
        ]);
    }

    public function actualizar(Store $store, FormaPago $formaPago, array $data): FormaPago
    {
        $this->validarPertenencia($store, $formaPago);
        $payload = $this->normalizarYValidar($store, $data, $formaPago);
        $formaPago->update($payload);

        return $formaPago->fresh(['cuentaContable']);
    }

    /**
     * @return array{
     *   en_uso: bool,
     *   codigo: int,
     *   nombre: string,
     *   aplica_a: string,
     *   cuenta_contable_id: int,
     *   medio_pago_dian: ?string,
     *   es_pago_en_linea: bool
     * }
     */
    private function normalizarYValidar(Store $store, array $data, ?FormaPago $existente = null): array
    {
        $aplicaA = trim((string) ($data['aplica_a'] ?? ''));
        if (! in_array($aplicaA, FormaPago::APLICA_A, true)) {
            throw new Exception('El alcance «aplica a» no es válido.');
        }

        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new Exception('El nombre de la forma de pago es obligatorio.');
        }

        $codigoRaw = $data['codigo'] ?? null;
        $codigo = ($codigoRaw === null || $codigoRaw === '')
            ? $this->siguienteCodigo($store)
            : (int) $codigoRaw;

        if ($codigo < 1) {
            throw new Exception('El código debe ser un entero mayor o igual a 1.');
        }

        $dup = FormaPago::query()
            ->deStore($store)
            ->where('codigo', $codigo)
            ->when($existente, fn ($q) => $q->whereKeyNot($existente->id))
            ->exists();

        if ($dup) {
            throw new Exception('Ya existe una forma de pago con el código '.$codigo.'.');
        }

        $cuentaId = $this->requireId($data['cuenta_contable_id'] ?? null, 'cuenta contable');
        $this->assertCuentaAuxiliar($store, $cuentaId);

        $medio = $data['medio_pago_dian'] ?? null;
        if ($medio === '' || $medio === null) {
            $medio = null;
        } else {
            $medio = (string) $medio;
            if (! array_key_exists($medio, FormaPago::MEDIOS_PAGO_DIAN)) {
                throw new Exception('El medio de pago DIAN no es válido.');
            }
        }

        return [
            'en_uso' => array_key_exists('en_uso', $data) ? (bool) $data['en_uso'] : true,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'aplica_a' => $aplicaA,
            'cuenta_contable_id' => $cuentaId,
            'medio_pago_dian' => $medio,
            'es_pago_en_linea' => array_key_exists('es_pago_en_linea', $data)
                ? (bool) $data['es_pago_en_linea']
                : false,
        ];
    }

    private function asegurarAuxiliarBajoPadre(Store $store, string $codigoPadre, string $nombreCuenta): CuentaContable
    {
        $padre = CuentaContable::query()
            ->deStore($store)
            ->where('codigo', $codigoPadre)
            ->where('es_auxiliar', false)
            ->first();

        if (! $padre) {
            throw new Exception(
                'No existe la subcuenta padre '.$codigoPadre.' en el plan de cuentas. '
                .'Importa el PUC base desde Contabilidad → Plan de cuentas.'
            );
        }

        $existente = CuentaContable::query()
            ->deStore($store)
            ->where('es_auxiliar', true)
            ->where('codigo', 'like', $codigoPadre.'%')
            ->orderBy('codigo')
            ->get()
            ->first(fn (CuentaContable $c) => strlen(preg_replace('/\D/', '', $c->codigo) ?? '') === strlen($codigoPadre) + 2);

        if ($existente) {
            return $existente;
        }

        return $this->cuentaContableService->crearAuxiliar($store, [
            'cuenta_padre_id' => $padre->id,
            'nombre' => $nombreCuenta,
            'nivel_agrupacion' => CuentaContable::NIVEL_TRANSACCIONAL,
            'activo' => true,
        ]);
    }

    private function requireId(mixed $value, string $label): int
    {
        if ($value === null || $value === '') {
            throw new Exception('Debes seleccionar la '.$label.'.');
        }

        return (int) $value;
    }

    private function assertCuentaAuxiliar(Store $store, int $cuentaId): void
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
                'La cuenta contable debe ser auxiliar, transaccional, activa y pertenecer a esta tienda.'
            );
        }
    }

    private function validarPertenencia(Store $store, FormaPago $formaPago): void
    {
        if ($formaPago->store_id !== $store->id) {
            throw new Exception('La forma de pago no pertenece a esta tienda.');
        }
    }
}
