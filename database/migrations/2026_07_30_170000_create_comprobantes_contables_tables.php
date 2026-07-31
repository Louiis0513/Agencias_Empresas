<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comprobantes_contables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tipo_comprobante_id')->constrained('tipos_comprobante')->restrictOnDelete();
            $table->string('numero', 64)->nullable();
            $table->date('fecha');
            $table->foreignId('tercero_id')->nullable()->constrained('terceros')->nullOnDelete();
            $table->text('descripcion');
            $table->string('estado', 20)->default('BORRADOR');
            $table->string('evento', 64)->default('ASIENTO_MANUAL');
            $table->decimal('total_debito', 18, 2)->default(0);
            $table->decimal('total_credito', 18, 2)->default(0);
            $table->foreignId('reversa_de_id')->nullable()->constrained('comprobantes_contables')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contabilizado_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('contabilizado_at')->nullable();
            $table->foreignId('reversado_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversado_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['store_id', 'tipo_comprobante_id', 'numero'],
                'cc_store_tipo_numero_unique'
            );
            $table->unique('reversa_de_id');
            $table->index(['store_id', 'estado', 'fecha']);
            $table->index(['store_id', 'evento']);
        });

        Schema::create('movimientos_contables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_contable_id')->constrained('comprobantes_contables')->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cuenta_contable_id')->constrained('cuentas_contables')->restrictOnDelete();
            $table->foreignId('tercero_id')->nullable()->constrained('terceros')->nullOnDelete();
            $table->string('descripcion')->nullable();
            $table->decimal('debito', 18, 2)->default(0);
            $table->decimal('credito', 18, 2)->default(0);
            $table->unsignedInteger('orden')->default(1);
            $table->timestamps();

            $table->index(['store_id', 'cuenta_contable_id']);
            $table->index(['store_id', 'tercero_id']);
            $table->index(['comprobante_contable_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_contables');
        Schema::dropIfExists('comprobantes_contables');
    }
};
