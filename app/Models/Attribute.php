<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'name',
        'code',
        'type',
        'is_required',
    ];

    protected $casts = [
        'is_required' => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /** Atributo pertenece a un grupo (pivot con is_required, position). */
    public function groups()
    {
        return $this->belongsToMany(AttributeGroup::class, 'attribute_group_attribute')
            ->withPivot('position', 'is_required')
            ->withTimestamps();
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_attribute')
                    ->withPivot('is_required', 'position')
                    ->withTimestamps()
                    ->orderByPivot('position');
    }

    public function options()
    {
        return $this->hasMany(AttributeOption::class)->orderBy('position');
    }

    public function isSelectType(): bool
    {
        return $this->type === 'select';
    }
}
