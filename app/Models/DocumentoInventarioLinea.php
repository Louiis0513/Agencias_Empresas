<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentoInventarioLinea extends Model
{
    use HasFactory;

    public const DIRECCION_AUMENTA = 'AUMENTA';

    public const DIRECCION_DISMINUYE = 'DISMINUYE';

    protected $table = 'documento_inventario_lineas';

    protected $fillable = [
        'documento_inventario_id',
        'store_id',
        'orden',
        'product_id',
        'descripcion',
        'direccion',
        'bodega_id',
        'bodega_origen_id',
        'bodega_destino_id',
        'centro_costo_id',
        'cuenta_contable_id',
        'cantidad',
        'cantidad_sistema',
        'cantidad_contada',
        'costo_unitario',
        'costo_total',
    ];

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'cantidad' => 'decimal:4',
            'cantidad_sistema' => 'decimal:4',
            'cantidad_contada' => 'decimal:4',
            'costo_unitario' => 'decimal:4',
            'costo_total' => 'decimal:2',
        ];
    }

    public function etiquetaBodega(): string
    {
        return $this->bodega?->nombre
            ?? $this->bodega?->codigo
            ?? 'Sin asignar';
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

    public function bodegaOrigen(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_origen_id');
    }

    public function bodegaDestino(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_destino_id');
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }

    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CuentaContable::class, 'cuenta_contable_id');
    }

    /**
     * Etiqueta Siigo Aumenta/Disminuye (saldos iniciales sin dirección = Aumenta).
     */
    public function etiquetaDireccion(): string
    {
        return match ($this->direccion) {
            self::DIRECCION_DISMINUYE => 'Disminuye',
            self::DIRECCION_AUMENTA => 'Aumenta',
            default => 'Aumenta',
        };
    }

    public function etiquetaBodegaOrigen(): string
    {
        return $this->bodegaOrigen?->nombre
            ?? $this->bodegaOrigen?->codigo
            ?? 'Sin asignar';
    }

    public function etiquetaBodegaDestino(): string
    {
        return $this->bodegaDestino?->nombre
            ?? $this->bodegaDestino?->codigo
            ?? 'Sin asignar';
    }
}
