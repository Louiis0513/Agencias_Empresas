<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE invoice_details MODIFY product_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE invoice_details ALTER COLUMN product_id DROP NOT NULL');
        } else {
            Schema::table('invoice_details', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable()->change();
            });
        }

        Schema::table('invoice_details', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
        });

        Schema::table('invoice_details', function (Blueprint $table) {
            $table->foreignId('store_plan_id')->nullable()->after('product_id')->constrained('store_plans')->onDelete('restrict');
            $table->date('subscription_starts_at')->nullable()->after('store_plan_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropForeign(['store_plan_id']);
            $table->dropColumn(['store_plan_id', 'subscription_starts_at']);
        });

        Schema::table('invoice_details', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE invoice_details MODIFY product_id BIGINT UNSIGNED NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE invoice_details ALTER COLUMN product_id SET NOT NULL');
        } else {
            Schema::table('invoice_details', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable(false)->change();
            });
        }

        Schema::table('invoice_details', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('products')->onDelete('restrict');
        });
    }
};
