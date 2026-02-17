<?php

namespace App\Models;

use App\Services\InventarioService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'features',
        'cost_reference',
        'price',
        'barcode',
        'sku',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'cost_reference' => 'decimal:2',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batchItems()
    {
        return $this->hasMany(BatchItem::class);
    }

    /**
     * Stock total de esta variante: suma de quantity en todos sus batch_items.
     */
    public function getTotalStockAttribute(): int
    {
        return (int) $this->batchItems()->sum('quantity');
    }

    /**
     * Clave normalizada para comparar variantes (misma lógica que
     * InventarioService::detectorDeVariantesEnLotes).
     */
    public function getNormalizedKeyAttribute(): string
    {
        return InventarioService::detectorDeVariantesEnLotes($this->features);
    }

    /**
     * Nombre legible de la variante: "Talla: M, Color: Rojo".
     * Intenta resolver los IDs de atributos a sus nombres usando la categoría del producto.
     */
    public function getDisplayNameAttribute(): string
    {
        $features = $this->features;
        if (empty($features) || ! is_array($features)) {
            return '—';
        }

        $attrNames = [];
        $product = $this->relationLoaded('product') ? $this->product : $this->product;
        if ($product && $product->category) {
            $category = $product->relationLoaded('category') ? $product->category : $product->category()->with('attributes')->first();
            if ($category) {
                $attrs = $category->relationLoaded('attributes') ? $category->attributes : $category->attributes;
                $attrNames = $attrs->pluck('name', 'id')->all();
            }
        }

        $parts = [];
        foreach ($features as $attrId => $value) {
            $name = $attrNames[(int) $attrId] ?? $attrNames[(string) $attrId] ?? "Atributo {$attrId}";
            $parts[] = "{$name}: {$value}";
        }

        return implode(', ', $parts);
    }

    /**
     * Precio de venta efectivo: el de la variante si está definido,
     * si no el del producto padre.
     */
    public function getSellingPriceAttribute(): float
    {
        if ($this->price !== null) {
            return (float) $this->price;
        }

        $product = $this->relationLoaded('product') ? $this->product : $this->product;

        return (float) ($product->price ?? 0);
    }
}
