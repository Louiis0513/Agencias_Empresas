<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\On;
use Livewire\Component;

class RegistrarAsistenciaForm extends Component
{
    public int $storeId;

    public ?int $customer_id = null;

    public ?array $clienteSeleccionado = null;

    public string $fecha = '';

    public string $hora = '';

    public ?string $errorMessage = null;

    public function mount(int $storeId): void
    {
        $this->storeId = $storeId;
        $this->fecha = now()->format('Y-m-d');
        $this->hora = now()->format('H:i');
    }

    #[On('customer-selected')]
    public function onCustomerSelected($customer_id, $customer = null): void
    {
        $this->customer_id = (int) $customer_id;
        $this->clienteSeleccionado = is_array($customer) ? $customer : null;
        $this->errorMessage = null;
    }

    #[On('customer-cleared')]
    public function onCustomerCleared(): void
    {
        $this->customer_id = null;
        $this->clienteSeleccionado = null;
        $this->errorMessage = null;
    }

    public function submit(SubscriptionService $subscriptionService): mixed
    {
        $this->errorMessage = null;

        $this->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'fecha' => 'required|date',
            'hora' => 'required|string|regex:/^\d{1,2}:\d{2}$/',
        ], [], [
            'customer_id' => 'cliente',
            'fecha' => 'fecha',
            'hora' => 'hora',
        ]);

        $store = Store::findOrFail($this->storeId);
        $dateTime = Carbon::parse($this->fecha . ' ' . $this->hora);

        try {
            $subscriptionService->recordAttendance($store, $this->customer_id, $dateTime);
        } catch (InvalidArgumentException $e) {
            $this->errorMessage = $e->getMessage();
            return null;
        }

        return redirect()->route('stores.asistencias', $store)
            ->with('success', 'Asistencia registrada correctamente.');
    }

    public function render(): View
    {
        return view('livewire.registrar-asistencia-form');
    }
}
