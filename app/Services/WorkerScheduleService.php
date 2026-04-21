<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Worker;
use App\Models\WorkerSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class WorkerScheduleService
{
    public function __construct(
        private StoreTimezoneService $storeTimezoneService
    ) {}

    /**
     * Interpreta un datetime-local en la zona de la tienda y lo devuelve en UTC para persistir.
     */
    public function parseDateTimeInStoreTz(Store $store, string $value): Carbon
    {
        $tz = $this->storeTimezoneService->getTimezoneForStore($store);
        // El módulo de horarios trabaja y muestra hora local de tienda end-to-end.
        // Guardar en UTC aquí genera un corrimiento visual al re-editar.
        $parsedUtc = Carbon::parse($value, $tz);

        // #region agent log
        $this->debugLog('run-pre-fix-1', 'H1', 'WorkerScheduleService.php:parseDateTimeInStoreTz', 'Parse datetime input', [
            'store_id' => $store->id,
            'store_tz' => $tz,
            'app_tz' => (string) config('app.timezone'),
            'php_tz' => date_default_timezone_get(),
            'input' => $value,
            'parsed_for_db' => $parsedUtc->format('Y-m-d H:i:s'),
        ]);
        // #endregion

        return $parsedUtc;
    }

    /**
     * Lista horarios de la tienda en un rango (fechas en TZ tienda).
     */
    public function schedulesForStoreInRange(Store $store, Carbon $fromUtc, Carbon $toUtc)
    {
        return WorkerSchedule::query()
            ->where('store_id', $store->id)
            ->whereBetween('fecha_hora_entrada', [$fromUtc, $toUtc])
            ->with(['worker.role'])
            ->orderBy('fecha_hora_entrada')
            ->get();
    }

    /**
     * Crea un registro de horario con las mismas reglas que counter-tools (HorarioController@store).
     *
     * @param  array{worker_id:int,fecha_hora_entrada:string,fecha_hora_salida?:string|null,es_festivo?:bool|string,es_festivo2?:bool|string,no_compensa_semana_siguiente?:bool|string,observaciones?:string|null}  $data
     */
    public function create(Store $store, array $data, int $userId): WorkerSchedule
    {
        $this->validatePayload($store, $data, null);

        $fechaHoraEntrada = $this->parseDateTimeInStoreTz($store, $data['fecha_hora_entrada']);
        $fechaHoraSalida = ! empty($data['fecha_hora_salida'])
            ? $this->parseDateTimeInStoreTz($store, $data['fecha_hora_salida'])
            : null;

        $this->assertSalidaAfterEntrada($fechaHoraEntrada, $fechaHoraSalida);
        $this->assertNoOverlap($store, (int) $data['worker_id'], $fechaHoraEntrada, $fechaHoraSalida, null);

        $esDomingo = $this->isSundayInStoreTz($store, $fechaHoraEntrada);
        $esFestivo = $this->toBool($data['es_festivo'] ?? false);
        $esFestivo2Input = $this->toBool($data['es_festivo2'] ?? false);
        $noCompensaInput = $this->toBool($data['no_compensa_semana_siguiente'] ?? false);

        $cruzaDia = $fechaHoraSalida
            && $this->dateYmdInStoreTz($store, $fechaHoraEntrada) !== $this->dateYmdInStoreTz($store, $fechaHoraSalida);

        $esFestivo2 = $this->normalizeEsFestivo2($esFestivo2Input, $fechaHoraSalida, $cruzaDia);

        if ($esDomingo && $esFestivo) {
            throw ValidationException::withMessages([
                'es_festivo' => 'No se puede marcar como día festivo si la fecha de entrada es domingo.',
            ]);
        }

        if ($noCompensaInput && ! $esDomingo && ! $esFestivo) {
            throw ValidationException::withMessages([
                'no_compensa_semana_siguiente' => 'La opción "No compensa dentro de la semana siguiente" solo aplica para domingos o días festivos (día de entrada).',
            ]);
        }

        $saved = WorkerSchedule::create([
            'store_id' => $store->id,
            'worker_id' => (int) $data['worker_id'],
            'fecha_hora_entrada' => $fechaHoraEntrada,
            'fecha_hora_salida' => $fechaHoraSalida,
            'es_festivo' => $esFestivo,
            'es_festivo2' => $esFestivo2,
            'es_domingo' => $esDomingo,
            'no_compensa_semana_siguiente' => $noCompensaInput,
            'registered_by' => $userId,
            'observaciones' => $data['observaciones'] ?? null,
        ]);

        // #region agent log
        $this->debugLog('run-pre-fix-1', 'H2', 'WorkerScheduleService.php:create', 'Created worker schedule', [
            'id' => $saved->id,
            'store_id' => $saved->store_id,
            'worker_id' => $saved->worker_id,
            'entrada_attr_raw' => (string) $saved->getRawOriginal('fecha_hora_entrada'),
            'entrada_cast' => $saved->fecha_hora_entrada?->format('Y-m-d H:i:s'),
            'entrada_as_store_tz' => $saved->fecha_hora_entrada?->copy()->timezone($this->storeTimezoneService->getTimezoneForStore($store))->format('Y-m-d H:i:s'),
            'salida_attr_raw' => (string) $saved->getRawOriginal('fecha_hora_salida'),
            'salida_cast' => $saved->fecha_hora_salida?->format('Y-m-d H:i:s'),
            'app_tz' => (string) config('app.timezone'),
        ]);
        // #endregion

        return $saved;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Store $store, WorkerSchedule $schedule, array $data, int $userId): WorkerSchedule
    {
        if ($schedule->store_id !== $store->id) {
            abort(404);
        }

        $this->validatePayload($store, $data, $schedule->id);

        $fechaHoraEntrada = $this->parseDateTimeInStoreTz($store, $data['fecha_hora_entrada']);
        $fechaHoraSalida = ! empty($data['fecha_hora_salida'])
            ? $this->parseDateTimeInStoreTz($store, $data['fecha_hora_salida'])
            : null;

        $this->assertSalidaAfterEntrada($fechaHoraEntrada, $fechaHoraSalida);
        $this->assertNoOverlap($store, (int) $data['worker_id'], $fechaHoraEntrada, $fechaHoraSalida, $schedule->id);

        $esDomingo = $this->isSundayInStoreTz($store, $fechaHoraEntrada);
        $esFestivo = $this->toBool($data['es_festivo'] ?? false);
        $esFestivo2Input = $this->toBool($data['es_festivo2'] ?? false);
        $noCompensaInput = $this->toBool($data['no_compensa_semana_siguiente'] ?? false);

        $cruzaDia = $fechaHoraSalida
            && $this->dateYmdInStoreTz($store, $fechaHoraEntrada) !== $this->dateYmdInStoreTz($store, $fechaHoraSalida);

        $esFestivo2 = $this->normalizeEsFestivo2($esFestivo2Input, $fechaHoraSalida, $cruzaDia);

        if ($esDomingo && $esFestivo) {
            throw ValidationException::withMessages([
                'es_festivo' => 'No se puede marcar como día festivo si la fecha de entrada es domingo.',
            ]);
        }

        if ($noCompensaInput && ! $esDomingo && ! $esFestivo) {
            throw ValidationException::withMessages([
                'no_compensa_semana_siguiente' => 'La opción "No compensa dentro de la semana siguiente" solo aplica para domingos o días festivos (día de entrada).',
            ]);
        }

        $schedule->update([
            'worker_id' => (int) $data['worker_id'],
            'fecha_hora_entrada' => $fechaHoraEntrada,
            'fecha_hora_salida' => $fechaHoraSalida,
            'es_festivo' => $esFestivo,
            'es_festivo2' => $esFestivo2,
            'es_domingo' => $esDomingo,
            'no_compensa_semana_siguiente' => $noCompensaInput,
            'observaciones' => $data['observaciones'] ?? null,
        ]);
        $fresh = $schedule->fresh();

        // #region agent log
        $this->debugLog('run-pre-fix-1', 'H2', 'WorkerScheduleService.php:update', 'Updated worker schedule', [
            'id' => $fresh?->id,
            'store_id' => $fresh?->store_id,
            'worker_id' => $fresh?->worker_id,
            'entrada_attr_raw' => $fresh ? (string) $fresh->getRawOriginal('fecha_hora_entrada') : null,
            'entrada_cast' => $fresh?->fecha_hora_entrada?->format('Y-m-d H:i:s'),
            'entrada_as_store_tz' => $fresh?->fecha_hora_entrada?->copy()->timezone($this->storeTimezoneService->getTimezoneForStore($store))->format('Y-m-d H:i:s'),
            'salida_attr_raw' => $fresh ? (string) $fresh->getRawOriginal('fecha_hora_salida') : null,
            'salida_cast' => $fresh?->fecha_hora_salida?->format('Y-m-d H:i:s'),
            'app_tz' => (string) config('app.timezone'),
        ]);
        // #endregion

        return $fresh;
    }

    public function delete(Store $store, WorkerSchedule $schedule): void
    {
        if ($schedule->store_id !== $store->id) {
            abort(404);
        }
        $schedule->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validatePayload(Store $store, array $data, ?int $ignoreId): void
    {
        Validator::make($data, [
            'worker_id' => 'required|integer|exists:workers,id',
            'fecha_hora_entrada' => 'required|date',
            'fecha_hora_salida' => 'nullable|date',
            'observaciones' => 'nullable|string|max:500',
        ])->validate();

        $worker = Worker::where('id', $data['worker_id'])->where('store_id', $store->id)->first();
        if (! $worker) {
            throw ValidationException::withMessages([
                'worker_id' => 'El trabajador no pertenece a esta tienda.',
            ]);
        }
    }

    private function assertSalidaAfterEntrada(Carbon $entrada, ?Carbon $salida): void
    {
        if ($salida && $salida->lte($entrada)) {
            throw ValidationException::withMessages([
                'fecha_hora_salida' => 'La fecha y hora de salida debe ser posterior a la entrada.',
            ]);
        }
    }

    /**
     * Detección de solapes (equivalente a counter-tools HorarioController).
     */
    private function assertNoOverlap(Store $store, int $workerId, Carbon $fechaHoraEntrada, ?Carbon $fechaHoraSalida, ?int $ignoreId): void
    {
        $existentes = WorkerSchedule::query()
            ->where('store_id', $store->id)
            ->where('worker_id', $workerId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->get();

        foreach ($existentes as $ex) {
            if (! $this->intervalsOverlap($fechaHoraEntrada, $fechaHoraSalida, $ex->fecha_hora_entrada, $ex->fecha_hora_salida)) {
                continue;
            }

            if ($ex->fecha_hora_salida === null) {
                throw ValidationException::withMessages([
                    'fecha_hora_entrada' => 'Ya existe un horario pendiente de salida que se cruza con este horario. Debe completar ese horario antes de registrar uno nuevo.',
                ]);
            }

            throw ValidationException::withMessages([
                'fecha_hora_entrada' => 'El horario se cruza con un horario existente. Verifique que los horarios no se solapen.',
            ]);
        }
    }

    /**
     * Dos intervalos [inicio, fin] se solapan; fin null = intervalo abierto hacia el futuro.
     */
    private function intervalsOverlap(Carbon $aInicio, ?Carbon $aFin, Carbon $bInicio, ?Carbon $bFin): bool
    {
        $aFinEf = $aFin ?? $aInicio->copy()->addYears(50);
        $bFinEf = $bFin ?? $bInicio->copy()->addYears(50);

        return $aInicio->lt($bFinEf) && $bInicio->lt($aFinEf);
    }

    private function normalizeEsFestivo2(bool $marcado, ?Carbon $fechaHoraSalida, bool $cruzaDia): bool
    {
        if (! $marcado) {
            return false;
        }
        if (! $fechaHoraSalida) {
            throw ValidationException::withMessages([
                'es_festivo2' => 'La opción "Es día festivo (día de salida)" solo puede marcarse cuando se registra una fecha de salida.',
            ]);
        }
        if (! $cruzaDia) {
            throw ValidationException::withMessages([
                'es_festivo2' => 'La opción "Es día festivo (día de salida)" solo aplica cuando la fecha de salida es diferente a la fecha de entrada.',
            ]);
        }

        return true;
    }

    public function isSundayInStoreTz(Store $store, Carbon $fechaHoraUtc): bool
    {
        $tz = $this->storeTimezoneService->getTimezoneForStore($store);

        return $fechaHoraUtc->copy()->timezone($tz)->dayOfWeek === Carbon::SUNDAY;
    }

    public function dateYmdInStoreTz(Store $store, Carbon $fechaHoraUtc): string
    {
        $tz = $this->storeTimezoneService->getTimezoneForStore($store);

        return $fechaHoraUtc->copy()->timezone($tz)->format('Y-m-d');
    }

    private function toBool(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === '0') {
            return false;
        }
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Convierte instante UTC a Carbon en zona de la tienda (para inputs datetime-local).
     */
    public function toStoreLocalForInput(Store $store, ?Carbon $utc): ?string
    {
        if (! $utc) {
            return null;
        }
        $tz = $this->storeTimezoneService->getTimezoneForStore($store);

        $value = $utc->copy()->timezone($tz)->format('Y-m-d\TH:i');

        // #region agent log
        $this->debugLog('run-pre-fix-1', 'H1', 'WorkerScheduleService.php:toStoreLocalForInput', 'Format value for edit input', [
            'store_id' => $store->id,
            'store_tz' => $tz,
            'utc_in' => $utc->format('Y-m-d H:i:s'),
            'result_local' => $value,
            'app_tz' => (string) config('app.timezone'),
        ]);
        // #endregion

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function debugLog(string $runId, string $hypothesisId, string $location, string $message, array $data): void
    {
        try {
            $line = json_encode([
                'sessionId' => '0f8c29',
                'runId' => $runId,
                'hypothesisId' => $hypothesisId,
                'location' => $location,
                'message' => $message,
                'data' => $data,
                'timestamp' => (int) round(microtime(true) * 1000),
            ], JSON_UNESCAPED_UNICODE).PHP_EOL;
            $fh = fopen(base_path('debug-0f8c29.log'), 'ab');
            if ($fh !== false) {
                fwrite($fh, $line);
                fclose($fh);
            }
        } catch (\Throwable) {
            // noop: instrumentation must never break business flow
        }
    }
}
