<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reestructuración:
     * - Caja = total/realidad del dinero de la tienda (una por tienda). Sin balance aquí.
     * - Bolsillos = componentes de la caja (cuenta corriente, efectivo, etc.). Cada uno con detalles y saldo.
     * - Movimientos = por bolsillo (movimientos_bolsillo).
     */
    public function up(): void
    {
        Schema::dropIfExists('movimientos_caja');

        Schema::table('cajas', function (Blueprint $table) {
            $table->dropColumn(['balance', 'is_bank_account', 'is_active']);
        });

        Schema::create('bolsillos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained()->onDelete('cascade');

            $table->string('name');
            $table->string('detalles', 1000)->nullable()->comment('Ej: nº cuenta corriente, datos del bolsillo');
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

        Schema::table('cajas', function (Blueprint $table) {
            $table->decimal('balance', 15, 2)->default(0)->after('name');
            $table->boolean('is_bank_account')->default(false)->after('balance');
            $table->boolean('is_active')->default(true)->after('is_bank_account');
        });

        Schema::create('movimientos_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('caja_id')->constrained()->onDelete('cascade');
            $table->foreignId('invoice_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->constrained();
            $table->string('type');
            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
