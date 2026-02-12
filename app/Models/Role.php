<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['name', 'store_id'];

    /**
     * La tienda a la que pertenece este rol
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Los permisos que tiene este rol
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission')
            ->withTimestamps();
    }

    /**
     * Los usuarios que tienen este rol en la tienda (legacy, vía store_user)
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'store_user', 'role_id', 'user_id')
            ->withPivot('store_id')
            ->withTimestamps();
    }

    /**
     * Los trabajadores que tienen este rol (tabla workers)
     */
    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }
}
