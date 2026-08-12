<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MovimientoInventario extends Model
{
    use HasFactory;

    public const DIRECCION_ENTRADA = 'ENTRADA';

    public const DIRECCION_SALIDA = 'SALIDA';

    public const CLASE_SALDO_INICIAL = 'SALDO_INICIAL';

    public const CLASE_COMPRA = 'COMPRA';

    public const CLASE_VENTA = 'VENTA';

    public const CLASE_AJUSTE_ENTRADA = 'AJUSTE_ENTRADA';

    public const CLASE_AJUSTE_SALIDA = 'AJUSTE_SALIDA';

    public const CLASE_TRASLADO_SALIDA = 'TRASLADO_SALIDA';

    public const CLASE_TRASLADO_ENTRADA = 'TRASLADO_ENTRADA';

    public const CLASE_CONTEO_ENTRADA = 'CONTEO_ENTRADA';

    public const CLASE_CONTEO_SALIDA = 'CONTEO_SALIDA';

    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'store_id',
        'product_id',
        'bodega_id',
        'fecha',
        'clase_movimiento',
        'direccion',
        'cantidad',
        'costo_unitario_entrada',
        'valor_entrada',
        'documento_type',
        'documento_id',
        'documento_etiqueta',
        'descripcion',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'cantidad' => 'decimal:4',
            'costo_unitario_entrada' => 'decimal:4',
            'valor_entrada' => 'decimal:2',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documento(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeDeStore($query, Store|int $store)
    {
        $storeId = $store instanceof Store ? $store->id : $store;

        return $query->where('store_id', $storeId);
    }

    public function esEntrada(): bool
    {
        return $this->direccion === self::DIRECCION_ENTRADA;
    }
}
