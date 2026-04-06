<?php

use App\Models\Customer;
use App\Models\Store;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Cliente «consumidor final» (NIT Colombia) por cada tienda existente.
     */
    public function up(): void
    {
        Store::query()->orderBy('id')->pluck('id')->each(function ($storeId): void {
            Customer::ensureConsumidorFinalForStore((int) $storeId);
        });
    }

    /**
     * No eliminamos clientes: pueden tener facturas vinculadas.
     */
    public function down(): void
    {
        //
    }
};
