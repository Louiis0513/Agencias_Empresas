<?php

namespace App\Services;

use App\Models\Bolsillo;
use App\Models\CuentaContable;
use App\Models\Store;
use App\Support\CatalogoImpuestosPredeterminados;
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
     * Clases (código de 1 dígito) para la raíz del árbol PUC.
     *
     * @return list<array<string, mixed>>
     */
    public function nodosRaizArbol(Store $store): array
    {
        $raices = CuentaContable::query()
            ->deStore($store)
            ->whereRaw('LENGTH(codigo) = 1')
            ->orderBy('codigo')
            ->get();

        return $this->serializarNodosArbol($store, $raices);
    }

    /**
     * Hijos directos de una cuenta (siguiente nivel de longitud) para el árbol.
     *
     * @return list<array<string, mixed>>
     */
    public function hijosDirectosArbol(Store $store, CuentaContable $padre): array
    {
        if ($padre->store_id !== $store->id) {
            throw new Exception('La cuenta no pertenece a esta tienda.');
        }

        $codigoPadre = preg_replace('/\D/', '', $padre->codigo) ?? '';
        $lenPadre = strlen($codigoPadre);

        if (! in_array($lenPadre, CuentaContable::LONGITUDES_PADRE_HIJO, true)) {
            return [];
        }

        $lenHijo = CuentaContable::longitudHijoEsperada($lenPadre);
        $hijos = CuentaContable::query()
            ->deStore($store)
            ->where('codigo', 'like', $codigoPadre.'%')
            ->whereRaw('LENGTH(codigo) = ?', [$lenHijo])
            ->orderBy('codigo')
            ->get();

        return $this->serializarNodosArbol($store, $hijos);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, CuentaContable>  $cuentas
     * @return list<array<string, mixed>>
     */
    public function serializarNodosArbol(Store $store, Collection $cuentas): array
    {
        if ($cuentas->isEmpty()) {
            return [];
        }

        $usos = $this->usosCatalogoPorCuentaIds($store, $cuentas->pluck('id')->all());
        $out = [];

        foreach ($cuentas as $cuenta) {
            $meta = $this->metaCrearHijo($store, $cuenta);
            $tieneHijos = $this->contarHijosDirectos($store, $cuenta) > 0;
            $usosNodo = [];
            foreach ($usos[$cuenta->id] ?? [] as $uso) {
                if (! is_array($uso)) {
                    $etiqueta = trim((string) $uso);
                    if ($etiqueta !== '') {
                        $usosNodo[] = ['etiqueta' => $etiqueta, 'url' => null];
                    }

                    continue;
                }
                $etiqueta = trim((string) ($uso['etiqueta'] ?? ''));
                if ($etiqueta === '') {
                    continue;
                }
                $usosNodo[] = [
                    'etiqueta' => $etiqueta,
                    'url' => $uso['url'] ?? null,
                ];
            }

            $out[] = [
                'id' => $cuenta->id,
                'codigo' => $cuenta->codigo,
                'nombre' => $cuenta->nombre,
                'clase' => $cuenta->clase,
                'es_auxiliar' => (bool) $cuenta->es_auxiliar,
                'nivel_agrupacion' => $cuenta->nivel_agrupacion,
                'activo' => (bool) $cuenta->activo,
                'tiene_hijos' => $tieneHijos,
                'nivel_label' => CuentaContable::labelNivelPorLongitud(
                    CuentaContable::longitudCodigo($cuenta->codigo)
                ),
                'meta' => $meta,
                'usos' => $usosNodo,
            ];
        }

        return $out;
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
            ->whereRaw('LENGTH(codigo) = ?', [CuentaContable::MAX_CODIGO_BASE])
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
     * Meta UI para crear hijo bajo una cuenta (nivel siguiente, usos, sufijo sugerido).
     *
     * @return array{puede: bool, longitud_padre: int, longitud_hijo: int, digitos_sufijo?: int, accion: ?string, tiene_usos: bool, sufijo_sugerido: ?string, mensaje: ?string}
     */
    public function metaCrearHijo(Store $store, CuentaContable $padre): array
    {
        if ($padre->store_id !== $store->id) {
            throw new Exception('La cuenta no pertenece a esta tienda.');
        }

        $len = CuentaContable::longitudCodigo($padre->codigo);
        $accion = CuentaContable::labelAccionCrearHijo($len);

        if ($accion === null || ! in_array($len, CuentaContable::LONGITUDES_PADRE_HIJO, true)) {
            return [
                'puede' => false,
                'longitud_padre' => $len,
                'longitud_hijo' => $len,
                'accion' => null,
                'tiene_usos' => false,
                'sufijo_sugerido' => null,
                'mensaje' => 'No se pueden crear hijos bajo este nivel.',
            ];
        }

        $tieneHijos = $this->contarHijosDirectos($store, $padre) > 0;
        $tieneUsos = ! $tieneHijos && $this->padreTieneUsos($padre);
        $digitosSufijo = CuentaContable::digitosSufijoHijo($len);

        $sufijoSugerido = null;
        $mensajeSinCupo = null;
        try {
            $sufijoSugerido = $this->siguienteSufijo($store, $padre->codigo);
        } catch (Exception $e) {
            $mensajeSinCupo = $e->getMessage();
        }

        $mensaje = $mensajeSinCupo;
        if ($mensaje === null && $tieneUsos) {
            $mensaje = 'El padre posee movimiento o está vinculado a catálogos; al crear el primer hijo se trasladarán al nuevo código.';
        }

        return [
            'puede' => $sufijoSugerido !== null,
            'longitud_padre' => $len,
            'longitud_hijo' => CuentaContable::longitudHijoEsperada($len),
            'digitos_sufijo' => $digitosSufijo,
            'accion' => $accion,
            'tiene_usos' => $tieneUsos,
            'sufijo_sugerido' => $sufijoSugerido,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * Crea un hijo bajo cualquier nivel permitido (1→+1 dígito; 2/4/6/8→+2 dígitos).
     *
     * @return array{cuenta: CuentaContable, traslado_realizado: bool}
     */
    public function crearHijo(Store $store, array $data): array
    {
        $padre = CuentaContable::query()
            ->deStore($store)
            ->whereKey($data['cuenta_padre_id'] ?? 0)
            ->first();

        if (! $padre) {
            throw new Exception('La cuenta padre no existe en esta tienda.');
        }

        $lenPadre = CuentaContable::longitudCodigo($padre->codigo);
        if (! in_array($lenPadre, CuentaContable::LONGITUDES_PADRE_HIJO, true)) {
            throw new Exception('No se puede crear un hijo bajo este nivel de cuenta.');
        }

        $digitosSufijo = CuentaContable::digitosSufijoHijo($lenPadre);
        $sufijo = preg_replace('/\D/', '', (string) ($data['sufijo'] ?? '')) ?? '';
        if ($sufijo === '') {
            $sufijo = $this->siguienteSufijo($store, $padre->codigo);
        }
        $sufijo = str_pad($sufijo, $digitosSufijo, '0', STR_PAD_LEFT);
        $maxSufijo = $digitosSufijo === 1 ? 9 : 99;
        if (strlen($sufijo) !== $digitosSufijo || (int) $sufijo < 1 || (int) $sufijo > $maxSufijo) {
            throw new Exception(
                $digitosSufijo === 1
                    ? 'El sufijo debe ser un dígito entre 1 y 9.'
                    : 'El sufijo debe ser numérico entre 01 y 99.'
            );
        }

        $codigo = preg_replace('/\D/', '', $padre->codigo).$sufijo;
        $lenHijo = strlen($codigo);

        if ($lenHijo !== CuentaContable::longitudHijoEsperada($lenPadre)) {
            throw new Exception('La longitud del código hijo no es válida para este nivel.');
        }

        if (CuentaContable::query()->deStore($store)->where('codigo', $codigo)->exists()) {
            throw new Exception('Ya existe la cuenta '.$codigo.' en esta tienda.');
        }

        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new Exception('El nombre de la cuenta es obligatorio.');
        }

        $tieneHijos = $this->contarHijosDirectos($store, $padre) > 0;
        $tieneUsos = ! $tieneHijos && $this->padreTieneUsos($padre);
        $confirmarTraslado = (bool) ($data['confirmar_traslado'] ?? false);

        if ($tieneUsos && ! $confirmarTraslado) {
            throw new Exception(
                'El padre posee movimiento o vínculos a catálogos. '
                .'Debes confirmar el traslado al nuevo hijo (confirmar_traslado).'
            );
        }

        $defaultsPadre = CuentaContable::defaultsParaCodigoPadre($padre->codigo);
        $esAuxiliar = $lenHijo > CuentaContable::MAX_CODIGO_BASE;
        $nivel = $lenHijo >= 8 ? CuentaContable::NIVEL_TRANSACCIONAL : null;

        $forzarTransaccional = (bool) ($data['forzar_transaccional'] ?? false);
        if ($forzarTransaccional && $lenHijo >= CuentaContable::MAX_CODIGO_BASE) {
            $nivel = CuentaContable::NIVEL_TRANSACCIONAL;
        } elseif ($lenHijo >= 8 && array_key_exists('nivel_agrupacion', $data)) {
            $nivelRaw = $data['nivel_agrupacion'];
            $nivel = ($nivelRaw === '' || $nivelRaw === null)
                ? null
                : (string) $nivelRaw;
        } elseif (
            $lenHijo === CuentaContable::MAX_CODIGO_BASE
            && array_key_exists('nivel_agrupacion', $data)
            && $data['nivel_agrupacion'] === CuentaContable::NIVEL_TRANSACCIONAL
        ) {
            $nivel = CuentaContable::NIVEL_TRANSACCIONAL;
        }

        $clase = trim((string) ($data['clase'] ?? ''));
        if ($clase === '') {
            $clase = $defaultsPadre['clase']
                ?? CuentaContable::claseDesdeCodigo($codigo)
                ?? $padre->clase
                ?? 'Activo';
        }

        $categoria = array_key_exists('categoria', $data)
            ? $this->nullableTrim($data['categoria'])
            : null;
        if ($categoria === null && $defaultsPadre['categoria'] !== null) {
            $categoria = $defaultsPadre['categoria'];
        } elseif ($categoria === null) {
            $categoria = $padre->categoria;
        }

        $relacionCon = array_key_exists('relacion_con', $data)
            ? $this->nullableTrim($data['relacion_con'])
            : null;
        if ($relacionCon === null && $defaultsPadre['relacion_con'] !== null) {
            $relacionCon = $defaultsPadre['relacion_con'];
        } elseif ($relacionCon === null) {
            $relacionCon = $padre->relacion_con;
        }

        $manejaVencimientos = array_key_exists('maneja_vencimientos', $data)
            ? $this->nullableTrim($data['maneja_vencimientos'])
            : null;
        if ($manejaVencimientos === null) {
            $manejaVencimientos = $defaultsPadre['maneja_vencimientos']
                ?? $padre->maneja_vencimientos
                ?? ($lenHijo >= 8 ? CuentaContable::MANEJA_VENCIMIENTOS_NO : null);
        }

        $activo = array_key_exists('activo', $data) ? (bool) $data['activo'] : true;
        $diferenciaFiscal = array_key_exists('diferencia_fiscal', $data)
            ? (bool) $data['diferencia_fiscal']
            : false;

        $omitirBolsillo = (bool) ($data['omitir_bolsillo'] ?? false);
        $crearBolsillo = ! $omitirBolsillo
            && $nivel === CuentaContable::NIVEL_TRANSACCIONAL
            && CuentaContable::codigoEsDisponibleOperable($codigo);

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
            $esAuxiliar,
            $crearBolsillo,
            $tieneUsos
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
                'es_auxiliar' => $esAuxiliar,
                'origen' => CuentaContable::ORIGEN_MANUAL,
                'cuenta_padre_id' => $padre->id,
            ]);

            $traslado = false;
            if ($tieneUsos) {
                $this->trasladarUsosAlHijo($padre, $cuenta);
                if ($padre->nivel_agrupacion === CuentaContable::NIVEL_TRANSACCIONAL) {
                    $padre->update(['nivel_agrupacion' => null]);
                }
                $traslado = true;
            }

            if ($crearBolsillo && ! Bolsillo::query()->where('cuenta_contable_id', $cuenta->id)->exists()) {
                $this->crearBolsilloDesdeCuenta($store, $cuenta);
            }

            return [
                'cuenta' => $cuenta->fresh(['bolsillo']),
                'traslado_realizado' => $traslado,
            ];
        });
    }

    /**
     * Asegura una cuenta por código exacto creando la cadena PUC faltante (1→2→4→6→8).
     *
     * @param  array{
     *   nombre?: string,
     *   categoria?: ?string,
     *   relacion_con?: ?string,
     *   clase?: ?string,
     *   forzar_transaccional?: bool
     * }  $meta
     */
    public function asegurarCuentaPorCodigo(Store $store, string $codigo, array $meta = []): CuentaContable
    {
        $codigo = preg_replace('/\D/', '', $codigo) ?? '';
        if ($codigo === '') {
            throw new Exception('El código de cuenta es obligatorio.');
        }

        $cadena = CatalogoImpuestosPredeterminados::cadenaCodigos($codigo);
        $cuenta = null;

        foreach ($cadena as $codigoNivel) {
            $existente = CuentaContable::query()
                ->deStore($store)
                ->where('codigo', $codigoNivel)
                ->first();

            if ($existente) {
                $cuenta = $existente;
                continue;
            }

            $len = strlen($codigoNivel);
            $nombre = $this->nombreParaCodigoCadena($codigoNivel, $codigo, $meta);
            $esHojaFinal = $codigoNivel === $codigo;
            $forzarTransaccional = $esHojaFinal && (
                (bool) ($meta['forzar_transaccional'] ?? false)
                || CatalogoImpuestosPredeterminados::esHojaSeisTransaccional($codigoNivel)
                || $len > CuentaContable::MAX_CODIGO_BASE
            );

            if ($len === 1) {
                $cuenta = CuentaContable::create([
                    'store_id' => $store->id,
                    'codigo' => $codigoNivel,
                    'nombre' => $nombre,
                    'clase' => CuentaContable::claseDesdeCodigo($codigoNivel) ?? $nombre,
                    'categoria' => null,
                    'relacion_con' => null,
                    'maneja_vencimientos' => null,
                    'diferencia_fiscal' => false,
                    'activo' => true,
                    'nivel_agrupacion' => null,
                    'es_auxiliar' => false,
                    'origen' => CuentaContable::ORIGEN_PLANTILLA,
                    'cuenta_padre_id' => null,
                ]);
                continue;
            }

            $lenPadre = $len === 2 ? 1 : $len - 2;
            $codigoPadre = substr($codigoNivel, 0, $lenPadre);
            $padre = CuentaContable::query()
                ->deStore($store)
                ->where('codigo', $codigoPadre)
                ->first();

            if (! $padre) {
                throw new Exception('No se pudo asegurar el padre '.$codigoPadre.' para '.$codigoNivel.'.');
            }

            $sufijo = substr($codigoNivel, $lenPadre);
            $payload = [
                'cuenta_padre_id' => $padre->id,
                'sufijo' => $sufijo,
                'nombre' => $nombre,
                'confirmar_traslado' => true,
                'omitir_bolsillo' => true,
                'activo' => true,
            ];

            if ($esHojaFinal) {
                if (array_key_exists('categoria', $meta)) {
                    $payload['categoria'] = $meta['categoria'];
                }
                if (array_key_exists('relacion_con', $meta)) {
                    $payload['relacion_con'] = $meta['relacion_con'];
                }
                if (! empty($meta['clase'])) {
                    $payload['clase'] = $meta['clase'];
                }
                $payload['maneja_vencimientos'] = CuentaContable::MANEJA_VENCIMIENTOS_NO;
            }

            if ($forzarTransaccional) {
                $payload['forzar_transaccional'] = true;
                $payload['nivel_agrupacion'] = CuentaContable::NIVEL_TRANSACCIONAL;
            }

            $cuenta = $this->crearHijo($store, $payload)['cuenta'];
        }

        if (! $cuenta) {
            throw new Exception('No se pudo asegurar la cuenta '.$codigo.'.');
        }

        $esHojaSeis = CatalogoImpuestosPredeterminados::esHojaSeisTransaccional($codigo)
            || (bool) ($meta['forzar_transaccional'] ?? false);

        if (
            $esHojaSeis
            && strlen($codigo) === CuentaContable::MAX_CODIGO_BASE
            && ! $cuenta->esTransaccional()
        ) {
            $cuenta->update(['nivel_agrupacion' => CuentaContable::NIVEL_TRANSACCIONAL]);
            $cuenta = $cuenta->fresh();
        }

        if (
            strlen($codigo) > CuentaContable::MAX_CODIGO_BASE
            && ! $cuenta->esTransaccional()
        ) {
            $cuenta->update(['nivel_agrupacion' => CuentaContable::NIVEL_TRANSACCIONAL]);
            $cuenta = $cuenta->fresh();
        }

        return $cuenta;
    }

    /**
     * @param  array{nombre?: string}  $meta
     */
    private function nombreParaCodigoCadena(string $codigoNivel, string $codigoHoja, array $meta): string
    {
        if ($codigoNivel === $codigoHoja && ! empty($meta['nombre'])) {
            return (string) $meta['nombre'];
        }

        $desdePadres = CatalogoImpuestosPredeterminados::nombrePadre($codigoNivel);
        if ($desdePadres !== null) {
            return $desdePadres;
        }

        if ($codigoNivel === $codigoHoja && ! empty($meta['nombre'])) {
            return (string) $meta['nombre'];
        }

        return 'Cuenta '.$codigoNivel;
    }

    /**
     * Crea una cuenta auxiliar bajo una subcuenta de 6 dígitos (compat).
     */
    public function crearAuxiliar(Store $store, array $data): CuentaContable
    {
        $padre = CuentaContable::query()
            ->deStore($store)
            ->whereKey($data['cuenta_padre_id'] ?? 0)
            ->first();

        if (! $padre) {
            throw new Exception('La cuenta padre no existe en esta tienda.');
        }

        if (CuentaContable::longitudCodigo($padre->codigo) !== CuentaContable::MAX_CODIGO_BASE) {
            throw new Exception('El padre debe ser una subcuenta de 6 dígitos (ej. 110505).');
        }

        if (! array_key_exists('nivel_agrupacion', $data)) {
            $data['nivel_agrupacion'] = CuentaContable::NIVEL_TRANSACCIONAL;
        }

        $data['confirmar_traslado'] = (bool) ($data['confirmar_traslado'] ?? true);

        return $this->crearHijo($store, $data)['cuenta'];
    }

    public function padreTieneUsos(CuentaContable $cuenta): bool
    {
        if (\App\Models\MovimientoContable::query()->where('cuenta_contable_id', $cuenta->id)->exists()) {
            return true;
        }

        if (Bolsillo::query()->where('cuenta_contable_id', $cuenta->id)->exists()) {
            return true;
        }

        if (\App\Models\FormaPago::query()->where('cuenta_contable_id', $cuenta->id)->exists()) {
            return true;
        }

        if (\App\Models\Impuesto::query()
            ->where(function ($q) use ($cuenta) {
                $q->where('cuenta_ventas_id', $cuenta->id)
                    ->orWhere('cuenta_compras_id', $cuenta->id)
                    ->orWhere('cuenta_devolucion_ventas_id', $cuenta->id)
                    ->orWhere('cuenta_devolucion_compras_id', $cuenta->id);
            })
            ->exists()) {
            return true;
        }

        if (\App\Models\CategoriaContable::query()
            ->where(function ($q) use ($cuenta) {
                $q->where('cuenta_inventario_id', $cuenta->id)
                    ->orWhere('cuenta_costo_id', $cuenta->id)
                    ->orWhere('cuenta_ingreso_id', $cuenta->id)
                    ->orWhere('cuenta_devolucion_id', $cuenta->id);
            })
            ->exists()) {
            return true;
        }

        return false;
    }

    private function contarHijosDirectos(Store $store, CuentaContable $padre): int
    {
        $codigoPadre = preg_replace('/\D/', '', $padre->codigo) ?? '';
        $lenHijo = CuentaContable::longitudHijoEsperada(strlen($codigoPadre));

        return CuentaContable::query()
            ->deStore($store)
            ->where('codigo', 'like', $codigoPadre.'%')
            ->whereRaw('LENGTH(codigo) = ?', [$lenHijo])
            ->count();
    }

    private function trasladarUsosAlHijo(CuentaContable $padre, CuentaContable $hijo): void
    {
        \App\Models\MovimientoContable::query()
            ->where('cuenta_contable_id', $padre->id)
            ->update(['cuenta_contable_id' => $hijo->id]);

        Bolsillo::query()
            ->where('cuenta_contable_id', $padre->id)
            ->update(['cuenta_contable_id' => $hijo->id]);

        \App\Models\FormaPago::query()
            ->where('cuenta_contable_id', $padre->id)
            ->update(['cuenta_contable_id' => $hijo->id]);

        foreach (['cuenta_ventas_id', 'cuenta_compras_id', 'cuenta_devolucion_ventas_id', 'cuenta_devolucion_compras_id'] as $col) {
            \App\Models\Impuesto::query()
                ->where($col, $padre->id)
                ->update([$col => $hijo->id]);
        }

        foreach (['cuenta_inventario_id', 'cuenta_costo_id', 'cuenta_ingreso_id', 'cuenta_devolucion_id'] as $col) {
            \App\Models\CategoriaContable::query()
                ->where($col, $padre->id)
                ->update([$col => $hijo->id]);
        }
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

        $resultado = $this->crearHijo($store, [
            'cuenta_padre_id' => $padre->id,
            'sufijo' => $data['sufijo'] ?? null,
            'nombre' => $data['nombre'] ?? '',
            'clase' => $data['clase'] ?? null,
            'categoria' => $data['categoria'] ?? CuentaContable::CATEGORIA_CAJA_BANCOS,
            'relacion_con' => $data['relacion_con'] ?? CuentaContable::RELACION_FORMAS_DE_PAGO,
            'maneja_vencimientos' => $data['maneja_vencimientos'] ?? CuentaContable::MANEJA_VENCIMIENTOS_NO,
            'diferencia_fiscal' => $data['diferencia_fiscal'] ?? false,
            'activo' => $data['activo'] ?? true,
            'nivel_agrupacion' => CuentaContable::NIVEL_TRANSACCIONAL,
            'confirmar_traslado' => true,
            'omitir_bolsillo' => true,
        ]);

        return $resultado['cuenta'];
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
            ->orderByRaw('LENGTH(codigo) asc')
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
     * Usos en catálogos (estilo Siigo «Relacionado con») para un conjunto de cuentas.
     *
     * @param  list<int>  $cuentaIds
     * @return array<int, list<array{etiqueta: string, url: string|null}>>
     */
    public function usosCatalogoPorCuentaIds(Store $store, array $cuentaIds): array
    {
        $cuentaIds = array_values(array_unique(array_filter(array_map('intval', $cuentaIds))));
        if ($cuentaIds === []) {
            return [];
        }

        $mapa = [];
        foreach ($cuentaIds as $id) {
            $mapa[$id] = [];
        }

        $formas = \App\Models\FormaPago::query()
            ->deStore($store)
            ->whereIn('cuenta_contable_id', $cuentaIds)
            ->get(['id', 'cuenta_contable_id', 'nombre']);

        foreach ($formas as $forma) {
            $cid = (int) $forma->cuenta_contable_id;
            $mapa[$cid][] = [
                'etiqueta' => 'Formas de pago',
                'url' => route('stores.contabilidad.formas-pago', [
                    'store' => $store,
                    'cuenta_contable_id' => $cid,
                ]),
            ];
        }

        $impuestos = \App\Models\Impuesto::query()
            ->deStore($store)
            ->where(function ($q) use ($cuentaIds) {
                $q->whereIn('cuenta_ventas_id', $cuentaIds)
                    ->orWhereIn('cuenta_compras_id', $cuentaIds)
                    ->orWhereIn('cuenta_devolucion_ventas_id', $cuentaIds)
                    ->orWhereIn('cuenta_devolucion_compras_id', $cuentaIds);
            })
            ->get([
                'id',
                'cuenta_ventas_id',
                'cuenta_compras_id',
                'cuenta_devolucion_ventas_id',
                'cuenta_devolucion_compras_id',
            ]);

        foreach ($impuestos as $impuesto) {
            $roles = [
                (int) $impuesto->cuenta_ventas_id => 'Impuestos - Ventas',
                (int) $impuesto->cuenta_compras_id => 'Impuestos - Compras',
                (int) $impuesto->cuenta_devolucion_ventas_id => 'Impuestos - Devolución ventas',
                (int) $impuesto->cuenta_devolucion_compras_id => 'Impuestos - Devolución compras',
            ];
            foreach ($roles as $cid => $etiqueta) {
                if (! isset($mapa[$cid])) {
                    continue;
                }
                $mapa[$cid][] = [
                    'etiqueta' => $etiqueta,
                    'url' => route('stores.contabilidad.impuestos', $store),
                ];
            }
        }

        $categorias = \App\Models\CategoriaContable::query()
            ->deStore($store)
            ->where(function ($q) use ($cuentaIds) {
                $q->whereIn('cuenta_inventario_id', $cuentaIds)
                    ->orWhereIn('cuenta_costo_id', $cuentaIds)
                    ->orWhereIn('cuenta_ingreso_id', $cuentaIds)
                    ->orWhereIn('cuenta_devolucion_id', $cuentaIds);
            })
            ->get([
                'id',
                'cuenta_inventario_id',
                'cuenta_costo_id',
                'cuenta_ingreso_id',
                'cuenta_devolucion_id',
            ]);

        foreach ($categorias as $categoria) {
            $roles = [
                (int) $categoria->cuenta_inventario_id => 'Categorías de productos y servicios - Inventario',
                (int) $categoria->cuenta_costo_id => 'Categorías de productos y servicios - Costo',
                (int) $categoria->cuenta_ingreso_id => 'Categorías de productos y servicios - Ventas',
                (int) $categoria->cuenta_devolucion_id => 'Categorías de productos y servicios - Devolución',
            ];
            foreach ($roles as $cid => $etiqueta) {
                if ($cid < 1 || ! isset($mapa[$cid])) {
                    continue;
                }
                $mapa[$cid][] = [
                    'etiqueta' => $etiqueta,
                    'url' => route('stores.contabilidad.categorias', $store),
                ];
            }
        }

        // Deduplicar por etiqueta por cuenta (varias formas → una sola etiqueta Formas de pago).
        foreach ($mapa as $cid => $usos) {
            $vistos = [];
            $unicos = [];
            foreach ($usos as $uso) {
                $key = $uso['etiqueta'];
                if (isset($vistos[$key])) {
                    continue;
                }
                $vistos[$key] = true;
                $unicos[] = $uso;
            }
            $mapa[$cid] = $unicos;
        }

        return $mapa;
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
        $codigoPadre = preg_replace('/\D/', '', $codigoPadre) ?? '';
        $lenPadre = strlen($codigoPadre);
        $digitosSufijo = CuentaContable::digitosSufijoHijo($lenPadre);
        $lenHijo = CuentaContable::longitudHijoEsperada($lenPadre);
        $maxSufijo = $digitosSufijo === 1 ? 9 : 99;

        $existentes = CuentaContable::query()
            ->deStore($store)
            ->where('codigo', 'like', $codigoPadre.'%')
            ->whereRaw('LENGTH(codigo) = ?', [$lenHijo])
            ->pluck('codigo');

        $usados = [];
        foreach ($existentes as $codigo) {
            $usados[(int) substr((string) $codigo, -$digitosSufijo)] = true;
        }

        for ($i = 1; $i <= $maxSufijo; $i++) {
            if (! isset($usados[$i])) {
                return $digitosSufijo === 1
                    ? (string) $i
                    : str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            }
        }

        throw new Exception(
            $digitosSufijo === 1
                ? 'No hay sufijos disponibles bajo '.$codigoPadre.' (1–9).'
                : 'No hay sufijos disponibles bajo '.$codigoPadre.' (01–99).'
        );
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
