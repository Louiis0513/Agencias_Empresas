<?php

namespace App\Console\Commands;

use App\Models\Store;
use App\Services\ImportacionPucService;
use Illuminate\Console\Command;

class ImportarPucCommand extends Command
{
    protected $signature = 'contable:importar-puc
        {store : ID o slug de la tienda}
        {path? : Ruta al Excel (default docs/cuentas-contables-puc.xlsx)}
        {--con-auxiliares : También importa códigos de más de 6 dígitos}';

    protected $description = 'Importa el plan de cuentas PUC base (sin auxiliares por defecto) para una tienda';

    public function handle(ImportacionPucService $importacionPucService): int
    {
        $storeKey = $this->argument('store');
        $store = is_numeric($storeKey)
            ? Store::find($storeKey)
            : Store::where('slug', $storeKey)->first();

        if (! $store) {
            $this->error('Tienda no encontrada: '.$storeKey);

            return self::FAILURE;
        }

        $path = $this->argument('path');
        $soloBase = ! $this->option('con-auxiliares');

        $this->info('Importando PUC para tienda #'.$store->id.' ('.$store->name.')...');
        $this->info($soloBase ? 'Modo: solo base (≤6 dígitos)' : 'Modo: incluye auxiliares');

        try {
            $stats = $importacionPucService->importarDesdeExcel($store, $path, $soloBase);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Importadas', $stats['importadas']],
                ['Actualizadas', $stats['actualizadas']],
                ['Omitidas (auxiliar)', $stats['omitidas_auxiliar']],
                ['Omitidas (vacías)', $stats['omitidas_vacias']],
            ]
        );

        return self::SUCCESS;
    }
}
