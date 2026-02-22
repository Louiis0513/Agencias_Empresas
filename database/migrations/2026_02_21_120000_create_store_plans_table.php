<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Planes de suscripción por tienda (ej: membresía gym, tiquetera, etc.)
     */
    public function up(): void
    {
        Schema::create('store_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('duration_days')->comment('Cuántos días dura la membresía');
            $table->unsignedInteger('daily_entries_limit')->nullable()->comment('Si es 1: una entrada al día. Null: ilimitado por día');
            $table->unsignedInteger('total_entries_limit')->nullable()->comment('Ej: 12 = tiquetera de 12 clases. Null: ilimitado');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_plans');
    }
};
