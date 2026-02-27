<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simplifica atributos: elimina code, type, attribute_options y product_attribute_options.
     * Convierte features numéricos (IDs de opciones) a strings antes de eliminar.
     */
    public function up(): void
    {
        $hasAttributeOptions = Schema::hasTable('attribute_options');

        // 1. Convertir product_variants.features: valores numéricos -> valor de AttributeOption (si la tabla existe)
        if ($hasAttributeOptions) {
            $variants = DB::table('product_variants')->get();
        foreach ($variants as $pv) {
            $features = $pv->features ? json_decode($pv->features, true) : [];
            if (! is_array($features)) {
                continue;
            }
            $changed = false;
            foreach ($features as $attrId => $value) {
                if (is_numeric($value) && (string) (int) $value === (string) $value) {
                    $opt = DB::table('attribute_options')->find((int) $value);
                    if ($opt) {
                        $features[$attrId] = $opt->value;
                        $changed = true;
                    }
                }
            }
            if ($changed) {
                DB::table('product_variants')
                    ->where('id', $pv->id)
                    ->update(['features' => json_encode($features)]);
            }
        }
        }

        // 2. Convertir product_items.features
        if ($hasAttributeOptions) {
            $items = DB::table('product_items')->whereNotNull('features')->get();
        foreach ($items as $pi) {
            $features = json_decode($pi->features, true);
            if (! is_array($features)) {
                continue;
            }
            $changed = false;
            foreach ($features as $attrId => $value) {
                if (is_numeric($value) && (string) (int) $value === (string) $value) {
                    $opt = DB::table('attribute_options')->find((int) $value);
                    if ($opt) {
                        $features[$attrId] = $opt->value;
                        $changed = true;
                    }
                }
            }
            if ($changed) {
                DB::table('product_items')
                    ->where('id', $pi->id)
                    ->update(['features' => json_encode($features)]);
            }
        }
        }

        // 3. Eliminar product_attribute_options (FK a attribute_options)
        Schema::dropIfExists('product_attribute_options');

        // 4. Eliminar attribute_options
        Schema::dropIfExists('attribute_options');

        // 5. Eliminar code y type de attributes (solo si existen)
        if (Schema::hasColumn('attributes', 'code')) {
            Schema::table('attributes', function (Blueprint $table) {
                // El índice único (store_id, code) soporta el FK de store_id. Añadimos índice en store_id antes de eliminarlo.
                $table->index('store_id');
                $table->dropUnique(['store_id', 'code']);
                $table->dropColumn('code');
            });
        }
        if (Schema::hasColumn('attributes', 'type')) {
            Schema::table('attributes', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attributes', function (Blueprint $table) {
            $table->string('type')->default('text')->after('name');
        });

        Schema::table('attributes', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
            $table->unique(['store_id', 'code']);
        });

        Schema::create('attribute_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->onDelete('cascade');
            $table->string('value');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('product_attribute_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('attribute_option_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['product_id', 'attribute_option_id']);
        });
    }
};
