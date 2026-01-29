<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountPayablePaymentPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_payable_payment_id',
        'bolsillo_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function accountPayablePayment()
    {
        return $this->belongsTo(AccountPayablePayment::class);
    }

    public function bolsillo()
    {
        return $this->belongsTo(Bolsillo::class);
    }
}
