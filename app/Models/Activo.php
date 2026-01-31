<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Activo extends Model
{
    use HasFactory;

    /** Control por unidad con serial (computador, caminadora). Cantidad siempre 1. */
    public const CONTROL_SERIALIZADO = 'SERIALIZADO';

    /** Control por lote/granel (sillas, pesas). Cantidad N. */
    public const CONTROL_LOTE = 'LOTE';

    /** Condición física */
    public const CONDITION_NUEVO = 'NUEVO';
    public const CONDITION_BUENO = 'BUENO';
    public const CONDITION_REGULAR = 'REGULAR';
    public const CONDITION_MALO = 'MALO';

    /** Estado operativo */
    public const STATUS_ACTIVO = 'ACTIVO';
    public const STATUS_EN_MANTENIMIENTO = 'EN_MANTENIMIENTO';
    public const STATUS_BAJA = 'BAJA';
    public const STATUS_PRESTADO = 'PRESTADO';

    protected $fillable = [
        'store_id',
        'control_type',
        'name',
        'code',
        'serial_number',
        'model',
        'brand',
        'description',
        'quantity',
        'unit_cost',
        'location',
        'location_id',
        'assigned_to_user_id',
        'condition',
        'status',
        'warranty_expiry',
        'purchase_date',
        'is_active',
        'activo_template_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'is_active' => 'boolean',
        'warranty_expiry' => 'date',
        'purchase_date' => 'date',
    ];

    /** Valor total del activo (cantidad × costo unitario). */
    public function getValorTotalAttribute(): float
    {
        return (float) ($this->quantity * $this->unit_cost);
    }

    public function isSerializado(): bool
    {
        return $this->control_type === self::CONTROL_SERIALIZADO;
    }

    public function isLote(): bool
    {
        return $this->control_type === self::CONTROL_LOTE;
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function locationRelation()
    {
        return $this->belongsTo(ActivoLocation::class, 'location_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function template()
    {
        return $this->belongsTo(Activo::class, 'activo_template_id');
    }

    public function instances()
    {
        return $this->hasMany(Activo::class, 'activo_template_id');
    }

    public function purchaseDetails()
    {
        return $this->hasMany(PurchaseDetail::class);
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoActivo::class)->orderByDesc('created_at');
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function scopeActivos(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeSerializados(Builder $query): void
    {
        $query->where('control_type', self::CONTROL_SERIALIZADO);
    }

    public function scopeLote(Builder $query): void
    {
        $query->where('control_type', self::CONTROL_LOTE);
    }

    public function scopeTemplates(Builder $query): void
    {
        $query->whereNull('activo_template_id');
    }

    public function scopeBuscar(Builder $query, string $term): void
    {
        $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('code', 'like', "%{$term}%")
                ->orWhere('serial_number', 'like', "%{$term}%")
                ->orWhere('model', 'like', "%{$term}%")
                ->orWhere('brand', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%");
        });
    }

    /** Opciones de condición para selects. */
    public static function condicionesDisponibles(): array
    {
        return [
            self::CONDITION_NUEVO => 'Nuevo',
            self::CONDITION_BUENO => 'Bueno',
            self::CONDITION_REGULAR => 'Regular',
            self::CONDITION_MALO => 'Malo',
        ];
    }

    /** Opciones de estado para selects. */
    public static function estadosDisponibles(): array
    {
        return [
            self::STATUS_ACTIVO => 'Activo',
            self::STATUS_EN_MANTENIMIENTO => 'En mantenimiento',
            self::STATUS_BAJA => 'Baja',
            self::STATUS_PRESTADO => 'Prestado',
        ];
    }
}
