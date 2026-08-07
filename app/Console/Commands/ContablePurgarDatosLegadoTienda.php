<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purga datos demo de catálogo/inventario/compras/suscripciones de una tienda.
 * No toca PUC, categorias_contables, impuestos ni tipos_comprobante.
 */
class ContablePurgarDatosLegadoTienda extends Command
{
    protected $signature = 'contable:purgar-legado-tienda {store : ID o slug de la tienda}';

    protected $description = 'Purga productos y restos demo de módulos demolidos sin tocar contabilidad base';

    public function handle(): int
    {
        $key = $this->argument('store');
        $store = is_numeric($key)
            ? Store::find($key)
            : Store::where('slug', $key)->first();

        if (! $store) {
            $this->error('Tienda no encontrada.');

            return self::FAILURE;
        }

        $this->info("Purgando legado de tienda #{$store->id} ({$store->slug})…");

        DB::transaction(function () use ($store) {
            // Facturas históricas: solo nullificar plan si la columna aún existe
            if (Schema::hasTable('invoice_details') && Schema::hasColumn('invoice_details', 'store_plan_id')) {
                DB::table('invoice_details')
                    ->whereIn('invoice_id', function ($q) use ($store) {
                        $q->select('id')->from('invoices')->where('store_id', $store->id);
                    })
                    ->update(['store_plan_id' => null]);
            }

            // Productos de la tienda (cascarón)
            Product::where('store_id', $store->id)->delete();

            // Tablas que pueden quedar hasta la migración de drop
            $scopedDeletes = [
                'movimientos_inventario' => 'store_id',
                'product_items' => 'store_id',
                'batches' => 'store_id',
                'categories' => 'store_id',
                'attributes' => 'store_id',
                'attribute_groups' => 'store_id',
                'purchases' => 'store_id',
                'support_documents' => 'store_id',
                'activos' => 'store_id',
                'movimientos_activo' => 'store_id',
                'activo_locations' => 'store_id',
                'store_plans' => 'store_id',
                'customer_subscriptions' => 'store_id',
                'panel_suscripciones_configs' => 'store_id',
                'cotizaciones' => 'store_id',
            ];

            foreach ($scopedDeletes as $table => $column) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    $n = DB::table($table)->where($column, $store->id)->delete();
                    if ($n > 0) {
                        $this->line("  {$table}: {$n} filas");
                    }
                }
            }
        });

        $this->info('Listo. Contabilidad base (PUC / categorías contables) intacta.');

        return self::SUCCESS;
    }
}
