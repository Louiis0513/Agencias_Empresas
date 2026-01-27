<?php

namespace App\Livewire;

use App\Models\Store;
use App\Services\CustomerService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CreateCustomerModal extends Component
{
    public int $storeId;

    public string $name = '';
    public ?string $email = null;
    public ?string $phone = null;
    public ?string $document_number = null;
    public ?string $address = null;

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

    public function save(CustomerService $customerService)
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear clientes en esta tienda.');
        }

        try {
            $customerService->createCustomer($store, [
                'name' => $this->name,
                'email' => $this->email ?: null,
                'phone' => $this->phone ?: null,
                'document_number' => $this->document_number ?: null,
                'address' => $this->address ?: null,
            ]);

            $this->dispatch('customer-created');
            $this->dispatch('close-modal', 'create-customer');
            $this->reset(['name', 'email', 'phone', 'document_number', 'address']);
        } catch (\Exception $e) {
            $this->addError('name', $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.create-customer-modal');
    }
}
