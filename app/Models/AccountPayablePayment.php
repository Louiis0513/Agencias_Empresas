<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountPayablePayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'account_payable_id',
        'amount',
        'payment_date',
        'notes',
        'user_id',
        'reversed_at',
        'reversal_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'reversed_at' => 'datetime',
    ];

    public function reversalUser()
    {
        return $this->belongsTo(User::class, 'reversal_user_id');
    }

    public function isReversed(): bool
    {
        return (bool) $this->reversed_at;
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function accountPayable()
    {
        return $this->belongsTo(AccountPayable::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parts()
    {
        return $this->hasMany(AccountPayablePaymentPart::class, 'account_payable_payment_id');
    }
}
