<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Maestro de productos y servicios (estilo Siigo).
 * Las cuentas auxiliares viven en la categoría contable, no aquí.
 */
class Product extends Model
{
    use HasFactory;

    public const TIPO_PRODUCTO = 'producto';

    public const TIPO_SERVICIO = 'servicio';

    public const TIPOS = [
        self::TIPO_PRODUCTO,
        self::TIPO_SERVICIO,
    ];

    /** Código DIAN por defecto: unidad. */
    public const UNIDAD_MEDIDA_DIAN_DEFAULT = '94';

    public const MAX_IMAGENES = 5;

    protected $fillable = [
        'store_id',
        'categoria_contable_id',
        'tipo',
        'codigo',
        'nombre',
        'codigo_barras',
        'unidad_medida_dian',
        'es_inventariable',
        'visible_en_ventas',
        'impuesto_cargo_id',
        'impuesto_retencion_id',
        'referencia',
        'unidad_medida_factura',
        'stock_minimo',
        'descripcion',
        'marca',
        'modelo',
        'codigo_arancelario',
        'precio_incluye_iva',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'es_inventariable' => 'boolean',
            'visible_en_ventas' => 'boolean',
            'precio_incluye_iva' => 'boolean',
            'is_active' => 'boolean',
            'stock_minimo' => 'decimal:4',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function categoriaContable(): BelongsTo
    {
        return $this->belongsTo(CategoriaContable::class, 'categoria_contable_id');
    }

    public function impuestoCargo(): BelongsTo
    {
        return $this->belongsTo(Impuesto::class, 'impuesto_cargo_id');
    }

    public function impuestoRetencion(): BelongsTo
    {
        return $this->belongsTo(Impuesto::class, 'impuesto_retencion_id');
    }

    public function precios(): HasMany
    {
        return $this->hasMany(ProductPrecio::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('orden');
    }

    public function invoiceDetails(): HasMany
    {
        return $this->hasMany(InvoiceDetail::class);
    }

    public function scopeDeStore($query, Store|int $store)
    {
        $storeId = $store instanceof Store ? $store->id : $store;

        return $query->where('store_id', $storeId);
    }

    public function scopeActivos($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVisiblesEnVentas($query)
    {
        return $query->where('visible_en_ventas', true);
    }

    public function scopeDeTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function esProducto(): bool
    {
        return $this->tipo === self::TIPO_PRODUCTO;
    }

    public function esServicio(): bool
    {
        return $this->tipo === self::TIPO_SERVICIO;
    }
}
