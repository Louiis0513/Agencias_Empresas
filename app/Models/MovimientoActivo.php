<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class MovimientoActivo extends Model
{
    use HasFactory;

    protected $table = 'movimientos_activo';

    protected $fillable = [
        'store_id',
        'user_id',
        'activo_id',
        'purchase_id',
        'type',
        'quantity',
        'description',
        'unit_cost',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'metadata' => 'array',
    ];

    /** Alta del activo (entrada única). */
    public const TYPE_ALTA = 'ALTA';
    /** Baja del activo (salida única). */
    public const TYPE_BAJA = 'BAJA';
    /** Cambio de estado (lifecycle). */
    public const TYPE_CAMBIO_ESTADO = 'CAMBIO_ESTADO';
    /** Asignación de responsable. */
    public const TYPE_ASIGNACION = 'ASIGNACION';
    /** Cambio de ubicación. */
    public const TYPE_CAMBIO_UBICACION = 'CAMBIO_UBICACION';

    public const TYPE_ENTRADA = 'ALTA';
    public const TYPE_SALIDA = 'BAJA';

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function activo()
    {
        return $this->belongsTo(Activo::class);
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function scopePorActivo(Builder $query, int $activoId): void
    {
        $query->where('activo_id', $activoId);
    }

    public function scopePorTipo(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }
}
