<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountReceivableCuota extends Model
{
    use HasFactory;

    protected $table = 'account_receivable_cuotas';

    protected $fillable = [
        'account_receivable_id',
        'sequence',
        'amount',
        'amount_paid',
        'due_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'due_date' => 'date',
    ];

    public function accountReceivable()
    {
        return $this->belongsTo(AccountReceivable::class);
    }

    /** Saldo pendiente de esta cuota. */
    public function getPendingAttribute(): float
    {
        return max(0, (float) $this->amount - (float) $this->amount_paid);
    }
}
