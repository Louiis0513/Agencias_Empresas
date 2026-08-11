<?php

namespace App\Services;

use App\Models\Bodega;
use App\Models\CentroCosto;
use App\Models\CuentaContable;
use App\Models\DocumentoInventario;
use App\Models\DocumentoInventarioLinea;
use App\Models\MovimientoContable;
use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\Store;
use App\Models\TipoComprobante;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentoInventarioService
{
    public const CODIGO_PUENTE_SALDOS_INICIALES = '99999999';

    public function __construct(
        protected TipoComprobanteService $tipoComprobanteService,
        protected CuentaContableService $cuentaContableService,
        protected InventarioService $inventarioService,
    ) {}

    /**
     * @param  array{
     *   fecha: string,
     *   tercero_nombre?: string|null,
     *   observaciones?: string|null,
     *   lineas: list<array{
     *     product_id: int,
     *     bodega_id?: int|null,
     *     centro_costo_id?: int|null,
     *     cantidad: float|int|string,
     *     costo_unitario: float|int|string,
     *     descripcion?: string|null
     *   }>
     * }  $payload
     */
    public function contabilizarSaldosIniciales(Store $store, int $userId, array $payload): DocumentoInventario
    {
        return DB::transaction(function () use ($store, $userId, $payload) {
            $lineasNorm = $this->validarLineasSaldosIniciales($store, $payload['lineas'] ?? []);
            $fecha = (string) ($payload['fecha'] ?? '');
            if ($fecha === '') {
                throw new Exception('La fecha de saldos iniciales es obligatoria.');
            }

            $tipo = $this->asegurarTipoAjusteInventario($store);
            $puente = $this->asegurarCuentaPuenteSaldosIniciales($store);
            $numero = $this->tipoComprobanteService->tomarSiguienteNumero($store, $tipo);

            $total = round(array_sum(array_column($lineasNorm, 'costo_total')), 2);

            $documento = DocumentoInventario::create([
                'store_id' => $store->id,
                'tipo_comprobante_id' => $tipo->id,
                'numero' => $numero,
                'tipo_documento' => DocumentoInventario::TIPO_SALDO_INICIAL,
                'fecha' => $fecha,
                'tercero_nombre' => trim((string) ($payload['tercero_nombre'] ?? '')) ?: null,
                'observaciones' => trim((string) ($payload['observaciones'] ?? '')) ?: null,
                'total' => $total,
                'total_debito' => 0,
                'total_credito' => 0,
                'estado' => DocumentoInventario::ESTADO_CONTABILIZADO,
                'created_by' => $userId,
            ]);

            $ordenLinea = 1;
            $ordenMov = 1;
            $totalDebito = 0.0;

            foreach ($lineasNorm as $linea) {
                DocumentoInventarioLinea::create([
                    'documento_inventario_id' => $documento->id,
                    'store_id' => $store->id,
                    'orden' => $ordenLinea++,
                    'product_id' => $linea['product_id'],
                    'descripcion' => $linea['descripcion'],
                    'bodega_id' => $linea['bodega_id'],
                    'centro_costo_id' => $linea['centro_costo_id'],
                    'cantidad' => $linea['cantidad'],
                    'costo_unitario' => $linea['costo_unitario'],
                    'costo_total' => $linea['costo_total'],
                ]);

                $this->inventarioService->registrarMovimiento($store, $userId, [
                    'product_id' => $linea['product_id'],
                    'bodega_id' => $linea['bodega_id'],
                    'fecha' => $fecha,
                    'clase_movimiento' => MovimientoInventario::CLASE_SALDO_INICIAL,
                    'direccion' => MovimientoInventario::DIRECCION_ENTRADA,
                    'cantidad' => $linea['cantidad'],
                    'costo_unitario_entrada' => $linea['costo_unitario'],
                    'valor_entrada' => $linea['costo_total'],
                    'documento' => $documento,
                    'documento_etiqueta' => $numero,
                    'descripcion' => $linea['descripcion'],
                ]);

                $valor = round((float) $linea['costo_total'], 2);
                $totalDebito = round($totalDebito + $valor, 2);

                $detalle = 'Prod: '.$linea['product_codigo']
                    .($linea['bodega_codigo'] ? ' Bod: '.$linea['bodega_codigo'] : '')
                    .' Cant: '.number_format($linea['cantidad'], 2, '.', '');

                // Estilo Siigo: una línea Dr inventario + Cr puente por cada producto.
                MovimientoContable::create([
                    'comprobante_contable_id' => null,
                    'documento_inventario_id' => $documento->id,
                    'store_id' => $store->id,
                    'cuenta_contable_id' => $linea['cuenta_inventario_id'],
                    'tercero_id' => null,
                    'centro_costo_id' => $linea['centro_costo_id'],
                    'detalle_contable' => $detalle,
                    'descripcion' => $linea['descripcion'],
                    'debito' => $valor,
                    'credito' => 0,
                    'orden' => $ordenMov++,
                ]);

                MovimientoContable::create([
                    'comprobante_contable_id' => null,
                    'documento_inventario_id' => $documento->id,
                    'store_id' => $store->id,
                    'cuenta_contable_id' => $puente->id,
                    'tercero_id' => null,
                    'centro_costo_id' => null,
                    'detalle_contable' => null,
                    'descripcion' => 'Saldos iniciales por conciliar',
                    'debito' => 0,
                    'credito' => $valor,
                    'orden' => $ordenMov++,
                ]);
            }

            if (abs($totalDebito - $total) > 0.009) {
                throw new Exception('El asiento de saldos iniciales no cuadra con el total del documento.');
            }

            $documento->update([
                'total_debito' => $totalDebito,
                'total_credito' => $totalDebito,
            ]);

            return $documento->fresh(['lineas.product', 'lineas.bodega', 'tipoComprobante', 'movimientosContables.cuentaContable']);
        });
    }

    /**
     * @param  array{
     *   fecha: string,
     *   tercero_nombre?: string|null,
     *   observaciones?: string|null,
     *   lineas: list<array{
     *     product_id: int,
     *     bodega_id?: int|null,
     *     centro_costo_id?: int|null,
     *     cuenta_contable_id: int,
     *     direccion: string,
     *     cantidad: float|int|string,
     *     costo_unitario: float|int|string,
     *     descripcion?: string|null
     *   }>
     * }  $payload
     */
    public function contabilizarAjuste(Store $store, int $userId, array $payload): DocumentoInventario
    {
        return DB::transaction(function () use ($store, $userId, $payload) {
            $lineasNorm = $this->validarLineasAjuste($store, $payload['lineas'] ?? []);
            $fecha = (string) ($payload['fecha'] ?? '');
            if ($fecha === '') {
                throw new Exception('La fecha del ajuste es obligatoria.');
            }

            $tipo = $this->asegurarTipoAjusteInventario($store);
            $numero = $this->tipoComprobanteService->tomarSiguienteNumero($store, $tipo);

            $total = round(array_sum(array_column($lineasNorm, 'costo_total')), 2);

            $documento = DocumentoInventario::create([
                'store_id' => $store->id,
                'tipo_comprobante_id' => $tipo->id,
                'numero' => $numero,
                'tipo_documento' => DocumentoInventario::TIPO_AJUSTE,
                'fecha' => $fecha,
                'tercero_nombre' => trim((string) ($payload['tercero_nombre'] ?? '')) ?: null,
                'observaciones' => trim((string) ($payload['observaciones'] ?? '')) ?: null,
                'total' => $total,
                'total_debito' => 0,
                'total_credito' => 0,
                'estado' => DocumentoInventario::ESTADO_CONTABILIZADO,
                'created_by' => $userId,
            ]);

            $ordenLinea = 1;
            $ordenMov = 1;
            $totalDebito = 0.0;

            foreach ($lineasNorm as $linea) {
                DocumentoInventarioLinea::create([
                    'documento_inventario_id' => $documento->id,
                    'store_id' => $store->id,
                    'orden' => $ordenLinea++,
                    'product_id' => $linea['product_id'],
                    'descripcion' => $linea['descripcion'],
                    'direccion' => $linea['direccion'],
                    'bodega_id' => $linea['bodega_id'],
                    'centro_costo_id' => $linea['centro_costo_id'],
                    'cuenta_contable_id' => $linea['cuenta_contable_id'],
                    'cantidad' => $linea['cantidad'],
                    'costo_unitario' => $linea['costo_unitario'],
                    'costo_total' => $linea['costo_total'],
                ]);

                $aumenta = $linea['direccion'] === DocumentoInventarioLinea::DIRECCION_AUMENTA;

                $movDatos = [
                    'product_id' => $linea['product_id'],
                    'bodega_id' => $linea['bodega_id'],
                    'fecha' => $fecha,
                    'clase_movimiento' => $aumenta
                        ? MovimientoInventario::CLASE_AJUSTE_ENTRADA
                        : MovimientoInventario::CLASE_AJUSTE_SALIDA,
                    'direccion' => $aumenta
                        ? MovimientoInventario::DIRECCION_ENTRADA
                        : MovimientoInventario::DIRECCION_SALIDA,
                    'cantidad' => $linea['cantidad'],
                    'documento' => $documento,
                    'documento_etiqueta' => $numero,
                    'descripcion' => $linea['descripcion'],
                ];

                if ($aumenta) {
                    $movDatos['costo_unitario_entrada'] = $linea['costo_unitario'];
                    $movDatos['valor_entrada'] = $linea['costo_total'];
                }

                $this->inventarioService->registrarMovimiento($store, $userId, $movDatos);

                $valor = round((float) $linea['costo_total'], 2);
                $totalDebito = round($totalDebito + $valor, 2);

                $detalle = 'Prod: '.$linea['product_codigo']
                    .($linea['bodega_codigo'] ? ' Bod: '.$linea['bodega_codigo'] : '')
                    .' Cant: '.number_format($linea['cantidad'], 2, '.', '');

                if ($aumenta) {
                    // Dr inventario / Cr contrapartida (ingreso/gasto)
                    MovimientoContable::create([
                        'comprobante_contable_id' => null,
                        'documento_inventario_id' => $documento->id,
                        'store_id' => $store->id,
                        'cuenta_contable_id' => $linea['cuenta_inventario_id'],
                        'tercero_id' => null,
                        'centro_costo_id' => $linea['centro_costo_id'],
                        'detalle_contable' => $detalle,
                        'descripcion' => $linea['descripcion'],
                        'debito' => $valor,
                        'credito' => 0,
                        'orden' => $ordenMov++,
                    ]);

                    MovimientoContable::create([
                        'comprobante_contable_id' => null,
                        'documento_inventario_id' => $documento->id,
                        'store_id' => $store->id,
                        'cuenta_contable_id' => $linea['cuenta_contable_id'],
                        'tercero_id' => null,
                        'centro_costo_id' => null,
                        'detalle_contable' => null,
                        'descripcion' => 'Ajuste de inventario (aumenta)',
                        'debito' => 0,
                        'credito' => $valor,
                        'orden' => $ordenMov++,
                    ]);
                } else {
                    // Dr contrapartida / Cr inventario
                    MovimientoContable::create([
                        'comprobante_contable_id' => null,
                        'documento_inventario_id' => $documento->id,
                        'store_id' => $store->id,
                        'cuenta_contable_id' => $linea['cuenta_contable_id'],
                        'tercero_id' => null,
                        'centro_costo_id' => $linea['centro_costo_id'],
                        'detalle_contable' => $detalle,
                        'descripcion' => 'Ajuste de inventario (disminuye)',
                        'debito' => $valor,
                        'credito' => 0,
                        'orden' => $ordenMov++,
                    ]);

                    MovimientoContable::create([
                        'comprobante_contable_id' => null,
                        'documento_inventario_id' => $documento->id,
                        'store_id' => $store->id,
                        'cuenta_contable_id' => $linea['cuenta_inventario_id'],
                        'tercero_id' => null,
                        'centro_costo_id' => null,
                        'detalle_contable' => null,
                        'descripcion' => $linea['descripcion'],
                        'debito' => 0,
                        'credito' => $valor,
                        'orden' => $ordenMov++,
                    ]);
                }
            }

            if (abs($totalDebito - $total) > 0.009) {
                throw new Exception('El asiento del ajuste no cuadra con el total del documento.');
            }

            $documento->update([
                'total_debito' => $totalDebito,
                'total_credito' => $totalDebito,
            ]);

            return $documento->fresh(['lineas.product', 'lineas.bodega', 'tipoComprobante', 'movimientosContables.cuentaContable']);
        });
    }

    /**
     * @param  array{
     *   fecha: string,
     *   tercero_nombre?: string|null,
     *   observaciones?: string|null,
     *   lineas: list<array{
     *     product_id: int,
     *     bodega_origen_id?: int|null,
     *     bodega_destino_id?: int|null,
     *     cantidad: float|int|string,
     *     descripcion?: string|null
     *   }>
     * }  $payload
     */
    public function contabilizarTraslado(Store $store, int $userId, array $payload): DocumentoInventario
    {
        return DB::transaction(function () use ($store, $userId, $payload) {
            if (! $store->maneja_bodegas) {
                throw new Exception('Activa el manejo de bodegas antes de elaborar una nota de traslado.');
            }

            $lineasNorm = $this->validarLineasTraslado($store, $payload['lineas'] ?? []);
            $fecha = (string) ($payload['fecha'] ?? '');
            if ($fecha === '') {
                throw new Exception('La fecha de traslado es obligatoria.');
            }

            $tipo = $this->asegurarTipoNotaTraslado($store);
            $numero = $this->tipoComprobanteService->tomarSiguienteNumero($store, $tipo);

            $terceroNombre = trim((string) ($payload['tercero_nombre'] ?? ''));
            if ($terceroNombre === '') {
                $terceroNombre = (string) $store->name;
            }

            $documento = DocumentoInventario::create([
                'store_id' => $store->id,
                'tipo_comprobante_id' => $tipo->id,
                'numero' => $numero,
                'tipo_documento' => DocumentoInventario::TIPO_TRASLADO,
                'fecha' => $fecha,
                'tercero_nombre' => $terceroNombre,
                'observaciones' => trim((string) ($payload['observaciones'] ?? '')) ?: null,
                'total' => 0,
                'total_debito' => 0,
                'total_credito' => 0,
                'estado' => DocumentoInventario::ESTADO_CONTABILIZADO,
                'created_by' => $userId,
            ]);

            $ordenLinea = 1;
            $ordenMov = 1;

            foreach ($lineasNorm as $linea) {
                DocumentoInventarioLinea::create([
                    'documento_inventario_id' => $documento->id,
                    'store_id' => $store->id,
                    'orden' => $ordenLinea++,
                    'product_id' => $linea['product_id'],
                    'descripcion' => $linea['descripcion'],
                    'bodega_origen_id' => $linea['bodega_origen_id'],
                    'bodega_destino_id' => $linea['bodega_destino_id'],
                    'cantidad' => $linea['cantidad'],
                    'costo_unitario' => 0,
                    'costo_total' => 0,
                ]);

                $this->inventarioService->registrarMovimiento($store, $userId, [
                    'product_id' => $linea['product_id'],
                    'bodega_id' => $linea['bodega_origen_id'],
                    'fecha' => $fecha,
                    'clase_movimiento' => MovimientoInventario::CLASE_TRASLADO_SALIDA,
                    'direccion' => MovimientoInventario::DIRECCION_SALIDA,
                    'cantidad' => $linea['cantidad'],
                    'documento' => $documento,
                    'documento_etiqueta' => $numero,
                    'descripcion' => $linea['descripcion'],
                ]);

                $this->inventarioService->registrarMovimiento($store, $userId, [
                    'product_id' => $linea['product_id'],
                    'bodega_id' => $linea['bodega_destino_id'],
                    'fecha' => $fecha,
                    'clase_movimiento' => MovimientoInventario::CLASE_TRASLADO_ENTRADA,
                    'direccion' => MovimientoInventario::DIRECCION_ENTRADA,
                    'cantidad' => $linea['cantidad'],
                    'costo_unitario_entrada' => 0,
                    'valor_entrada' => 0,
                    'documento' => $documento,
                    'documento_etiqueta' => $numero,
                    'descripcion' => $linea['descripcion'],
                ]);

                $cantFmt = number_format($linea['cantidad'], 2, '.', '');
                $bodOrigen = $linea['bodega_origen_codigo'] ?? 'Sin asignar';
                $bodDestino = $linea['bodega_destino_codigo'] ?? 'Sin asignar';

                // Siigo: 2 filas a $0 con Bod origen / Bod destino.
                MovimientoContable::create([
                    'comprobante_contable_id' => null,
                    'documento_inventario_id' => $documento->id,
                    'store_id' => $store->id,
                    'cuenta_contable_id' => $linea['cuenta_inventario_id'],
                    'tercero_id' => null,
                    'centro_costo_id' => null,
                    'detalle_contable' => $linea['descripcion'],
                    'descripcion' => 'Prod: '.$linea['product_codigo'].' Bod: '.$bodOrigen.' Cant: '.$cantFmt,
                    'debito' => 0,
                    'credito' => 0,
                    'orden' => $ordenMov++,
                ]);

                MovimientoContable::create([
                    'comprobante_contable_id' => null,
                    'documento_inventario_id' => $documento->id,
                    'store_id' => $store->id,
                    'cuenta_contable_id' => $linea['cuenta_inventario_id'],
                    'tercero_id' => null,
                    'centro_costo_id' => null,
                    'detalle_contable' => $linea['descripcion'],
                    'descripcion' => 'Prod: '.$linea['product_codigo'].' Bod: '.$bodDestino.' Cant: '.$cantFmt,
                    'debito' => 0,
                    'credito' => 0,
                    'orden' => $ordenMov++,
                ]);
            }

            return $documento->fresh([
                'lineas.product',
                'lineas.bodegaOrigen',
                'lineas.bodegaDestino',
                'tipoComprobante',
                'movimientosContables.cuentaContable',
            ]);
        });
    }

    public function listar(Store $store, array $filtros = [], int $perPage = 30): LengthAwarePaginator
    {
        $q = DocumentoInventario::query()
            ->deStore($store)
            ->with(['tipoComprobante:id,familia,codigo,prefijo,nombre'])
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if (! empty($filtros['tipo_documento'])) {
            $q->where('tipo_documento', $filtros['tipo_documento']);
        }

        if (! empty($filtros['fecha_desde'])) {
            $q->whereDate('fecha', '>=', $filtros['fecha_desde']);
        }

        if (! empty($filtros['fecha_hasta'])) {
            $q->whereDate('fecha', '<=', $filtros['fecha_hasta']);
        }

        if (! empty($filtros['search'])) {
            $search = trim((string) $filtros['search']);
            $q->where(function ($qq) use ($search) {
                $qq->where('numero', 'like', '%'.$search.'%')
                    ->orWhere('tercero_nombre', 'like', '%'.$search.'%')
                    ->orWhere('observaciones', 'like', '%'.$search.'%');
            });
        }

        return $q->paginate($perPage)->withQueryString();
    }

    public function obtener(Store $store, DocumentoInventario $documento): DocumentoInventario
    {
        if ($documento->store_id !== $store->id) {
            throw new Exception('El documento no pertenece a esta tienda.');
        }

        return $documento->load([
            'tipoComprobante',
            'creador:id,name',
            'lineas.product:id,codigo,nombre,referencia,categoria_contable_id',
            'lineas.product.categoriaContable:id,cuenta_inventario_id',
            'lineas.product.categoriaContable.cuentaInventario:id,codigo,nombre',
            'lineas.bodega:id,codigo,nombre',
            'lineas.bodegaOrigen:id,codigo,nombre',
            'lineas.bodegaDestino:id,codigo,nombre',
            'lineas.centroCosto:id,codigo,nombre',
            'lineas.cuentaContable:id,codigo,nombre',
            'movimientosContables.cuentaContable:id,codigo,nombre',
            'movimientosContables.centroCosto:id,codigo,nombre',
        ]);
    }

    /**
     * Datos para PDF/show imprimible estilo Siigo.
     *
     * @return array{
     *   documento: DocumentoInventario,
     *   store: Store,
     *   logoAbsPath: string|null,
     *   ciudadEmpresa: string,
     *   naturaleza: string
     * }
     */
    public function datosVistaPdf(Store $store, DocumentoInventario $documento): array
    {
        $documento = $this->obtener($store, $documento);

        $ciudadEmpresa = trim(implode(' - ', array_filter([
            $store->city ?? null,
            $store->country ?? null,
        ])));

        $logoAbsPath = null;
        if (filled($store->logo_path)) {
            $candidate = storage_path('app/public/'.$store->logo_path);
            if (is_file($candidate)) {
                $logoAbsPath = $candidate;
            }
        }

        return [
            'documento' => $documento,
            'store' => $store,
            'logoAbsPath' => $logoAbsPath,
            'ciudadEmpresa' => $ciudadEmpresa,
            'naturaleza' => $documento->etiquetaNaturaleza(),
        ];
    }

    public function exportContabilizacionExcel(Store $store, DocumentoInventario $documento): StreamedResponse
    {
        $documento = $this->obtener($store, $documento);
        $filename = 'Contabilizacion_'.$documento->numero.'_'.$documento->fecha->format('Y_m_d').'.xlsx';

        return response()->streamDownload(function () use ($store, $documento) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Contabilizacion');

            $sheet->setCellValue('A1', 'Contabilización  '.$documento->numero.' '.$documento->fecha->format('Y/m/d'));
            $sheet->setCellValue('A2', $store->name);
            $sheet->setCellValue('A3', (string) ($store->nit ?? ''));

            $headers = ['A' => 'Código contable', 'B' => 'Cuenta contable', 'C' => 'Nombre del tercero', 'D' => 'Detalle', 'E' => 'Descripción', 'F' => 'Centro de costo', 'G' => 'Débito', 'H' => 'Crédito'];
            foreach ($headers as $col => $h) {
                $sheet->setCellValue($col.'7', $h);
            }

            $row = 8;
            foreach ($documento->movimientosContables as $mov) {
                $sheet->setCellValue('A'.$row, $mov->cuentaContable?->codigo);
                $sheet->setCellValue('B'.$row, $mov->cuentaContable?->nombre);
                $sheet->setCellValue('C'.$row, $documento->tercero_nombre);
                $sheet->setCellValue('D'.$row, $mov->detalle_contable);
                $sheet->setCellValue('E'.$row, $mov->descripcion);
                $sheet->setCellValue('F'.$row, $mov->centroCosto?->nombre);
                $sheet->setCellValue('G'.$row, (float) $mov->debito > 0 ? (float) $mov->debito : null);
                $sheet->setCellValue('H'.$row, (float) $mov->credito > 0 ? (float) $mov->credito : null);
                $row++;
            }

            $sheet->setCellValue('A'.$row, 'Total general');
            $sheet->setCellValue('G'.$row, (float) $documento->total_debito);
            $sheet->setCellValue('H'.$row, (float) $documento->total_credito);

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function asegurarTipoAjusteInventario(Store $store): TipoComprobante
    {
        $this->tipoComprobanteService->asegurarTiposPorDefecto($store);

        $tipo = TipoComprobante::query()
            ->deStore($store)
            ->where('familia', TipoComprobante::FAMILIA_A)
            ->where('codigo', '1')
            ->first();

        if (! $tipo) {
            throw new Exception('No se pudo asegurar el tipo de comprobante A-1.');
        }

        return $tipo;
    }

    public function asegurarTipoNotaTraslado(Store $store): TipoComprobante
    {
        $this->tipoComprobanteService->asegurarTiposPorDefecto($store);

        $tipo = TipoComprobante::query()
            ->deStore($store)
            ->where('familia', TipoComprobante::FAMILIA_NT)
            ->where('codigo', '1')
            ->first();

        if (! $tipo) {
            throw new Exception('No se pudo asegurar el tipo de comprobante NT-1.');
        }

        return $tipo;
    }

    public function asegurarCuentaPuenteSaldosIniciales(Store $store): CuentaContable
    {
        return $this->cuentaContableService->asegurarCuentaPorCodigo(
            $store,
            self::CODIGO_PUENTE_SALDOS_INICIALES,
            [
                'nombre' => 'Saldos iniciales por conciliar',
                'forzar_transaccional' => true,
                'categoria' => CuentaContable::CATEGORIA_ORDEN,
                'relacion_con' => 'Cuenta Puente',
                'clase' => CuentaContable::CLASES_POR_DIGITO['9'] ?? 'Cuentas de orden acreedoras',
            ]
        );
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    protected function validarLineasSaldosIniciales(Store $store, array $lineas): array
    {
        if ($lineas === []) {
            throw new Exception('Debe agregar al menos una línea de producto.');
        }

        $out = [];
        foreach ($lineas as $idx => $raw) {
            $n = $idx + 1;
            $productId = (int) ($raw['product_id'] ?? 0);
            $product = Product::query()
                ->with(['categoriaContable.cuentaInventario'])
                ->where('store_id', $store->id)
                ->whereKey($productId)
                ->first();

            if (! $product) {
                throw new Exception("Línea {$n}: producto no válido.");
            }
            if (! $product->es_inventariable) {
                throw new Exception("Línea {$n}: el producto «{$product->codigo}» no es inventariable.");
            }

            $cuentaInv = $product->categoriaContable?->cuentaInventario;
            if (! $cuentaInv || ! $cuentaInv->esUsableEnDocumentoContable()) {
                throw new Exception(
                    "Línea {$n}: el producto «{$product->codigo}» no tiene cuenta de inventario usable en su categoría contable."
                );
            }

            $cantidad = round((float) ($raw['cantidad'] ?? 0), 4);
            $costo = round((float) ($raw['costo_unitario'] ?? 0), 4);
            if ($cantidad <= 0) {
                throw new Exception("Línea {$n}: la cantidad debe ser mayor a 0.");
            }
            if ($costo <= 0) {
                throw new Exception("Línea {$n}: el costo unitario debe ser mayor a 0.");
            }

            $bodegaId = null;
            $bodegaCodigo = null;
            if (! empty($raw['bodega_id'])) {
                $bodega = Bodega::query()
                    ->deStore($store)
                    ->whereKey((int) $raw['bodega_id'])
                    ->first();
                if (! $bodega || ! $bodega->activo) {
                    throw new Exception("Línea {$n}: la bodega indicada no es válida o está inactiva.");
                }
                $bodegaId = $bodega->id;
                $bodegaCodigo = $bodega->codigo;
            }
            // Sin bodega = «Sin asignar» (permitido aunque la tienda maneje bodegas).

            $centroId = null;
            if (! empty($raw['centro_costo_id'])) {
                $centro = CentroCosto::query()
                    ->deStore($store)
                    ->whereKey((int) $raw['centro_costo_id'])
                    ->first();
                if (! $centro) {
                    throw new Exception("Línea {$n}: centro de costo no válido.");
                }
                $centroId = $centro->id;
            }

            $descripcion = trim((string) ($raw['descripcion'] ?? '')) ?: $product->nombre;

            $out[] = [
                'product_id' => $product->id,
                'product_codigo' => $product->codigo,
                'descripcion' => $descripcion,
                'bodega_id' => $bodegaId,
                'bodega_codigo' => $bodegaCodigo,
                'centro_costo_id' => $centroId,
                'cantidad' => $cantidad,
                'costo_unitario' => $costo,
                'costo_total' => round($cantidad * $costo, 2),
                'cuenta_inventario_id' => $cuentaInv->id,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    protected function validarLineasAjuste(Store $store, array $lineas): array
    {
        if ($lineas === []) {
            throw new Exception('Debe agregar al menos una línea de producto.');
        }

        $out = [];
        foreach ($lineas as $idx => $raw) {
            $n = $idx + 1;
            $productId = (int) ($raw['product_id'] ?? 0);
            $product = Product::query()
                ->with(['categoriaContable.cuentaInventario'])
                ->where('store_id', $store->id)
                ->whereKey($productId)
                ->first();

            if (! $product) {
                throw new Exception("Línea {$n}: producto no válido.");
            }
            if (! $product->es_inventariable) {
                throw new Exception("Línea {$n}: el producto «{$product->codigo}» no es inventariable.");
            }

            $cuentaInv = $product->categoriaContable?->cuentaInventario;
            if (! $cuentaInv || ! $cuentaInv->esUsableEnDocumentoContable()) {
                throw new Exception(
                    "Línea {$n}: el producto «{$product->codigo}» no tiene cuenta de inventario usable en su categoría contable."
                );
            }

            $direccion = strtoupper(trim((string) ($raw['direccion'] ?? '')));
            if (! in_array($direccion, [
                DocumentoInventarioLinea::DIRECCION_AUMENTA,
                DocumentoInventarioLinea::DIRECCION_DISMINUYE,
            ], true)) {
                throw new Exception("Línea {$n}: indica si el ajuste aumenta o disminuye.");
            }

            $cuentaId = (int) ($raw['cuenta_contable_id'] ?? 0);
            $cuentaContra = CuentaContable::query()
                ->deStore($store)
                ->whereKey($cuentaId)
                ->first();
            if (! $cuentaContra || ! $cuentaContra->esUsableEnDocumentoContable()) {
                throw new Exception("Línea {$n}: la cuenta contable de contrapartida no es válida.");
            }

            $cantidad = round((float) ($raw['cantidad'] ?? 0), 4);
            $costo = round((float) ($raw['costo_unitario'] ?? 0), 4);
            if ($cantidad <= 0) {
                throw new Exception("Línea {$n}: la cantidad debe ser mayor a 0.");
            }
            if ($costo <= 0) {
                throw new Exception("Línea {$n}: el costo unitario debe ser mayor a 0.");
            }

            $bodegaId = null;
            $bodegaCodigo = null;
            if (! empty($raw['bodega_id'])) {
                $bodega = Bodega::query()
                    ->deStore($store)
                    ->whereKey((int) $raw['bodega_id'])
                    ->first();
                if (! $bodega || ! $bodega->activo) {
                    throw new Exception("Línea {$n}: la bodega indicada no es válida o está inactiva.");
                }
                $bodegaId = $bodega->id;
                $bodegaCodigo = $bodega->codigo;
            }

            $centroId = null;
            if (! empty($raw['centro_costo_id'])) {
                $centro = CentroCosto::query()
                    ->deStore($store)
                    ->whereKey((int) $raw['centro_costo_id'])
                    ->first();
                if (! $centro) {
                    throw new Exception("Línea {$n}: centro de costo no válido.");
                }
                $centroId = $centro->id;
            }

            $descripcion = trim((string) ($raw['descripcion'] ?? '')) ?: $product->nombre;

            $out[] = [
                'product_id' => $product->id,
                'product_codigo' => $product->codigo,
                'descripcion' => $descripcion,
                'direccion' => $direccion,
                'bodega_id' => $bodegaId,
                'bodega_codigo' => $bodegaCodigo,
                'centro_costo_id' => $centroId,
                'cuenta_contable_id' => $cuentaContra->id,
                'cantidad' => $cantidad,
                'costo_unitario' => $costo,
                'costo_total' => round($cantidad * $costo, 2),
                'cuenta_inventario_id' => $cuentaInv->id,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    protected function validarLineasTraslado(Store $store, array $lineas): array
    {
        if ($lineas === []) {
            throw new Exception('Debe agregar al menos una línea de producto.');
        }

        $out = [];
        /** @var array<string, float> $reservadoPorOrigen productId:bodegaKey => cantidad ya comprometida en líneas previas */
        $reservadoPorOrigen = [];

        foreach ($lineas as $idx => $raw) {
            $n = $idx + 1;
            $productId = (int) ($raw['product_id'] ?? 0);
            $product = Product::query()
                ->with(['categoriaContable.cuentaInventario'])
                ->where('store_id', $store->id)
                ->whereKey($productId)
                ->first();

            if (! $product) {
                throw new Exception("Línea {$n}: producto no válido.");
            }
            if (! $product->es_inventariable) {
                throw new Exception("Línea {$n}: el producto «{$product->codigo}» no es inventariable.");
            }

            $cuentaInv = $product->categoriaContable?->cuentaInventario;
            if (! $cuentaInv || ! $cuentaInv->esUsableEnDocumentoContable()) {
                throw new Exception(
                    "Línea {$n}: el producto «{$product->codigo}» no tiene cuenta de inventario usable en su categoría contable."
                );
            }

            $cantidad = round((float) ($raw['cantidad'] ?? 0), 4);
            if ($cantidad <= 0) {
                throw new Exception("Línea {$n}: la cantidad debe ser mayor a 0.");
            }

            $origenId = null;
            $origenCodigo = null;
            $origenEtiqueta = 'Sin asignar';
            if (! empty($raw['bodega_origen_id'])) {
                $bodega = Bodega::query()
                    ->deStore($store)
                    ->whereKey((int) $raw['bodega_origen_id'])
                    ->first();
                if (! $bodega || ! $bodega->activo) {
                    throw new Exception("Línea {$n}: la bodega de origen no es válida o está inactiva.");
                }
                $origenId = $bodega->id;
                $origenCodigo = $bodega->codigo;
                $origenEtiqueta = trim(($bodega->codigo ? $bodega->codigo.' · ' : '').$bodega->nombre);
            }

            $destinoId = null;
            $destinoCodigo = null;
            if (! empty($raw['bodega_destino_id'])) {
                $bodega = Bodega::query()
                    ->deStore($store)
                    ->whereKey((int) $raw['bodega_destino_id'])
                    ->first();
                if (! $bodega || ! $bodega->activo) {
                    throw new Exception("Línea {$n}: la bodega de destino no es válida o está inactiva.");
                }
                $destinoId = $bodega->id;
                $destinoCodigo = $bodega->codigo;
            }

            if ($origenId !== null && $destinoId !== null && $origenId === $destinoId) {
                throw new Exception("Línea {$n}: la bodega de origen y destino deben ser distintas.");
            }

            if ($origenId === null && $destinoId === null) {
                throw new Exception("Línea {$n}: indica al menos bodega de origen o de destino.");
            }

            $disponible = $this->inventarioService->stockEnBodega($store, $product, $origenId);
            $claveOrigen = $product->id.':'.($origenId === null ? 'null' : (string) $origenId);
            $yaReservado = $reservadoPorOrigen[$claveOrigen] ?? 0.0;
            $disponibleNeto = round($disponible - $yaReservado, 4);

            if ($cantidad > $disponibleNeto + 0.00005) {
                $dispFmt = number_format(max(0, $disponibleNeto), 2, ',', '.');
                throw new Exception(
                    "Línea {$n}: el producto «{$product->codigo}» solo tiene {$dispFmt} disponible en «{$origenEtiqueta}». ".
                    'El traslado no crea existencias; usa Ajuste de inventario o Saldos iniciales para registrarlas.'
                );
            }

            $reservadoPorOrigen[$claveOrigen] = round($yaReservado + $cantidad, 4);

            $descripcion = trim((string) ($raw['descripcion'] ?? '')) ?: $product->nombre;

            $out[] = [
                'product_id' => $product->id,
                'product_codigo' => $product->codigo,
                'descripcion' => $descripcion,
                'bodega_origen_id' => $origenId,
                'bodega_origen_codigo' => $origenCodigo,
                'bodega_destino_id' => $destinoId,
                'bodega_destino_codigo' => $destinoCodigo,
                'cantidad' => $cantidad,
                'cuenta_inventario_id' => $cuentaInv->id,
            ];
        }

        return $out;
    }
}
