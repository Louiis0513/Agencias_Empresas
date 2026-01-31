<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ComprobanteEgreso extends Model
{
    use HasFactory;

    protected $table = 'comprobantes_egreso';

    protected $fillable = [
        'store_id',
        'proveedor_id',
        'number',
        'total_amount',
        'payment_date',
        'notes',
        'type',
        'beneficiary_name',
        'user_id',
        'reversed_at',
        'reversal_user_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'payment_date' => 'date',
        'reversed_at' => 'datetime',
    ];

    public const TYPE_PAGO_CUENTA = 'PAGO_CUENTA';
    public const TYPE_GASTO_DIRECTO = 'GASTO_DIRECTO';
    public const TYPE_MIXTO = 'MIXTO';

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
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
        return $this->hasMany(ComprobanteEgresoDestino::class, 'comprobante_egreso_id');
    }

    public function origenes()
    {
        return $this->hasMany(ComprobanteEgresoOrigen::class, 'comprobante_egreso_id');
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function isReversed(): bool
    {
        return (bool) $this->reversed_at;
    }
}
