<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Impuesto extends Model
{
    use HasFactory;

    public const TIPO_IVA = 'IVA';

    public const TIPO_RETEFUENTE = 'Retefuente';

    public const TIPO_RETEICA = 'ReteICA';

    public const TIPO_RETEIVA = 'ReteIVA';

    public const TIPO_IMPOCONSUMO = 'Impoconsumo';

    public const TIPO_BEBIDAS_AZUCARADAS = 'Bebidas azucaradas';

    public const TIPO_COMESTIBLES_ULTRAPROCESADOS = 'Comestibles ultraprocesados';

    /** Lista fija estilo Siigo (no editable por el usuario). */
    public const TIPOS = [
        self::TIPO_IVA,
        self::TIPO_RETEFUENTE,
        self::TIPO_RETEICA,
        self::TIPO_RETEIVA,
        self::TIPO_IMPOCONSUMO,
        self::TIPO_BEBIDAS_AZUCARADAS,
        self::TIPO_COMESTIBLES_ULTRAPROCESADOS,
    ];

    protected $table = 'impuestos';

    protected $fillable = [
        'store_id',
        'en_uso',
        'codigo',
        'nombre',
        'tipo',
        'por_valor',
        'tarifa',
        'cuenta_ventas_id',
        'cuenta_compras_id',
        'cuenta_devolucion_ventas_id',
        'cuenta_devolucion_compras_id',
    ];

    protected function casts(): array
    {
        return [
            'en_uso' => 'boolean',
            'por_valor' => 'boolean',
            'codigo' => 'integer',
            'tarifa' => 'decimal:4',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cuentaVentas(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_ventas_id');
    }

    public function cuentaCompras(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_compras_id');
    }

    public function cuentaDevolucionVentas(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_devolucion_ventas_id');
    }

    public function cuentaDevolucionCompras(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_devolucion_compras_id');
    }

    public function scopeDeStore($query, Store|int $store)
    {
        $storeId = $store instanceof Store ? $store->id : $store;

        return $query->where('store_id', $storeId);
    }

    public function scopeEnUso($query)
    {
        return $query->where('en_uso', true);
    }
}
