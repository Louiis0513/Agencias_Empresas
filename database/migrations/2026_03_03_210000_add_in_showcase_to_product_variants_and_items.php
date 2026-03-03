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
        Schema::table('product_variants', function (Blueprint $table) {
            $table->boolean('in_showcase')->default(false)->after('is_active');
        });

        Schema::table('product_items', function (Blueprint $table) {
            $table->boolean('in_showcase')->default(false)->after('image_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('in_showcase');
        });

        Schema::table('product_items', function (Blueprint $table) {
            $table->dropColumn('in_showcase');
        });
    }
};
