<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaContable extends Model
{
    use HasFactory;

    public const TIPO_PRODUCTO = 'producto';

    public const TIPO_SERVICIO = 'servicio';

    public const TIPOS = [
        self::TIPO_PRODUCTO,
        self::TIPO_SERVICIO,
    ];

    protected $table = 'categorias_contables';

    protected $fillable = [
        'store_id',
        'codigo',
        'nombre',
        'tipo',
        'cuenta_inventario_id',
        'cuenta_costo_id',
        'cuenta_ingreso_id',
        'cuenta_devolucion_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function cuentaInventario(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_inventario_id');
    }

    public function cuentaCosto(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_costo_id');
    }

    public function cuentaIngreso(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_ingreso_id');
    }

    public function cuentaDevolucion(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_devolucion_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'categoria_contable_id');
    }

    public function scopeDeStore($query, Store|int $store)
    {
        $storeId = $store instanceof Store ? $store->id : $store;

        return $query->where('store_id', $storeId);
    }

    public function scopeActivas($query)
    {
        return $query->where('activo', true);
    }

    public function esProducto(): bool
    {
        return $this->tipo === self::TIPO_PRODUCTO;
    }

    public function esServicio(): bool
    {
        return $this->tipo === self::TIPO_SERVICIO;
    }

    public function etiquetaTipo(): string
    {
        return $this->esServicio() ? 'Servicio' : 'Producto';
    }
}
