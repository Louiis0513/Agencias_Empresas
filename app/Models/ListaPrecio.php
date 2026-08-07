<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lista de precios de venta por tienda (hasta 12 slots estilo Siigo).
 */
class ListaPrecio extends Model
{
    public const MAX_POR_TIENDA = 12;

    protected $table = 'listas_precios';

    protected $fillable = [
        'store_id',
        'numero',
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function productPrecios(): HasMany
    {
        return $this->hasMany(ProductPrecio::class, 'lista_precio_id');
    }

    public function scopeDeStore($query, Store|int $store)
    {
        $storeId = $store instanceof Store ? $store->id : $store;

        return $query->where('store_id', $storeId);
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }
}
