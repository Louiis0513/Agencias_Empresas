<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Support\Quantity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exportación del inventario de la tienda a Excel.
 *
 * Filas (sin agrupar lote/serializado):
 * - Simple: una fila por producto (datos en `products`).
 * - Lote: una fila por variante (nombre base + atributos de variante; stock/precio/sku/barcode en variante y stock desde batch_items).
 * - Serializado: una fila por unidad en `product_items` (nombre + atributos + serial; precio/costo/stock por ítem).
 */
class InventoryExcelExportService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function buildRows(Store $store): array
    {
        $rows = [];

        // --- Simples ---
        $simpleProducts = Product::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->where('type', 'simple')
                    ->orWhereNull('type')
                    ->orWhere('type', '');
            })
            ->with('category')
            ->orderBy('name')
            ->get();

        foreach ($simpleProducts as $product) {
            $rows[] = [
                'tipo' => 'Simple',
                'nombre' => $product->name,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'serial' => null,
                'stock' => Quantity::normalizeStockByMode((string) ($product->quantity_mode ?? Product::QUANTITY_MODE_UNIT), $product->stock),
                'precio' => $product->price !== null ? (float) $product->price : null,
                'costo' => $product->cost !== null ? (float) $product->cost : null,
                'margen' => $product->margin !== null ? (float) $product->margin : null,
                'categoria' => $product->category?->name,
                'estado' => null,
            ];
        }

        // --- Por lote: una fila por variante activa ---
        $variants = ProductVariant::query()
            ->select('product_variants.*')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('products.store_id', $store->id)
            ->where('products.is_active', true)
            ->where('products.type', MovimientoInventario::PRODUCT_TYPE_BATCH)
            ->where('product_variants.is_active', true)
            ->with(['product.category'])
            ->withSum('batchItems', 'quantity')
            ->orderBy('products.name')
            ->orderBy('product_variants.id')
            ->get();

        foreach ($variants as $variant) {
            /** @var Product $product */
            $product = $variant->product;
            $nombre = $product->name;
            if ($variant->display_name && $variant->display_name !== '—') {
                $nombre .= ' ('.$variant->display_name.')';
            }

            $stockRaw = (float) ($variant->batch_items_sum_quantity ?? $variant->batchItems()->sum('quantity'));
            $stock = $product->usesDecimalQuantity() ? Quantity::normalize($stockRaw) : (float) floor($stockRaw);
            $precio = $variant->selling_price;
            $costo = $variant->cost_reference !== null ? (float) $variant->cost_reference : null;
            $margen = $variant->margin !== null ? (float) $variant->margin : null;

            $rows[] = [
                'tipo' => 'Lote',
                'nombre' => $nombre,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'serial' => null,
                'stock' => $stock,
                'precio' => $precio,
                'costo' => $costo,
                'margen' => $margen,
                'categoria' => $product->category?->name,
                'estado' => null,
            ];
        }

        // --- Serializado: una fila por product_item ---
        $items = ProductItem::query()
            ->where('store_id', $store->id)
            ->whereHas('product', function ($q) {
                $q->where('is_active', true)
                    ->where('type', MovimientoInventario::PRODUCT_TYPE_SERIALIZED);
            })
            ->with(['product.category.attributes'])
            ->orderBy('product_id')
            ->orderBy('serial_number')
            ->get();

        foreach ($items as $pi) {
            /** @var ProductItem $pi */
            $product = $pi->product;
            $product->loadMissing('category.attributes');
            $attrNames = $product->category
                ? $product->category->attributes->pluck('name', 'id')->all()
                : [];
            $featStr = ProductVariant::formatFeaturesWithAttributeNames($pi->features ?? [], $attrNames);

            $nombre = $product->name;
            if ($featStr !== '') {
                $nombre .= ' ('.$featStr.')';
            }
            $nombre .= ' — Serial: '.($pi->serial_number ?? '');

            $stockUnidad = $pi->isAvailable() ? 1 : 0;

            $rows[] = [
                'tipo' => 'Serializado',
                'nombre' => $nombre,
                'sku' => $product->sku,
                'barcode' => $product->barcode,
                'serial' => $pi->serial_number,
                'stock' => $stockUnidad,
                'precio' => $pi->price !== null ? (float) $pi->price : ($product->price !== null ? (float) $product->price : null),
                'costo' => $pi->cost !== null ? (float) $pi->cost : ($product->cost !== null ? (float) $product->cost : null),
                'margen' => $pi->margin !== null ? (float) $pi->margin : ($product->margin !== null ? (float) $product->margin : null),
                'categoria' => $product->category?->name,
                'estado' => ProductItem::estadosDisponibles()[$pi->status] ?? $pi->status,
            ];
        }

        return $rows;
    }

    /**
     * Totales de valorización: Σ(stock × costo unit.) y Σ(stock × precio unit.), alineado con cada fila del export.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{cost: float, price: float, margin: float}
     */
    public function computeInventoryValuationTotals(array $rows): array
    {
        $totalCost = 0.0;
        $totalPrice = 0.0;

        foreach ($rows as $row) {
            $q = (int) ($row['stock'] ?? 0);
            $c = isset($row['costo']) && $row['costo'] !== null ? (float) $row['costo'] : 0.0;
            $p = isset($row['precio']) && $row['precio'] !== null ? (float) $row['precio'] : 0.0;
            $totalCost += $q * $c;
            $totalPrice += $q * $p;
        }

        return [
            'cost' => $totalCost,
            'price' => $totalPrice,
            'margin' => $totalPrice - $totalCost,
        ];
    }

    protected function formatMoneyForHeader(float $amount, Store $store): string
    {
        $curr = trim((string) ($store->currency ?? ''));
        $suffix = $curr !== '' ? ' '.$curr : '';

        return number_format($amount, 2, ',', '.').$suffix;
    }

    public function download(Store $store): StreamedResponse
    {
        $rows = $this->buildRows($store);
        $totals = $this->computeInventoryValuationTotals($rows);
        $generatedAt = Carbon::now();
        $userName = Auth::user()?->name ?? '—';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Inventario');

        $sheet->setCellValue('A1', 'Inventario — '.$store->name);
        $sheet->mergeCells('A1:K1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $sheet->setCellValue('A2', 'Generado: '.$generatedAt->format('d/m/Y H:i'));
        $sheet->setCellValue('A3', 'Usuario: '.$userName);

        $sheet->setCellValue(
            'A4',
            'Valorización inventario al costo: '.$this->formatMoneyForHeader((float) $totals['cost'], $store)
        );
        $sheet->mergeCells('A4:K4');
        $sheet->getStyle('A4')->getFont()->setBold(true);

        $sheet->setCellValue(
            'A5',
            'Valorización inventario al precio de venta: '.$this->formatMoneyForHeader((float) $totals['price'], $store)
        );
        $sheet->mergeCells('A5:K5');
        $sheet->getStyle('A5')->getFont()->setBold(true);

        $sheet->setCellValue(
            'A6',
            'Margen bruto potencial: '.$this->formatMoneyForHeader((float) $totals['margin'], $store)
        );
        $sheet->mergeCells('A6:K6');
        $sheet->getStyle('A6')->getFont()->setBold(true);

        $headers = [
            'Tipo',
            'Nombre / descripción',
            'SKU',
            'Código de barras',
            'Serial',
            'Stock',
            'Precio venta',
            'Costo',
            'Margen %',
            'Categoría',
            'Estado unidad',
        ];

        $headerRow = 8;
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col.$headerRow, $h);
            $col++;
        }

        $sheet->getStyle('A'.$headerRow.':K'.$headerRow)->getFont()->setBold(true);
        $sheet->getStyle('A'.$headerRow.':K'.$headerRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1F2937');
        $sheet->getStyle('A'.$headerRow.':K'.$headerRow)->getFont()->getColor()->setARGB('FFF9FAFB');

        $r = $headerRow + 1;
        foreach ($rows as $row) {
            $sheet->setCellValue('A'.$r, $row['tipo']);
            $sheet->setCellValue('B'.$r, $row['nombre']);
            $sheet->setCellValue('C'.$r, $row['sku']);
            $sheet->setCellValue('D'.$r, $row['barcode']);
            $sheet->setCellValue('E'.$r, $row['serial']);
            $sheet->setCellValue('F'.$r, $row['stock']);
            $sheet->setCellValue('G'.$r, $row['precio']);
            $sheet->setCellValue('H'.$r, $row['costo']);
            $sheet->setCellValue('I'.$r, $row['margen']);
            $sheet->setCellValue('J'.$r, $row['categoria']);
            $sheet->setCellValue('K'.$r, $row['estado']);
            $r++;
        }

        foreach (range('A', 'K') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'inventario-'.$store->slug.'-'.$generatedAt->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
