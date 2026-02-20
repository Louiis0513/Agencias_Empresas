<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Activo extends Model
{
    use HasFactory;

    /** Condición física */
    public const CONDITION_NUEVO = 'NUEVO';
    public const CONDITION_BUENO = 'BUENO';
    public const CONDITION_REGULAR = 'REGULAR';
    public const CONDITION_MALO = 'MALO';

    /** Lifecycle: estado operativo del activo (1 activo físico = 1 registro) */
    public const STATUS_OPERATIVO = 'OPERATIVO';
    public const STATUS_EN_REPARACION = 'EN_REPARACION';
    public const STATUS_EN_PRESTAMO = 'EN_PRESTAMO';
    public const STATUS_DONADO = 'DONADO';
    public const STATUS_DADO_DE_BAJA = 'DADO_DE_BAJA';
    public const STATUS_VENDIDO = 'VENDIDO';

    protected $fillable = [
        'store_id',
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
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'is_active' => 'boolean',
        'warranty_expiry' => 'date',
        'purchase_date' => 'date',
    ];

    /** Valor del activo (1 unidad × costo unitario). */
    public function getValorTotalAttribute(): float
    {
        return (float) ($this->unit_cost ?? 0);
    }

    /** No se puede vender ni donar un activo en reparación o en préstamo. */
    public function puedePasarA(string $nuevoStatus): bool
    {
        $noPermitidoDesde = [self::STATUS_EN_REPARACION, self::STATUS_EN_PRESTAMO];
        $transicionesRestringidas = [self::STATUS_VENDIDO, self::STATUS_DONADO];

        if (in_array($nuevoStatus, $transicionesRestringidas, true)
            && in_array($this->status, $noPermitidoDesde, true)) {
            return false;
        }

        return true;
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

    /** Opciones de estado (lifecycle) para selects. */
    public static function estadosDisponibles(): array
    {
        return [
            self::STATUS_OPERATIVO => 'Operativo',
            self::STATUS_EN_REPARACION => 'En reparación',
            self::STATUS_EN_PRESTAMO => 'En préstamo',
            self::STATUS_DONADO => 'Donado',
            self::STATUS_DADO_DE_BAJA => 'Dado de baja',
            self::STATUS_VENDIDO => 'Vendido',
        ];
    }
}
