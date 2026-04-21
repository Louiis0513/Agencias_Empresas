<?php

namespace App\Services;

use App\Models\WorkerSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Motor de liquidación por tipo de hora (port de counter-tools LiquidacionService) sobre {@see WorkerSchedule}.
 */
class WorkerScheduleLiquidationService
{
    const HORA_INICIO_NOCTURNO = 21;

    const HORA_FIN_NOCTURNO = 5;

    const HORAS_ORDINARIAS_DIARIAS = 7.333333;

    const HORAS_ORDINARIAS_SEMANALES = 44;

    /**
     * Si la pausa entre salida y siguiente entrada es menor a este umbral,
     * ambos turnos hacen parte de la misma jornada continua.
     */
    const MAX_PAUSA_CONTINUIDAD_MINUTOS = 240;

    /**
     * @return array{
     *   resultadosPorDia: array,
     *   resultadosPorSemana: array,
     *   horasOrdinariasSemanales: array,
     *   totalHorasPorTipo: array,
     *   totalHorasTrabajadas: float
     * }
     */
    public function calcularLiquidacion(Collection $schedules, string $periodo = 'mensual'): array
    {
        $totalHorasPorTipo = [
            'HorasOrdinarias' => 0,
            'HorasExtrasDiurnas' => 0,
            'HorasExtrasNocturnas' => 0,
            'HorasRecargoNocturno' => 0,
            'HorasOrdinariasFestivas' => 0,
            'HorasExtrasDiurnasFestivas' => 0,
            'HorasExtrasNocturnasFestivas' => 0,
            'HorasRecargoNocturnoFestivo' => 0,
            'HorasFestivasNoCompensa' => 0,
        ];

        $resultadosPorDia = [];

        $schedulesPorDia = [];
        foreach ($schedules as $schedule) {
            if (! $schedule->fecha_hora_entrada) {
                continue;
            }

            $fecha = $schedule->fecha_hora_entrada->format('Y-m-d');
            $schedulesPorDia[$fecha][] = $schedule;
        }

        foreach ($schedulesPorDia as $fecha => $delDia) {
            $rangosTrabajados = $this->obtenerRangosTrabajados($delDia);
            $gruposRangos = $this->agruparRangosContinuos($rangosTrabajados);

            $this->inicializarResultadoDia($resultadosPorDia, $fecha);

            foreach ($gruposRangos as $grupoRangos) {
                $this->elDiscriminador($grupoRangos, $resultadosPorDia);
            }
        }

        ksort($resultadosPorDia);
        foreach ($resultadosPorDia as $resultadoDia) {
            foreach ($resultadoDia['horasPorTipo'] as $tipo => $valor) {
                $totalHorasPorTipo[$tipo] += $valor;
            }
        }

        $resultadosPorSemana = $this->agruparPorSemanas($resultadosPorDia);
        $horasOrdinariasSemanales = $this->calcularHorasOrdinariasSemanales($resultadosPorSemana);
        $totalHorasTrabajadas = array_sum($totalHorasPorTipo);

        return [
            'resultadosPorDia' => $resultadosPorDia,
            'resultadosPorSemana' => $resultadosPorSemana,
            'horasOrdinariasSemanales' => $horasOrdinariasSemanales,
            'totalHorasPorTipo' => array_map(fn ($v) => round($v, 2), $totalHorasPorTipo),
            'totalHorasTrabajadas' => round($totalHorasTrabajadas, 2),
        ];
    }

