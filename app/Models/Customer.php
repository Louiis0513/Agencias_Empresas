<?php

namespace App\Models;

/**
 * Alias de compatibilidad: Customer = Tercero (rol cliente).
 * Preferir App\Models\Tercero en código nuevo.
 */
class Customer extends Tercero
{
    protected $table = 'terceros';

    public static function ensureConsumidorFinalForStore(int $storeId): Tercero
    {
        $store = Store::query()->findOrFail($storeId);

        return app(\App\Services\TerceroService::class)->asegurarConsumidorFinal($store);
    }
}
