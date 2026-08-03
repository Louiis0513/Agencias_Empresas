<?php

namespace App\Services;

use App\Models\Store;
use App\Models\TipoComprobante;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TipoComprobanteService
{
    /**
     * Tipos base estilo Siigo (un registro por familia con código "1").
     *
     * @return list<array{
     *   familia: string,
     *   codigo: string,
     *   nombre: string,
     *   titulo: string,
     *   prefijo: string,
     *   libro_oficial: ?string
     * }>
     */
    public function defaults(): array
    {
        return [
            [
                'familia' => TipoComprobante::FAMILIA_FV,
                'codigo' => '1',
                'nombre' => 'Factura de venta',
                'titulo' => 'Factura de venta',
                'prefijo' => 'FV',
                'libro_oficial' => TipoComprobante::LIBRO_VENTAS,
            ],
            [
                'familia' => TipoComprobante::FAMILIA_RC,
                'codigo' => '1',
                'nombre' => 'Recibo de caja',
                'titulo' => 'Recibo de caja',
                'prefijo' => 'RC',
                'libro_oficial' => null,
            ],
            [
                'familia' => TipoComprobante::FAMILIA_FC,
                'codigo' => '1',
                'nombre' => 'Factura de compra',
                'titulo' => 'Factura de compra',
                'prefijo' => 'FC',
                'libro_oficial' => TipoComprobante::LIBRO_COMPRAS,
            ],
            [
                'familia' => TipoComprobante::FAMILIA_RP,
                'codigo' => '1',
                'nombre' => 'Recibo de pago / egreso',
                'titulo' => 'Recibo de pago / egreso',
                'prefijo' => 'RP',
                'libro_oficial' => null,
            ],
            [
                'familia' => TipoComprobante::FAMILIA_CC,
                'codigo' => '1',
                'nombre' => 'Comprobante contable',
                'titulo' => 'Comprobante contable',
                'prefijo' => 'CC',
                'libro_oficial' => null,
            ],
        ];
    }

    public function tipoPorDefecto(Store $store, string $familia): ?TipoComprobante
    {
        if (! in_array($familia, TipoComprobante::FAMILIAS, true)) {
            throw new Exception('Familia de comprobante no válida.');
        }

        $this->asegurarTiposPorDefecto($store);

        return TipoComprobante::query()
            ->deStore($store)
            ->activas()
            ->where('familia', $familia)
            ->orderByRaw('CAST(codigo AS UNSIGNED) asc')
            ->orderBy('id')
            ->first();
    }

    public function listar(Store $store, array $filtros = [], int $perPage = 30): LengthAwarePaginator
    {
        $q = TipoComprobante::query()
            ->deStore($store)
            ->orderByRaw("CASE familia WHEN 'FV' THEN 1 WHEN 'RC' THEN 2 WHEN 'FC' THEN 3 WHEN 'RP' THEN 4 WHEN 'CC' THEN 5 ELSE 99 END")
            ->orderByRaw('CAST(codigo AS UNSIGNED) asc')
            ->orderBy('codigo');

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $q->where(function ($qq) use ($search) {
                $qq->where('codigo', 'like', $search.'%')
                    ->orWhere('nombre', 'like', '%'.$search.'%')
                    ->orWhere('prefijo', 'like', $search.'%')
                    ->orWhere('titulo', 'like', '%'.$search.'%');
            });
        }

        if (! empty($filtros['familia'])) {
            $q->where('familia', $filtros['familia']);
        }

        if (isset($filtros['activo']) && $filtros['activo'] !== '' && $filtros['activo'] !== null) {
            $q->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN));
        }

        return $q->paginate($perPage)->withQueryString();
    }

    public function sugerirCodigo(Store $store, string $familia): string
    {
        if (! in_array($familia, TipoComprobante::FAMILIAS, true)) {
            $familia = TipoComprobante::FAMILIA_FV;
        }

        $codigos = TipoComprobante::query()
            ->deStore($store)
            ->where('familia', $familia)
            ->pluck('codigo');

        $max = 0;
        foreach ($codigos as $codigo) {
            if (ctype_digit((string) $codigo)) {
                $max = max($max, (int) $codigo);
            }
        }

        return (string) ($max + 1);
    }

    public function crear(Store $store, array $data): TipoComprobante
    {
        $payload = $this->normalizarYValidar($store, $data);

        return DB::transaction(function () use ($store, $payload) {
            return TipoComprobante::create([
                'store_id' => $store->id,
                ...$payload,
            ])->fresh();
        });
    }

    public function actualizar(Store $store, TipoComprobante $tipo, array $data): TipoComprobante
    {
        if ($tipo->store_id !== $store->id) {
            throw new Exception('El tipo de comprobante no pertenece a esta tienda.');
        }

        $payload = $this->normalizarYValidar($store, $data, $tipo);

        return DB::transaction(function () use ($tipo, $payload) {
            $tipo->update($payload);

            return $tipo->fresh();
        });
    }

    /**
     * Asegura los 5 tipos base (FV/RC/FC/RP/CC con código 1). Idempotente.
     *
     * @return array{creadas: list<string>, omitidas: list<string>, errores: list<string>}
     */
    public function asegurarTiposPorDefecto(Store $store): array
    {
        $stats = [
            'creadas' => [],
            'omitidas' => [],
            'errores' => [],
        ];

        foreach ($this->defaults() as $def) {
            $clave = $def['familia'].':'.$def['codigo'];

            try {
                $existe = TipoComprobante::query()
                    ->deStore($store)
                    ->where('familia', $def['familia'])
                    ->where('codigo', $def['codigo'])
                    ->exists();

                if ($existe) {
                    $stats['omitidas'][] = $clave;
                    continue;
                }

                $this->crear($store, [
                    'familia' => $def['familia'],
                    'codigo' => $def['codigo'],
                    'nombre' => $def['nombre'],
                    'titulo' => $def['titulo'],
                    'prefijo' => $def['prefijo'],
                    'numeracion_automatica' => true,
                    'siguiente_numero' => 1,
                    'activo' => true,
                    'maneja_centro_costos' => false,
                    'centro_costo_obligatorio' => false,
                    'centro_costo_default_id' => null,
                    'libro_oficial' => $def['libro_oficial'],
                ]);
                $stats['creadas'][] = $clave;
            } catch (Exception $e) {
                $stats['errores'][] = $clave.': '.$e->getMessage();
            }
        }

        return $stats;
    }

    /**
     * Toma el siguiente número del tipo (lock) y devuelve p.ej. RC-0001.
     * Listo para fase 2; aún no se usa desde CI/CE.
     */
    public function tomarSiguienteNumero(Store $store, TipoComprobante $tipo): string
    {
        if ($tipo->store_id !== $store->id) {
            throw new Exception('El tipo de comprobante no pertenece a esta tienda.');
        }

        if (! $tipo->activo) {
            throw new Exception('El tipo de comprobante está inactivo.');
        }

        if (! $tipo->numeracion_automatica) {
            throw new Exception('Este tipo de comprobante no usa numeración automática.');
        }

        return DB::transaction(function () use ($tipo) {
            $locked = TipoComprobante::query()
                ->where('id', $tipo->id)
                ->lockForUpdate()
                ->firstOrFail();

            $numero = (int) $locked->siguiente_numero;
            if ($numero < 1) {
                $numero = 1;
            }

            $locked->update(['siguiente_numero' => $numero + 1]);

            $prefijo = strtoupper(trim((string) $locked->prefijo));
            if ($prefijo === '') {
                $prefijo = $locked->familia;
            }

            return $prefijo.'-'.str_pad((string) $numero, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * @return array{
     *   familia: string,
     *   codigo: string,
     *   nombre: string,
     *   titulo: string,
     *   prefijo: string,
     *   numeracion_automatica: bool,
     *   siguiente_numero: int,
     *   activo: bool,
     *   maneja_centro_costos: bool,
     *   libro_oficial: ?string
     * }
     */
    private function normalizarYValidar(Store $store, array $data, ?TipoComprobante $existente = null): array
    {
        $familia = strtoupper(trim((string) ($data['familia'] ?? '')));
        if (! in_array($familia, TipoComprobante::FAMILIAS, true)) {
            throw new Exception('La familia debe ser FV, RC, FC, RP o CC.');
        }

        $codigo = trim((string) ($data['codigo'] ?? ''));
        if ($codigo === '') {
            $codigo = $this->sugerirCodigo($store, $familia);
        }

        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') {
            throw new Exception('El nombre del tipo de comprobante es obligatorio.');
        }

        $titulo = trim((string) ($data['titulo'] ?? ''));
        if ($titulo === '') {
            $titulo = $nombre;
        }

        $prefijo = strtoupper(trim((string) ($data['prefijo'] ?? '')));
        if ($prefijo === '') {
            $prefijo = $familia;
        }

        $libro = $data['libro_oficial'] ?? null;
        if ($libro === '' || $libro === null) {
            $libro = null;
        } else {
            $libro = strtolower(trim((string) $libro));
            if (! in_array($libro, TipoComprobante::LIBROS_OFICIALES, true)) {
                throw new Exception('El libro oficial debe ser ventas, compras o vacío.');
            }
        }

        $duplicado = TipoComprobante::query()
            ->deStore($store)
            ->where('familia', $familia)
            ->where('codigo', $codigo)
            ->when($existente, fn ($q) => $q->where('id', '!=', $existente->id))
            ->exists();

        if ($duplicado) {
            throw new Exception('Ya existe un tipo «'.$familia.'» con código '.$codigo.' en esta tienda.');
        }

        $siguiente = isset($data['siguiente_numero']) ? (int) $data['siguiente_numero'] : 1;
        if ($siguiente < 1) {
            throw new Exception('El siguiente número debe ser al menos 1.');
        }

        if ($existente && $siguiente < (int) $existente->siguiente_numero) {
            throw new Exception(
                'No puedes bajar el siguiente número por debajo de '.$existente->siguiente_numero
                .' (ya reservado o en uso).'
            );
        }

        return [
            'familia' => $familia,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'titulo' => $titulo,
            'prefijo' => $prefijo,
            'numeracion_automatica' => array_key_exists('numeracion_automatica', $data)
                ? (bool) $data['numeracion_automatica']
                : true,
            'siguiente_numero' => $siguiente,
            'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : true,
            'maneja_centro_costos' => array_key_exists('maneja_centro_costos', $data)
                ? (bool) $data['maneja_centro_costos']
                : ($existente?->maneja_centro_costos ?? false),
            'centro_costo_obligatorio' => array_key_exists('centro_costo_obligatorio', $data)
                ? (bool) $data['centro_costo_obligatorio']
                : ($existente?->centro_costo_obligatorio ?? false),
            'centro_costo_default_id' => array_key_exists('centro_costo_default_id', $data)
                ? (($data['centro_costo_default_id'] === null || $data['centro_costo_default_id'] === '')
                    ? null
                    : (int) $data['centro_costo_default_id'])
                : $existente?->centro_costo_default_id,
            'libro_oficial' => $libro,
        ];
    }
}
