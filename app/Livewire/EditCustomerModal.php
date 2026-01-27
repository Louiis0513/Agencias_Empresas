<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Store;
use App\Services\CustomerService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EditCustomerModal extends Component
{
    public int $storeId;
    public ?int $customerId = null;

    public string $name = '';
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $document_number = null;
    public ?string $address = null;

    protected $listeners = ['open-modal' => 'loadCustomer'];

    public function loadCustomer($data)
    {
        if (isset($data['modal']) && $data['modal'] === 'edit-customer' && isset($data['customerId'])) {
            $this->customerId = $data['customerId'];
            $this->loadCustomerData();
        }
    }

    public function loadCustomerData()
    {
        if (!$this->customerId) {
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
            $this->phone = $customer->phone;
            $this->document_number = $customer->document_number;
            $this->address = $customer->address;
        }
    }

    protected function rules(): array
    {
        $store = $this->getStoreProperty();

        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'document_number' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'El nombre del cliente es obligatorio.',
            'email.email' => 'Debe ser un correo electrónico válido.',
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function update(CustomerService $customerService)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para editar clientes en esta tienda.');
        }

        if (!$this->customerId) {
            return;
        }

        try {
            $customerService->updateCustomer($store, $this->customerId, [
                'name' => $this->name,
                'email' => $this->email ?: null,
                'phone' => $this->phone ?: null,
                'document_number' => $this->document_number ?: null,
                'address' => $this->address ?: null,
            ]);

            $this->dispatch('customer-updated');
            $this->dispatch('close-modal', 'edit-customer');
            $this->reset(['customerId', 'name', 'email', 'phone', 'document_number', 'address']);
        } catch (\Exception $e) {
            $this->addError('name', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.edit-customer-modal');
    }
}
