<?php

namespace App\Livewire;

use App\Models\CuentaContable;
use App\Models\Store;
use App\Services\CajaService;
use App\Services\ComprobanteIngresoService;
use App\Services\StorePermissionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreateBolsilloModal extends Component
{
    public int $storeId;

    public string $name = '';

    public ?string $detalles = null;

    public string $saldo = '0';

    /** efectivo|corriente_cop|ahorro|divisas */
    public string $tipo_disponible = 'efectivo';

    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'detalles' => ['nullable', 'string', 'max:1000'],
            'saldo' => ['required', 'numeric', 'min:0'],
            'tipo_disponible' => ['required', 'in:efectivo,corriente_cop,ahorro,divisas'],
            'is_active' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'El nombre del bolsillo es obligatorio.',
            'tipo_disponible.in' => 'Selecciona un tipo de disponible válido.',
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function getCodigoPreviewProperty(): ?string
    {
        $store = $this->getStoreProperty();
        if (! $store) {
            return null;
        }

        return app(CajaService::class)->previewCodigoAuxiliar($store, $this->tipo_disponible);
    }

    public function getCodigoPadreProperty(): ?string
    {
        return CuentaContable::TIPOS_BOLSILLO_PADRE[$this->tipo_disponible] ?? null;
    }

    public function resetForm(): void
    {
        $this->reset(['name', 'detalles', 'saldo', 'tipo_disponible', 'is_active']);
        $this->tipo_disponible = 'efectivo';
        $this->is_active = true;
        $this->saldo = '0';
        $this->resetValidation();
    }

    public function save(CajaService $cajaService, ComprobanteIngresoService $comprobanteIngresoService, StorePermissionService $permissionService)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear bolsillos en esta tienda.');
        }
        if (! $permissionService->can($store, 'caja.bolsillos.create')) {
            abort(403, 'No tienes permiso para crear bolsillos.');
        }

        try {
            $bolsillo = $cajaService->crearBolsillo($store, [
                'name' => $this->name,
                'detalles' => $this->detalles ?: null,
                'tipo_disponible' => $this->tipo_disponible,
                'is_active' => $this->is_active,
            ]);

            $currency = $store->currency ?? 'COP';
            $saldoInicial = parse_money($this->saldo, $currency);
            if ($saldoInicial > 0) {
                $comprobanteIngresoService->crearComprobante($store, (int) Auth::id(), [
                    'date' => now()->toDateString(),
                    'notes' => 'Saldo inicial desde creación del bolsillo "'.$bolsillo->name.'"',
                    'destinos' => [
                        ['bolsillo_id' => $bolsillo->id, 'amount' => $saldoInicial],
                    ],
                ]);
            }

            $this->resetForm();

            $codigo = $bolsillo->cuentaContable?->codigo;
            $msg = $saldoInicial > 0
                ? 'Bolsillo creado correctamente'.($codigo ? " (cuenta {$codigo})" : '').'. Se registró un comprobante de ingreso por el saldo inicial.'
                : 'Bolsillo creado correctamente'.($codigo ? " (cuenta {$codigo})" : '').'.';
            if ($permissionService->can($store, 'store-config.view')) {
                return redirect()->to(route('stores.configuracion', $store).'?panel=caja')->with('success', $msg);
            }

            return redirect()->route('stores.cajas.movimientos', $store)->with('success', $msg);
        } catch (\Exception $e) {
            $this->addError('name', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.create-bolsillo-modal');
    }
}
