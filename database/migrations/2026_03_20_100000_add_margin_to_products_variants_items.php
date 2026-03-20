<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'margin')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('margin', 5, 2)->nullable()->after('cost');
            });
        }

        if (! Schema::hasColumn('product_variants', 'margin')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->decimal('margin', 5, 2)->nullable()->after('price');
            });
        }

        if (! Schema::hasColumn('product_items', 'margin')) {
            Schema::table('product_items', function (Blueprint $table) {
                $table->decimal('margin', 5, 2)->nullable()->after('price');
            });
        }

        DB::statement("
            UPDATE products
            SET margin = CASE
                WHEN ROUND(((price - cost) / price) * 100, 2) BETWEEN -999.99 AND 999.99
                    THEN ROUND(((price - cost) / price) * 100, 2)
                ELSE NULL
            END
            WHERE price IS NOT NULL AND price > 0
        ");

        DB::statement("
            UPDATE product_variants
            SET margin = CASE
                WHEN ROUND(((price - cost_reference) / price) * 100, 2) BETWEEN -999.99 AND 999.99
                    THEN ROUND(((price - cost_reference) / price) * 100, 2)
                ELSE NULL
            END
            WHERE price IS NOT NULL AND price > 0
        ");

        DB::statement("
            UPDATE product_items
            SET margin = CASE
                WHEN ROUND(((price - cost) / price) * 100, 2) BETWEEN -999.99 AND 999.99
                    THEN ROUND(((price - cost) / price) * 100, 2)
                ELSE NULL
            END
            WHERE price IS NOT NULL AND price > 0
        ");
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_items', 'margin')) {
            Schema::table('product_items', function (Blueprint $table) {
                $table->dropColumn('margin');
            });
        }

        if (Schema::hasColumn('product_variants', 'margin')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->dropColumn('margin');
            });
        }

        if (Schema::hasColumn('products', 'margin')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('margin');
            });
        }
    }
};
