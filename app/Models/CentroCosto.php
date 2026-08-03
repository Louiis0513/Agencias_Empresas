<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentroCosto extends Model
{
    use HasFactory;

    protected $table = 'centros_costo';

    protected $fillable = [
        'store_id',
        'codigo',
        'nombre',
        'activo',
        'parent_id',
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

    public function padre(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function movimientosContables(): HasMany
    {
        return $this->hasMany(MovimientoContable::class, 'centro_costo_id');
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

    public function scopeCentros($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeSubcentros($query)
    {
        return $query->whereNotNull('parent_id');
    }

    public function esCentro(): bool
    {
        return $this->parent_id === null;
    }

    public function esSubcentro(): bool
    {
        return $this->parent_id !== null;
    }
}
