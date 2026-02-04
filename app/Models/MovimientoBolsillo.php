<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;

class MovimientoBolsillo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'movimientos_bolsillo';

    protected $fillable = [
        'store_id',
        'bolsillo_id',
        'invoice_id',
        'account_payable_payment_id',
        'comprobante_egreso_id',
        'comprobante_ingreso_id',
        'reversal_of_account_payable_payment_id',
        'reversal_of_comprobante_egreso_id',
        'reversal_of_comprobante_ingreso_id',
        'user_id',
        'type',
        'amount',
        'payment_method',
        'description',
    ];

    public const PAYMENT_CASH = 'CASH';
    public const PAYMENT_CARD = 'CARD';
    public const PAYMENT_TRANSFER = 'TRANSFER';

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public const TYPE_INCOME = 'INCOME';
    public const TYPE_EXPENSE = 'EXPENSE';

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function bolsillo()
    {
        return $this->belongsTo(Bolsillo::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function accountPayablePayment()
    {
        return $this->belongsTo(AccountPayablePayment::class);
    }

    public function comprobanteEgreso()
    {
        return $this->belongsTo(ComprobanteEgreso::class);
    }

    public function comprobanteIngreso()
    {
        return $this->belongsTo(ComprobanteIngreso::class);
    }

    public function reversalOfAccountPayablePayment()
    {
        return $this->belongsTo(AccountPayablePayment::class, 'reversal_of_account_payable_payment_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->where('store_id', $storeId);
    }

    public function scopePorBolsillo(Builder $query, int $bolsilloId): void
    {
        $query->where('bolsillo_id', $bolsilloId);
    }

    public function scopePorTipo(Builder $query, string $type): void
    {
        $query->where('type', $type);
    }

    public function isReversal(): bool
    {
        return (bool) $this->reversal_of_account_payable_payment_id;
    }
}
