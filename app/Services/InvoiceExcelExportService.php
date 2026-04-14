<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceExcelExportService
{
    /**
     * @param  Collection<int, Invoice>  $invoices
     */
    public function downloadList(Store $store, Collection $invoices): StreamedResponse
    {
        $currency = $store->currency ?? 'COP';
        $generatedAt = Carbon::now();
        $userName = Auth::user()?->name ?? '—';

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Facturas');

        $sheet->setCellValue('A1', 'Facturas — '.$store->name);
        $sheet->mergeCells('A1:L1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'Generado: '.$generatedAt->format('d/m/Y H:i'));
        $sheet->setCellValue('A3', 'Usuario: '.$userName);
        $sheet->setCellValue('A4', 'Moneda: '.$currency);

        $headerRow = 6;
        $headers = [
            'ID',
            'Fecha',
            'Cliente',
            'Email cliente',
            'Subtotal',
            'IVA',
            'Descuento',
            'Total',
            'Estado',
            'Método pago',
            'Usuario',
            'Email usuario',
        ];
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col.$headerRow, $h);
            $col++;
        }
        $sheet->getStyle('A'.$headerRow.':L'.$headerRow)->getFont()->setBold(true);
        $sheet->getStyle('A'.$headerRow.':L'.$headerRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1F2937');
        $sheet->getStyle('A'.$headerRow.':L'.$headerRow)->getFont()->getColor()->setARGB('FFF9FAFB');

        $r = $headerRow + 1;
        if ($invoices->isEmpty()) {
            $sheet->setCellValue('A'.$r, 'No hay facturas con los filtros aplicados.');
            $sheet->mergeCells('A'.$r.':L'.$r);
        } else {
            foreach ($invoices as $inv) {
                $sheet->setCellValue('A'.$r, $inv->id);
                $sheet->setCellValue('B'.$r, $inv->created_at->format('d/m/Y H:i'));
                $sheet->setCellValue('C'.$r, $inv->customer?->name ?? '');
                $sheet->setCellValue('D'.$r, $inv->customer?->email ?? '');
                $sheet->setCellValue('E'.$r, (float) $inv->subtotal);
                $sheet->setCellValue('F'.$r, (float) $inv->tax);
                $sheet->setCellValue('G'.$r, (float) $inv->discount);
                $sheet->setCellValue('H'.$r, (float) $inv->total);
                $sheet->setCellValue('I'.$r, (string) $inv->status);
                $sheet->setCellValue('J'.$r, $inv->payment_method ?? '');
                $sheet->setCellValue('K'.$r, $inv->user?->name ?? '');
                $sheet->setCellValue('L'.$r, $inv->user?->email ?? '');
                $r++;
            }
        }

        foreach (range('A', 'L') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'facturas-'.$store->slug.'-'.$generatedAt->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
