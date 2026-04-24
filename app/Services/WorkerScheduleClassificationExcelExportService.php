<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Worker;
use App\Models\WorkerSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Excel: resumen de clasificación de horas + detalle por día (sin agrupación semanal).
 */
class WorkerScheduleClassificationExcelExportService
{
    /** @var list<array{key: string, abbr: string, label: string, cardArgb: string}> */
    private const TIPOS = [
        ['key' => 'HorasOrdinarias', 'abbr' => 'HO', 'label' => 'Horas Ordinarias (HO)', 'cardArgb' => 'FFDDEBF7'],
        ['key' => 'HorasExtrasDiurnas', 'abbr' => 'HED', 'label' => 'Hora Extra Diurna (HED)', 'cardArgb' => 'FFE2EFDA'],
        ['key' => 'HorasExtrasNocturnas', 'abbr' => 'HEN', 'label' => 'Hora Extra Nocturna (HEN)', 'cardArgb' => 'FFDCEAF2'],
        ['key' => 'HorasRecargoNocturno', 'abbr' => 'HRN', 'label' => 'Recargo Nocturno (HRN)', 'cardArgb' => 'FFFFFACD'],
        ['key' => 'HorasOrdinariasFestivas', 'abbr' => 'HROF', 'label' => 'Recargo Ordinario Festivo (HROF)', 'cardArgb' => 'FFFCE4D6'],
        ['key' => 'HorasExtrasDiurnasFestivas', 'abbr' => 'HEDF', 'label' => 'Extra Diurna Festiva (HEDF)', 'cardArgb' => 'FFF8CBAD'],
        ['key' => 'HorasExtrasNocturnasFestivas', 'abbr' => 'HENF', 'label' => 'Extra Nocturna Festiva (HENF)', 'cardArgb' => 'FFF4B084'],
        ['key' => 'HorasRecargoNocturnoFestivo', 'abbr' => 'HRNF', 'label' => 'Recargo Nocturno Festivo (HRNF)', 'cardArgb' => 'FFFCE4D6'],
        ['key' => 'HorasFestivasNoCompensa', 'abbr' => 'HFNO', 'label' => 'Horas Festivas No Compensa (HFNO)', 'cardArgb' => 'FFE7E6E6'],
    ];

    private const ZEBRA_WHITE = 'FFFFFFFF';

    private const ZEBRA_AZUL = 'FFDDEBF7';

    private const LAST_COL_LETTER = 'K';

