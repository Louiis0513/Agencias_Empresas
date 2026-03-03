<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('in_showcase')->default(false)->after('is_active');
        });

        Schema::table('store_plans', function (Blueprint $table) {
            $table->boolean('in_showcase')->default(false)->after('total_entries_limit');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('in_showcase');
        });

        Schema::table('store_plans', function (Blueprint $table) {
            $table->dropColumn('in_showcase');
        });
    }
};
