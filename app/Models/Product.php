<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'category_id',
        'name',
        'barcode',
        'sku',
        'price',
        'cost',
        'stock',
        'location',
        'type',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost' => 'decimal:2',
        'stock' => 'integer',
        'is_active' => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function attributeValues()
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'product_attribute_values')
            ->withPivot('value')
            ->withTimestamps();
    }

    public function invoiceDetails()
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    public function movimientosInventario()
    {
        return $this->hasMany(MovimientoInventario::class);
    }

    public function productItems()
    {
        return $this->hasMany(ProductItem::class);
    }

    public function batches()
    {
        return $this->hasMany(Batch::class);
    }

    public function proveedores()
    {
        return $this->belongsToMany(Proveedor::class, 'producto_proveedor')
            ->withTimestamps();
    }

    /** Indica si el producto tiene control de inventario (serializado o por lotes). */
    public function isProductoInventario(): bool
    {
        return in_array($this->type, [MovimientoInventario::PRODUCT_TYPE_SERIALIZED, MovimientoInventario::PRODUCT_TYPE_BATCH], true);
    }

    /** Indica si el producto es serializado (cada unidad en product_items). */
    public function isSerialized(): bool
    {
        return $this->type === MovimientoInventario::PRODUCT_TYPE_SERIALIZED;
    }

    /** Indica si el producto es por lotes (batches + batch_items). */
    public function isBatch(): bool
    {
        return $this->type === MovimientoInventario::PRODUCT_TYPE_BATCH;
    }
}
