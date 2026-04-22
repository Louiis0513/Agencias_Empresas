<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\WorkerHourRateTemplate;
use App\Services\StorePermissionService;
use App\Services\WorkerHourRateTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreWorkerHourRateTemplateController extends Controller
{
    public function store(
        Request $request,
        Store $store,
        StorePermissionService $permission,
        WorkerHourRateTemplateService $templateService
    ): RedirectResponse {
        $permission->authorize($store, 'workers.schedules.edit');

        try {
            $payload = $this->validatePayload($request, $store, null, $templateService);
            WorkerHourRateTemplate::create([
                'store_id' => $store->id,
                'name' => $payload['name'],
                'rates_json' => $payload['rates'],
                'created_by' => $request->user()?->id,
                'updated_by' => $request->user()?->id,
            ]);
        } catch (ValidationException $e) {
            return redirect()
                ->route('stores.workers.time-attendance', array_merge(
                    ['store' => $store, 'rate_modal' => 1],
                    $this->redirectQueryFromRequest($request)
                ))
                ->withErrors($e->errors(), 'hourRates')
                ->withInput();
        }

        return redirect()
            ->route('stores.workers.time-attendance', array_merge(
                ['store' => $store, 'rate_modal' => 1],
                $this->redirectQueryFromRequest($request)
            ))
            ->with('success', 'Plantilla de valor hora creada correctamente.');
    }

    public function update(
        Request $request,
        Store $store,
        WorkerHourRateTemplate $template,
        StorePermissionService $permission,
        WorkerHourRateTemplateService $templateService
    ): RedirectResponse {
        $permission->authorize($store, 'workers.schedules.edit');
        $this->assertTemplateBelongsToStore($store, $template);

        try {
            $payload = $this->validatePayload($request, $store, $template, $templateService);
            $template->update([
                'name' => $payload['name'],
                'rates_json' => $payload['rates'],
                'updated_by' => $request->user()?->id,
            ]);
        } catch (ValidationException $e) {
            return redirect()
                ->route('stores.workers.time-attendance', array_merge(
                    ['store' => $store, 'rate_modal' => 1, 'rate_edit' => $template->id],
                    $this->redirectQueryFromRequest($request)
                ))
                ->withErrors($e->errors(), 'hourRates')
                ->withInput();
        }

        return redirect()
            ->route('stores.workers.time-attendance', array_merge(
                ['store' => $store, 'rate_modal' => 1],
                $this->redirectQueryFromRequest($request)
            ))
            ->with('success', 'Plantilla de valor hora actualizada correctamente.');
    }

    public function destroy(
        Request $request,
        Store $store,
        WorkerHourRateTemplate $template,
        StorePermissionService $permission
    ): RedirectResponse {
        $permission->authorize($store, 'workers.schedules.edit');
        $this->assertTemplateBelongsToStore($store, $template);

        $template->delete();

        return redirect()
            ->route('stores.workers.time-attendance', array_merge(
                ['store' => $store, 'rate_modal' => 1],
                $this->redirectQueryFromRequest($request)
            ))
            ->with('success', 'Plantilla de valor hora eliminada.');
    }

    /**
     * @return array{name: string, rates: array<string, float>}
     */
    private function validatePayload(
        Request $request,
        Store $store,
        ?WorkerHourRateTemplate $template,
        WorkerHourRateTemplateService $templateService
    ): array {
        $validator = Validator::make($request->all(), [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('worker_hour_rate_templates', 'name')
                    ->where(fn ($q) => $q->where('store_id', $store->id))
                    ->ignore($template?->id),
            ],
            'rates' => ['required', 'array'],
        ], [
            'name.required' => 'Debes ingresar un nombre para la plantilla.',
            'name.unique' => 'Ya existe una plantilla con ese nombre en esta tienda.',
            'rates.required' => 'Debes completar todos los valores por tipo de hora.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $normalizedRates = $templateService->normalizeAndValidateRates((array) $request->input('rates', []));

        return [
            'name' => trim((string) $request->input('name')),
            'rates' => $normalizedRates,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function redirectQueryFromRequest(Request $request): array
    {
        return array_filter([
            'from' => $request->input('redirect_from'),
            'to' => $request->input('redirect_to'),
            'worker_id' => $request->input('redirect_worker_id'),
            'template_id' => $request->input('redirect_template_id'),
            'edit' => $request->input('redirect_edit'),
        ], fn ($v) => $v !== null && $v !== '');
    }

    private function assertTemplateBelongsToStore(Store $store, WorkerHourRateTemplate $template): void
    {
        if ($template->store_id !== $store->id) {
            abort(404);
        }
    }
}
