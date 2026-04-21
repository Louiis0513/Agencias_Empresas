<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Registro de jornadas por trabajador (equivalente a horarios en counter-tools).
     */
    public function up(): void
    {
        Schema::create('worker_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('worker_id')->constrained()->onDelete('cascade');
            $table->dateTime('fecha_hora_entrada');
            $table->dateTime('fecha_hora_salida')->nullable();
            $table->boolean('es_festivo')->default(false);
            $table->boolean('es_festivo2')->default(false);
            $table->boolean('es_domingo')->default(false);
            $table->boolean('no_compensa_semana_siguiente')->default(false);
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'worker_id', 'fecha_hora_entrada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_schedules');
    }
};