    /**
     * @param  array<int, array{entrada: Carbon, salida: Carbon, esContinuo: bool, schedule: WorkerSchedule}>  $grupoRangos
     * @param  array<string, array{
     *   horasPorTipo: array<string, float|int>,
     *   esDomingo: bool,
     *   esFestivo: bool,
     *   detalles: array<int, array{inicio: string, fin: string, tipo: string, horas: float}>
     * }>  $resultadosPorDia
     */
    private function elDiscriminador(array $grupoRangos, array &$resultadosPorDia): void
    {
        $horasAcumuladasJornada = 0;

        foreach ($grupoRangos as $rango) {
            $entrada = $rango['entrada'];
            $salida = $rango['salida'];
            $schedule = $rango['schedule'];

            $puntosDeCorte = [];

            $horasRestantesParaExtras = self::HORAS_ORDINARIAS_DIARIAS - $horasAcumuladasJornada;

            if ($horasRestantesParaExtras > 0) {
                $minutosRestantes = round($horasRestantesParaExtras * 60);
                $momentoExactoExtras = $entrada->copy()->addMinutes($minutosRestantes);

                if ($momentoExactoExtras->gt($entrada) && $momentoExactoExtras->lt($salida)) {
                    $puntosDeCorte[] = $momentoExactoExtras;
                }
            }

            $fronteras = $this->obtenerFronterasRelevantes($entrada, $salida);
            foreach ($fronteras as $frontera) {
                $puntosDeCorte[] = $frontera;
            }

            $puntosDeCorte = collect($puntosDeCorte)
                ->unique(fn ($d) => $d->timestamp)
                ->sort(fn ($a, $b) => $a->timestamp <=> $b->timestamp)
                ->values();

            $puntosDeCorte->push($salida);

            $cursor = $entrada->copy();

            foreach ($puntosDeCorte as $corte) {
                if ($corte->lte($cursor)) {
                    continue;
                }

                $inicioBloque = $cursor;
                $finBloque = $corte;
                $duracion = $inicioBloque->diffInMinutes($finBloque) / 60;

                $esExtra = ($horasAcumuladasJornada >= (self::HORAS_ORDINARIAS_DIARIAS - 0.001));

                $puntoMedio = $inicioBloque->copy()->addMinutes($inicioBloque->diffInMinutes($finBloque) / 2);
                $esNocturno = $this->esHoraNocturna($puntoMedio);

                $esFestivo = $this->esFechaFestivo($inicioBloque, $schedule);

                $noCompensaSemanaSiguiente = $esFestivo && (bool) $schedule->no_compensa_semana_siguiente;

                $tipoHora = $this->determinarTipoHora($esExtra, $esNocturno, $esFestivo, $noCompensaSemanaSiguiente);

                $claveDiaBloque = $inicioBloque->format('Y-m-d');
                $this->inicializarResultadoDia($resultadosPorDia, $claveDiaBloque);
                $resultadosPorDia[$claveDiaBloque]['horasPorTipo'][$tipoHora] += $duracion;
                if ($esFestivo) {
                    $resultadosPorDia[$claveDiaBloque]['esFestivo'] = true;
                }

                $resultadosPorDia[$claveDiaBloque]['detalles'][] = [
                    'inicio' => $inicioBloque->format('Y-m-d H:i'),
                    'fin' => $finBloque->format('Y-m-d H:i'),
                    'tipo' => $tipoHora,
                    'horas' => round($duracion, 2),
                ];

                $horasAcumuladasJornada += $duracion;
                $cursor = $corte;
            }
        }
    }

    /**
     * @param  array<string, array{
     *   horasPorTipo: array<string, float|int>,
     *   esDomingo: bool,
     *   esFestivo: bool,
     *   detalles: array<int, array{inicio: string, fin: string, tipo: string, horas: float}>
     * }>  $resultadosPorDia
     */
    private function inicializarResultadoDia(array &$resultadosPorDia, string $fecha): void
    {
        if (isset($resultadosPorDia[$fecha])) {
            return;
        }

        $fechaCarbon = Carbon::parse($fecha);
        $resultadosPorDia[$fecha] = [
            'horasPorTipo' => [
                'HorasOrdinarias' => 0,
                'HorasExtrasDiurnas' => 0,
                'HorasExtrasNocturnas' => 0,
                'HorasRecargoNocturno' => 0,
                'HorasOrdinariasFestivas' => 0,
                'HorasExtrasDiurnasFestivas' => 0,
                'HorasExtrasNocturnasFestivas' => 0,
                'HorasRecargoNocturnoFestivo' => 0,
                'HorasFestivasNoCompensa' => 0,
            ],
            'esDomingo' => $fechaCarbon->dayOfWeek === 0,
            'esFestivo' => false,
            'detalles' => [],
        ];
    }

    private function obtenerFronterasRelevantes(Carbon $entrada, Carbon $salida): array
    {
        $fronteras = [];
        $inicioDia = $entrada->copy()->startOfDay();

        $candidatos = [
            $inicioDia->copy()->setTime(6, 0, 0),
            $inicioDia->copy()->addHours(self::HORA_INICIO_NOCTURNO),
            $inicioDia->copy()->addDay()->startOfDay(),
            $inicioDia->copy()->addDay()->setTime(6, 0, 0),
            $inicioDia->copy()->addDay()->addHours(self::HORA_INICIO_NOCTURNO),
        ];

        foreach ($candidatos as $candidato) {
            if ($candidato->gt($entrada) && $candidato->lt($salida)) {
                $fronteras[] = $candidato;
            }
        }

        return $fronteras;
    }

    private function determinarTipoHora(bool $esExtra, bool $esNocturno, bool $esFestivo, bool $noCompensaSemanaSiguiente = false): string
    {
        if ($esFestivo && $noCompensaSemanaSiguiente) {
            return 'HorasFestivasNoCompensa';
        }

        if ($esFestivo) {
            if ($esExtra) {
                return $esNocturno ? 'HorasExtrasNocturnasFestivas' : 'HorasExtrasDiurnasFestivas';
            }

            return $esNocturno ? 'HorasRecargoNocturnoFestivo' : 'HorasOrdinariasFestivas';
        }

        if ($esExtra) {
            return $esNocturno ? 'HorasExtrasNocturnas' : 'HorasExtrasDiurnas';
        }

        return $esNocturno ? 'HorasRecargoNocturno' : 'HorasOrdinarias';
    }

