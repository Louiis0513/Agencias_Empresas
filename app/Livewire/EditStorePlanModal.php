<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\ConvertidorImgService;
use App\Services\StorePermissionService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditStorePlanModal extends Component
{
    use WithFileUploads;

    public int $storeId;
    public ?int $planId = null;

    public string $name = '';
    public ?string $description = null;
    public string $price = '';
    public string $duration_days = '';
    public ?string $daily_entries_limit = null;
    public ?string $total_entries_limit = null;

    /** Ruta de la imagen actual del plan (para mostrar en el formulario). */
    public ?string $currentImagePath = null;

    public bool $deleteImage = false;

    public $image = null;

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
            $this->currentImagePath = $plan->image_path;
            $this->deleteImage = false;
            $this->image = null;

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
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function update(StorePermissionService $permission, SubscriptionService $subscriptionService, ConvertidorImgService $convertidorImgService): mixed
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

        $plan = $subscriptionService->getPlanForStore($store, $this->planId);
        if (! $plan) {
            return null;
        }

        $imagePath = $plan->image_path;

        if ($this->deleteImage && $imagePath) {
            Storage::disk('public')->delete($imagePath);
            $imagePath = null;
        }

        if ($this->image) {
            try {
                $path = $this->image->store('plans/'.$store->id, 'public');
                $path = $convertidorImgService->convertPublicImageToWebp($path);
                if ($imagePath) {
                    Storage::disk('public')->delete($imagePath);
                }
                $imagePath = $path;
            } catch (\Throwable $e) {
                Log::error('Error al actualizar imagen del plan', [
                    'store_id' => $store->id,
                    'plan_id' => $this->planId,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $currency = $store->currency ?? 'COP';
        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'price' => parse_money($this->price, $currency),
            'duration_days' => $this->duration_days,
            'daily_entries_limit' => $this->daily_entries_limit,
            'total_entries_limit' => $this->total_entries_limit,
            'image_path' => $imagePath,
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
