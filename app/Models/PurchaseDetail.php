<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'item_type',
        'description',
        'quantity',
        'unit_cost',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public const TYPE_INVENTARIO = 'INVENTARIO';
    public const TYPE_ACTIVO_FIJO = 'ACTIVO_FIJO';

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function isInventario(): bool
    {
        return $this->item_type === self::TYPE_INVENTARIO;
    }

    public function isActivoFijo(): bool
    {
        return $this->item_type === self::TYPE_ACTIVO_FIJO;
    }
}
