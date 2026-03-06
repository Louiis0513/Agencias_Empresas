<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Store;
use App\Services\CustomerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class EditCustomerModal extends Component
{
    public int $storeId;
    public ?int $customerId = null;

    public string $name = '';
    public ?string $email = null;
    public ?string $phone_country_code = '57';
    public ?string $phone = null;
    public ?string $document_number = null;
    public ?string $address = null;

    private const COUNTRY_CODES = ['593', '598', '595', '591', '503', '502', '506', '507', '505', '504', '57', '52', '54', '51', '58', '34', '56', '1'];

    public function loadCustomer($customerId = null)
    {
        if ($customerId === null) {
            return;
        }

        if (is_array($customerId) && isset($customerId['id'])) {
            $customerId = $customerId['id'];
        } elseif (is_object($customerId) && isset($customerId->id)) {
            $customerId = $customerId->id;
        } elseif (is_array($customerId) && isset($customerId['customerId'])) {
            $customerId = $customerId['customerId'];
        } elseif (is_object($customerId) && isset($customerId->customerId)) {
            $customerId = $customerId->customerId;
        }

        $this->customerId = (int) $customerId;

        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }

        $customer = Customer::where('id', $this->customerId)
            ->where('store_id', $store->id)
            ->first();

        if ($customer) {
            $this->name = $customer->name;
            $this->email = $customer->email;
            $this->document_number = $customer->document_number;
            $this->address = $customer->address;

            $parsed = $this->parsePhone($customer->phone);
            $this->phone_country_code = $parsed['code'] ?? '57';
            $this->phone = $parsed['number'] ?? '';

            $this->dispatch('open-modal', 'edit-customer');
        }
    }

    public function loadCustomerData()
    {
        if (! $this->customerId) {
            return;
        }

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            return;
        }

        $customer = Customer::where('id', $this->customerId)
            ->where('store_id', $store->id)
            ->first();

        if ($customer) {
            $this->name = $customer->name;
            $this->email = $customer->email;
            $this->document_number = $customer->document_number;
            $this->address = $customer->address;

            $parsed = $this->parsePhone($customer->phone);
            $this->phone_country_code = $parsed['code'] ?? '57';
            $this->phone = $parsed['number'] ?? '';
        }
    }

    private function parsePhone(?string $phone): array
    {
        if (! $phone || trim($phone) === '') {
            return ['code' => '57', 'number' => ''];
        }

        $v = preg_replace('/\D/', '', $phone);
        if ($v === '') {
            return ['code' => '57', 'number' => ''];
        }

        foreach (self::COUNTRY_CODES as $code) {
            if (str_starts_with($v, $code)) {
                return [
                    'code' => $code,
                    'number' => substr($v, strlen($code)) ?: '',
                ];
            }
        }

        return ['code' => '57', 'number' => $v];
    }

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
                    ->whereNotNull('email')
                    ->ignore($this->customerId),
            ],
            'phone_country_code' => ['nullable', 'string', 'regex:/^[0-9]{1,4}$/', 'max:4'],
            'phone' => ['required', 'string', 'regex:/^[0-9]+$/', 'max:20'],
            'document_number' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
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
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function update(CustomerService $customerService)
    {
        $this->email = $this->email ? Str::lower($this->email) : null;
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para editar clientes en esta tienda.');
        }

        if (! $this->customerId) {
            return;
        }

        $fullPhone = $this->buildFullPhone();

        try {
            $customerService->updateCustomer($store, $this->customerId, [
                'name' => $this->name,
                'email' => $this->email ?: null,
                'phone' => $fullPhone,
                'document_number' => $this->document_number ?: null,
                'address' => $this->address ?: null,
            ]);

            $this->reset(['customerId', 'name', 'email', 'phone_country_code', 'phone', 'document_number', 'address']);
            $this->resetValidation();

            return redirect()->route('stores.customers', $store)
                ->with('success', 'Cliente actualizado correctamente.');
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
        return view('livewire.edit-customer-modal');
    }
}
