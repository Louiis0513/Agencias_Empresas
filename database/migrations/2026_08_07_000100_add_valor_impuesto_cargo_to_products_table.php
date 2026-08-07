<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'valor_impuesto_cargo')) {
                $table->decimal('valor_impuesto_cargo', 14, 2)->nullable()->after('impuesto_retencion_id');
            }
            if (! Schema::hasColumn('products', 'aplica_impuesto_bolsas')) {
                $table->boolean('aplica_impuesto_bolsas')->default(false)->after('valor_impuesto_cargo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $drop = collect(['valor_impuesto_cargo', 'aplica_impuesto_bolsas'])
                ->filter(fn ($c) => Schema::hasColumn('products', $c))
                ->values()
                ->all();

            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
