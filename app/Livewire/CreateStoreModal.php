<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Store;
use App\Services\StoreService;
use Illuminate\Support\Facades\Auth;

class CreateStoreModal extends Component
{
    public $open = false; // Controla si el modal está abierto o cerrado
    public $name = '';    // El campo del formulario

    protected $rules = [
        'name' => 'required|min:3|max:50',
    ];

    public function save(StoreService $service)
    {
        $this->validate();

        // Llamamos a tu servicio para la lógica dura
        $store = $service->createStore(Auth::user(), $this->name);

        // Limpiamos y cerramos
        $this->reset(['open', 'name']);

        // Opcional: Redirigir directamente a la nueva tienda
        return redirect()->route('stores.dashboard', $store);
    }
    public function getPlanProperty()
{
    return Auth::user()->plan;
}

public function getStoreLimitProperty()
{
    // Si no tiene plan, límite 0. Si tiene, devuelve el max_stores.
    return $this->plan ? $this->plan->max_stores : 0;
}

public function getStoreCountProperty()
{
    // Hacemos la consulta aquí, en el controlador
    return Store::where('user_id', Auth::id())->count();
}

public function getProgressPercentProperty()
{
    // Evitamos división por cero
    if ($this->storeLimit == 0) return 100;
    
    return ($this->storeCount / $this->storeLimit) * 100;
}


    public function render()
    {
        return view('livewire.create-store-modal');
    }
}