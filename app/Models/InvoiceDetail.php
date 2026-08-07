<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'product_name', // Snapshot del nombre del producto
        'receipt_description', // Descripción simplificada para recibo
        'unit_price',   // Snapshot del precio unitario
        'quantity',
        'discount_type',
        'discount_value',
        'discount_amount',
        'subtotal_before_discount',
        'subtotal',
        'subscription_starts_at',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'subtotal_before_discount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'subscription_starts_at' => 'date',
    ];

    // Relación: Un detalle pertenece a una factura
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    // Relación: Un detalle es de un producto específico
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
