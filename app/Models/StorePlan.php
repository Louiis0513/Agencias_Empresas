<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StorePlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'name',
        'description',
        'price',
        'duration_days',
        'daily_entries_limit',
        'total_entries_limit',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function customerSubscriptions()
    {
        return $this->hasMany(CustomerSubscription::class, 'store_plan_id');
    }

    /** Si tiene límite de entradas por día (ej: 1 = una vez al día) */
    public function hasDailyLimit(): bool
    {
        return $this->daily_entries_limit !== null;
    }

    /** Si tiene límite total de entradas (ej: tiquetera de 12) */
    public function hasTotalLimit(): bool
    {
        return $this->total_entries_limit !== null;
    }
}
