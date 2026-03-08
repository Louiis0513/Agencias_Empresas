<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\CustomerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class CreateCustomerModal extends Component
{
    public int $storeId;

    public string $name = '';
    public ?string $email = null;
    public ?string $phone_country_code = '57';
    public ?string $phone = null;
    public ?string $document_number = null;
    public ?string $address = null;
    public ?string $gender = null;
    public ?string $blood_type = null;
    public ?string $eps = null;
    public ?string $birth_date = null;
    public ?string $emergency_contact_name = null;
    public ?string $emergency_contact_phone = null;

    protected function rules(): array
    {
        $store = $this->getStoreProperty();

        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('customers', 'email')
                    ->where('store_id', $store?->id ?? 0)
                    ->whereNotNull('email'),
            ],
            'phone_country_code' => ['nullable', 'string', 'regex:/^[0-9]{1,4}$/', 'max:4'],
            'phone' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'document_number' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'gender' => ['nullable', 'string', 'in:M,F,NN', 'max:5'],
            'blood_type' => ['nullable', 'string', 'max:20'],
            'eps' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'regex:/^[0-9]+$/', 'max:30'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'El nombre del cliente es obligatorio.',
            'email.required' => 'El email del cliente es obligatorio.',
            'email.email' => 'Debe ser un correo electrónico válido.',
            'email.unique' => 'Ya existe un cliente con este correo en esta tienda.',
            'phone.required' => 'El teléfono del cliente es obligatorio.',
            'phone.regex' => 'El teléfono solo debe contener números.',
            'phone_country_code.regex' => 'El indicativo solo debe contener números.',
            'document_number.required' => 'El número de documento es obligatorio.',
            'emergency_contact_phone.regex' => 'El número de contacto de emergencia solo debe contener números.',
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function save(CustomerService $customerService)
    {
        $this->email = $this->email ? Str::lower($this->email) : null;
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear clientes en esta tienda.');
        }

        $fullPhone = $this->buildFullPhone();

        try {
            $customerService->createCustomer($store, [
                'name' => $this->name,
                'email' => $this->email ?: null,
                'phone' => $fullPhone,
                'document_number' => $this->document_number ?: null,
                'address' => $this->address ?: null,
                'gender' => $this->gender ?: null,
                'blood_type' => $this->blood_type ?: null,
                'eps' => $this->eps ?: null,
                'birth_date' => $this->birth_date ?: null,
                'emergency_contact_name' => $this->emergency_contact_name ?: null,
                'emergency_contact_phone' => $this->emergency_contact_phone ?: null,
            ]);

            $this->reset(['name', 'email', 'phone_country_code', 'phone', 'document_number', 'address', 'gender', 'blood_type', 'eps', 'birth_date', 'emergency_contact_name', 'emergency_contact_phone']);
            $this->resetValidation();

            return redirect()->route('stores.customers', $store)
                ->with('success', 'Cliente creado correctamente.');
        } catch (\Exception $e) {
            $this->addError('name', $e->getMessage());
        }
    }

    private function buildFullPhone(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->phone);
        if ($digits === '') {
            return null;
        }
        $code = preg_replace('/\D/', '', (string) ($this->phone_country_code ?? ''));
        if ($code !== '') {
            return '+'.$code.$digits;
        }

        return '+'.$digits;
    }

    public function render()
    {
        return view('livewire.create-customer-modal');
    }
}
