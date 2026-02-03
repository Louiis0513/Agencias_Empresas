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
        'purchase_id',
        'type',
        'quantity',
        'description',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'integer',
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

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
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
}
