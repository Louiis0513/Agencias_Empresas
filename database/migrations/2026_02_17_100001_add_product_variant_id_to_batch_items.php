<?php

use App\Models\Batch;
use App\Models\BatchItem;
use App\Services\InventarioService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega product_variant_id a batch_items, migra datos existentes
     * creando ProductVariant por cada combinación única de (product_id, features),
     * y elimina las columnas features, price e is_active de batch_items.
     */
    public function up(): void
    {
        // 1. Agregar columna nullable temporalmente
        Schema::table('batch_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')
                ->nullable()
                ->after('batch_id')
                ->constrained('product_variants')
                ->onDelete('cascade');
        });

        // 2. Migrar datos: crear ProductVariant por cada variante única y asignar FK
        $batchItems = DB::table('batch_items')
            ->join('batches', 'batch_items.batch_id', '=', 'batches.id')
            ->select(
                'batch_items.id as batch_item_id',
                'batch_items.features',
                'batch_items.price',
                'batch_items.is_active',
                'batch_items.unit_cost',
                'batches.product_id'
            )
            ->orderBy('batch_items.id')
            ->get();

        $variantCache = []; // "product_id|normalized_key" => variant_id

        foreach ($batchItems as $bi) {
            $features = $bi->features ? json_decode($bi->features, true) : null;

            if (empty($features) || ! is_array($features)) {
                // batch_item sin features: crear variante "sin atributos"
                $features = [];
            }

            // Normalizar features
            $normalized = [];
            foreach ($features as $k => $v) {
                if ($v === '' || $v === null) {
                    continue;
                }
                $key = is_numeric($k) ? (string) (int) $k : (string) $k;
                $normalized[$key] = (string) $v;
            }
            ksort($normalized, SORT_STRING);

            $normalizedKey = json_encode($normalized);
            $cacheKey = $bi->product_id . '|' . $normalizedKey;

            if (! isset($variantCache[$cacheKey])) {
                // Buscar o crear el ProductVariant
                $existing = DB::table('product_variants')
                    ->where('product_id', $bi->product_id)
                    ->get()
                    ->first(function ($pv) use ($normalizedKey) {
                        $pvFeatures = json_decode($pv->features, true) ?? [];
                        $pvNormalized = [];
                        foreach ($pvFeatures as $k => $v) {
                            if ($v === '' || $v === null) {
                                continue;
                            }
                            $kk = is_numeric($k) ? (string) (int) $k : (string) $k;
                            $pvNormalized[$kk] = (string) $v;
                        }
                        ksort($pvNormalized, SORT_STRING);
                        return json_encode($pvNormalized) === $normalizedKey;
                    });

                if ($existing) {
                    $variantCache[$cacheKey] = $existing->id;
                } else {
                    $variantId = DB::table('product_variants')->insertGetId([
                        'product_id' => $bi->product_id,
                        'features' => $normalizedKey ?: '{}',
                        'cost_reference' => $bi->unit_cost ?? 0,
                        'price' => $bi->price,
                        'is_active' => $bi->is_active ?? true,
                        'barcode' => null,
                        'sku' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $variantCache[$cacheKey] = $variantId;
                }
            }

            DB::table('batch_items')
                ->where('id', $bi->batch_item_id)
                ->update(['product_variant_id' => $variantCache[$cacheKey]]);
        }

        // 3. Hacer la columna NOT NULL (solo si no hay registros huérfanos)
        // Primero verificar que no queden nulls
        $nullCount = DB::table('batch_items')->whereNull('product_variant_id')->count();
        if ($nullCount === 0) {
            Schema::table('batch_items', function (Blueprint $table) {
                $table->foreignId('product_variant_id')->nullable(false)->change();
            });
        }

        // 4. Eliminar columnas migradas
        Schema::table('batch_items', function (Blueprint $table) {
            $table->dropColumn(['features', 'price', 'is_active']);
        });
    }

    public function down(): void
    {
        // Restaurar columnas en batch_items
        Schema::table('batch_items', function (Blueprint $table) {
            $table->json('features')->nullable()->after('quantity');
            $table->decimal('price', 12, 2)->nullable()->after('unit_cost');
            $table->boolean('is_active')->default(true)->after('price');
        });

        // Migrar datos inversa: copiar features/price/is_active desde product_variants
        $batchItems = DB::table('batch_items')
            ->join('product_variants', 'batch_items.product_variant_id', '=', 'product_variants.id')
            ->select(
                'batch_items.id as batch_item_id',
                'product_variants.features',
                'product_variants.price',
                'product_variants.is_active'
            )
            ->get();

        foreach ($batchItems as $bi) {
            DB::table('batch_items')
                ->where('id', $bi->batch_item_id)
                ->update([
                    'features' => $bi->features,
                    'price' => $bi->price,
                    'is_active' => $bi->is_active,
                ]);
        }

        // Eliminar FK y columna
        Schema::table('batch_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn('product_variant_id');
        });
    }
};
