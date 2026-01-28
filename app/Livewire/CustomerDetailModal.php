<?php

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CustomerDetailModal extends Component
{
    public int $storeId;
    public ?int $customerId = null;

    public ?Customer $customer = null;

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

        $this->customer = Customer::where('id', $this->customerId)
            ->where('store_id', $store->id)
            ->with(['user:id,name,email', 'store:id,name'])
            ->first();
            
        // Abrir el modal
        if ($this->customer) {
            $this->dispatch('open-modal', 'customer-detail');
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

        $this->customer = Customer::where('id', $this->customerId)
            ->where('store_id', $store->id)
            ->with(['user:id,name,email', 'store:id,name'])
            ->first();
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function render()
    {
        return view('livewire.customer-detail-modal');
    }
}
