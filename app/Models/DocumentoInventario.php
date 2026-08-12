<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DocumentoInventario extends Model
{
    use HasFactory;

    public const TIPO_SALDO_INICIAL = 'SALDO_INICIAL';

    public const TIPO_AJUSTE = 'AJUSTE';

    public const TIPO_TRASLADO = 'TRASLADO';

    public const TIPO_CONTEO_FISICO = 'CONTEO_FISICO';

    public const ESTADO_CONTABILIZADO = 'CONTABILIZADO';

    protected $table = 'documentos_inventario';

    protected $fillable = [
        'store_id',
        'tipo_comprobante_id',
        'numero',
        'tipo_documento',
        'fecha',
        'tercero_nombre',
        'observaciones',
        'total',
        'total_debito',
        'total_credito',
        'estado',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total' => 'decimal:2',
            'total_debito' => 'decimal:2',
            'total_credito' => 'decimal:2',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function tipoComprobante(): BelongsTo
    {
        return $this->belongsTo(TipoComprobante::class, 'tipo_comprobante_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(DocumentoInventarioLinea::class, 'documento_inventario_id')
            ->orderBy('orden');
    }

    public function movimientosInventario(): MorphMany
    {
        return $this->morphMany(MovimientoInventario::class, 'documento');
    }

    public function movimientosContables(): HasMany
    {
        return $this->hasMany(MovimientoContable::class, 'documento_inventario_id')
            ->orderBy('orden');
    }

    public function scopeDeStore($query, Store|int $store)
    {
        $storeId = $store instanceof Store ? $store->id : $store;

        return $query->where('store_id', $storeId);
    }

    public function tituloTipoDocumento(): string
    {
        return match ($this->tipo_documento) {
            self::TIPO_SALDO_INICIAL => 'Ajuste / Saldo inicial de inventario',
            self::TIPO_AJUSTE => 'Ajuste de inventario',
            self::TIPO_TRASLADO => 'Nota de traslado entre bodegas',
            self::TIPO_CONTEO_FISICO => 'Conteo físico',
            default => $this->tipo_documento,
        };
    }

    /**
     * Etiqueta Siigo de naturaleza del movimiento a nivel documento (fallback).
     * En ajustes la naturaleza va por línea.
     */
    public function etiquetaNaturaleza(): string
    {
        return match ($this->tipo_documento) {
            self::TIPO_SALDO_INICIAL => 'Aumenta',
            self::TIPO_AJUSTE => 'Ajuste',
            self::TIPO_TRASLADO => 'Traslado',
            self::TIPO_CONTEO_FISICO => 'Conteo',
            default => 'Aumenta',
        };
    }

    public function esTraslado(): bool
    {
        return $this->tipo_documento === self::TIPO_TRASLADO;
    }

    public function esConteoFisico(): bool
    {
        return $this->tipo_documento === self::TIPO_CONTEO_FISICO;
    }

    /**
     * Número para impresión (ej. A-0002 → 2, o el número crudo si no aplica).
     */
    public function numeroImpresion(): string
    {
        if (preg_match('/(\d+)\s*$/', (string) $this->numero, $m)) {
            return (string) ((int) $m[1]);
        }

        return (string) $this->numero;
    }
}
