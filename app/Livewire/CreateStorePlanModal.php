<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\ConvertidorImgService;
use App\Services\StorePermissionService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateStorePlanModal extends Component
{
    use WithFileUploads;

    public int $storeId;

    public string $name = '';
    public ?string $description = null;
    public string $price = '';
    public string $duration_days = '';
    public ?string $daily_entries_limit = null;
    public ?string $total_entries_limit = null;

    public $image = null;

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
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function save(StorePermissionService $permission, SubscriptionService $subscriptionService, ConvertidorImgService $convertidorImgService)
    {
        $this->validate();

        $store = Store::find($this->storeId);
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear planes en esta tienda.');
        }

        $permission->authorize($store, 'subscriptions.create');

        $currency = $store->currency ?? 'COP';
        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'price' => parse_money($this->price, $currency),
            'duration_days' => $this->duration_days,
            'daily_entries_limit' => $this->daily_entries_limit,
            'total_entries_limit' => $this->total_entries_limit,
        ];

        $plan = $subscriptionService->createPlan($store, $data);

        if ($this->image) {
            try {
                $path = $this->image->store('plans/'.$store->id, 'public');
                $path = $convertidorImgService->convertPublicImageToWebp($path);
                $plan->update(['image_path' => $path]);
            } catch (\Throwable $e) {
                Log::error('Error al subir imagen del plan', [
                    'store_id' => $store->id,
                    'plan_id' => $plan->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        session()->flash('success', 'Plan creado correctamente.');

        return $this->redirect(route('stores.subscriptions.plans', $store), navigate: true);
    }

    public function render()
    {
        return view('livewire.create-store-plan-modal');
    }
}
