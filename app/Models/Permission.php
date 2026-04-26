<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['name', 'slug', 'description'];

    /**
     * Los roles que tienen este permiso
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission')
            ->withTimestamps();
    }

    public function planFeatures(): BelongsToMany
    {
        return $this->belongsToMany(PlanFeature::class, 'permission_plan_features')
            ->withTimestamps();
    }
}
