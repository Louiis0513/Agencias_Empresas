<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttributeGroup extends Model
{
    use HasFactory;

    protected $fillable = ['store_id', 'name', 'position'];

    protected $casts = [
        'position' => 'integer',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'attribute_group_attribute')
            ->withPivot('position', 'is_required')
            ->withTimestamps()
            ->orderByPivot('position');
    }
}
