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

    public function loadCustomer($customerId = null)
    {
        // Si se llama desde Alpine.js, el parámetro puede venir como objeto { id: X }
        if ($customerId === null) {
            return;
        }
        
        // Extraer el ID si viene como objeto
        if (is_array($customerId) && isset($customerId['id'])) {
            $customerId = $customerId['id'];
        } elseif (is_object($customerId) && isset($customerId->id)) {
            $customerId = $customerId->id;
        } elseif (is_array($customerId) && isset($customerId['customerId'])) {
            $customerId = $customerId['customerId'];
        } elseif (is_object($customerId) && isset($customerId->customerId)) {
            $customerId = $customerId->customerId;
        }
        
        $this->customerId = (int)$customerId;
        
        $store = $this->getStoreProperty();
        if (!$store) {
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
            
            // Abrir el modal
            $this->dispatch('open-modal', 'edit-customer');
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
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
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
            'phone.required' => 'El teléfono del cliente es obligatorio.',
            'document_number.required' => 'El número de documento es obligatorio.',
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

            $this->reset(['customerId', 'name', 'email', 'phone', 'document_number', 'address']);
            $this->resetValidation();

            return redirect()->route('stores.customers', $store)
                ->with('success', 'Cliente actualizado correctamente.');
        } catch (\Exception $e) {
            $this->addError('name', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.edit-customer-modal');
    }
}
