<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoInventarioLinea extends Model
{
    use HasFactory;

    protected $table = 'documento_inventario_lineas';

    protected $fillable = [
        'documento_inventario_id',
        'store_id',
        'orden',
        'product_id',
        'descripcion',
        'bodega_id',
        'centro_costo_id',
        'cantidad',
        'costo_unitario',
        'costo_total',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'cantidad' => 'decimal:4',
            'costo_unitario' => 'decimal:4',
            'costo_total' => 'decimal:2',
        ];
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(DocumentoInventario::class, 'documento_inventario_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class);
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }
}
