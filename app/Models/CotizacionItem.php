<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CotizacionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cotizacion_id',
        'product_id',
        'type',
        'quantity',
        'variant_features',
        'serial_numbers',
        'name',
        'variant_display_name',
    ];

    protected $casts = [
        'variant_features' => 'array',
        'serial_numbers' => 'array',
        'quantity' => 'integer',
    ];

    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
