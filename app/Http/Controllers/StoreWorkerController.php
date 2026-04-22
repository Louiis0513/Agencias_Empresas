<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkerRequest;
use App\Models\Role;
use App\Models\Store;
use App\Models\Worker;
use App\Models\WorkerHourRateTemplate;
use App\Models\WorkerSchedule;
use App\Services\StorePermissionService;
use App\Services\StoreTimezoneService;
use App\Services\WorkerHourRateTemplateService;
use App\Services\WorkerScheduleClassificationExcelExportService;
use App\Services\WorkerScheduleLiquidationService;
use App\Services\WorkerScheduleService;
use App\Services\WorkerService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StoreWorkerController extends Controller
{
    public function index(Store $store, StorePermissionService $permission)
    {
        $permission->authorize($store, 'workers.view');

        $roles = Role::where('store_id', $store->id)->get()->keyBy('id');

        $owner = $store->owner;
        $workersList = collect();

        if ($owner) {
            $workersList->push([
                'id' => 'owner-'.$owner->id,
                'worker_id' => null,
                'name' => $owner->name,
                'email' => $owner->email,
                'role' => 'Dueño',
                'role_id' => null,
                'vinculado' => true,
            ]);
        }

        foreach ($store->workerRecords()->with('role')->get() as $w) {
            $workersList->push([
                'id' => $w->id,
                'worker_id' => $w->id,
                'name' => $w->name,
                'email' => $w->email,
                'role' => $w->role->name ?? '-',
                'role_id' => $w->role_id,
                'vinculado' => $w->estaVinculado(),
            ]);
        }

        $rolesList = Role::where('store_id', $store->id)->orderBy('name')->get();

        return view('stores.trabajadoryrol.workers', compact('store', 'workersList', 'rolesList'));
    }

    public function timeAttendance(
        Request $request,
        Store $store,
        StorePermissionService $permission,
        WorkerScheduleService $scheduleService,
        StoreTimezoneService $timezoneService,
        WorkerScheduleLiquidationService $liquidationService,
        WorkerHourRateTemplateService $templateService
    ) {
        $permission->authorize($store, 'workers.schedules.view');

        $tz = $timezoneService->getTimezoneForStore($store);
        $workers = Worker::where('store_id', $store->id)->orderBy('name')->get();
        $hourRateTemplates = WorkerHourRateTemplate::query()
            ->where('store_id', $store->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $editing = null;
        if ($request->filled('edit')) {
            $editing = WorkerSchedule::query()
                ->where('store_id', $store->id)
                ->where('id', $request->query('edit'))
                ->first();
        }

        $filterFrom = $request->query('from');
        $filterTo = $request->query('to');
        $filterWorkerId = $request->query('worker_id');
        $selectedTemplateId = $request->query('template_id');
        $selectedTemplate = null;
        if ($selectedTemplateId !== null && $selectedTemplateId !== '') {
            $selectedTemplate = $hourRateTemplates->firstWhere('id', (int) $selectedTemplateId);
            if (! $selectedTemplate) {
                $selectedTemplateId = null;
            }
        }

        $schedules = collect();
        $liquidacion = null;
        $historialApplied = false;

        $attemptedHistorial = $request->anyFilled(['from', 'to', 'worker_id']);

        if ($attemptedHistorial) {
            $validator = $this->makeHistorialRangeValidator($request, $store, $tz);

            if ($validator->fails()) {
                return view('stores.trabajadoryrol.worker-time-attendance', [
                    'store' => $store,
                    'workers' => $workers,
                    'schedules' => $schedules,
                    'editing' => $editing,
                    'liquidacion' => null,
                    'scheduleService' => $scheduleService,
                    'historialApplied' => false,
                    'historialFrom' => $filterFrom,
                    'historialTo' => $filterTo,
                    'historialWorkerId' => $filterWorkerId,
                    'historialAttempted' => $attemptedHistorial,
                    'hourRateTemplates' => $hourRateTemplates,
                    'rateTemplateKeys' => $templateService->expectedRateKeys(),
                    'selectedTemplateId' => $selectedTemplateId,
                    'rateEditTemplate' => null,
                ])->withErrors($validator);
            }

            $fromLocal = Carbon::parse($filterFrom, $tz)->startOfDay();
            $toLocal = Carbon::parse($filterTo, $tz)->endOfDay();
            $fromUtc = $fromLocal->copy()->utc();
            $toUtc = $toLocal->copy()->utc();

            $workerId = $filterWorkerId !== null && $filterWorkerId !== '' ? (int) $filterWorkerId : null;

            $query = WorkerSchedule::query()
                ->where('store_id', $store->id)
                ->whereBetween('fecha_hora_entrada', [$fromUtc, $toUtc])
                ->with(['worker.role'])
                ->orderByDesc('fecha_hora_entrada');

            if ($workerId !== null) {
                $query->where('worker_id', $workerId);
            }

            $schedules = $query->get();
            $historialApplied = true;

            // #region agent log
            $first = $schedules->first();
            try {
                $line = json_encode([
                    'sessionId' => '0f8c29',
                    'runId' => 'run-pre-fix-1',
                    'hypothesisId' => 'H1',
                    'location' => 'StoreWorkerController.php:timeAttendance',
                    'message' => 'Loaded schedules for historial',
                    'data' => [
                        'store_id' => $store->id,
                        'app_tz' => (string) config('app.timezone'),
                        'store_tz' => $tz,
                        'count' => $schedules->count(),
                        'first_id' => $first?->id,
                        'first_entrada_raw' => $first ? (string) $first->getRawOriginal('fecha_hora_entrada') : null,
                        'first_entrada_cast' => $first?->fecha_hora_entrada?->format('Y-m-d H:i:s'),
                        'first_entrada_as_store_tz' => $first?->fecha_hora_entrada?->copy()->timezone($tz)->format('Y-m-d H:i:s'),
                    ],
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
            // #endregion

            $completedForLiquidacion = $schedules->filter(fn (WorkerSchedule $s) => $s->fecha_hora_salida !== null);
            $liquidacion = $completedForLiquidacion->isNotEmpty()
                ? $liquidationService->calcularLiquidacion($completedForLiquidacion)
                : null;
        }

        return view('stores.trabajadoryrol.worker-time-attendance', [
            'store' => $store,
            'workers' => $workers,
            'schedules' => $schedules,
            'editing' => $editing,
            'liquidacion' => $liquidacion,
            'scheduleService' => $scheduleService,
            'historialApplied' => $historialApplied,
            'historialFrom' => $filterFrom,
            'historialTo' => $filterTo,
            'historialWorkerId' => $filterWorkerId,
            'historialAttempted' => $attemptedHistorial,
            'hourRateTemplates' => $hourRateTemplates,
            'rateTemplateKeys' => $templateService->expectedRateKeys(),
            'selectedTemplateId' => $selectedTemplate?->id,
            'rateEditTemplate' => $request->filled('rate_edit')
                ? $hourRateTemplates->firstWhere('id', (int) $request->query('rate_edit'))
                : null,
        ]);
    }

    public function exportTimeAttendanceClassification(
        Request $request,
        Store $store,
        StorePermissionService $permission,
        StoreTimezoneService $timezoneService,
        WorkerScheduleLiquidationService $liquidationService,
        WorkerScheduleClassificationExcelExportService $excelExport
    ) {
        $permission->authorize($store, 'workers.schedules.view');

        $tz = $timezoneService->getTimezoneForStore($store);
        $validator = $this->makeHistorialRangeValidator($request, $store, $tz);

        if ($validator->fails()) {
            return redirect()
                ->route('stores.workers.time-attendance', array_filter([
                    'store' => $store,
                    'edit' => $request->query('edit'),
                    'from' => $request->query('from'),
                    'to' => $request->query('to'),
                    'worker_id' => $request->query('worker_id'),
                    'template_id' => $request->query('template_id'),
                ], fn ($v) => $v !== null && $v !== ''))
                ->withErrors($validator);
        }

        $filterFrom = $request->query('from');
        $filterTo = $request->query('to');
        $filterWorkerId = $request->query('worker_id');
        $templateId = $request->query('template_id');

        $fromLocal = Carbon::parse($filterFrom, $tz)->startOfDay();
        $toLocal = Carbon::parse($filterTo, $tz)->endOfDay();
        $fromUtc = $fromLocal->copy()->utc();
        $toUtc = $toLocal->copy()->utc();

        $workerId = $filterWorkerId !== null && $filterWorkerId !== '' ? (int) $filterWorkerId : null;

        $query = WorkerSchedule::query()
            ->where('store_id', $store->id)
            ->whereBetween('fecha_hora_entrada', [$fromUtc, $toUtc])
            ->with(['worker.role'])
            ->orderByDesc('fecha_hora_entrada');

        if ($workerId !== null) {
            $query->where('worker_id', $workerId);
        }

        $schedules = $query->get();

        $filteredWorker = null;
        if ($workerId !== null) {
            $filteredWorker = Worker::where('store_id', $store->id)->whereKey($workerId)->first();
        }

        $ratesOverride = null;
        if ($templateId !== null && $templateId !== '') {
            $template = WorkerHourRateTemplate::query()
                ->where('store_id', $store->id)
                ->whereKey((int) $templateId)
                ->first();

            if (! $template) {
                return redirect()
                    ->route('stores.workers.time-attendance', array_filter([
                        'store' => $store,
                        'edit' => $request->query('edit'),
                        'from' => $request->query('from'),
                        'to' => $request->query('to'),
                        'worker_id' => $request->query('worker_id'),
                        'template_id' => $templateId,
                        'rate_modal' => 1,
                    ], fn ($v) => $v !== null && $v !== ''))
                    ->withErrors(['template_id' => 'La plantilla seleccionada no existe para esta tienda.']);
            }

            $ratesOverride = (array) $template->rates_json;
        }

        return $excelExport->download(
            $store,
            $tz,
            $fromLocal,
            $toLocal,
            $filteredWorker,
            $schedules,
            $liquidationService,
            $ratesOverride
        );
    }

    /**
     * Validación GET para historial y exportación Excel (máx. 60 días entre fechas en zona de la tienda).
     */
    private function makeHistorialRangeValidator(Request $request, Store $store, string $timezone): \Illuminate\Validation\Validator
    {
        $filterFrom = $request->query('from');
        $filterTo = $request->query('to');
        $filterWorkerId = $request->query('worker_id');

        $validator = Validator::make(
            [
                'from' => $filterFrom,
                'to' => $filterTo,
                'worker_id' => $filterWorkerId,
            ],
            [
                'from' => 'required|date',
                'to' => 'required|date',
                'worker_id' => 'nullable|integer',
            ],
            [
                'from.required' => 'Indica la fecha de inicio del rango.',
                'to.required' => 'Indica la fecha de fin del rango.',
            ]
        );

        if ($validator->passes()) {
            $fromLocal = Carbon::parse($filterFrom, $timezone)->startOfDay();
            $toLocal = Carbon::parse($filterTo, $timezone)->endOfDay();

            if ($toLocal->lt($fromLocal)) {
                $validator->errors()->add('to', 'La fecha fin debe ser igual o posterior a la fecha inicio.');
            } else {
                $daySpan = $fromLocal->copy()->startOfDay()->diffInDays($toLocal->copy()->startOfDay());
                if ($daySpan > 60) {
                    $validator->errors()->add('to', 'El rango no puede superar 60 días.');
                }
            }

            $workerId = $filterWorkerId !== null && $filterWorkerId !== '' ? (int) $filterWorkerId : null;
            if ($workerId !== null) {
                $belongs = Worker::where('store_id', $store->id)->whereKey($workerId)->exists();
                if (! $belongs) {
                    $validator->errors()->add('worker_id', 'Trabajador no válido para esta tienda.');
                }
            }
        }

        return $validator;
    }

    public function create(Store $store, StorePermissionService $permission)
    {
        $permission->authorize($store, 'workers.create');

        $rolesList = Role::where('store_id', $store->id)->orderBy('name')->get();

        return view('stores.trabajadoryrol.worker-create', compact('store', 'rolesList'));
    }

    public function store(Store $store, StoreWorkerRequest $request, StorePermissionService $permission, WorkerService $workerService)
    {
        $permission->authorize($store, 'workers.create');

        if (! Role::where('id', $request->role_id)->where('store_id', $store->id)->exists()) {
            return redirect()->back()->withInput()->with('error', 'El rol seleccionado no pertenece a esta tienda.');
        }

        try {
            $workerService->createWorker($store, $request->only(['name', 'email', 'role_id', 'phone', 'document_number', 'address']));
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('stores.workers', $store)
            ->with('success', 'Trabajador añadido correctamente.');
    }

    public function edit(Store $store, Worker $worker, StorePermissionService $permission)
    {
        $permission->authorize($store, 'workers.edit');

        if ($worker->store_id !== $store->id) {
            abort(404, 'El trabajador no pertenece a esta tienda.');
        }

        $rolesList = Role::where('store_id', $store->id)->orderBy('name')->get();

        return view('stores.trabajadoryrol.worker-edit', compact('store', 'worker', 'rolesList'));
    }

    public function update(Store $store, Worker $worker, StoreWorkerRequest $request, StorePermissionService $permission, WorkerService $workerService)
    {
        $permission->authorize($store, 'workers.edit');

        if ($worker->store_id !== $store->id) {
            abort(404, 'El trabajador no pertenece a esta tienda.');
        }

        if (! Role::where('id', $request->role_id)->where('store_id', $store->id)->exists()) {
            return redirect()->back()->withInput()->with('error', 'El rol no pertenece a esta tienda.');
        }

        try {
            $workerService->updateWorker($worker, $request->only(['name', 'email', 'role_id', 'phone', 'document_number', 'address']));
        } catch (Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('stores.workers', $store)
            ->with('success', 'Trabajador actualizado correctamente.');
    }

    public function destroy(Store $store, Worker $worker, StorePermissionService $permission, WorkerService $workerService)
    {
        $permission->authorize($store, 'workers.destroy');

        if ($worker->store_id !== $store->id) {
            abort(404, 'El trabajador no pertenece a esta tienda.');
        }

        $workerService->deleteWorker($worker);

        return redirect()->route('stores.workers', $store)
            ->with('success', 'Trabajador eliminado de la tienda.');
    }
}
