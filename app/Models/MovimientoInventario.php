<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class MovimientoInventario extends Model
{
    use HasFactory;

    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'store_id',
        'user_id',
        'product_id',
        'product_variant_id',
        'product_item_id',
        'purchase_id',
        'invoice_id',
        'support_document_id',
        'type',
        'quantity',
        'description',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
    ];

    public const TYPE_ENTRADA = 'ENTRADA';
    public const TYPE_SALIDA = 'SALIDA';

    /** Productos con control de inventario (movimientos de entrada/salida). */
    public const PRODUCT_TYPE_INVENTARIO = 'producto';

    /** Productos serializados (cada unidad rastreada individualmente en product_items). */
    public const PRODUCT_TYPE_SERIALIZED = 'serialized';

    /** Productos por lotes (batches + batch_items con variantes). */
    public const PRODUCT_TYPE_BATCH = 'batch';

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function productItem()
    {
        return $this->belongsTo(ProductItem::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function supportDocument()
    {
        return $this->belongsTo(SupportDocument::class);
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function scopePorProducto(Builder $query, int $productId): void
    {
        $query->where('product_id', $productId);
    }

    public function scopePorTipo(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    /**
     * Nombre a mostrar en la columna Producto de la vista de inventario.
     * Simple: nombre. Lote: nombre + variante. Serializado: nombre + serial + features.
     */
    public function getProductDisplayAttribute(): string
    {
        $product = $this->relationLoaded('product') ? $this->product : $this->product;
        $name = $product?->name ?? '—';

        if ($this->productVariant) {
            $variantName = $this->productVariant->display_name;
            return $variantName !== '—'
                ? "{$name} ({$variantName})"
                : $name;
        }

        if ($this->productItem) {
            $item = $this->productItem;
            $serial = $item->serial_number ?? '';
            $features = $item->features;
            $attrIds = ! empty($features) && is_array($features)
                ? array_map('intval', array_keys($features))
                : [];
            $attrNames = $attrIds ? Attribute::whereIn('id', $attrIds)->pluck('name', 'id')->all() : [];
            $formatted = ProductVariant::formatFeaturesWithAttributeNames($features ?? [], $attrNames);
            $extra = $serial !== ''
                ? ($formatted !== '' ? "Serial: {$serial}, {$formatted}" : "Serial: {$serial}")
                : $formatted;
            return $extra !== '' ? "{$name} ({$extra})" : $name;
        }

        return $name;
    }
}
