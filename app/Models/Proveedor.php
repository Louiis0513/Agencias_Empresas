<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Alias de compatibilidad: Proveedor = Tercero (rol proveedor).
 * Preferir App\Models\Tercero en código nuevo.
 */
class Proveedor extends Tercero
{
    protected $table = 'terceros';

    public function scopeDeTienda(Builder $query, int $storeId): void
    {
        $query->deStore($storeId)->conRol(self::ROL_PROVEEDOR);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('terceros.activo', true);
    }

    public function getEstadoAttribute(): bool
    {
        return (bool) ($this->attributes['activo'] ?? true);
    }
}
