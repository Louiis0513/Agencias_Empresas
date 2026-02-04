<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AccountReceivable extends Model
{
    use HasFactory;

    protected $table = 'accounts_receivable';

    protected $fillable = [
        'store_id',
        'invoice_id',
        'customer_id',
        'total_amount',
        'balance',
        'due_date',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'balance' => 'decimal:2',
        'due_date' => 'date',
    ];

    public const STATUS_PENDIENTE = 'PENDIENTE';
    public const STATUS_PARCIAL = 'PARCIAL';
    public const STATUS_PAGADO = 'PAGADO';

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function cuotas()
    {
        return $this->hasMany(AccountReceivableCuota::class, 'account_receivable_id')->orderBy('sequence');
    }

    public function comprobanteIngresoAplicaciones()
    {
        return $this->hasMany(ComprobanteIngresoAplicacion::class, 'account_receivable_id');
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function isPendiente(): bool
    {
        return $this->status === self::STATUS_PENDIENTE;
    }

    public function isPagado(): bool
    {
        return $this->status === self::STATUS_PAGADO;
    }

    public function isParcial(): bool
    {
        return $this->status === self::STATUS_PARCIAL;
    }
}
