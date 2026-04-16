<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'quantity_mode')) {
                $table->string('quantity_mode', 20)
                    ->default(Product::QUANTITY_MODE_UNIT)
                    ->after('stock');
            }
            if (! Schema::hasColumn('products', 'quantity_step')) {
                $table->decimal('quantity_step', 5, 2)
                    ->default(1.00)
                    ->after('quantity_mode');
            }
        });

        $this->alterQuantityColumnsToDecimal();
    }

    public function down(): void
    {
        $this->alterQuantityColumnsToInteger();

        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'quantity_step')) {
                $table->dropColumn('quantity_step');
            }
            if (Schema::hasColumn('products', 'quantity_mode')) {
                $table->dropColumn('quantity_mode');
            }
        });
    }

    protected function alterQuantityColumnsToDecimal(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE products MODIFY stock DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'Cache: product_items o batch_items'");
            DB::statement('ALTER TABLE movimientos_inventario MODIFY quantity DECIMAL(14,2) NOT NULL');
            DB::statement('ALTER TABLE batch_items MODIFY quantity DECIMAL(14,2) NOT NULL');
            DB::statement('ALTER TABLE purchase_details MODIFY quantity DECIMAL(14,2) NOT NULL');
            DB::statement('ALTER TABLE invoice_details MODIFY quantity DECIMAL(14,2) NOT NULL');
            DB::statement('ALTER TABLE cotizacion_items MODIFY quantity DECIMAL(14,2) NOT NULL');
            DB::statement('ALTER TABLE support_document_inventory_items MODIFY quantity DECIMAL(14,2) NOT NULL DEFAULT 1.00');
            DB::statement('ALTER TABLE support_document_service_items MODIFY quantity DECIMAL(14,2) NOT NULL DEFAULT 1.00');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE products ALTER COLUMN stock TYPE NUMERIC(14,2)');
            DB::statement('ALTER TABLE products ALTER COLUMN stock SET DEFAULT 0');
            DB::statement('ALTER TABLE movimientos_inventario ALTER COLUMN quantity TYPE NUMERIC(14,2)');
            DB::statement('ALTER TABLE batch_items ALTER COLUMN quantity TYPE NUMERIC(14,2)');
            DB::statement('ALTER TABLE purchase_details ALTER COLUMN quantity TYPE NUMERIC(14,2)');
            DB::statement('ALTER TABLE invoice_details ALTER COLUMN quantity TYPE NUMERIC(14,2)');
            DB::statement('ALTER TABLE cotizacion_items ALTER COLUMN quantity TYPE NUMERIC(14,2)');
            DB::statement('ALTER TABLE support_document_inventory_items ALTER COLUMN quantity TYPE NUMERIC(14,2)');
            DB::statement('ALTER TABLE support_document_service_items ALTER COLUMN quantity TYPE NUMERIC(14,2)');
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->decimal('stock', 14, 2)->default(0)->change();
        });
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->decimal('quantity', 14, 2)->change();
        });
        Schema::table('batch_items', function (Blueprint $table) {
            $table->decimal('quantity', 14, 2)->change();
        });
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->decimal('quantity', 14, 2)->change();
        });
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->decimal('quantity', 14, 2)->change();
        });
        Schema::table('cotizacion_items', function (Blueprint $table) {
            $table->decimal('quantity', 14, 2)->change();
        });
        Schema::table('support_document_inventory_items', function (Blueprint $table) {
            $table->decimal('quantity', 14, 2)->default(1.00)->change();
        });
        Schema::table('support_document_service_items', function (Blueprint $table) {
            $table->decimal('quantity', 14, 2)->default(1.00)->change();
        });
    }

    protected function alterQuantityColumnsToInteger(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE products MODIFY stock INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Cache: product_items o batch_items'");
            DB::statement('ALTER TABLE movimientos_inventario MODIFY quantity INT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE batch_items MODIFY quantity INT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE purchase_details MODIFY quantity INT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE invoice_details MODIFY quantity INT NOT NULL');
            DB::statement('ALTER TABLE cotizacion_items MODIFY quantity INT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE support_document_inventory_items MODIFY quantity INT UNSIGNED NOT NULL DEFAULT 1');
            DB::statement('ALTER TABLE support_document_service_items MODIFY quantity INT UNSIGNED NOT NULL DEFAULT 1');
            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE products ALTER COLUMN stock TYPE INTEGER USING ROUND(stock)');
            DB::statement('ALTER TABLE products ALTER COLUMN stock SET DEFAULT 0');
            DB::statement('ALTER TABLE movimientos_inventario ALTER COLUMN quantity TYPE INTEGER USING ROUND(quantity)');
            DB::statement('ALTER TABLE batch_items ALTER COLUMN quantity TYPE INTEGER USING ROUND(quantity)');
            DB::statement('ALTER TABLE purchase_details ALTER COLUMN quantity TYPE INTEGER USING ROUND(quantity)');
            DB::statement('ALTER TABLE invoice_details ALTER COLUMN quantity TYPE INTEGER USING ROUND(quantity)');
            DB::statement('ALTER TABLE cotizacion_items ALTER COLUMN quantity TYPE INTEGER USING ROUND(quantity)');
            DB::statement('ALTER TABLE support_document_inventory_items ALTER COLUMN quantity TYPE INTEGER USING ROUND(quantity)');
            DB::statement('ALTER TABLE support_document_service_items ALTER COLUMN quantity TYPE INTEGER USING ROUND(quantity)');
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('stock')->default(0)->change();
        });
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->change();
        });
        Schema::table('batch_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->change();
        });
        Schema::table('purchase_details', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->change();
        });
        Schema::table('invoice_details', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });
        Schema::table('cotizacion_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->change();
        });
        Schema::table('support_document_inventory_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->change();
        });
        Schema::table('support_document_service_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->change();
        });
    }
};
