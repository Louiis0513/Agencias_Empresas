<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tipo_comprobante_id')->constrained('tipos_comprobante')->restrictOnDelete();
            $table->string('numero', 64);
            $table->string('tipo_documento', 32)->default('SALDO_INICIAL');
            $table->date('fecha');
            $table->string('tercero_nombre')->nullable();
            $table->text('observaciones')->nullable();
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('total_debito', 18, 2)->default(0);
            $table->decimal('total_credito', 18, 2)->default(0);
            $table->string('estado', 20)->default('CONTABILIZADO');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['store_id', 'tipo_comprobante_id', 'numero'],
                'doc_inv_store_tipo_numero_unique'
            );
            $table->index(['store_id', 'fecha']);
            $table->index(['store_id', 'tipo_documento', 'estado']);
        });

        Schema::create('documento_inventario_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_inventario_id')
                ->constrained('documentos_inventario')
                ->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('orden')->default(1);
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('descripcion')->nullable();
            $table->foreignId('bodega_id')->nullable()->constrained('bodegas')->nullOnDelete();
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo')->nullOnDelete();
            $table->decimal('cantidad', 18, 4);
            $table->decimal('costo_unitario', 18, 4);
            $table->decimal('costo_total', 18, 2);
            $table->timestamps();

            $table->index(['documento_inventario_id', 'orden']);
        });

        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('bodega_id')->nullable()->constrained('bodegas')->nullOnDelete();
            $table->date('fecha');
            $table->string('clase_movimiento', 40);
            $table->string('direccion', 10);
            $table->decimal('cantidad', 18, 4);
            $table->decimal('costo_unitario_entrada', 18, 4)->nullable();
            $table->decimal('valor_entrada', 18, 2)->nullable();
            $table->nullableMorphs('documento');
            $table->string('documento_etiqueta', 64)->nullable();
            $table->string('descripcion')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['store_id', 'product_id', 'fecha'], 'mov_inv_store_product_fecha_idx');
            $table->index(['store_id', 'bodega_id'], 'mov_inv_store_bodega_idx');
            $table->index(['store_id', 'clase_movimiento'], 'mov_inv_store_clase_idx');
        });

        Schema::table('movimientos_contables', function (Blueprint $table) {
            $table->foreignId('documento_inventario_id')
                ->nullable()
                ->after('comprobante_contable_id')
                ->constrained('documentos_inventario')
                ->nullOnDelete();
        });

        Schema::table('movimientos_contables', function (Blueprint $table) {
            $table->dropForeign(['comprobante_contable_id']);
        });

        Schema::table('movimientos_contables', function (Blueprint $table) {
            $table->unsignedBigInteger('comprobante_contable_id')->nullable()->change();
        });

        Schema::table('movimientos_contables', function (Blueprint $table) {
            $table->foreign('comprobante_contable_id')
                ->references('id')
                ->on('comprobantes_contables')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_contables', function (Blueprint $table) {
            $table->dropForeign(['documento_inventario_id']);
            $table->dropColumn('documento_inventario_id');
        });

        Schema::table('movimientos_contables', function (Blueprint $table) {
            $table->dropForeign(['comprobante_contable_id']);
        });

        Schema::table('movimientos_contables', function (Blueprint $table) {
            $table->unsignedBigInteger('comprobante_contable_id')->nullable(false)->change();
        });

        Schema::table('movimientos_contables', function (Blueprint $table) {
            $table->foreign('comprobante_contable_id')
                ->references('id')
                ->on('comprobantes_contables')
                ->cascadeOnDelete();
        });

        Schema::dropIfExists('movimientos_inventario');
        Schema::dropIfExists('documento_inventario_lineas');
        Schema::dropIfExists('documentos_inventario');
    }
};
