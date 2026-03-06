<?php

namespace App\Services;

use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\Session;

class StoreTimezoneService
{
    /**
     * Obtiene la fecha/hora actual en la zona horaria de la tienda.
     * Si no hay tienda, usa America/Bogota por defecto.
     */
    public function nowForStore(?Store $store = null): Carbon
    {
        $timezone = $this->getTimezoneForStore($store);

        return Carbon::now($timezone);
    }

    /**
     * Obtiene la zona horaria configurada para la tienda.
     */
    public function getTimezoneForStore(?Store $store = null): string
    {
        if ($store && $store->timezone) {
            return $store->timezone;
        }

        if ($storeId = Session::get('current_store_id')) {
            $store = Store::find($storeId);
            if ($store && $store->timezone) {
                return $store->timezone;
            }
        }

        return 'America/Bogota';
    }

    /**
     * Obtiene el Store actual desde la sesión.
     */
    public function getCurrentStore(): ?Store
    {
        $storeId = Session::get('current_store_id');
        if (! $storeId) {
            return null;
        }

        return Store::find($storeId);
    }

    /**
     * Formatea una fecha en la zona y formato de la tienda.
     */
    public function formatForStore(Carbon $date, ?Store $store = null, bool $includeTime = true): string
    {
        $store = $store ?? $this->getCurrentStore();
        $timezone = $this->getTimezoneForStore($store);
        $dateFormat = $store && $store->date_format ? $store->date_format : 'd-m-Y';
        $timeFormat = $store && $store->time_format === '12' ? 'h:i A' : 'H:i';

        $date = $date->copy()->setTimezone($timezone);

        if ($includeTime) {
            return $date->format($dateFormat.' '.$timeFormat);
        }

        return $date->format($dateFormat);
    }
}
