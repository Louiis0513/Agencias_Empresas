<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Caja = suma de todos los bolsillos (no existe tabla).
     * Bolsillos pertenecen directamente a la tienda (Caja 1, Caja 2, Cuenta banco, etc.).
     */
    public function up(): void
    {
        Schema::dropIfExists('movimientos_bolsillo');
        Schema::dropIfExists('bolsillos');
        Schema::dropIfExists('cajas');

        Schema::create('bolsillos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');

            $table->string('name');
            $table->string('detalles', 1000)->nullable();
            $table->decimal('saldo', 15, 2)->default(0);

            $table->boolean('is_bank_account')->default(false);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('movimientos_bolsillo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('bolsillo_id')->constrained()->onDelete('cascade');

            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->constrained();

            $table->string('type');
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_bolsillo');
        Schema::dropIfExists('bolsillos');
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
