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
        'unit_price',   // Snapshot del precio unitario
        'quantity',
        'subtotal'
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'subtotal' => 'decimal:2',
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
