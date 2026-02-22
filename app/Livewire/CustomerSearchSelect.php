<?php

namespace App\Livewire;

use App\Models\Customer;
use Livewire\Component;

class CustomerSearchSelect extends Component
{
    public int $storeId;

    public ?int $selectedCustomerId = null;

    public string $emitEventName = 'customer-selected';

    public bool $mostrarModal = false;

    public string $filtroClienteNombre = '';

    public string $filtroClienteDocumento = '';

    public string $filtroClienteTelefono = '';

    public array $clientesEncontrados = [];

    public ?array $clienteSeleccionado = null;

    public function mount(int $storeId, ?int $selectedCustomerId = null, string $emitEventName = 'customer-selected'): void
    {
        $this->storeId = $storeId;
        $this->selectedCustomerId = $selectedCustomerId;
        $this->emitEventName = $emitEventName;

        if ($selectedCustomerId) {
            $this->loadSelectedCustomer($selectedCustomerId);
        }
    }

    public function updatedSelectedCustomerId($value): void
    {
        if ($value) {
            $this->loadSelectedCustomer((int) $value);
        } else {
            $this->clienteSeleccionado = null;
        }
    }

    protected function loadSelectedCustomer(int $customerId): void
    {
        $cliente = Customer::where('id', $customerId)
            ->where('store_id', $this->storeId)
            ->first();

        if ($cliente) {
            $this->clienteSeleccionado = [
                'id' => $cliente->id,
                'name' => $cliente->name,
                'document_number' => $cliente->document_number,
                'email' => $cliente->email,
                'phone' => $cliente->phone,
            ];
        } else {
            $this->clienteSeleccionado = null;
        }
    }

    public function abrirModal(): void
    {
        $this->mostrarModal = true;
        $this->filtroClienteNombre = '';
        $this->filtroClienteDocumento = '';
        $this->filtroClienteTelefono = '';
        $this->clientesEncontrados = [];
    }

    public function cerrarModal(): void
    {
        $this->mostrarModal = false;
        $this->filtroClienteNombre = '';
        $this->filtroClienteDocumento = '';
        $this->filtroClienteTelefono = '';
        $this->clientesEncontrados = [];
    }

    public function buscarClientes(): void
    {
        $nombre = trim($this->filtroClienteNombre);
        $documento = trim($this->filtroClienteDocumento);
        $telefono = trim($this->filtroClienteTelefono);

        if ($nombre === '' && $documento === '' && $telefono === '') {
            $this->clientesEncontrados = [];
            return;
        }

        $query = Customer::deTienda($this->storeId);

        if ($nombre !== '') {
            $query->where('name', 'like', '%' . $nombre . '%');
        }
        if ($documento !== '') {
            $query->where('document_number', 'like', '%' . $documento . '%');
        }
        if ($telefono !== '') {
            $query->where('phone', 'like', '%' . $telefono . '%');
        }

        $this->clientesEncontrados = $query->orderBy('name')
            ->limit(15)
            ->get()
            ->map(fn ($customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                'document_number' => $customer->document_number,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ])
            ->toArray();
    }

    public function seleccionarCliente($clienteId): void
    {
        $cliente = Customer::where('id', $clienteId)
            ->where('store_id', $this->storeId)
            ->first();

        if ($cliente) {
            $this->clienteSeleccionado = [
                'id' => $cliente->id,
                'name' => $cliente->name,
                'document_number' => $cliente->document_number,
                'email' => $cliente->email,
                'phone' => $cliente->phone,
            ];
            $this->selectedCustomerId = $cliente->id;
            $this->cerrarModal();

            $this->dispatch($this->emitEventName, customer_id: $cliente->id, customer: $this->clienteSeleccionado);
        }
    }

    public function limpiarCliente(): void
    {
        $this->clienteSeleccionado = null;
        $this->selectedCustomerId = null;
        $this->cerrarModal();
        $this->dispatch('customer-cleared');
    }

    public function render()
    {
        return view('livewire.customer-search-select');
    }
}
