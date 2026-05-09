<?php

namespace App\Services;

use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\ComprobanteEgreso;
use App\Models\ComprobanteIngreso;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MovimientosExcelExportService
{
    public function __construct(
        protected ComprobanteIngresoService $comprobanteIngresoService,
        protected ComprobanteEgresoService $comprobanteEgresoService,
        protected CajaService $cajaService,
        protected StoreTimezoneService $storeTimezoneService
    ) {}

    /**
     * @param  array{fecha_desde: ?string, fecha_hasta: ?string, fecha_dia: ?string, search: ?string, bolsillo_ids: array<int>, empleado_user_ids: array<int>, customer_id: ?int, proveedor_id: ?int, export_mes?: string}  $mf
     * @param  array{fecha_desde: ?string, fecha_hasta: ?string, search: ?string, bolsillo_ids: array<int>, empleado_user_ids: array<int>, timezone: string}  $filtrosBase
     */
    public function download(
        Store $store,
        array $mf,
        array $filtrosBase,
        string $movimientosResumenEtiqueta,
        ?EloquentCollection $cuentasPorCobrar,
        ?EloquentCollection $cuentasPorPagar,
        ?float $saldoPendienteCobrar,
        ?float $deudaTotalPagar,
    ): StreamedResponse {
        $currency = $store->currency ?? 'COP';
        $tzName = $this->storeTimezoneService->getTimezoneForStore($store);
        $generatedAt = Carbon::now();
        $userName = Auth::user()?->name ?? '—';

        $filtrosIng = array_merge($filtrosBase, ['customer_id' => $mf['customer_id']]);
        $filtrosEgr = array_merge($filtrosBase, ['proveedor_id' => $mf['proveedor_id']]);

        $totalIngresos = $this->comprobanteIngresoService->sumarMontosDestinosMovimientos($store, $filtrosIng);
        $totalEgresos = $this->comprobanteEgresoService->sumarMontosOrigenesMovimientos($store, $filtrosEgr);
        $balance = $totalIngresos - $totalEgresos;
        $totalCaja = $this->cajaService->totalCaja($store);

        $lineasIngreso = $this->comprobanteIngresoService->coleccionDestinosParaExportacionMovimientos($store, $filtrosIng);
        $lineasEgreso = $this->comprobanteEgresoService->coleccionOrigenesParaExportacionMovimientos($store, $filtrosEgr);
        $ingPorBol = $this->comprobanteIngresoService->totalesIngresosPorBolsilloMovimientos($store, $filtrosIng);
        $egrPorBol = $this->comprobanteEgresoService->totalesEgresosPorBolsilloMovimientos($store, $filtrosEgr);

        $spreadsheet = new Spreadsheet;
        $resumen = $spreadsheet->getActiveSheet();
        $resumen->setTitle('Resumen');

        $ingSheet = new Worksheet($spreadsheet, 'Ingresos');
        $spreadsheet->addSheet($ingSheet, 1);
        $egrSheet = new Worksheet($spreadsheet, 'Egresos');
        $spreadsheet->addSheet($egrSheet, 2);

        $this->fillResumenSheet($resumen, $store, $currency, $tzName, $userName, $generatedAt, $movimientosResumenEtiqueta, $mf, $totalIngresos, $totalEgresos, $balance, $totalCaja, $ingPorBol, $egrPorBol, $saldoPendienteCobrar, $deudaTotalPagar);
        $this->fillIngresosSheet($ingSheet, $store, $lineasIngreso);
        $this->fillEgresosSheet($egrSheet, $store, $lineasEgreso);

        $nextIdx = 3;
        if ($cuentasPorCobrar !== null) {
            $cxSheet = new Worksheet($spreadsheet, 'Por cobrar');
            $spreadsheet->addSheet($cxSheet, $nextIdx++);
            $this->fillPorCobrarSheet($cxSheet, $store, $cuentasPorCobrar);
        }
        if ($cuentasPorPagar !== null) {
            $cpSheet = new Worksheet($spreadsheet, 'Por pagar');
            $spreadsheet->addSheet($cpSheet, $nextIdx++);
            $this->fillPorPagarSheet($cpSheet, $store, $cuentasPorPagar);
        }

        $spreadsheet->setActiveSheetIndex(0);

        $suffix = ! empty($mf['export_mes']) ? '-'.$mf['export_mes'] : '';
        $filename = 'movimientos-'.$store->slug.$suffix.'-'.$generatedAt->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function fillResumenSheet(
        Worksheet $sheet,
        Store $store,
        string $currency,
        string $tzName,
        string $userName,
        Carbon $generatedAt,
        string $movimientosResumenEtiqueta,
        array $mf,
        float $totalIngresos,
        float $totalEgresos,
        float $balance,
        float $totalCaja,
        \Illuminate\Support\Collection $ingPorBol,
        \Illuminate\Support\Collection $egrPorBol,
        ?float $saldoPendienteCobrar,
        ?float $deudaTotalPagar,
    ): void {
        $r = 1;
        $sheet->setCellValue('A'.$r, 'Movimientos financieros — '.$store->name);
        $sheet->mergeCells('A'.$r.':F'.$r);
        $sheet->getStyle('A'.$r)->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A'.++$r, 'Generado: '.$generatedAt->format('d/m/Y H:i'));
        $sheet->setCellValue('A'.++$r, 'Usuario: '.$userName);
        $sheet->setCellValue('A'.++$r, 'Moneda: '.$currency);
        $sheet->setCellValue('A'.++$r, 'Zona horaria (fechas detalle): '.$tzName);
        $sheet->setCellValue('A'.++$r, $movimientosResumenEtiqueta);

        $filtrosLinea = $this->resumenFiltrosLinea($mf);
        $sheet->setCellValue('A'.++$r, 'Filtros aplicados: '.$filtrosLinea);

        $r += 2;
        $sheet->setCellValue('A'.$r, 'Total ingresos (período)');
        $sheet->setCellValue('B'.$r, $totalIngresos);
        $sheet->setCellValue('A'.++$r, 'Total egresos (período)');
        $sheet->setCellValue('B'.$r, $totalEgresos);
        $sheet->setCellValue('A'.++$r, 'Balance (ingresos − egresos)');
        $sheet->setCellValue('B'.$r, $balance);
        $sheet->setCellValue('A'.++$r, 'Total caja actual (suma saldos bolsillos)');
        $sheet->setCellValue('B'.$r, $totalCaja);

        if ($saldoPendienteCobrar !== null) {
            $sheet->setCellValue('A'.++$r, 'Saldo pendiente cobro (global tienda)');
            $sheet->setCellValue('B'.$r, $saldoPendienteCobrar);
        }
        if ($deudaTotalPagar !== null) {
            $sheet->setCellValue('A'.++$r, 'Deuda pendiente proveedores (global tienda)');
            $sheet->setCellValue('B'.$r, $deudaTotalPagar);
        }

        $headerRow = $r + 2;
        $sheet->setCellValue('A'.$headerRow, 'Bolsillo');
        $sheet->setCellValue('B'.$headerRow, 'Ingresos período');
        $sheet->setCellValue('C'.$headerRow, 'Egresos período');
        $sheet->setCellValue('D'.$headerRow, 'Neto período');
        $this->styleHeaderRow($sheet, 'A'.$headerRow.':D'.$headerRow);

        $ingMap = $ingPorBol->keyBy(fn ($row) => $row->bolsillo_name);
        $egrMap = $egrPorBol->keyBy(fn ($row) => $row->bolsillo_name);
        $names = $ingMap->keys()->merge($egrMap->keys())->unique()->sort()->values();

        $row = $headerRow + 1;
        if ($names->isEmpty()) {
            $sheet->setCellValue('A'.$row, 'Sin movimientos por bolsillo en este período.');
            $sheet->mergeCells('A'.$row.':D'.$row);
        } else {
            foreach ($names as $name) {
                $i = (float) ($ingMap->get($name)?->total ?? 0);
                $e = (float) ($egrMap->get($name)?->total ?? 0);
                $sheet->setCellValue('A'.$row, $name);
                $sheet->setCellValue('B'.$row, $i);
                $sheet->setCellValue('C'.$row, $e);
                $sheet->setCellValue('D'.$row, $i - $e);
                $row++;
            }
        }

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function resumenFiltrosLinea(array $mf): string
    {
        $parts = [];
        if (! empty($mf['export_mes'])) {
            $parts[] = 'Mes exportación: '.$mf['export_mes'];
        }
        if (! empty($mf['search'])) {
            $parts[] = 'Búsqueda: '.$mf['search'];
        }
        if (! empty($mf['customer_id'])) {
            $parts[] = 'Cliente ID: '.$mf['customer_id'];
        }
        if (! empty($mf['proveedor_id'])) {
            $parts[] = 'Proveedor ID: '.$mf['proveedor_id'];
        }
        if (! empty($mf['bolsillo_ids'])) {
            $parts[] = 'Bolsillos (IDs): '.implode(',', $mf['bolsillo_ids']);
        }
        if (! empty($mf['empleado_user_ids'])) {
            $parts[] = 'Empleados (user IDs): '.implode(',', $mf['empleado_user_ids']);
        }
        if ($parts === []) {
            return 'ninguno adicional';
        }

        return implode(' · ', $parts);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Models\ComprobanteIngresoDestino>  $lineas
     */
    private function fillIngresosSheet(Worksheet $sheet, Store $store, \Illuminate\Database\Eloquent\Collection $lineas): void
    {
        $headers = [
            'Fecha y hora',
            'Comprobante',
            'Tipo',
            'Concepto',
            'Referencia línea',
            'Monto',
            'Bolsillo',
            'Cliente',
            'Usuario',
        ];
        $headerRow = 1;
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col.$headerRow, $h);
            $col++;
        }
        $this->styleHeaderRow($sheet, 'A'.$headerRow.':I'.$headerRow);

        $r = $headerRow + 1;
        if ($lineas->isEmpty()) {
            $sheet->setCellValue('A'.$r, 'No hay ingresos con los filtros aplicados.');
            $sheet->mergeCells('A'.$r.':I'.$r);

            return;
        }

        foreach ($lineas as $linea) {
            $ci = $linea->comprobanteIngreso;
            $created = $ci?->created_at;
            $fechaTxt = $created
                ? $this->storeTimezoneService->formatForStore(Carbon::parse($created), $store, true)
                : '';
            $sheet->setCellValue('A'.$r, $fechaTxt);
            $sheet->setCellValue('B'.$r, $ci?->number ?? '');
            $sheet->setCellValue('C'.$r, $this->labelTipoIngreso($ci?->type));
            $sheet->setCellValue('D'.$r, $ci?->notes ?? '');
            $sheet->setCellValue('E'.$r, $linea->reference ?? '');
            $sheet->setCellValue('F'.$r, (float) $linea->amount);
            $sheet->setCellValue('G'.$r, $linea->bolsillo?->name ?? '');
            $sheet->setCellValue('H'.$r, $ci?->customer?->name ?? '');
            $sheet->setCellValue('I'.$r, $ci?->user?->name ?? '');
            $r++;
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Models\ComprobanteEgresoOrigen>  $lineas
     */
    private function fillEgresosSheet(Worksheet $sheet, Store $store, \Illuminate\Database\Eloquent\Collection $lineas): void
    {
        $headers = [
            'Fecha y hora',
            'Comprobante',
            'Tipo',
            'Concepto',
            'Referencia línea',
            'Monto',
            'Bolsillo',
            'Beneficiario',
            'Proveedor',
            'Usuario',
        ];
        $headerRow = 1;
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col.$headerRow, $h);
            $col++;
        }
        $this->styleHeaderRow($sheet, 'A'.$headerRow.':J'.$headerRow);

        $r = $headerRow + 1;
        if ($lineas->isEmpty()) {
            $sheet->setCellValue('A'.$r, 'No hay egresos con los filtros aplicados.');
            $sheet->mergeCells('A'.$r.':J'.$r);

            return;
        }

        foreach ($lineas as $linea) {
            $ce = $linea->comprobanteEgreso;
            $created = $ce?->created_at;
            $fechaTxt = $created
                ? $this->storeTimezoneService->formatForStore(Carbon::parse($created), $store, true)
                : '';
            $sheet->setCellValue('A'.$r, $fechaTxt);
            $sheet->setCellValue('B'.$r, $ce?->number ?? '');
            $sheet->setCellValue('C'.$r, $this->labelTipoEgreso($ce?->type));
            $sheet->setCellValue('D'.$r, $ce?->notes ?? '');
            $sheet->setCellValue('E'.$r, $linea->reference ?? '');
            $sheet->setCellValue('F'.$r, (float) $linea->amount);
            $sheet->setCellValue('G'.$r, $linea->bolsillo?->name ?? '');
            $sheet->setCellValue('H'.$r, $ce?->beneficiary_name ?? '');
            $sheet->setCellValue('I'.$r, $ce?->proveedor?->nombre ?? '');
            $sheet->setCellValue('J'.$r, $ce?->user?->name ?? '');
            $r++;
        }

        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    /**
     * @param  EloquentCollection<int, \App\Models\AccountReceivable>  $cuentas
     */
    private function fillPorCobrarSheet(Worksheet $sheet, Store $store, EloquentCollection $cuentas): void
    {
        $headers = [
            'ID cuenta',
            'Cliente',
            'Factura',
            'Total',
            'Saldo',
            'Medio de pago',
            'Vencimiento',
            'Estado',
            'Registro',
        ];
        $headerRow = 1;
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col.$headerRow, $h);
            $col++;
        }
        $this->styleHeaderRow($sheet, 'A'.$headerRow.':I'.$headerRow);

        $r = $headerRow + 1;
        if ($cuentas->isEmpty()) {
            $sheet->setCellValue('A'.$r, 'No hay cuentas por cobrar en el período / filtros seleccionados.');
            $sheet->mergeCells('A'.$r.':I'.$r);

            return;
        }

        foreach ($cuentas as $ar) {
            $sheet->setCellValue('A'.$r, $ar->id);
            $sheet->setCellValue('B'.$r, $ar->customer?->name ?? '');
            $sheet->setCellValue('C'.$r, $ar->invoice_id ?? '');
            $sheet->setCellValue('D'.$r, (float) $ar->total_amount);
            $sheet->setCellValue('E'.$r, (float) $ar->balance);
            $sheet->setCellValue('F'.$r, 'Crédito');
            $sheet->setCellValue('G'.$r, $ar->due_date?->format('d/m/Y') ?? '');
            $sheet->setCellValue('H'.$r, $this->labelEstadoCxc($ar->status));
            $sheet->setCellValue('I'.$r, $ar->created_at
                ? $this->storeTimezoneService->formatForStore(Carbon::parse($ar->created_at), $store, true)
                : '');
            $r++;
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    /**
     * @param  EloquentCollection<int, \App\Models\AccountPayable>  $cuentas
     */
    private function fillPorPagarSheet(Worksheet $sheet, Store $store, EloquentCollection $cuentas): void
    {
        $headers = [
            'ID cuenta',
            'Proveedor',
            'Compra',
            'Total',
            'Saldo',
            'Medio de pago',
            'Vencimiento',
            'Estado',
            'Registro',
        ];
        $headerRow = 1;
        $col = 'A';
        foreach ($headers as $h) {
            $sheet->setCellValue($col.$headerRow, $h);
            $col++;
        }
        $this->styleHeaderRow($sheet, 'A'.$headerRow.':I'.$headerRow);

        $r = $headerRow + 1;
        if ($cuentas->isEmpty()) {
            $sheet->setCellValue('A'.$r, 'No hay CxP en el período / filtros seleccionados.');
            $sheet->mergeCells('A'.$r.':I'.$r);

            return;
        }

        foreach ($cuentas as $ap) {
            $sheet->setCellValue('A'.$r, $ap->id);
            $sheet->setCellValue('B'.$r, $ap->purchase?->proveedor?->nombre ?? '');
            $sheet->setCellValue('C'.$r, $ap->purchase_id ?? '');
            $sheet->setCellValue('D'.$r, (float) $ap->total_amount);
            $sheet->setCellValue('E'.$r, (float) $ap->balance);
            $sheet->setCellValue('F'.$r, 'Crédito proveedor');
            $sheet->setCellValue('G'.$r, $ap->due_date?->format('d/m/Y') ?? '');
            $sheet->setCellValue('H'.$r, $this->labelEstadoCxp($ap->status));
            $sheet->setCellValue('I'.$r, $ap->created_at
                ? $this->storeTimezoneService->formatForStore(Carbon::parse($ap->created_at), $store, true)
                : '');
            $r++;
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function styleHeaderRow(Worksheet $sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1F2937');
        $sheet->getStyle($range)->getFont()->getColor()->setARGB('FFF9FAFB');
    }

    private function labelTipoIngreso(?string $type): string
    {
        return match ($type) {
            ComprobanteIngreso::TYPE_PAGO_FACTURA => 'Pago factura',
            ComprobanteIngreso::TYPE_COBRO_CUENTA => 'Cobro cuenta por cobrar',
            ComprobanteIngreso::TYPE_INGRESO_MANUAL => 'Ingreso manual',
            default => $type ?? '',
        };
    }

    private function labelTipoEgreso(?string $type): string
    {
        return match ($type) {
            ComprobanteEgreso::TYPE_PAGO_CUENTA => 'Pago CxP',
            ComprobanteEgreso::TYPE_GASTO_DIRECTO => 'Gasto directo',
            ComprobanteEgreso::TYPE_MIXTO => 'Mixto',
            default => $type ?? '',
        };
    }

    private function labelEstadoCxc(?string $status): string
    {
        return match ($status) {
            AccountReceivable::STATUS_PENDIENTE => 'Pendiente',
            AccountReceivable::STATUS_PARCIAL => 'Parcial',
            AccountReceivable::STATUS_PAGADO => 'Cobrado',
            default => $status ?? '',
        };
    }

    private function labelEstadoCxp(?string $status): string
    {
        return match ($status) {
            AccountPayable::STATUS_PENDIENTE => 'Pendiente',
            AccountPayable::STATUS_PARCIAL => 'Parcial',
            AccountPayable::STATUS_PAGADO => 'Pagado',
            default => $status ?? '',
        };
    }
}
