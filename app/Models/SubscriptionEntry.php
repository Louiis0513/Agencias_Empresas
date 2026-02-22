<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_subscription_id',
        'store_id',
        'customer_id',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function customerSubscription()
    {
        return $this->belongsTo(CustomerSubscription::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
