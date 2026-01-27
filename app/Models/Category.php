<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['store_id', 'parent_id', 'name'];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    // Relaciones recursivas para jerarquía
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    // Helper para obtener todas las categorías hijas recursivamente
    public function childrenRecursive()
    {
        return $this->children()->with('childrenRecursive');
    }

    // Relación many-to-many con atributos
    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'category_attribute')
                    ->withPivot('is_required', 'position')
                    ->withTimestamps()
                    ->orderByPivot('position');
    }
}