    /**
     * @param  array<int, WorkerSchedule>  $schedules
     * @return array<int, array{entrada: Carbon, salida: Carbon, esContinuo: bool, schedule: WorkerSchedule}>
     */
    private function obtenerRangosTrabajados(array $schedules): array
    {
        if (empty($schedules)) {
            return [];
        }

        usort($schedules, fn ($a, $b) => $a->fecha_hora_entrada->timestamp <=> $b->fecha_hora_entrada->timestamp);

        $rangos = [];

        foreach ($schedules as $index => $schedule) {
            $entrada = $schedule->fecha_hora_entrada;
            $salida = $schedule->fecha_hora_salida;

            if (! $entrada || ! $salida) {
                continue;
            }

            $esContinuo = false;
            if ($index > 0) {
                $salidaAnterior = $schedules[$index - 1]->fecha_hora_salida;
                $minutosEntreTurnos = $salidaAnterior?->diffInMinutes($entrada, false);
                if ($minutosEntreTurnos !== null && $minutosEntreTurnos >= 0 && $minutosEntreTurnos < self::MAX_PAUSA_CONTINUIDAD_MINUTOS) {
                    $esContinuo = true;
                }
            }

            $rangos[] = [
                'entrada' => $entrada,
                'salida' => $salida,
                'esContinuo' => $esContinuo,
                'schedule' => $schedule,
            ];
        }

        return $rangos;
    }

    /**
     * @param  array<int, array{entrada: Carbon, salida: Carbon, esContinuo: bool, schedule: WorkerSchedule}>  $rangos
     * @return array<int, array<int, array{entrada: Carbon, salida: Carbon, esContinuo: bool, schedule: WorkerSchedule}>>
     */
    private function agruparRangosContinuos(array $rangos): array
    {
        if (empty($rangos)) {
            return [];
        }

        $grupos = [];
        $actual = [];

        foreach ($rangos as $rango) {
            if ($rango['esContinuo'] && ! empty($actual)) {
                $actual[] = $rango;
            } else {
                if (! empty($actual)) {
                    $grupos[] = $actual;
                }
                $actual = [$rango];
            }
        }

        if (! empty($actual)) {
            $grupos[] = $actual;
        }

        return $grupos;
    }

    private function esHoraNocturna(Carbon $fechaHora): bool
    {
        $hora = $fechaHora->hour;

        return $hora >= self::HORA_INICIO_NOCTURNO || $hora < 6;
    }

    private function esFechaFestivo(Carbon $fecha, WorkerSchedule $schedule): bool
    {
        if ($fecha->dayOfWeek === 0) {
            return true;
        }

        $fechaEntrada = $schedule->fecha_hora_entrada->format('Y-m-d');
        $fechaSalida = $schedule->fecha_hora_salida?->format('Y-m-d');
        $fechaActual = $fecha->format('Y-m-d');

        if ($fechaActual === $fechaEntrada) {
            return (bool) $schedule->es_festivo;
        }

        if ($fechaSalida !== null && $fechaActual === $fechaSalida && $fechaSalida !== $fechaEntrada) {
            return (bool) $schedule->es_festivo2;
        }

        return false;
    }

    private function agruparPorSemanas(array $resultadosPorDia): array
    {
        $semanas = [];

        foreach ($resultadosPorDia as $fecha => $resultadoDia) {
            $fechaCarbon = Carbon::parse($fecha);
            $inicioSemana = $fechaCarbon->copy()->startOfWeek(Carbon::MONDAY);
            $finSemana = $fechaCarbon->copy()->endOfWeek(Carbon::SUNDAY);

            $claveSemana = $inicioSemana->format('Y-m-d');

            if (! isset($semanas[$claveSemana])) {
                $semanas[$claveSemana] = [
                    'inicioSemana' => $claveSemana,
                    'finSemana' => $finSemana->format('Y-m-d'),
                    'horasPorTipo' => [
                        'HorasOrdinarias' => 0,
                        'HorasExtrasDiurnas' => 0,
                        'HorasExtrasNocturnas' => 0,
                        'HorasRecargoNocturno' => 0,
                        'HorasOrdinariasFestivas' => 0,
                        'HorasExtrasDiurnasFestivas' => 0,
                        'HorasExtrasNocturnasFestivas' => 0,
                        'HorasRecargoNocturnoFestivo' => 0,
                        'HorasFestivasNoCompensa' => 0,
                    ],
                    'totalHorasTrabajadas' => 0,
                ];
            }

            foreach ($resultadoDia['horasPorTipo'] as $tipo => $valor) {
                $semanas[$claveSemana]['horasPorTipo'][$tipo] += $valor;
            }
        }

        foreach ($semanas as &$semana) {
            $semana['totalHorasTrabajadas'] = array_sum($semana['horasPorTipo']);
        }

        ksort($semanas);

        return array_values($semanas);
    }

    private function calcularHorasOrdinariasSemanales(array $resultadosPorSemana): array
    {
        $horasSemanales = [];

        foreach ($resultadosPorSemana as $semana) {
            $horasOrdinarias = $semana['horasPorTipo']['HorasOrdinarias'] ?? 0;
            $completo = $horasOrdinarias >= self::HORAS_ORDINARIAS_SEMANALES;

            $horasSemanales[] = [
                'inicioSemana' => $semana['inicioSemana'],
                'horasOrdinarias' => round($horasOrdinarias, 2),
                'completo' => $completo,
            ];
        }

        return $horasSemanales;
    }
}
