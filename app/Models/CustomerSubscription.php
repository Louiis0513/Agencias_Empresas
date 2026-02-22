<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'customer_id',
        'store_plan_id',
        'starts_at',
        'expires_at',
        'entries_used',
        'last_entry_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_entry_at' => 'datetime',
        'entries_used' => 'integer',
    ];

    protected $attributes = [
        'entries_used' => 0,
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function storePlan()
    {
        return $this->belongsTo(StorePlan::class);
    }

    public function subscriptionEntries()
    {
        return $this->hasMany(SubscriptionEntry::class);
    }

    /** Si la suscripción está vigente (dentro del rango de fechas) */
    public function isActive(): bool
    {
        $now = now();
        return $now->between($this->starts_at, $this->expires_at);
    }

    /** Si puede registrar una entrada hoy (respeta daily_entries_limit) */
    public function canEnterToday(): bool
    {
        $plan = $this->storePlan;
        if ($plan->daily_entries_limit === null) {
            return true;
        }
        if ($this->last_entry_at === null) {
            return true;
        }
        return $this->last_entry_at->toDateString() !== now()->toDateString();
    }

    /** Si puede registrar una entrada más (respeta total_entries_limit) */
    public function hasEntriesRemaining(): bool
    {
        $plan = $this->storePlan;
        if ($plan->total_entries_limit === null) {
            return true;
        }
        return $this->entries_used < $plan->total_entries_limit;
    }

    /** Si puede pasar (vigente + límites respetados) */
    public function canEnter(): bool
    {
        return $this->isActive()
            && $this->canEnterToday()
            && $this->hasEntriesRemaining();
    }
}
