<?php

/**
 * Valor hora por tipo (misma clave que WorkerScheduleLiquidationService).
 * Usado en la exportación Excel de clasificación. Configura vía .env o valores aquí.
 */
return [
    'HorasOrdinarias' => (float) env('WORKER_RATE_HO', 0),
    'HorasExtrasDiurnas' => (float) env('WORKER_RATE_HED', 0),
    'HorasExtrasNocturnas' => (float) env('WORKER_RATE_HEN', 0),
    'HorasRecargoNocturno' => (float) env('WORKER_RATE_HRN', 0),
    'HorasOrdinariasFestivas' => (float) env('WORKER_RATE_HROF', 0),
    'HorasExtrasDiurnasFestivas' => (float) env('WORKER_RATE_HEDF', 0),
    'HorasExtrasNocturnasFestivas' => (float) env('WORKER_RATE_HENF', 0),
    'HorasRecargoNocturnoFestivo' => (float) env('WORKER_RATE_HRNF', 0),
    'HorasFestivasNoCompensa' => (float) env('WORKER_RATE_HFNO', 0),
];