    /**
     * @param  Collection<int, WorkerSchedule>  $schedulesInRange
     */
    public function download(
        Store $store,
        string $storeTimezone,
        Carbon $fromLocalStart,
        Carbon $toLocalEnd,
        ?Worker $filteredWorker,
        Collection $schedulesInRange,
        WorkerScheduleLiquidationService $liquidationService,
        ?array $ratesOverride = null
    ): StreamedResponse {
        $completed = $schedulesInRange->filter(fn (WorkerSchedule $s) => $s->fecha_hora_salida !== null);

        $liquidacion = $liquidationService->calcularLiquidacion($completed);
        $totalPorTipo = $liquidacion['totalHorasPorTipo'];
        $resultadosPorDia = $liquidacion['resultadosPorDia'];

        $rates = is_array($ratesOverride) && $ratesOverride !== []
            ? $ratesOverride
            : (array) config('worker_schedule_hour_rates', []);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Clasificación');

        $sheet->setCellValue('A1', 'Clasificación de Horas Trabajadas');
        $sheet->mergeCells('A1:'.self::LAST_COL_LETTER.'1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $periodoTxt = $fromLocalStart->format('d/m/Y').' — '.$toLocalEnd->format('d/m/Y');
        $sheet->setCellValue('A2', 'Período: '.$periodoTxt);
        $sheet->mergeCells('A2:'.self::LAST_COL_LETTER.'2');

        $alcance = $filteredWorker
            ? 'Trabajador: '.$filteredWorker->name
            : 'Trabajadores: todos';
        $sheet->setCellValue('A3', $alcance.' · Zona: '.$storeTimezone);
        $sheet->mergeCells('A3:'.self::LAST_COL_LETTER.'3');

        $sheet->setCellValue('A4', 'Generado: '.Carbon::now($storeTimezone)->format('d/m/Y H:i').' · Usuario: '.(Auth::user()?->name ?? '—'));
        $sheet->mergeCells('A4:'.self::LAST_COL_LETTER.'4');

        $rowCards = 6;
        $this->writeSummaryCards($sheet, $rowCards, $totalPorTipo, $rates);

        $detailTitleRow = $rowCards + 10;
        $sheet->setCellValue('A'.$detailTitleRow, 'Información Detallada por Día');
        $sheet->mergeCells('A'.$detailTitleRow.':'.self::LAST_COL_LETTER.$detailTitleRow);
        $sheet->getStyle('A'.$detailTitleRow)->getFont()->setBold(true)->setSize(12);

        $headerRow = $detailTitleRow + 1;
        $headers = ['Fecha', 'HO', 'HED', 'HEN', 'HRN', 'HROF', 'HEDF', 'HENF', 'HRNF', 'HFNO', 'Total'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).$headerRow, $h);
        }
        $sheet->getStyle('A'.$headerRow.':'.self::LAST_COL_LETTER.$headerRow)->getFont()->setBold(true);
        $sheet->getStyle('A'.$headerRow.':'.self::LAST_COL_LETTER.$headerRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF374151');
        $sheet->getStyle('A'.$headerRow.':'.self::LAST_COL_LETTER.$headerRow)->getFont()->getColor()->setARGB('FFF9FAFB');

        $dataRow = $headerRow + 1;
        $cursor = $fromLocalStart->copy()->startOfDay();
        $endDay = $toLocalEnd->copy()->startOfDay();
        $i = 0;

        while ($cursor->lte($endDay)) {
            $key = $cursor->format('Y-m-d');
            $horasDia = array_fill_keys(array_column(self::TIPOS, 'key'), 0.0);
            if (isset($resultadosPorDia[$key]['horasPorTipo'])) {
                foreach (array_keys($horasDia) as $k) {
                    $horasDia[$k] = (float) ($resultadosPorDia[$key]['horasPorTipo'][$k] ?? 0);
                }
            }

            $totalDia = array_sum($horasDia);
            $zebra = $i % 2 === 0 ? self::ZEBRA_WHITE : self::ZEBRA_AZUL;

            $sheet->setCellValue('A'.$dataRow, $cursor->format('d/m/Y'));
            foreach (self::TIPOS as $ti => $tipo) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($ti + 2).$dataRow, $this->formatHoursCell($horasDia[$tipo['key']]));
            }
            $sheet->setCellValue('K'.$dataRow, $this->formatHoursCell($totalDia));

            $sheet->getStyle('A'.$dataRow.':'.self::LAST_COL_LETTER.$dataRow)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($zebra);

