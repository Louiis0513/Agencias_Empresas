<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\StorePermissionService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateSubscriptionModal extends Component
{
    public int $storeId;

    public ?int $customer_id = null;

    public ?array $clienteSeleccionado = null;

    public ?int $plan_id = null;

    public string $starts_at = '';

    /** @var Collection<int, \App\Models\StorePlan> */
    public Collection $plans;

    public function mount(int $storeId): void
    {
        $this->storeId = $storeId;
        $this->plans = app(SubscriptionService::class)->getPlansForStore(Store::findOrFail($storeId));
        $this->starts_at = now()->format('Y-m-d');
    }

    #[On('customer-selected')]
    public function onCustomerSelected($customer_id, $customer = null): void
    {
        $this->customer_id = (int) $customer_id;
        $this->clienteSeleccionado = is_array($customer) ? $customer : null;
    }

    #[On('customer-cleared')]
    public function onCustomerCleared(): void
    {
        $this->customer_id = null;
        $this->clienteSeleccionado = null;
    }

    public function save(SubscriptionService $subscriptionService, StorePermissionService $permission): mixed
    {
        $store = Store::findOrFail($this->storeId);
        $permission->authorize($store, 'subscriptions.create');

        $this->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'plan_id' => 'required|integer',
            'starts_at' => 'required|date',
        ], [], [
            'customer_id' => 'cliente',
            'plan_id' => 'plan',
            'starts_at' => 'fecha de inicio',
        ]);

        $plan = $subscriptionService->getPlanForStore($store, $this->plan_id);
        if (! $plan) {
            $this->addError('plan_id', 'El plan no existe o no pertenece a esta tienda.');
            return null;
        }

        $subscriptionService->createSubscription(
            $store,
            $this->customer_id,
            $this->plan_id,
            Carbon::parse($this->starts_at)
        );

        $this->customer_id = null;
        $this->clienteSeleccionado = null;
        $this->plan_id = null;
        $this->starts_at = now()->format('Y-m-d');

        return redirect()->route('stores.subscriptions.memberships', $store)
            ->with('success', 'Membresía creada correctamente.');
    }

    public function render()
    {
        return view('livewire.create-subscription-modal');
    }
}
