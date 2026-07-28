<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\CuentaContableService;
use Illuminate\Console\Command;

class VincularBolsillosCuentasCommand extends Command
{
    protected $signature = 'contable:vincular-bolsillos
        {store : ID o slug de la tienda}';

    protected $description = 'Crea cuentas auxiliares del Disponible (11) para bolsillos sin cuenta_contable_id';

    public function handle(CuentaContableService $cuentaContableService): int
    {
        $storeKey = $this->argument('store');
        $store = is_numeric($storeKey)
            ? Store::find($storeKey)
            : Store::where('slug', $storeKey)->first();

        if (! $store) {
            $this->error('Tienda no encontrada: '.$storeKey);

            return self::FAILURE;
        }

        $this->info('Vinculando bolsillos de #'.$store->id.' ('.$store->name.')...');

        $stats = $cuentaContableService->backfillBolsillosSinCuenta($store);

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Vinculados', $stats['vinculados']],
                ['Omitidos', $stats['omitidos']],
            ]
        );

        foreach ($stats['errores'] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
