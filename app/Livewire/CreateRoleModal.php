<?php

namespace App\Livewire;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Services\StorePermissionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Component;

class CreateRoleModal extends Component
{
    public int $storeId;

    public string $name = '';

    /** @var array<int> */
    public array $selectedPermissions = [];

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'selectedPermissions' => ['array'],
            'selectedPermissions.*' => ['integer', 'exists:permissions,id'],
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function getPermissionsByGroupProperty()
    {
        return Permission::orderBy('slug')->get()->groupBy(fn ($p) => Str::before($p->slug, '.') ?: 'general');
    }

    public function save()
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear roles en esta tienda.');
        }

        $role = Role::create([
            'store_id' => $store->id,
            'name' => $this->name,
        ]);

        $role->permissions()->sync($this->selectedPermissions);

        $userIds = DB::table('store_user')
            ->where('role_id', $role->id)
            ->where('store_id', $store->id)
            ->pluck('user_id');

        $permissionService = app(StorePermissionService::class);
        foreach ($userIds as $userId) {
            $permissionService->clearPermissionCache((int) $userId, $store->id);
        }

        $this->reset(['name', 'selectedPermissions']);
        $this->resetValidation();

        return redirect()->route('stores.roles', $store)
            ->with('success', 'Rol creado correctamente.');
    }

    public function render()
    {
        return view('livewire.create-role-modal');
    }
}
