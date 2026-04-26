<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorePlanFeatureOverride extends Model
{
    protected $fillable = [
        'store_id',
        'plan_feature_id',
        'scope',
        'status',
        'updated_by_user_id',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function planFeature(): BelongsTo
    {
        return $this->belongsTo(PlanFeature::class);
    }
}

