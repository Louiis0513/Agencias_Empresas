<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\TerceroService;
use Illuminate\Console\Command;

class MigrarTercerosCommand extends Command
{
    protected $signature = 'terceros:migrar {store? : ID o slug de la tienda (opcional)}';

    protected $description = 'Asegura consumidor final y reporta terceros por tienda (el cutover de datos corre en migraciones).';

    public function handle(TerceroService $terceroService): int
    {
        $storeArg = $this->argument('store');

        $stores = Store::query()->when($storeArg, function ($q) use ($storeArg) {
            if (ctype_digit((string) $storeArg)) {
                $q->where('id', (int) $storeArg);
            } else {
                $q->where('slug', $storeArg);
            }
        })->get();

        if ($stores->isEmpty()) {
            $this->error('No se encontró la tienda.');

            return self::FAILURE;
        }

        foreach ($stores as $store) {
            $cf = $terceroService->asegurarConsumidorFinal($store);
            $total = $store->terceros()->count();
            $this->info("Tienda {$store->id} ({$store->slug}): {$total} terceros. Consumidor final #{$cf->id}.");
        }

        return self::SUCCESS;
    }
}
