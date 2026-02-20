<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detalles por bolsillo por sesión. Auditoría y conciliación por bolsillo (obligatorio).
     */
    public function up(): void
    {
        Schema::create('sesion_caja_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesion_caja_id')->constrained('sesiones_caja')->onDelete('cascade');
            $table->foreignId('bolsillo_id')->constrained()->onDelete('cascade');

            $table->decimal('saldo_esperado_apertura', 15, 2)->default(0);
            $table->decimal('saldo_fisico_apertura', 15, 2)->default(0);

            $table->decimal('saldo_esperado_cierre', 15, 2)->nullable();
            $table->decimal('saldo_fisico_cierre', 15, 2)->nullable();

            $table->timestamps();
        });

        Schema::table('sesion_caja_detalles', function (Blueprint $table) {
            $table->unique(['sesion_caja_id', 'bolsillo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesion_caja_detalles');
    }
};
