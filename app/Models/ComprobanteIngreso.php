<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ComprobanteIngreso extends Model
{
    use HasFactory;

    protected $table = 'comprobantes_ingreso';

    protected $fillable = [
        'store_id',
        'number',
        'total_amount',
        'date',
        'notes',
        'type',
        'customer_id',
        'user_id',
        'reversed_at',
        'reversal_user_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'date' => 'date',
        'reversed_at' => 'datetime',
    ];

    public const TYPE_INGRESO_MANUAL = 'INGRESO_MANUAL';
    public const TYPE_COBRO_CUENTA = 'COBRO_CUENTA';

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reversalUser()
    {
        return $this->belongsTo(User::class, 'reversal_user_id');
    }

    public function destinos()
    {
        return $this->hasMany(ComprobanteIngresoDestino::class, 'comprobante_ingreso_id');
    }

    public function aplicaciones()
    {
        return $this->hasMany(ComprobanteIngresoAplicacion::class, 'comprobante_ingreso_id');
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function isReversed(): bool
    {
        return (bool) $this->reversed_at;
    }

    /** True si este ingreso está ligado a una o más cuentas por cobrar (cobro). */
    public function isCobroCuenta(): bool
    {
        return $this->type === self::TYPE_COBRO_CUENTA && $this->aplicaciones()->exists();
    }
}
