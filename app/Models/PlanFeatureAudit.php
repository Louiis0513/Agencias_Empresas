<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanFeatureAudit extends Model
{
    protected $fillable = [
        'store_id',
        'plan_feature_id',
        'user_id',
        'action',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}

