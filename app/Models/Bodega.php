<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bodega extends Model
{
    use HasFactory;

    protected $table = 'bodegas';

    protected $fillable = [
        'store_id',
        'codigo',
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeDeStore($query, Store|int $store)
    {
        $storeId = $store instanceof Store ? $store->id : $store;

        return $query->where('store_id', $storeId);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
