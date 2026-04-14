<?php

namespace App\Services;

use App\Models\Store;
use App\Models\SupportDocument;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportDocumentExcelExportService
{
    /**
     * @param  Collection<int, SupportDocument>  $documents
     */
    public function downloadList(Store $store, Collection $documents): StreamedResponse
    {
        $currency = $store->currency ?? 'COP';
        $generatedAt = Carbon::now();
        $userName = Auth::user()?->name ?? '—';

        $spreadsheet = new Spreadsheet;
        $sheetDocs = $spreadsheet->getActiveSheet();
        $sheetDocs->setTitle('Documentos');

        $sheetDocs->setCellValue('A1', 'Documentos soporte — '.$store->name);
        $sheetDocs->mergeCells('A1:N1');
        $sheetDocs->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheetDocs->setCellValue('A2', 'Generado: '.$generatedAt->format('d/m/Y H:i'));
        $sheetDocs->setCellValue('A3', 'Usuario: '.$userName);
        $sheetDocs->setCellValue('A4', 'Moneda: '.$currency);

        $headerRow = 6;
        $docHeaders = [
            'ID',
            'Número',
            'Fecha emisión',
            'Registro',
            'Estado',
            'Estado pago',
            'Vencimiento',
            'Proveedor',
            'NIT proveedor',
            'Subtotal',
            'IVA',
            'Total',
            'Comprobante egreso',
            'Notas',
        ];
        $col = 'A';
        foreach ($docHeaders as $h) {
            $sheetDocs->setCellValue($col.$headerRow, $h);
            $col++;
        }
        $sheetDocs->getStyle('A'.$headerRow.':N'.$headerRow)->getFont()->setBold(true);
        $sheetDocs->getStyle('A'.$headerRow.':N'.$headerRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1F2937');
        $sheetDocs->getStyle('A'.$headerRow.':N'.$headerRow)->getFont()->getColor()->setARGB('FFF9FAFB');

        $r = $headerRow + 1;
        if ($documents->isEmpty()) {
            $sheetDocs->setCellValue('A'.$r, 'No hay documentos soporte con los filtros aplicados.');
            $sheetDocs->mergeCells('A'.$r.':N'.$r);
        } else {
            foreach ($documents as $doc) {
                $numero = $doc->doc_prefix.'-'.$doc->doc_number;
                $sheetDocs->setCellValue('A'.$r, $doc->id);
                $sheetDocs->setCellValue('B'.$r, $numero);
                $sheetDocs->setCellValue('C'.$r, $doc->issue_date->format('d/m/Y'));
                $sheetDocs->setCellValue('D'.$r, $doc->created_at->format('d/m/Y H:i'));
                $sheetDocs->setCellValue('E'.$r, $doc->status);
                $sheetDocs->setCellValue('F'.$r, $doc->payment_status);
                $sheetDocs->setCellValue('G'.$r, $doc->due_date ? $doc->due_date->format('d/m/Y') : '');
                $sheetDocs->setCellValue('H'.$r, $doc->proveedor?->nombre ?? '');
                $sheetDocs->setCellValue('I'.$r, $doc->proveedor?->nit ?? '');
                $sheetDocs->setCellValue('J'.$r, (float) $doc->subtotal);
                $sheetDocs->setCellValue('K'.$r, (float) $doc->tax_total);
                $sheetDocs->setCellValue('L'.$r, (float) $doc->total);
                $ce = $doc->comprobanteEgreso;
                $sheetDocs->setCellValue('M'.$r, $ce ? (string) ($ce->number ?? $doc->comprobante_egreso_id) : '');
                $sheetDocs->setCellValue('N'.$r, $doc->notes ? mb_substr((string) $doc->notes, 0, 500) : '');
                $r++;
            }
        }

        foreach (range('A', 'N') as $column) {
            $sheetDocs->getColumnDimension($column)->setAutoSize(true);
        }

        $sheetLines = $spreadsheet->createSheet();
        $sheetLines->setTitle('Lineas');
        $sheetLines->setCellValue('A1', 'Líneas por documento — '.$store->name);
        $sheetLines->mergeCells('A1:H1');
        $sheetLines->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $lh = 3;
        $lineHeaders = ['Documento', 'Tipo', 'Descripción', 'Cantidad', 'Costo unitario', 'IVA %', 'Impuesto', 'Total línea'];
        $col = 'A';
        foreach ($lineHeaders as $h) {
            $sheetLines->setCellValue($col.$lh, $h);
            $col++;
        }
        $sheetLines->getStyle('A'.$lh.':H'.$lh)->getFont()->setBold(true);
        $sheetLines->getStyle('A'.$lh.':H'.$lh)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1F2937');
        $sheetLines->getStyle('A'.$lh.':H'.$lh)->getFont()->getColor()->setARGB('FFF9FAFB');

        $lr = $lh + 1;
        foreach ($documents as $doc) {
            $numero = $doc->doc_prefix.'-'.$doc->doc_number;
            foreach ($doc->inventoryItems as $line) {
                $desc = $line->description ?: ($line->product?->name ?? 'Ítem');
                $sheetLines->setCellValue('A'.$lr, $numero);
                $sheetLines->setCellValue('B'.$lr, 'Inventario');
                $sheetLines->setCellValue('C'.$lr, $desc);
                $sheetLines->setCellValue('D'.$lr, $line->quantity);
                $sheetLines->setCellValue('E'.$lr, (float) $line->unit_cost);
                $sheetLines->setCellValue('F'.$lr, $line->tax_rate !== null ? (float) $line->tax_rate : '');
                $sheetLines->setCellValue('G'.$lr, (float) $line->tax_amount);
                $sheetLines->setCellValue('H'.$lr, (float) $line->line_total);
                $lr++;
            }
            foreach ($doc->serviceItems as $line) {
                $desc = trim($line->service_name.($line->description ? ' — '.$line->description : ''));
                $sheetLines->setCellValue('A'.$lr, $numero);
                $sheetLines->setCellValue('B'.$lr, 'Servicio');
                $sheetLines->setCellValue('C'.$lr, $desc);
                $sheetLines->setCellValue('D'.$lr, $line->quantity);
                $sheetLines->setCellValue('E'.$lr, (float) $line->unit_cost);
                $sheetLines->setCellValue('F'.$lr, $line->tax_rate !== null ? (float) $line->tax_rate : '');
                $sheetLines->setCellValue('G'.$lr, (float) $line->tax_amount);
                $sheetLines->setCellValue('H'.$lr, (float) $line->line_total);
                $lr++;
            }
        }
        if ($lr === $lh + 1) {
            $sheetLines->setCellValue('A'.$lr, 'Sin líneas (sin documentos o sin detalle).');
            $sheetLines->mergeCells('A'.$lr.':H'.$lr);
        }

        foreach (range('A', 'H') as $column) {
            $sheetLines->getColumnDimension($column)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'documentos-soporte-'.$store->slug.'-'.$generatedAt->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
