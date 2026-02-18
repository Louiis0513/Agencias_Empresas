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

class EditRoleModal extends Component
{
    public int $storeId;

    public ?int $roleId = null;

    public string $name = '';

    /** @var array<int> */
    public array $selectedPermissions = [];

    public function loadRole($roleId = null)
    {
        if ($roleId === null) {
            return;
        }

        if (is_array($roleId) && isset($roleId['id'])) {
            $roleId = $roleId['id'];
        } elseif (is_object($roleId) && isset($roleId->id)) {
            $roleId = $roleId->id;
        }

        $this->roleId = (int) $roleId;

        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }

        $role = Role::where('id', $this->roleId)
            ->where('store_id', $store->id)
            ->first();

        if ($role) {
            $this->name = $role->name;
            $this->selectedPermissions = $role->permissions()->pluck('id')->all();
            $this->dispatch('open-modal', 'edit-role');
        }
    }

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

    public function update()
    {
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para editar roles en esta tienda.');
        }

        if (! $this->roleId) {
            return;
        }

        $role = Role::where('id', $this->roleId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        $role->update(['name' => $this->name]);
        $role->permissions()->sync($this->selectedPermissions);

        $userIds = DB::table('store_user')
            ->where('role_id', $role->id)
            ->where('store_id', $store->id)
            ->pluck('user_id');

        $permissionService = app(StorePermissionService::class);
        foreach ($userIds as $userId) {
            $permissionService->clearPermissionCache((int) $userId, $store->id);
        }

        $this->reset(['name', 'roleId', 'selectedPermissions']);
        $this->resetValidation();

        return redirect()->route('stores.roles', $store)
            ->with('success', 'Rol actualizado correctamente.');
    }

    public function render()
    {
        return view('livewire.edit-role-modal');
    }
}
