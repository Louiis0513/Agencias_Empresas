<?php

namespace App\Services;

use App\Models\MovimientoInventario;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exporta el historial de movimientos de inventario a Excel (columnas alineadas con la vista de inventario).
 */
class InventoryMovementsExcelExportService
{
    private const CHUNK_SIZE = 500;

    public function __construct(
        private InventarioService $inventarioService
    ) {}

    /**
     * @param  array<string, mixed>  $filtros  Mismas claves que el listado (sin per_page).
     */
    public function download(Store $store, array $filtros = []): StreamedResponse
    {
        $generatedAt = Carbon::now();
        $userName = Auth::user()?->name ?? '—';
        $currency = $store->currency ?? 'COP';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Movimientos');

        $sheet->setCellValue('A1', 'Movimientos de inventario — '.$store->name);
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $sheet->setCellValue('A2', 'Generado: '.$generatedAt->format('d/m/Y H:i'));
        $sheet->setCellValue('A3', 'Usuario: '.$userName);

        $headers = [
            'Fecha',
            'Tipo',
            'Producto',
            'Cantidad',
            'Costo unit.',
            'Descripción',
            'Usuario',
        ];

        $headerRow = 5;
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col.$headerRow, $h);
            $col++;
        }

        $sheet->getStyle('A'.$headerRow.':G'.$headerRow)->getFont()->setBold(true);
        $sheet->getStyle('A'.$headerRow.':G'.$headerRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1F2937');
        $sheet->getStyle('A'.$headerRow.':G'.$headerRow)->getFont()->getColor()->setARGB('FFF9FAFB');

        $r = $headerRow + 1;

        $query = $this->inventarioService->movimientosQuery($store, $filtros);

        foreach ($query->lazyById(self::CHUNK_SIZE, 'id', 'id', true) as $mov) {
            /** @var MovimientoInventario $mov */
            $sign = $mov->type === MovimientoInventario::TYPE_ENTRADA ? '+' : '-';
            $cantidad = $sign.$mov->quantity;

            $costo = $mov->unit_cost !== null
                ? money((float) $mov->unit_cost, $currency, false)
                : '—';

            $sheet->setCellValue('A'.$r, $mov->created_at->format('d/m/Y H:i'));
            $sheet->setCellValue('B'.$r, $mov->type);
            $sheet->setCellValue('C'.$r, $mov->product_display);
            $sheet->setCellValue('D'.$r, $cantidad);
            $sheet->setCellValue('E'.$r, $costo);
            $sheet->setCellValue('F'.$r, $mov->description ?? '—');
            $sheet->setCellValue('G'.$r, $mov->user->name ?? '—');
            $r++;
        }

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'movimientos-inventario-'.$store->slug.'-'.$generatedAt->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
