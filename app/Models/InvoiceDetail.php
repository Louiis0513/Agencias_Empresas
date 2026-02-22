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
        'product_name', // Snapshot del nombre del producto (o "Suscripción: ...")
        'unit_price',   // Snapshot del precio unitario
        'quantity',
        'subtotal',
        'store_plan_id',
        'subscription_starts_at',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'quantity' => 'integer',
        'subtotal' => 'decimal:2',
        'subscription_starts_at' => 'date',
    ];

    // Relación: Un detalle pertenece a una factura
    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    // Relación: Un detalle es de un producto específico (null para líneas de suscripción)
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    // Relación: Plan de suscripción (solo en líneas tipo suscripción)
    public function storePlan()
    {
        return $this->belongsTo(StorePlan::class, 'store_plan_id');
    }

    /** Indica si la línea es de suscripción (no producto de inventario). */
    public function isSubscriptionLine(): bool
    {
        return $this->product_id === null;
    }
}
