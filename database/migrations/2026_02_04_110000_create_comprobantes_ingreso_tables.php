<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Módulo Comprobantes de Ingreso.
     * Documento que acredita un ingreso de dinero. En movimientos de caja se ve el comprobante.
     * Tipo: COBRO_CUENTA (ligado a cuenta por cobrar) o INGRESO_MANUAL (ingreso normal sin factura/cobro).
     */
    public function up(): void
    {
        Schema::create('comprobantes_ingreso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('number', 50)->comment('Consecutivo CI-001, CI-002');
            $table->decimal('total_amount', 15, 2)->comment('Monto total del ingreso');
            $table->date('date');
            $table->string('notes')->nullable();
            $table->string('type', 20)->default('INGRESO_MANUAL'); // INGRESO_MANUAL | COBRO_CUENTA
            $table->foreignId('customer_id')->nullable()->constrained()->onDelete('set null')->comment('Cliente si es cobro');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversal_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        /** Destinos: a qué bolsillos entra el dinero (como "orígenes" en egreso: de qué bolsillos sale). */
        Schema::create('comprobante_ingreso_destinos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_ingreso_id')->constrained('comprobantes_ingreso')->onDelete('cascade');
            $table->foreignId('bolsillo_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('reference')->nullable()->comment('Número de transacción o referencia');
            $table->timestamps();
        });

        /** Aplicaciones: si es cobro, qué cuenta(s) por cobrar se están abonando. */
        Schema::create('comprobante_ingreso_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_ingreso_id')->constrained('comprobantes_ingreso')->onDelete('cascade');
            $table->foreignId('account_receivable_id')->constrained('accounts_receivable')->onDelete('cascade');
            $table->decimal('amount', 15, 2)->comment('Monto aplicado a esta cuenta por cobrar');
            $table->timestamps();
        });

        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->foreignId('comprobante_ingreso_id')->nullable()->after('comprobante_egreso_id')
                ->constrained('comprobantes_ingreso')->onDelete('set null');
        });

        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->foreignId('reversal_of_comprobante_ingreso_id')->nullable()->after('reversal_of_comprobante_egreso_id')
                ->constrained('comprobantes_ingreso')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->dropForeign(['reversal_of_comprobante_ingreso_id']);
        });
        Schema::table('movimientos_bolsillo', function (Blueprint $table) {
            $table->dropForeign(['comprobante_ingreso_id']);
        });
        Schema::dropIfExists('comprobante_ingreso_aplicaciones');
        Schema::dropIfExists('comprobante_ingreso_destinos');
        Schema::dropIfExists('comprobantes_ingreso');
    }
};
