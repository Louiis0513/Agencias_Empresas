<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ComprobanteContable extends Model
{
    use HasFactory;

    public const ESTADO_BORRADOR = 'BORRADOR';

    public const ESTADO_CONTABILIZADO = 'CONTABILIZADO';

    public const ESTADO_REVERSADO = 'REVERSADO';

    public const ESTADOS = [
        self::ESTADO_BORRADOR,
        self::ESTADO_CONTABILIZADO,
        self::ESTADO_REVERSADO,
    ];

    public const EVENTO_MANUAL = 'ASIENTO_MANUAL';

    public const EVENTO_REVERSO = 'REVERSO_ASIENTO_MANUAL';

    protected $table = 'comprobantes_contables';

    protected $fillable = [
        'store_id',
        'tipo_comprobante_id',
        'numero',
        'fecha',
        'tercero_id',
        'descripcion',
        'estado',
        'evento',
        'total_debito',
        'total_credito',
        'reversa_de_id',
        'created_by',
        'contabilizado_by',
        'contabilizado_at',
        'reversado_by',
        'reversado_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total_debito' => 'decimal:2',
            'total_credito' => 'decimal:2',
            'contabilizado_at' => 'datetime',
            'reversado_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function tipoComprobante(): BelongsTo
    {
        return $this->belongsTo(TipoComprobante::class);
    }

    public function tercero(): BelongsTo
    {
        return $this->belongsTo(Tercero::class);
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoContable::class)->orderBy('orden');
    }

    public function reversaDe(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversa_de_id');
    }

    public function reverso(): HasOne
    {
        return $this->hasOne(self::class, 'reversa_de_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contabilizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contabilizado_by');
    }

    public function reversadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversado_by');
    }

    public function scopeDeStore(Builder $query, Store|int $store): Builder
    {
        $storeId = $store instanceof Store ? $store->id : $store;

        return $query->where('store_id', $storeId);
    }

    public function esBorrador(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }

    public function estaContabilizado(): bool
    {
        return $this->estado === self::ESTADO_CONTABILIZADO;
    }

    public function estaReversado(): bool
    {
        return $this->estado === self::ESTADO_REVERSADO;
    }
}
