<?php

namespace App\Models;

use App\Services\InventarioService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

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
     * Formatea un array de features (attrId => value) usando un mapa de nombres de atributos.
     * Útil para variantes y para unidades serializadas (ProductItem features).
     *
     * @param  array  $features  ['8' => 'airfuzr', '9' => 'Mango', '10' => '30000']
     * @param  array  $attrNames  [8 => 'Marca', 9 => 'Sabor', 10 => 'Push']
     * @return string  "Marca: airfuzr, Sabor: Mango, Push: 30000" (o "Atributo X: valor" si falta nombre)
     */
    public static function formatFeaturesWithAttributeNames(array $features, array $attrNames): string
    {
        if (empty($features) || ! is_array($features)) {
            return '';
        }
        $parts = [];
        foreach ($features as $attrId => $value) {
            if ((string) $value === '') {
                continue;
            }
            $name = $attrNames[(int) $attrId] ?? $attrNames[(string) $attrId] ?? "Atributo {$attrId}";
            $parts[] = "{$name}: {$value}";
        }
        return implode(', ', $parts);
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
                $attrNames = ($attrs instanceof Collection ? $attrs : collect($attrs))->pluck('name', 'id')->all();
            }
        }

        $formatted = static::formatFeaturesWithAttributeNames($features, $attrNames);

        return $formatted !== '' ? $formatted : '—';
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
