<?php

namespace App\Livewire;

use App\Models\Store;
use App\Models\Tercero;
use App\Services\TerceroService;
use Livewire\Component;

class CustomerSearchSelect extends Component
{
    public int $storeId;

    public ?int $selectedCustomerId = null;

    public ?int $selectedTerceroId = null;

    public string $emitEventName = 'customer-selected';

    public string $emitClearEventName = 'customer-cleared';

    public bool $mostrarModal = false;

    public string $filtroClienteNombre = '';

    public string $filtroClienteDocumento = '';

    public string $filtroClienteTelefono = '';

    public array $clientesEncontrados = [];

    public ?array $clienteSeleccionado = null;

    public bool $showConsumidorFinalButton = false;

    public function mount(
        int $storeId,
        ?int $selectedCustomerId = null,
        string $emitEventName = 'customer-selected',
        string $emitClearEventName = 'customer-cleared',
        bool $showConsumidorFinalButton = false,
        ?int $selectedTerceroId = null,
    ): void {
        $selectedCustomerId = $selectedTerceroId ?? $selectedCustomerId;
        $this->storeId = $storeId;
        $this->selectedCustomerId = $selectedCustomerId;
        $this->selectedTerceroId = $selectedCustomerId;
        $this->emitEventName = $emitEventName;
        $this->emitClearEventName = $emitClearEventName;
        $this->showConsumidorFinalButton = $showConsumidorFinalButton;

        if ($selectedCustomerId) {
            $this->loadSelectedCustomer($selectedCustomerId);
        }
    }

    public function updatedSelectedCustomerId($value): void
    {
        $this->selectedTerceroId = $value ? (int) $value : null;
        if ($value) {
            $this->loadSelectedCustomer((int) $value);
        } else {
            $this->clienteSeleccionado = null;
        }
    }

    public function updatedSelectedTerceroId($value): void
    {
        $this->selectedCustomerId = $value ? (int) $value : null;
        $value ? $this->loadSelectedCustomer((int) $value) : $this->clienteSeleccionado = null;
    }

    protected function loadSelectedCustomer(int $customerId): void
    {
        $cliente = Tercero::where('id', $customerId)
            ->where('store_id', $this->storeId)
            ->conRol(Tercero::ROL_CLIENTE)
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

        $query = Tercero::deStore($this->storeId)->conRol(Tercero::ROL_CLIENTE);

        if ($nombre !== '') {
            $query->where('nombre', 'like', '%' . $nombre . '%');
        }
        if ($documento !== '') {
            $query->where('numero_identificacion', 'like', '%' . $documento . '%');
        }
        if ($telefono !== '') {
            $query->where('telefono', 'like', '%' . $telefono . '%');
        }

        $this->clientesEncontrados = $query->orderBy('nombre')
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

    public function aplicarConsumidorFinal(): void
    {
        $store = Store::findOrFail($this->storeId);
        $cliente = app(TerceroService::class)->asegurarConsumidorFinal($store);
        $this->seleccionarCliente($cliente->id);
    }

    public function seleccionarCliente($clienteId): void
    {
        $cliente = Tercero::where('id', $clienteId)
            ->where('store_id', $this->storeId)
            ->conRol(Tercero::ROL_CLIENTE)
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
            $this->selectedTerceroId = $cliente->id;
            $this->cerrarModal();

            $this->dispatch($this->emitEventName, customer_id: $cliente->id, customer: $this->clienteSeleccionado);
        }
    }

    public function limpiarCliente(): void
    {
        $this->clienteSeleccionado = null;
        $this->selectedCustomerId = null;
        $this->selectedTerceroId = null;
        $this->cerrarModal();
        $this->dispatch($this->emitClearEventName);
    }

    public function render()
    {
        return view('livewire.customer-search-select');
    }
}