            $dataRow++;
            $cursor->addDay();
            $i++;
        }

        foreach (range(1, 11) as $colIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex))->setAutoSize(true);
        }

        $filename = 'clasificacion-horas-'.$store->slug.'-'.$fromLocalStart->format('Y-m-d').'-'.$toLocalEnd->format('Y-m-d').'.xlsx';

        $this->writeJornadaSheet(
            $spreadsheet,
            $schedulesInRange,
            $storeTimezone,
            $liquidationService
        );
        $this->writePaymentDetailSheet(
            $spreadsheet,
            $filteredWorker,
            $fromLocalStart,
            $toLocalEnd,
            $totalPorTipo,
            (float) ($liquidacion['totalHorasTrabajadas'] ?? 0),
            $rates,
            $storeTimezone
        );

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<string, float>  $totalPorTipo
     * @param  array<string, float>  $rates
     */
    private function writeSummaryCards(Worksheet $sheet, int $startRow, array $totalPorTipo, array $rates): void
    {
        $idx = 0;
        foreach (self::TIPOS as $tipo) {
            $cardRow = $startRow + (int) floor($idx / 3) * 3;
            $colOffset = ($idx % 3) * 4;
            $c1 = Coordinate::stringFromColumnIndex(1 + $colOffset);
            $c2 = Coordinate::stringFromColumnIndex(4 + $colOffset);

            $horas = round((float) ($totalPorTipo[$tipo['key']] ?? 0), 2);
            $rate = (float) ($rates[$tipo['key']] ?? 0);
            $costo = $horas * $rate;

            $sheet->mergeCells($c1.$cardRow.':'.$c2.$cardRow);
            $sheet->setCellValue($c1.$cardRow, $tipo['label']);
            $sheet->getStyle($c1.$cardRow.':'.$c2.$cardRow)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($tipo['cardArgb']);
            $sheet->getStyle($c1.$cardRow.':'.$c2.$cardRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                ->setVertical(Alignment::VERTICAL_CENTER)
                ->setWrapText(true);

            $sheet->mergeCells($c1.($cardRow + 1).':'.$c2.($cardRow + 1));
            $sheet->setCellValue($c1.($cardRow + 1), $this->formatHoursPlain($horas).'h');
            $sheet->getStyle($c1.($cardRow + 1))->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle($c1.($cardRow + 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $sheet->mergeCells($c1.($cardRow + 2).':'.$c2.($cardRow + 2));
            $sheet->setCellValue($c1.($cardRow + 2), $this->formatMoneyCOP($costo));
            $sheet->getStyle($c1.($cardRow + 2))->getFont()->getColor()->setARGB('FF16A34A');
            $sheet->getStyle($c1.($cardRow + 2))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $idx++;
        }
    }

    /**
     * Hoja 2: detalle por jornada (una fila por registro de horario).
     *
     * @param  Collection<int, WorkerSchedule>  $schedulesInRange
     */
    private function writeJornadaSheet(
        Spreadsheet $spreadsheet,
        Collection $schedulesInRange,
        string $storeTimezone,
        WorkerScheduleLiquidationService $liquidationService
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Detalle por jornada');

        $headers = [
            'Fecha',
            'Entrada',
            'Salida',
            'Tipo Día',
            'Horas Trabajadas',
            'Estado',
            'HO',
            'HED',
            'HEN',
            'HRN',
            'HROF',
            'HEDF',
            'HENF',
            'HRNF',
            'HFNO',
            'Observación',
        ];

        $sheet->setCellValue('A1', 'Información Detallada por Jornada');
        $sheet->mergeCells('A1:P1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        foreach ($headers as $i => $h) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($i + 1).'3', $h);
        }
        $sheet->getStyle('A3:P3')->getFont()->setBold(true);
        $sheet->getStyle('A3:P3')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF374151');
        $sheet->getStyle('A3:P3')->getFont()->getColor()->setARGB('FFF9FAFB');

        $row = 4;
        $dateGroupIndex = -1;
        $previousWorkerId = null;
        $previousSalida = null;
        $completedSchedules = $schedulesInRange
            ->filter(fn (WorkerSchedule $s) => $s->fecha_hora_entrada !== null && $s->fecha_hora_salida !== null)
            ->values();
        $liquidacionAll = $liquidationService->calcularLiquidacion($completedSchedules);
        $resultadosPorDia = $liquidacionAll['resultadosPorDia'] ?? [];
        $bloquesPorDia = [];

        foreach ($resultadosPorDia as $fechaDia => $resultadoDia) {
            $bloques = [];
            foreach (($resultadoDia['detalles'] ?? []) as $detalle) {
                $inicio = Carbon::createFromFormat('Y-m-d H:i', (string) ($detalle['inicio'] ?? ''), $storeTimezone);
                $fin = Carbon::createFromFormat('Y-m-d H:i', (string) ($detalle['fin'] ?? ''), $storeTimezone);
                if (! $inicio || ! $fin || $fin->lte($inicio)) {
                    continue;
                }
                $bloques[] = [
                    'inicio' => $inicio,
                    'fin' => $fin,
                    'tipo' => (string) ($detalle['tipo'] ?? ''),
                ];
            }
            $bloquesPorDia[$fechaDia] = $bloques;
        }

        /** @var WorkerSchedule $schedule */
        foreach ($schedulesInRange
            ->filter(fn (WorkerSchedule $s) => $s->fecha_hora_entrada !== null)
            ->sortBy('fecha_hora_entrada') as $schedule) {
            $entrada = $schedule->fecha_hora_entrada?->copy()->timezone($storeTimezone);
            $salida = $schedule->fecha_hora_salida?->copy()->timezone($storeTimezone);
            $estado = $salida ? 'Completo' : 'Pendiente';

            $hoursByType = array_fill_keys(array_column(self::TIPOS, 'key'), 0.0);
            if ($salida) {
                $bloquesJornada = [];
                $diaCursor = $entrada->copy()->startOfDay();
                $ultimoDia = $salida->copy()->startOfDay();
                while ($diaCursor->lte($ultimoDia)) {
                    $fechaDia = $diaCursor->format('Y-m-d');
                    foreach (($bloquesPorDia[$fechaDia] ?? []) as $bloqueDia) {
                        $bloquesJornada[] = $bloqueDia;
                    }
                    $diaCursor->addDay();
                }

                foreach ($bloquesJornada as $bloque) {
                    $inicioSolape = $bloque['inicio']->gt($entrada) ? $bloque['inicio'] : $entrada;
                    $finSolape = $bloque['fin']->lt($salida) ? $bloque['fin'] : $salida;
                    if ($finSolape->lte($inicioSolape)) {
                        continue;
                    }

                    $tipo = (string) ($bloque['tipo'] ?? '');
                    if (! array_key_exists($tipo, $hoursByType)) {
                        continue;
                    }

                    $hoursByType[$tipo] += $inicioSolape->diffInMinutes($finSolape) / 60;
                }
            }

            $tipoDia = $schedule->es_domingo
                ? 'Domingo'
                : ($schedule->es_festivo || $schedule->es_festivo2 ? 'Festivo' : 'Normal');

            $horasTrabajadas = $entrada && $salida
                ? $entrada->diffInMinutes($salida) / 60
                : 0.0;

            $sheet->setCellValue('A'.$row, $entrada?->format('d/m/Y'));
            $sheet->setCellValue('B'.$row, $entrada?->format('H:i'));
            $sheet->setCellValue('C'.$row, $salida?->format('H:i') ?? '—');
            $sheet->setCellValue('D'.$row, $tipoDia);
            $sheet->setCellValue('E'.$row, $this->formatHoursCell($horasTrabajadas));
            $sheet->setCellValue('F'.$row, $estado);

            foreach (self::TIPOS as $ti => $tipo) {
                $sheet->setCellValue(Coordinate::stringFromColumnIndex($ti + 7).$row, $this->formatHoursCell(round((float) $hoursByType[$tipo['key']], 2)));
            }

            $sheet->setCellValue('P'.$row, (string) ($schedule->observaciones ?? ''));

            $startsNewVisualGroup = false;
            if ($dateGroupIndex < 0) {
                $startsNewVisualGroup = true;
            } elseif ($previousWorkerId !== $schedule->worker_id) {
                $startsNewVisualGroup = true;
            } elseif (! $previousSalida || ! $entrada) {
                $startsNewVisualGroup = true;
            } else {
                $minutesBetweenSchedules = $previousSalida->diffInMinutes($entrada, false);
                $isContinuous = $minutesBetweenSchedules >= 0
                    && $minutesBetweenSchedules < WorkerScheduleLiquidationService::MAX_PAUSA_CONTINUIDAD_MINUTOS;
                $startsNewVisualGroup = ! $isContinuous;
            }

            if ($startsNewVisualGroup) {
                $dateGroupIndex++;
            }
            $zebra = $dateGroupIndex % 2 === 0 ? self::ZEBRA_WHITE : self::ZEBRA_AZUL;
            $sheet->getStyle('A'.$row.':P'.$row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($zebra);

            $previousWorkerId = $schedule->worker_id;
            $previousSalida = $salida;
            $row++;
        }

        foreach (range(1, 16) as $colIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex))->setAutoSize(true);
        }
    }

    /**
     * Hoja 3: detalle de pago informativo.
     *
     * @param  array<string, float>  $totalPorTipo
     * @param  array<string, float>  $rates
     */
    private function writePaymentDetailSheet(
        Spreadsheet $spreadsheet,
        ?Worker $filteredWorker,
        Carbon $fromLocalStart,
        Carbon $toLocalEnd,
        array $totalPorTipo,
        float $totalHorasTrabajadas,
        array $rates,
        string $storeTimezone
    ): void {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Detalle de pago');

        $workerLabel = $filteredWorker?->name ?? 'Todos los trabajadores';
        $periodoTxt = $fromLocalStart->format('d/m/Y').' - '.$toLocalEnd->format('d/m/Y');
        $fechaCorteTxt = $toLocalEnd->copy()->timezone($storeTimezone)->format('d/m/Y');

        $costByType = [];
        foreach (self::TIPOS as $tipo) {
            $key = $tipo['key'];
            $hours = (float) ($totalPorTipo[$key] ?? 0);
            $rate = (float) ($rates[$key] ?? 0);
            $costByType[$key] = $hours * $rate;
        }

        $sumByKeys = function (array $source, array $keys): float {
            $sum = 0.0;
            foreach ($keys as $key) {
                $sum += (float) ($source[$key] ?? 0);
            }

            return $sum;
        };

        $horasOrdinarias = (float) ($totalPorTipo['HorasOrdinarias'] ?? 0);
        $valorOrdinarias = (float) ($costByType['HorasOrdinarias'] ?? 0);

        $extrasKeys = [
            'HorasExtrasDiurnas',
            'HorasExtrasNocturnas',
            'HorasExtrasDiurnasFestivas',
            'HorasExtrasNocturnasFestivas',
        ];
        $recargosKeys = [
            'HorasRecargoNocturno',
            'HorasOrdinariasFestivas',
            'HorasRecargoNocturnoFestivo',
            'HorasFestivasNoCompensa',
        ];

        $horasExtras = $sumByKeys($totalPorTipo, $extrasKeys);
        $valorExtras = $sumByKeys($costByType, $extrasKeys);
        $horasRecargos = $sumByKeys($totalPorTipo, $recargosKeys);
        $valorRecargos = $sumByKeys($costByType, $recargosKeys);
        $totalPagar = array_sum($costByType);

        $sheet->setCellValue('A1', 'Comprobante de pago de nomina (informativo)');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1:H1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF1F4E78');
        $sheet->getStyle('A1:H1')->getFont()->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle('A1:H1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Documento informativo generado desde clasificacion de horarios.');
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2:H2')->getFont()->getColor()->setARGB('FF6B7280');

        $sheet->setCellValue('A4', 'Trabajador');
        $sheet->mergeCells('A4:C4');
        $sheet->setCellValue('D4', $workerLabel);
        $sheet->mergeCells('D4:H4');

        $sheet->setCellValue('A5', 'Fecha de corte');
        $sheet->mergeCells('A5:C5');
        $sheet->setCellValue('D5', $fechaCorteTxt);
        $sheet->mergeCells('D5:H5');

        $sheet->setCellValue('A6', 'Periodo liquidado');
        $sheet->mergeCells('A6:C6');
        $sheet->setCellValue('D6', $periodoTxt);
        $sheet->mergeCells('D6:H6');

        $sheet->setCellValue('A7', 'Total de horas trabajadas');
        $sheet->mergeCells('A7:C7');
        $sheet->setCellValue('D7', $this->formatHoursPlain($totalHorasTrabajadas).'h');
        $sheet->mergeCells('D7:H7');

        $sheet->setCellValue('A8', 'Total a pagar');
        $sheet->mergeCells('A8:C8');
        $sheet->setCellValue('D8', $this->formatMoneyCOP($totalPagar));
        $sheet->mergeCells('D8:H8');
        $sheet->getStyle('A8:H8')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE2F0D9');
        $sheet->getStyle('D8')->getFont()->setBold(true)->setSize(12);

        $sheet->getStyle('A4:C8')->getFont()->setBold(true);
        $sheet->getStyle('A4:H8')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->setCellValue('A10', 'Concepto');
        $sheet->setCellValue('B10', 'Horas');
        $sheet->setCellValue('C10', 'Valor');
        $sheet->mergeCells('C10:H10');
        $sheet->getStyle('A10:H10')->getFont()->setBold(true);
        $sheet->getStyle('A10:H10')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF374151');
        $sheet->getStyle('A10:H10')->getFont()->getColor()->setARGB('FFF9FAFB');

        $rows = [
            ['concepto' => 'Horas Ordinarias', 'horas' => $horasOrdinarias, 'valor' => $valorOrdinarias],
            ['concepto' => 'Horas Extras', 'horas' => $horasExtras, 'valor' => $valorExtras],
            ['concepto' => 'Recargos (incluye Festivas No Compensa)', 'horas' => $horasRecargos, 'valor' => $valorRecargos],
        ];

        $row = 11;
        foreach ($rows as $index => $item) {
            $zebra = $index % 2 === 0 ? self::ZEBRA_WHITE : self::ZEBRA_AZUL;
            $sheet->setCellValue('A'.$row, $item['concepto']);
            $sheet->setCellValue('B'.$row, $this->formatHoursPlain((float) $item['horas']).'h');
            $sheet->setCellValue('C'.$row, $this->formatMoneyCOP((float) $item['valor']));
            $sheet->mergeCells('C'.$row.':H'.$row);
            $sheet->getStyle('A'.$row.':H'.$row)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB($zebra);
            $row++;
        }

        $sheet->setCellValue('A14', 'Nota: este reporte es informativo y no sustituye un comprobante legal de nomina.');
        $sheet->mergeCells('A14:H14');
        $sheet->getStyle('A14')->getFont()->getColor()->setARGB('FF6B7280');

        foreach (range(1, 8) as $colIndex) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($colIndex))->setAutoSize(true);
        }
    }

    private function formatHoursCell(float $hours): string
    {
        return $this->formatHoursPlain($hours).'h';
    }

    private function formatHoursPlain(float $hours): string
    {
        return number_format(round($hours, 2), 2, '.', '');
    }

    private function formatMoneyCOP(float $amount): string
    {
        return '$'.number_format($amount, 2, ',', '.');
    }
}
