<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\StorePermissionService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreateStorePlanModal extends Component
{
    public int $storeId;

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

    public function save(StorePermissionService $permission, SubscriptionService $subscriptionService)
    {
        $this->validate();

        $store = Store::find($this->storeId);
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear planes en esta tienda.');
        }

        $permission->authorize($store, 'subscriptions.create');

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'duration_days' => $this->duration_days,
            'daily_entries_limit' => $this->daily_entries_limit,
            'total_entries_limit' => $this->total_entries_limit,
        ];

        $subscriptionService->createPlan($store, $data);

        session()->flash('success', 'Plan creado correctamente.');

        return $this->redirect(route('stores.subscriptions.plans', $store), navigate: true);
    }

    public function render()
    {
        return view('livewire.create-store-plan-modal');
    }
}
