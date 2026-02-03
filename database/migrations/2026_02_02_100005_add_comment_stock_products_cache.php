<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * products.stock es un cache/contador visual.
     * El stock real vive en: product_items (serialized) o batch_items (batch).
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE products MODIFY COLUMN stock INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Cache: product_items o batch_items'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE products MODIFY COLUMN stock INT UNSIGNED NOT NULL DEFAULT 0');
        }
    }
};
