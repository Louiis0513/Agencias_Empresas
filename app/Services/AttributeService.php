<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\Category;
use App\Models\Store;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AttributeService
{
    /**
     * Grupos de atributos de la tienda (con atributos y opciones).
     */
    public function getStoreAttributeGroups(Store $store)
    {
        return AttributeGroup::where('store_id', $store->id)
            ->with(['attributes' => fn ($q) => $q->orderByPivot('position')])
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    /**
     * Grupos de atributos de la tienda paginados, con filtro por nombre del grupo o de los atributos.
     */
    public function getStoreAttributeGroupsPaginated(Store $store, ?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = AttributeGroup::where('store_id', $store->id)
            ->with(['attributes' => fn ($q) => $q->orderByPivot('position')])
            ->orderBy('position')
            ->orderBy('name');

        if ($search !== null && trim($search) !== '') {
            $searchTerm = '%' . trim($search) . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                    ->orWhereHas('attributes', fn ($attr) => $attr->where('name', 'like', $searchTerm));
            });
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Atributos de la tienda (lista plana, con options y groups).
     */
    public function getStoreAttributes(Store $store)
    {
        return Attribute::where('store_id', $store->id)
            ->with(['groups'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Atributos de una categoría, agrupados por grupo.
     */
    public function getCategoryAttributesGrouped(Category $category)
    {
        $category->load(['attributes' => fn ($q) => $q->with(['groups'])->orderByPivot('position')]);
        $byGroup = [];
        foreach ($category->attributes as $attr) {
            $g = $attr->groups->first();
            $key = $g ? $g->name : 'Sin grupo';
            if (!isset($byGroup[$key])) {
                $byGroup[$key] = ['group' => $g, 'attributes' => []];
            }
            $byGroup[$key]['attributes'][] = $attr;
        }
        return $byGroup;
    }

    /**
     * Obtener atributos de una categoría (lista plana).
     */
    public function getCategoryAttributes(Category $category)
    {
        return $category->attributes()->get();
    }

    /**
     * Crear grupo de atributos.
     */
    public function createAttributeGroup(Store $store, array $data): AttributeGroup
    {
        $maxPos = AttributeGroup::where('store_id', $store->id)->max('position') ?? -1;

        return AttributeGroup::create([
            'store_id' => $store->id,
            'name' => $data['name'],
            'position' => $data['position'] ?? $maxPos + 1,
        ]);
    }

    /**
     * Actualizar grupo.
     */
    public function updateAttributeGroup(Store $store, int $groupId, array $data): AttributeGroup
    {
        $group = AttributeGroup::where('store_id', $store->id)->findOrFail($groupId);
        $group->update([
            'name' => $data['name'] ?? $group->name,
            'position' => $data['position'] ?? $group->position,
        ]);
        return $group->fresh();
    }

    /**
     * Eliminar grupo. Los atributos se quedan (hay que moverlos antes o borrarlos).
     */
    public function deleteAttributeGroup(Store $store, int $groupId): bool
    {
        $group = AttributeGroup::where('store_id', $store->id)->findOrFail($groupId);
        if ($group->attributes()->exists()) {
            throw new Exception('El grupo tiene atributos. Mueve o elimina los atributos antes de borrar el grupo.');
        }
        $group->delete();
        return true;
    }

    /**
     * Crear atributo y añadirlo a un grupo (con is_required y position en el grupo).
     */
    public function createAttribute(Store $store, array $data): Attribute
    {
        return DB::transaction(function () use ($store, $data) {
            $groupId = $data['attribute_group_id'] ?? null;
            if (!$groupId) {
                throw new Exception('Debes seleccionar un grupo de atributos.');
            }

            $group = AttributeGroup::where('store_id', $store->id)->findOrFail($groupId);

            $name = trim($data['name'] ?? '');
            $exists = $group->attributes()
                ->whereRaw('LOWER(attributes.name) = ?', [strtolower($name)])
                ->exists();
            if ($exists) {
                throw new Exception("Ya existe un atributo con el nombre \"{$name}\" en este grupo.");
            }

            $attribute = Attribute::create([
                'store_id' => $store->id,
                'name' => $data['name'],
                'is_required' => $data['is_required'] ?? false,
            ]);

            $position = $group->attributes()->count();
            $group->attributes()->attach($attribute->id, [
                'position' => $data['position'] ?? $position,
                'is_required' => $data['is_required'] ?? false,
            ]);

            return $attribute->load(['groups']);
        });
    }

    /**
     * Actualizar un atributo.
     */
    public function updateAttribute(Store $store, int $attributeId, array $data): Attribute
    {
        return DB::transaction(function () use ($store, $attributeId, $data) {
            $attribute = Attribute::where('id', $attributeId)
                ->where('store_id', $store->id)
                ->firstOrFail();

            $groupId = isset($data['attribute_group_id']) && $data['attribute_group_id']
                ? (int) $data['attribute_group_id']
                : $attribute->groups->first()?->id;

            if ($groupId) {
                $group = AttributeGroup::where('store_id', $store->id)->find($groupId);
                if ($group) {
                    $name = trim($data['name'] ?? $attribute->name ?? '');
                    $exists = $group->attributes()
                        ->where('attributes.id', '!=', $attributeId)
                        ->whereRaw('LOWER(attributes.name) = ?', [strtolower($name)])
                        ->exists();
                    if ($exists) {
                        throw new Exception("Ya existe un atributo con el nombre \"{$name}\" en este grupo.");
                    }
                }
            }

            $attribute->update([
                'name' => $data['name'] ?? $attribute->name,
                'is_required' => $data['is_required'] ?? $attribute->is_required,
            ]);

            // Si se proporciona un nuevo grupo, actualizar la relación
            if (isset($data['attribute_group_id']) && $data['attribute_group_id']) {
                $groupId = (int)$data['attribute_group_id'];
                $group = AttributeGroup::where('id', $groupId)
                    ->where('store_id', $store->id)
                    ->firstOrFail();

                // Detach de todos los grupos primero
                $attribute->groups()->detach();

                // Attach al nuevo grupo con is_required y position
                $isRequired = $data['is_required'] ?? false;
                $position = $group->attributes()->count();
                
                $attribute->groups()->attach($groupId, [
                    'is_required' => $isRequired,
                    'position' => $position,
                ]);
            } elseif (isset($data['attribute_group_id']) && $data['attribute_group_id'] === null) {
                // Si se quiere quitar del grupo (no debería pasar normalmente)
                // Solo actualizamos is_required en el grupo actual si existe
                $currentGroup = $attribute->groups->first();
                if ($currentGroup) {
                    $attribute->groups()->updateExistingPivot($currentGroup->id, [
                        'is_required' => $data['is_required'] ?? false,
                    ]);
                }
            } else {
                // Si no se cambia el grupo, solo actualizar is_required en el grupo actual
                $currentGroup = $attribute->groups->first();
                if ($currentGroup && isset($data['is_required'])) {
                    $attribute->groups()->updateExistingPivot($currentGroup->id, [
                        'is_required' => $data['is_required'],
                    ]);
                }
            }

            return $attribute->fresh()->load(['groups']);
        });
    }

    /**
     * Eliminar un atributo.
     * Valida que no esté siendo usado por categorías con productos.
     */
    public function deleteAttribute(Store $store, int $attributeId): bool
    {
        $attribute = Attribute::where('id', $attributeId)
            ->where('store_id', $store->id)
            ->firstOrFail();

        // Verificar si hay categorías con productos que usen este atributo
        $categoriesWithProducts = $attribute->categories()
            ->whereHas('products')
            ->exists();

        if ($categoriesWithProducts) {
            throw new Exception('No puedes eliminar este atributo porque está siendo usado por categorías que tienen productos.');
        }

        $attribute->delete();

        return true;
    }

    /**
     * Asignar atributos a una categoría (por lista de ids; usado internamente).
     */
    public function assignAttributesToCategory(Category $category, array $attributeIds, array $positions = [], array $requiredFlags = []): void
    {
        DB::transaction(function () use ($category, $attributeIds, $positions, $requiredFlags) {
            $category->attributes()->detach();

            foreach ($attributeIds as $index => $attributeId) {
                if ($attributeId) {
                    $attributeId = (int) $attributeId;
                    $isRequired = isset($requiredFlags[$attributeId]) && $requiredFlags[$attributeId] == '1';

                    $category->attributes()->attach($attributeId, [
                        'is_required' => $isRequired,
                        'position' => $positions[$index] ?? $index,
                    ]);
                }
            }
        });
    }

    /**
     * Asignar grupos de atributos a una categoría.
     * La categoría queda con todos los atributos de los grupos seleccionados.
     * is_required y position de cada atributo se toman del grupo (donde el usuario ya los definió).
     */
    public function assignGroupsToCategory(Category $category, array $attributeGroupIds): void
    {
        $storeId = $category->store_id;

        $groups = AttributeGroup::where('store_id', $storeId)
            ->whereIn('id', $attributeGroupIds)
            ->with(['attributes' => fn ($q) => $q->orderByPivot('position')])
            ->orderBy('position')
            ->get();

        $attributeIds = [];
        $positions = [];
        $requiredFlags = [];
        $positionIndex = 0;
        $seenIds = [];

        foreach ($groups as $group) {
            foreach ($group->attributes as $attribute) {
                if (in_array($attribute->id, $seenIds, true)) {
                    continue;
                }
                $seenIds[] = $attribute->id;
                $attributeIds[] = $attribute->id;
                $positions[] = $positionIndex++;
                $requiredFlags[$attribute->id] = $attribute->pivot->is_required ? '1' : '0';
            }
        }

        $this->assignAttributesToCategory($category, $attributeIds, $positions, $requiredFlags);
    }

    /**
     * Desasignar un atributo de una categoría.
     */
    public function removeAttributeFromCategory(Category $category, int $attributeId): void
    {
        // Validar que la categoría no tenga productos con este atributo
        if ($category->products()->exists()) {
            throw new Exception('No puedes quitar este atributo porque la categoría ya tiene productos.');
        }

        $category->attributes()->detach($attributeId);
    }
}
