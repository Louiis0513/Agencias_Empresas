<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Precio de un producto/servicio en una lista concreta.
 * Solo se insertan filas cuando hay precio configurado (escala sparse).
 */
class ProductPrecio extends Model
{
    protected $table = 'product_precios';

    protected $fillable = [
        'product_id',
        'lista_precio_id',
        'precio',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function listaPrecio(): BelongsTo
    {
        return $this->belongsTo(ListaPrecio::class, 'lista_precio_id');
    }
}
