<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\ConvertidorImgService;
use App\Services\StoreService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateStoreModal extends Component
{
    use WithFileUploads;

    public $open = false;

    public string $name = '';
    public ?string $rut_nit = null;
    public string $currency = 'COP';
    public string $timezone = 'America/Bogota';
    public string $date_format = 'd-m-Y';
    public string $time_format = '24';
    public ?string $country = null;
    public ?string $department = null;
    public ?string $city = null;
    public ?string $address = null;
    public ?string $phone = null;
    public ?string $mobile = null;
    public ?string $domain = null;
    public ?string $regimen = null;

    public $logo = null;

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:50'],
            'rut_nit' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'date_format' => ['nullable', 'string', 'max:20'],
            'time_format' => ['nullable', 'string', 'in:12,24'],
            'country' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+]+$/', 'max:20'],
            'mobile' => ['nullable', 'string', 'regex:/^[0-9+]+$/', 'max:20'],
            'domain' => ['nullable', 'string', 'max:255'],
            'regimen' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'max:5120'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'El nombre de la tienda es obligatorio.',
            'phone.regex' => 'El teléfono solo debe contener números.',
            'mobile.regex' => 'El celular solo debe contener números.',
        ];
    }

    public function save(StoreService $storeService, ConvertidorImgService $convertidorImgService)
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'rut_nit' => $this->rut_nit ?: null,
            'currency' => $this->currency ?: 'COP',
            'timezone' => $this->timezone ?: 'America/Bogota',
            'date_format' => $this->date_format ?: 'd-m-Y',
            'time_format' => $this->time_format ?: '24',
            'country' => $this->country ?: null,
            'department' => $this->department ?: null,
            'city' => $this->city ?: null,
            'address' => $this->address ?: null,
            'phone' => $this->phone ?: null,
            'mobile' => $this->mobile ?: null,
            'domain' => $this->domain ?: null,
            'regimen' => $this->regimen ?: null,
        ];

        $store = $storeService->createStore(Auth::user(), $data);

        if ($this->logo) {
            try {
                $basePath = 'stores/'.$store->id;
                $path = $this->logo->store($basePath, 'public');
                $path = $convertidorImgService->convertPublicImageToWebp($path);
                $store->update(['logo_path' => $path]);
            } catch (\Throwable $e) {
                Log::error('Error al procesar logo al crear tienda', [
                    'store_id' => $store->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->reset([
            'open', 'name', 'rut_nit', 'currency', 'timezone', 'date_format', 'time_format',
            'country', 'department', 'city', 'address', 'phone', 'mobile', 'domain', 'regimen', 'logo',
        ]);

        return redirect()->route('stores.dashboard', $store);
    }

    public function getPlanProperty()
    {
        return Auth::user()->plan;
    }

    public function getStoreLimitProperty()
    {
        return $this->plan ? $this->plan->max_stores : 0;
    }

    public function getStoreCountProperty()
    {
        return Store::where('user_id', Auth::id())->count();
    }

    public function getProgressPercentProperty()
    {
        if ($this->storeLimit == 0) {
            return 100;
        }

        return ($this->storeCount / $this->storeLimit) * 100;
    }

    public function render()
    {
        return view('livewire.create-store-modal');
    }
}
