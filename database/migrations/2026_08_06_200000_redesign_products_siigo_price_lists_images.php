<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro productos/servicios estilo Siigo + listas de precios (máx. 12/tienda) + imágenes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // name → nombre (sin doctrine/dbal)
        if (Schema::hasColumn('products', 'name') && ! Schema::hasColumn('products', 'nombre')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('nombre')->nullable()->after('categoria_contable_id');
            });
            DB::statement('UPDATE products SET nombre = name');
            DB::statement("UPDATE products SET nombre = '' WHERE nombre IS NULL");
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE products MODIFY nombre VARCHAR(255) NOT NULL');
            }
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('name');
            });
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'tipo')) {
                $table->string('tipo', 20)->default('producto')->after('categoria_contable_id');
            }
            if (! Schema::hasColumn('products', 'codigo')) {
                $table->string('codigo', 30)->nullable()->after('tipo');
            }
            if (! Schema::hasColumn('products', 'codigo_barras')) {
                $table->string('codigo_barras', 64)->nullable()->after('nombre');
            }
            if (! Schema::hasColumn('products', 'unidad_medida_dian')) {
                $table->string('unidad_medida_dian', 10)->default('94')->after('codigo_barras');
            }
            if (! Schema::hasColumn('products', 'es_inventariable')) {
                $table->boolean('es_inventariable')->default(true)->after('unidad_medida_dian');
            }
            if (! Schema::hasColumn('products', 'visible_en_ventas')) {
                $table->boolean('visible_en_ventas')->default(true)->after('es_inventariable');
            }
            if (! Schema::hasColumn('products', 'impuesto_cargo_id')) {
                $table->foreignId('impuesto_cargo_id')
                    ->nullable()
                    ->after('visible_en_ventas')
                    ->constrained('impuestos')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('products', 'impuesto_retencion_id')) {
                $table->foreignId('impuesto_retencion_id')
                    ->nullable()
                    ->after('impuesto_cargo_id')
                    ->constrained('impuestos')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('products', 'referencia')) {
                $table->string('referencia', 120)->nullable()->after('impuesto_retencion_id');
            }
            if (! Schema::hasColumn('products', 'unidad_medida_factura')) {
                $table->string('unidad_medida_factura', 60)->default('unidad')->after('referencia');
            }
            if (! Schema::hasColumn('products', 'stock_minimo')) {
                $table->decimal('stock_minimo', 14, 4)->nullable()->after('unidad_medida_factura');
            }
            if (! Schema::hasColumn('products', 'descripcion')) {
                $table->text('descripcion')->nullable()->after('stock_minimo');
            }
            if (! Schema::hasColumn('products', 'marca')) {
                $table->string('marca', 120)->nullable()->after('descripcion');
            }
            if (! Schema::hasColumn('products', 'modelo')) {
                $table->string('modelo', 120)->nullable()->after('marca');
            }
            if (! Schema::hasColumn('products', 'codigo_arancelario')) {
                $table->string('codigo_arancelario', 30)->nullable()->after('modelo');
            }
            if (! Schema::hasColumn('products', 'precio_incluye_iva')) {
                $table->boolean('precio_incluye_iva')->default(false)->after('codigo_arancelario');
            }
        });

        if (Schema::hasColumn('products', 'codigo')) {
            $sinCodigo = DB::table('products')
                ->where(function ($q) {
                    $q->whereNull('codigo')->orWhere('codigo', '');
                })
                ->get(['id']);

            foreach ($sinCodigo as $row) {
                DB::table('products')->where('id', $row->id)->update([
                    'codigo' => 'TMP-'.$row->id,
                ]);
            }

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE products MODIFY codigo VARCHAR(30) NOT NULL');
            }

            $this->addIndexIfMissing('products', 'products_store_codigo_unique', function (Blueprint $table) {
                $table->unique(['store_id', 'codigo'], 'products_store_codigo_unique');
            });
            $this->addIndexIfMissing('products', 'products_store_tipo_activo_index', function (Blueprint $table) {
                $table->index(['store_id', 'tipo', 'is_active'], 'products_store_tipo_activo_index');
            });
            $this->addIndexIfMissing('products', 'products_store_visible_ventas_index', function (Blueprint $table) {
                $table->index(['store_id', 'visible_en_ventas'], 'products_store_visible_ventas_index');
            });
        }

        Schema::create('listas_precios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('numero'); // 1..12 estilo Siigo
            $table->string('nombre', 120);
            $table->boolean('activo')->default(false);
            $table->timestamps();

            $table->unique(['store_id', 'numero'], 'listas_precios_store_numero_unique');
            $table->index(['store_id', 'activo']);
        });

        Schema::create('product_precios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('lista_precio_id')->constrained('listas_precios')->cascadeOnDelete();
            $table->decimal('precio', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'lista_precio_id'], 'product_precios_product_lista_unique');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('path');
            $table->unsignedTinyInteger('orden'); // 1..5
            $table->timestamps();

            $table->unique(['product_id', 'orden'], 'product_images_product_orden_unique');
        });

        $storeIds = DB::table('stores')->pluck('id');
        $now = now();
        foreach ($storeIds as $storeId) {
            $rows = [];
            for ($n = 1; $n <= 12; $n++) {
                $rows[] = [
                    'store_id' => $storeId,
                    'numero' => $n,
                    'nombre' => 'Precio de venta '.$n,
                    'activo' => $n <= 2,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('listas_precios')->insert($rows);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_precios');
        Schema::dropIfExists('listas_precios');

        Schema::table('products', function (Blueprint $table) {
            foreach ([
                'products_store_codigo_unique',
                'products_store_tipo_activo_index',
                'products_store_visible_ventas_index',
            ] as $index) {
                try {
                    $table->dropIndex($index);
                } catch (\Throwable) {
                }
            }

            if (Schema::hasColumn('products', 'impuesto_cargo_id')) {
                $table->dropConstrainedForeignId('impuesto_cargo_id');
            }
            if (Schema::hasColumn('products', 'impuesto_retencion_id')) {
                $table->dropConstrainedForeignId('impuesto_retencion_id');
            }

            $drop = collect([
                'tipo', 'codigo', 'codigo_barras', 'unidad_medida_dian',
                'es_inventariable', 'visible_en_ventas',
                'referencia', 'unidad_medida_factura', 'stock_minimo', 'descripcion',
                'marca', 'modelo', 'codigo_arancelario', 'precio_incluye_iva',
            ])->filter(fn ($c) => Schema::hasColumn('products', $c))->values()->all();

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });

        if (Schema::hasColumn('products', 'nombre') && ! Schema::hasColumn('products', 'name')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('name')->nullable()->after('categoria_contable_id');
            });
            DB::statement('UPDATE products SET name = nombre');
            DB::statement("UPDATE products SET name = '' WHERE name IS NULL");
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                DB::statement('ALTER TABLE products MODIFY name VARCHAR(255) NOT NULL');
            }
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('nombre');
            });
        }
    }

    private function addIndexIfMissing(string $tableName, string $indexName, callable $callback): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            $exists = collect(DB::select("SHOW INDEX FROM `{$tableName}`"))
                ->contains(fn ($row) => ($row->Key_name ?? null) === $indexName);
            if ($exists) {
                return;
            }
        } elseif ($driver === 'sqlite') {
            $exists = collect(DB::select("PRAGMA index_list('{$tableName}')"))
                ->contains(fn ($row) => ($row->name ?? null) === $indexName);
            if ($exists) {
                return;
            }
        }

        Schema::table($tableName, $callback);
    }
};
