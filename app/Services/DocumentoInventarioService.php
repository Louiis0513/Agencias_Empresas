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

            $agrupadoDebitos = [];
            $ordenLinea = 1;

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

                $cuentaId = $linea['cuenta_inventario_id'];
                $centroId = $linea['centro_costo_id'] ?? 0;
                $key = $cuentaId.'|'.$centroId;
                if (! isset($agrupadoDebitos[$key])) {
                    $agrupadoDebitos[$key] = [
                        'cuenta_contable_id' => $cuentaId,
                        'centro_costo_id' => $linea['centro_costo_id'],
                        'debito' => 0.0,
                        'detalle' => [],
                    ];
                }
                $agrupadoDebitos[$key]['debito'] = round(
                    $agrupadoDebitos[$key]['debito'] + $linea['costo_total'],
                    2
                );
                $agrupadoDebitos[$key]['detalle'][] = 'Prod: '.$linea['product_codigo']
                    .($linea['bodega_codigo'] ? ' Bod: '.$linea['bodega_codigo'] : '')
                    .' Cant: '.number_format($linea['cantidad'], 2, '.', '');
            }

            $ordenMov = 1;
            $totalDebito = 0.0;
            foreach ($agrupadoDebitos as $grupo) {
                $debito = round($grupo['debito'], 2);
                $totalDebito = round($totalDebito + $debito, 2);
                MovimientoContable::create([
                    'comprobante_contable_id' => null,
                    'documento_inventario_id' => $documento->id,
                    'store_id' => $store->id,
                    'cuenta_contable_id' => $grupo['cuenta_contable_id'],
                    'tercero_id' => null,
                    'centro_costo_id' => $grupo['centro_costo_id'],
                    'detalle_contable' => implode(' | ', array_slice($grupo['detalle'], 0, 5)),
                    'descripcion' => 'Saldo inicial de inventario '.$numero,
                    'debito' => $debito,
                    'credito' => 0,
                    'orden' => $ordenMov++,
                ]);
            }

            MovimientoContable::create([
                'comprobante_contable_id' => null,
                'documento_inventario_id' => $documento->id,
                'store_id' => $store->id,
                'cuenta_contable_id' => $puente->id,
                'tercero_id' => null,
                'centro_costo_id' => null,
                'detalle_contable' => null,
                'descripcion' => 'Saldos iniciales por conciliar '.$numero,
                'debito' => 0,
                'credito' => $totalDebito,
                'orden' => $ordenMov,
            ]);

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
            'lineas.product:id,codigo,nombre',
            'lineas.bodega:id,codigo,nombre',
            'lineas.centroCosto:id,codigo,nombre',
            'movimientosContables.cuentaContable:id,codigo,nombre',
            'movimientosContables.centroCosto:id,codigo,nombre',
        ]);
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
            if ($store->maneja_bodegas) {
                $bodegaId = (int) ($raw['bodega_id'] ?? 0);
                $bodega = Bodega::query()->deStore($store)->whereKey($bodegaId)->first();
                if (! $bodega || ! $bodega->activo) {
                    throw new Exception("Línea {$n}: debe indicar una bodega activa.");
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
}
