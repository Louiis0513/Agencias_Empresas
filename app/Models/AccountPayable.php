<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AccountPayable extends Model
{
    use HasFactory;

    protected $table = 'accounts_payables';

    protected $fillable = [
        'store_id',
        'purchase_id',
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

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function payments()
    {
        return $this->hasMany(AccountPayablePayment::class);
    }

    /**
     * Destinos de comprobantes de egreso que abonaron a esta cuenta por pagar.
     * Usar para historial de pagos (nueva estructura).
     */
    public function comprobanteDestinos()
    {
        return $this->hasMany(ComprobanteEgresoDestino::class, 'account_payable_id')
            ->where('type', ComprobanteEgresoDestino::TYPE_CUENTA_POR_PAGAR);
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function scopePendientes(Builder $query): void
    {
        $query->whereIn('status', [self::STATUS_PENDIENTE, self::STATUS_PARCIAL]);
    }

    public function isPagado(): bool
    {
        return $this->status === self::STATUS_PAGADO;
    }
}
