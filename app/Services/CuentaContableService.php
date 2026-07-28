<?php

namespace App\Services;

use App\Models\Bolsillo;
use App\Models\CuentaContable;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CuentaContableService
{
    /**
     * Lista cuentas de la tienda con filtros.
     */
    public function listar(Store $store, array $filtros = [], int $perPage = 50): LengthAwarePaginator
    {
        $q = CuentaContable::query()
            ->deStore($store)
            ->with('bolsillo:id,cuenta_contable_id,name,is_active')
            ->orderByRaw('codigo asc');

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $q->where(function ($qq) use ($search) {
                $qq->where('codigo', 'like', $search.'%')
                    ->orWhere('nombre', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filtros['clase'])) {
            $q->where('clase', $filtros['clase']);
        }

        if (isset($filtros['es_auxiliar']) && $filtros['es_auxiliar'] !== '' && $filtros['es_auxiliar'] !== null) {
            $q->where('es_auxiliar', (bool) $filtros['es_auxiliar']);
        }

        if (isset($filtros['activo']) && $filtros['activo'] !== '' && $filtros['activo'] !== null) {
            $q->where('activo', (bool) $filtros['activo']);
        }

        return $q->paginate($perPage)->withQueryString();
    }

    /**
     * Cuentas base (≤6 dígitos) sobre las que se pueden crear auxiliares.
     */
    public function padresParaAuxiliar(Store $store): Collection
    {
        return CuentaContable::query()
            ->deStore($store)
            ->activas()
            ->where('es_auxiliar', false)
            ->whereRaw('CHAR_LENGTH(codigo) = ?', [CuentaContable::MAX_CODIGO_BASE])
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'clase']);
    }

    /**
     * Siguiente sufijo sugerido (01–99) bajo un padre de 6 dígitos.
     */
    public function sugerirSufijo(Store $store, int|string $cuentaPadreIdOCodigo): string
    {
        $padre = $this->resolverPadre($store, $cuentaPadreIdOCodigo);

        return $this->siguienteSufijo($store, $padre->codigo);
    }

    /**
     * Próximo código auxiliar completo bajo un padre (ej. 11050503).
     */
    public function siguienteCodigoAuxiliar(Store $store, string $codigoPadre): string
    {
        $sufijo = $this->siguienteSufijo($store, $codigoPadre);

        return $codigoPadre.str_pad($sufijo, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Crea una cuenta auxiliar bajo una cuenta base (subcuenta de 6 dígitos).
     * Si el padre es Disponible operable (1105/1110/1120) y el nivel es Transaccional,
     * crea también el bolsillo espejo.
     */
    public function crearAuxiliar(Store $store, array $data): CuentaContable
    {
        $padre = CuentaContable::query()
            ->deStore($store)
            ->whereKey($data['cuenta_padre_id'])
            ->first();

        if (! $padre) {
            throw new Exception('La cuenta padre no existe en esta tienda.');
        }

        if ($padre->es_auxiliar) {
            throw new Exception('No se puede crear un auxiliar bajo otro auxiliar en el MVP. Usa una subcuenta de 6 dígitos.');
        }

        if (strlen(preg_replace('/\D/', '', $padre->codigo) ?? '') !== CuentaContable::MAX_CODIGO_BASE) {
            throw new Exception('El padre debe ser una subcuenta de 6 dígitos (ej. 110505).');
        }

        $sufijo = preg_replace('/\D/', '', (string) ($data['sufijo'] ?? '')) ?? '';
        if ($sufijo === '') {
            $sufijo = $this->siguienteSufijo($store, $padre->codigo);
        }
        $sufijo = str_pad($sufijo, 2, '0', STR_PAD_LEFT);

        $codigo = $padre->codigo.$sufijo;

        if (CuentaContable::query()->deStore($store)->where('codigo', $codigo)->exists()) {
            throw new Exception('Ya existe la cuenta auxiliar '.$codigo.' en esta tienda.');
        }

        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new Exception('El nombre de la cuenta auxiliar es obligatorio.');
        }

        $esDisponible = CuentaContable::esCodigoDisponible($padre->codigo);
        $defaultsDisponible = $esDisponible;

        $nivelRaw = array_key_exists('nivel_agrupacion', $data)
            ? $data['nivel_agrupacion']
            : CuentaContable::NIVEL_TRANSACCIONAL;
        if ($nivelRaw === '' || $nivelRaw === null) {
            $nivel = null;
        } else {
            $nivel = (string) $nivelRaw;
        }

        $clase = trim((string) ($data['clase'] ?? ''));
        if ($clase === '') {
            $clase = CuentaContable::claseDesdeCodigo($codigo)
                ?? $padre->clase
                ?? 'Activo';
        }

        $categoria = array_key_exists('categoria', $data)
            ? $this->nullableTrim($data['categoria'])
            : null;
        if ($categoria === null && $defaultsDisponible) {
            $categoria = CuentaContable::CATEGORIA_CAJA_BANCOS;
        } elseif ($categoria === null) {
            $categoria = $padre->categoria;
        }

        $relacionCon = array_key_exists('relacion_con', $data)
            ? $this->nullableTrim($data['relacion_con'])
            : null;
        if ($relacionCon === null && $defaultsDisponible) {
            $relacionCon = CuentaContable::RELACION_FORMAS_DE_PAGO;
        } elseif ($relacionCon === null) {
            $relacionCon = $padre->relacion_con;
        }

        $manejaVencimientos = array_key_exists('maneja_vencimientos', $data)
            ? $this->nullableTrim($data['maneja_vencimientos'])
            : null;
        if ($manejaVencimientos === null && $defaultsDisponible) {
            $manejaVencimientos = CuentaContable::MANEJA_VENCIMIENTOS_NO;
        } elseif ($manejaVencimientos === null) {
            $manejaVencimientos = $padre->maneja_vencimientos;
        }

        $activo = array_key_exists('activo', $data) ? (bool) $data['activo'] : true;
        $diferenciaFiscal = array_key_exists('diferencia_fiscal', $data)
            ? (bool) $data['diferencia_fiscal']
            : false;

        $crearBolsillo = $padre->esDisponibleOperable()
            && $nivel === CuentaContable::NIVEL_TRANSACCIONAL;

        return DB::transaction(function () use (
            $store,
            $padre,
            $codigo,
            $nombre,
            $clase,
            $categoria,
            $relacionCon,
            $manejaVencimientos,
            $diferenciaFiscal,
            $activo,
            $nivel,
            $crearBolsillo
        ) {
            $cuenta = CuentaContable::create([
                'store_id' => $store->id,
                'codigo' => $codigo,
                'nombre' => $nombre,
                'clase' => $clase,
                'categoria' => $categoria,
                'relacion_con' => $relacionCon,
                'maneja_vencimientos' => $manejaVencimientos,
                'diferencia_fiscal' => $diferenciaFiscal,
                'activo' => $activo,
                'nivel_agrupacion' => $nivel,
                'es_auxiliar' => true,
                'origen' => CuentaContable::ORIGEN_MANUAL,
                'cuenta_padre_id' => $padre->id,
            ]);

            if ($crearBolsillo) {
                $this->crearBolsilloDesdeCuenta($store, $cuenta);
            }

            return $cuenta->fresh(['bolsillo']);
        });
    }

    /**
     * Crea auxiliar bajo un padre por código sin crear bolsillo (CajaService lo crea).
     */
    public function crearAuxiliarSinBolsillo(Store $store, string $codigoPadre, array $data): CuentaContable
    {
        $padre = $this->asegurarPadreBolsillo($store, $codigoPadre);

        if (strlen(preg_replace('/\D/', '', $padre->codigo) ?? '') !== CuentaContable::MAX_CODIGO_BASE) {
            throw new Exception('El padre debe ser una subcuenta de 6 dígitos (ej. 110505).');
        }

        $sufijo = preg_replace('/\D/', '', (string) ($data['sufijo'] ?? '')) ?? '';
        if ($sufijo === '') {
            $sufijo = $this->siguienteSufijo($store, $padre->codigo);
        }
        $sufijo = str_pad($sufijo, 2, '0', STR_PAD_LEFT);
        $codigo = $padre->codigo.$sufijo;

        if (CuentaContable::query()->deStore($store)->where('codigo', $codigo)->exists()) {
            throw new Exception('Ya existe la cuenta auxiliar '.$codigo.' en esta tienda.');
        }

        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new Exception('El nombre de la cuenta auxiliar es obligatorio.');
        }

        $activo = array_key_exists('activo', $data) ? (bool) $data['activo'] : true;

        return CuentaContable::create([
            'store_id' => $store->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'clase' => $data['clase'] ?? CuentaContable::claseDesdeCodigo($codigo) ?? 'Activo',
            'categoria' => $data['categoria'] ?? CuentaContable::CATEGORIA_CAJA_BANCOS,
            'relacion_con' => $data['relacion_con'] ?? CuentaContable::RELACION_FORMAS_DE_PAGO,
            'maneja_vencimientos' => $data['maneja_vencimientos'] ?? CuentaContable::MANEJA_VENCIMIENTOS_NO,
            'diferencia_fiscal' => (bool) ($data['diferencia_fiscal'] ?? false),
            'activo' => $activo,
            'nivel_agrupacion' => CuentaContable::NIVEL_TRANSACCIONAL,
            'es_auxiliar' => true,
            'origen' => CuentaContable::ORIGEN_MANUAL,
            'cuenta_padre_id' => $padre->id,
        ]);
    }

    /**
     * Asegura subcuenta de 6 dígitos usada como padre de bolsillos.
     * Si falta (ej. 111010), la crea bajo su cuenta de 4 dígitos del PUC.
     */
    public function asegurarPadreBolsillo(Store $store, string $codigoPadre): CuentaContable
    {
        $nombres = [
            '1105' => 'Caja',
            '1110' => 'Bancos',
            '1120' => 'Cuentas de ahorro',
            '110505' => 'Caja general',
            '111005' => 'Moneda nacional',
            '111010' => 'Moneda extranjera',
            '112005' => 'Bancos',
        ];

        if (! isset($nombres[$codigoPadre]) || strlen($codigoPadre) !== CuentaContable::MAX_CODIGO_BASE) {
            throw new Exception("Código padre de bolsillo no soportado: {$codigoPadre}.");
        }

        $padre = CuentaContable::query()
            ->deStore($store)
            ->where('codigo', $codigoPadre)
            ->first();

        if ($padre) {
            return $padre;
        }

        $codigoCuenta = substr($codigoPadre, 0, 4);
        $cuenta = CuentaContable::query()
            ->deStore($store)
            ->where('codigo', $codigoCuenta)
            ->first();

        if (! $cuenta) {
            $cuentaGrupo = CuentaContable::query()
                ->deStore($store)
                ->where('codigo', '11')
                ->first();

            if (! $cuentaGrupo) {
                throw new Exception(
                    'No existe el grupo 11 (Disponible) en el plan de cuentas. '
                    .'Importa el PUC base desde Contabilidad → Plan de cuentas.'
                );
            }

            $cuenta = CuentaContable::create([
                'store_id' => $store->id,
                'codigo' => $codigoCuenta,
                'nombre' => $nombres[$codigoCuenta] ?? $codigoCuenta,
                'clase' => 'Activo',
                'categoria' => null,
                'relacion_con' => null,
                'maneja_vencimientos' => null,
                'diferencia_fiscal' => false,
                'activo' => true,
                'nivel_agrupacion' => null,
                'es_auxiliar' => false,
                'origen' => CuentaContable::ORIGEN_PLANTILLA,
                'cuenta_padre_id' => $cuentaGrupo->id,
            ]);
        }

        return CuentaContable::create([
            'store_id' => $store->id,
            'codigo' => $codigoPadre,
            'nombre' => $nombres[$codigoPadre],
            'clase' => 'Activo',
            'categoria' => null,
            'relacion_con' => null,
            'maneja_vencimientos' => null,
            'diferencia_fiscal' => false,
            'activo' => true,
            'nivel_agrupacion' => null,
            'es_auxiliar' => false,
            'origen' => CuentaContable::ORIGEN_PLANTILLA,
            'cuenta_padre_id' => $cuenta->id,
        ]);
    }

    public function actualizar(Store $store, CuentaContable $cuenta, array $data): CuentaContable
    {
        if ($cuenta->store_id !== $store->id) {
            throw new Exception('La cuenta no pertenece a esta tienda.');
        }

        return DB::transaction(function () use ($cuenta, $data) {
            $activoAnterior = $cuenta->activo;

            $cuenta->update([
                'nombre' => trim((string) ($data['nombre'] ?? $cuenta->nombre)),
                'categoria' => array_key_exists('categoria', $data)
                    ? $this->nullableTrim($data['categoria'])
                    : $cuenta->categoria,
                'relacion_con' => array_key_exists('relacion_con', $data)
                    ? $this->nullableTrim($data['relacion_con'])
                    : $cuenta->relacion_con,
                'maneja_vencimientos' => array_key_exists('maneja_vencimientos', $data)
                    ? $this->nullableTrim($data['maneja_vencimientos'])
                    : $cuenta->maneja_vencimientos,
                'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : $cuenta->activo,
            ]);

            if (array_key_exists('activo', $data) && (bool) $data['activo'] !== $activoAnterior) {
                $bolsillo = Bolsillo::query()->where('cuenta_contable_id', $cuenta->id)->first();
                if ($bolsillo) {
                    $bolsillo->update(['is_active' => (bool) $data['activo']]);
                }
            }

            // Si se renombra la cuenta y tiene bolsillo, alinear nombre operativo.
            if (array_key_exists('nombre', $data)) {
                $bolsillo = Bolsillo::query()->where('cuenta_contable_id', $cuenta->id)->first();
                if ($bolsillo && $bolsillo->name !== $cuenta->nombre) {
                    $bolsillo->update(['name' => $cuenta->nombre]);
                }
            }

            return $cuenta->fresh(['bolsillo']);
        });
    }

    /**
     * Asigna cuenta_padre_id por el prefijo de código más largo.
     */
    public function reconstruirPadres(Store $store): void
    {
        $cuentas = CuentaContable::query()
            ->deStore($store)
            ->orderByRaw('CHAR_LENGTH(codigo) asc')
            ->orderBy('codigo')
            ->get(['id', 'codigo']);

        $porCodigo = $cuentas->keyBy('codigo');

        foreach ($cuentas as $cuenta) {
            $padreId = null;
            $codigo = $cuenta->codigo;

            for ($len = strlen($codigo) - 1; $len >= 1; $len--) {
                $prefijo = substr($codigo, 0, $len);
                if ($porCodigo->has($prefijo)) {
                    $padreId = $porCodigo->get($prefijo)->id;
                    break;
                }
            }

            CuentaContable::query()
                ->whereKey($cuenta->id)
                ->update(['cuenta_padre_id' => $padreId]);
        }
    }

    public function contarPorStore(Store $store): array
    {
        $base = CuentaContable::query()->deStore($store);

        return [
            'total' => (clone $base)->count(),
            'base' => (clone $base)->where('es_auxiliar', false)->count(),
            'auxiliares' => (clone $base)->where('es_auxiliar', true)->count(),
            'transaccionales' => (clone $base)->where('nivel_agrupacion', CuentaContable::NIVEL_TRANSACCIONAL)->count(),
        ];
    }

    /**
     * Crea auxiliares para bolsillos existentes sin cuenta_contable_id.
     *
     * @return array{vinculados: int, omitidos: int, errores: list<string>}
     */
    public function backfillBolsillosSinCuenta(Store $store): array
    {
        $stats = ['vinculados' => 0, 'omitidos' => 0, 'errores' => []];

        $bolsillos = Bolsillo::deTienda($store->id)
            ->whereNull('cuenta_contable_id')
            ->orderBy('id')
            ->get();

        foreach ($bolsillos as $bolsillo) {
            try {
                $codigoPadre = $bolsillo->is_bank_account
                    ? CuentaContable::PADRE_BANCO_CORRIENTE_COP
                    : CuentaContable::PADRE_EFECTIVO;

                DB::transaction(function () use ($store, $bolsillo, $codigoPadre) {
                    $cuenta = $this->crearAuxiliarSinBolsillo($store, $codigoPadre, [
                        'nombre' => $bolsillo->name,
                        'activo' => $bolsillo->is_active,
                    ]);

                    $bolsillo->update([
                        'cuenta_contable_id' => $cuenta->id,
                        'is_bank_account' => CuentaContable::codigoEsBanco($cuenta->codigo),
                    ]);
                });

                $stats['vinculados']++;
            } catch (Exception $e) {
                $stats['omitidos']++;
                $stats['errores'][] = "Bolsillo #{$bolsillo->id} ({$bolsillo->name}): ".$e->getMessage();
            }
        }

        return $stats;
    }

    private function crearBolsilloDesdeCuenta(Store $store, CuentaContable $cuenta): Bolsillo
    {
        if (Bolsillo::query()->where('cuenta_contable_id', $cuenta->id)->exists()) {
            return Bolsillo::query()->where('cuenta_contable_id', $cuenta->id)->firstOrFail();
        }

        $nombre = $cuenta->nombre;
        $base = $nombre;
        $i = 2;
        while (Bolsillo::deTienda($store->id)->where('name', $nombre)->exists()) {
            $nombre = $base.' ('.$i.')';
            $i++;
        }

        return Bolsillo::create([
            'store_id' => $store->id,
            'cuenta_contable_id' => $cuenta->id,
            'name' => $nombre,
            'detalles' => 'Creado desde plan de cuentas '.$cuenta->codigo,
            'saldo' => 0,
            'is_bank_account' => $cuenta->esBancoSegunCodigo(),
            'is_active' => $cuenta->activo,
        ]);
    }

    private function resolverPadre(Store $store, int|string $cuentaPadreIdOCodigo): CuentaContable
    {
        $q = CuentaContable::query()->deStore($store)->where('es_auxiliar', false);
        if (is_numeric($cuentaPadreIdOCodigo)) {
            $padre = $q->whereKey((int) $cuentaPadreIdOCodigo)->first();
        } else {
            $padre = $q->where('codigo', (string) $cuentaPadreIdOCodigo)->first();
        }

        if (! $padre) {
            throw new Exception('La cuenta padre no existe en esta tienda.');
        }

        return $padre;
    }

    private function siguienteSufijo(Store $store, string $codigoPadre): string
    {
        $existentes = CuentaContable::query()
            ->deStore($store)
            ->where('codigo', 'like', $codigoPadre.'%')
            ->whereRaw('CHAR_LENGTH(codigo) = ?', [strlen($codigoPadre) + 2])
            ->pluck('codigo');

        $max = 0;
        foreach ($existentes as $codigo) {
            $suf = (int) substr($codigo, -2);
            if ($suf > $max) {
                $max = $suf;
            }
        }

        $next = $max + 1;
        if ($next > 99) {
            throw new Exception('No hay sufijos disponibles bajo '.$codigoPadre.' (01–99).');
        }

        return (string) $next;
    }

    private function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $t = trim((string) $value);

        return $t === '' ? null : $t;
    }
}
