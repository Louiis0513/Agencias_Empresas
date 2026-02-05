<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BatchItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'quantity',
        'features',
        'unit_cost',
        'price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'price' => 'decimal:2',
        'features' => 'array',
    ];

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * Precio de venta efectivo: el de la variante si está definido, si no el del producto.
     * Requiere tener cargado batch.product para el fallback.
     */
    public function getSellingPriceAttribute(): float
    {
        if (isset($this->attributes['price']) && $this->attributes['price'] !== null) {
            return (float) $this->attributes['price'];
        }
        $product = $this->relationLoaded('batch') && $this->batch
            ? $this->batch->product
            : $this->batch?->product;

        return (float) ($product->price ?? 0);
    }
}
