<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\WorkerSchedule;
use App\Services\StorePermissionService;
use App\Services\StoreTimezoneService;
use App\Services\WorkerScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StoreWorkerScheduleController extends Controller
{
    public function store(Request $request, Store $store, StorePermissionService $permission, WorkerScheduleService $scheduleService, StoreTimezoneService $timezoneService): RedirectResponse
    {
        $permission->authorize($store, 'workers.schedules.create');

        try {
            $saved = $scheduleService->create($store, $request->all(), (int) $request->user()->id);
        } catch (ValidationException $e) {
            // #region agent log
            $this->debugLog('run-pre-fix-1', 'H2', 'StoreWorkerScheduleController.php:store:catch', 'Store validation exception', [
                'store_id' => $store->id,
                'errors' => $e->errors(),
                'input_entrada' => $request->input('fecha_hora_entrada'),
                'input_salida' => $request->input('fecha_hora_salida'),
            ]);
            // #endregion
            return redirect()
                ->route('stores.workers.time-attendance', array_merge(['store' => $store], $this->historialQueryFromRequest($request)))
                ->withErrors($e->errors())
                ->withInput();
        }

        return redirect()
            ->route('stores.workers.time-attendance', $this->timeAttendanceSuccessQuery($store, $request, $saved, $timezoneService))
            ->with('success', 'Horario registrado correctamente.');
    }

    public function update(Request $request, Store $store, WorkerSchedule $workerSchedule, StorePermissionService $permission, WorkerScheduleService $scheduleService, StoreTimezoneService $timezoneService): RedirectResponse
    {
        $this->assertScheduleBelongsToStore($store, $workerSchedule);
        $permission->authorize($store, 'workers.schedules.edit');

        try {
            $saved = $scheduleService->update($store, $workerSchedule, $request->all(), (int) $request->user()->id);
        } catch (ValidationException $e) {
            // #region agent log
            $this->debugLog('run-pre-fix-1', 'H2', 'StoreWorkerScheduleController.php:update:catch', 'Update validation exception', [
                'store_id' => $store->id,
                'schedule_id' => $workerSchedule->id,
                'errors' => $e->errors(),
                'input_entrada' => $request->input('fecha_hora_entrada'),
                'input_salida' => $request->input('fecha_hora_salida'),
            ]);
            // #endregion
            return redirect()
                ->route('stores.workers.time-attendance', array_merge(['store' => $store, 'edit' => $workerSchedule->id], $this->historialQueryFromRequest($request)))
                ->withErrors($e->errors())
                ->withInput();
        }

        return redirect()
            ->route('stores.workers.time-attendance', $this->timeAttendanceSuccessQuery($store, $request, $saved, $timezoneService))
            ->with('success', 'Horario actualizado correctamente.');
    }

    public function destroy(Request $request, Store $store, WorkerSchedule $workerSchedule, StorePermissionService $permission, WorkerScheduleService $scheduleService, StoreTimezoneService $timezoneService): RedirectResponse
    {
        $this->assertScheduleBelongsToStore($store, $workerSchedule);
        $permission->authorize($store, 'workers.schedules.destroy');

        $params = $this->historialQueryFromRequest($request);
        if ($params === []) {
            $tz = $timezoneService->getTimezoneForStore($store);
            $day = $workerSchedule->fecha_hora_entrada->copy()->timezone($tz)->format('Y-m-d');
            $params = ['from' => $day, 'to' => $day];
        }

        $scheduleService->delete($store, $workerSchedule);

        return redirect()
            ->route('stores.workers.time-attendance', array_merge(['store' => $store], $params))
            ->with('success', 'Registro de horario eliminado.');
    }

    /**
     * Tras guardar: si el formulario traía filtros del historial, se mantienen; si no, se abre el día de la entrada en zona tienda para que el listado cargue y se vea el registro.
     *
     * @return array<string, mixed>
     */
    private function timeAttendanceSuccessQuery(Store $store, Request $request, WorkerSchedule $schedule, StoreTimezoneService $timezoneService): array
    {
        $manual = $this->historialQueryFromRequest($request);
        if ($manual !== []) {
            return array_merge(['store' => $store], $manual);
        }

        $tz = $timezoneService->getTimezoneForStore($store);
        $day = $schedule->fecha_hora_entrada->copy()->timezone($tz)->format('Y-m-d');

        return [
            'store' => $store,
            'from' => $day,
            'to' => $day,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function historialQueryFromRequest(Request $request): array
    {
        return array_filter([
            'from' => $request->input('redirect_from'),
            'to' => $request->input('redirect_to'),
            'worker_id' => $request->input('redirect_worker_id'),
            'template_id' => $request->input('redirect_template_id'),
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function assertScheduleBelongsToStore(Store $store, WorkerSchedule $schedule): void
    {
        if ($schedule->store_id !== $store->id) {
            abort(404);
        }
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
