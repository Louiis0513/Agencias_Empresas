<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\StorePermissionService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EditStorePlanModal extends Component
{
    public int $storeId;
    public ?int $planId = null;

    public string $name = '';
    public ?string $description = null;
    public string $price = '';
    public string $duration_days = '';
    public ?string $daily_entries_limit = null;
    public ?string $total_entries_limit = null;

    public function mount(int $storeId): void
    {
        $this->storeId = $storeId;
    }

    public function loadPlan($planId = null, SubscriptionService $subscriptionService): void
    {
        if ($planId === null) {
            return;
        }

        if (is_array($planId) && isset($planId['id'])) {
            $planId = $planId['id'];
        } elseif (is_object($planId) && isset($planId->id)) {
            $planId = $planId->id;
        }

        $this->planId = (int) $planId;

        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }

        $plan = $subscriptionService->getPlanForStore($store, $this->planId);

        if ($plan) {
            $this->name = $plan->name;
            $this->description = $plan->description ?? '';
            $this->price = (string) $plan->price;
            $this->duration_days = (string) $plan->duration_days;
            $this->daily_entries_limit = $plan->daily_entries_limit !== null ? (string) $plan->daily_entries_limit : '';
            $this->total_entries_limit = $plan->total_entries_limit !== null ? (string) $plan->total_entries_limit : '';

            $this->dispatch('open-modal', 'edit-store-plan');
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_days' => ['required', 'integer', 'min:1'],
            'daily_entries_limit' => ['nullable', 'integer', 'min:1'],
            'total_entries_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function update(StorePermissionService $permission, SubscriptionService $subscriptionService): mixed
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para editar planes en esta tienda.');
        }

        $permission->authorize($store, 'subscriptions.edit');

        if (! $this->planId) {
            return null;
        }

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'duration_days' => $this->duration_days,
            'daily_entries_limit' => $this->daily_entries_limit,
            'total_entries_limit' => $this->total_entries_limit,
        ];

        $subscriptionService->updatePlan($store, $this->planId, $data);

        session()->flash('success', 'Plan actualizado correctamente.');

        return $this->redirect(route('stores.subscriptions.plans', $store), navigate: true);
    }

    public function render()
    {
        return view('livewire.edit-store-plan-modal');
    }
}
