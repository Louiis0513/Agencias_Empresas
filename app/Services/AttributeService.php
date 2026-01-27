<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\AttributeOption;
use App\Models\Category;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class AttributeService
{
    /**
     * Grupos de atributos de la tienda (con atributos y opciones).
     */
    public function getStoreAttributeGroups(Store $store)
    {
        return AttributeGroup::where('store_id', $store->id)
            ->with(['attributes' => fn ($q) => $q->with('options')->orderByPivot('position')])
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    /**
     * Atributos de la tienda (lista plana, con options y groups).
     */
    public function getStoreAttributes(Store $store)
    {
        return Attribute::where('store_id', $store->id)
            ->with(['options', 'groups'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Atributos de una categoría, agrupados por grupo.
     */
    public function getCategoryAttributesGrouped(Category $category)
    {
        $category->load(['attributes' => fn ($q) => $q->with(['options', 'groups'])->orderByPivot('position')]);
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
        return $category->attributes()->with('options')->get();
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

            if (empty($data['code'])) {
                $data['code'] = Str::slug($data['name']);
            }
            $code = $data['code'];
            $counter = 1;
            while (Attribute::where('store_id', $store->id)->where('code', $code)->exists()) {
                $code = ($data['code'] ?? '') . '-' . $counter;
                $counter++;
            }

            $attribute = Attribute::create([
                'store_id' => $store->id,
                'name' => $data['name'],
                'code' => $code,
                'type' => $data['type'],
                'is_required' => $data['is_required'] ?? false,
            ]);

            $position = $group->attributes()->count();
            $group->attributes()->attach($attribute->id, [
                'position' => $data['position'] ?? $position,
                'is_required' => $data['is_required'] ?? false,
            ]);

            if ($attribute->type === 'select' && isset($data['options']) && is_array($data['options'])) {
                foreach ($data['options'] as $index => $optionValue) {
                    if (!empty($optionValue)) {
                        AttributeOption::create([
                            'attribute_id' => $attribute->id,
                            'value' => $optionValue,
                            'position' => $index,
                        ]);
                    }
                }
            }

            return $attribute->load(['options', 'groups']);
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

            // Si cambia de tipo select a otro, eliminar opciones
            if ($attribute->type === 'select' && ($data['type'] ?? $attribute->type) !== 'select') {
                $attribute->options()->delete();
            }

            $attribute->update([
                'name' => $data['name'] ?? $attribute->name,
                'type' => $data['type'] ?? $attribute->type,
                'is_required' => $data['is_required'] ?? $attribute->is_required,
            ]);

            // Si es tipo select, actualizar opciones
            if (($data['type'] ?? $attribute->type) === 'select' && isset($data['options']) && is_array($data['options'])) {
                // Eliminar opciones existentes
                $attribute->options()->delete();

                // Crear nuevas opciones
                foreach ($data['options'] as $index => $optionValue) {
                    if (!empty($optionValue)) {
                        AttributeOption::create([
                            'attribute_id' => $attribute->id,
                            'value' => $optionValue,
                            'position' => $index,
                        ]);
                    }
                }
            }

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

            return $attribute->fresh()->load(['options', 'groups']);
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
     * Asignar atributos a una categoría.
     */
    public function assignAttributesToCategory(Category $category, array $attributeIds, array $positions = [], array $requiredFlags = []): void
    {
        DB::transaction(function () use ($category, $attributeIds, $positions, $requiredFlags) {
            // Eliminar asignaciones existentes
            $category->attributes()->detach();

            // Asignar nuevos atributos
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
