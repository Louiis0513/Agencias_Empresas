<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

/**
 * Alias de compatibilidad: Worker = Tercero (rol trabajador).
 * Preferir App\Models\Tercero en código nuevo.
 */
class Worker extends Tercero
{
    protected $table = 'terceros';

    protected static function booted(): void
    {
        static::addGlobalScope('trabajador', function (Builder $query) {
            $query->conRol(self::ROL_TRABAJADOR);
        });
    }

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->deStore($storeId);
    }

    public function perfilTrabajador(): HasOne
    {
        return $this->hasOne(TerceroTrabajadorPerfil::class, 'tercero_id');
    }

    public function role(): HasOneThrough
    {
        return $this->hasOneThrough(
            Role::class,
            TerceroTrabajadorPerfil::class,
            'tercero_id',
            'id',
            'id',
            'role_id'
        );
    }

    public function getRoleIdAttribute()
    {
        return $this->perfilTrabajador?->role_id;
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(WorkerSchedule::class, 'tercero_id');
    }

    public function estaVinculado(): bool
    {
        return $this->user_id !== null;
    }
}
