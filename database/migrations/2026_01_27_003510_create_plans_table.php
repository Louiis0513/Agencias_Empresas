<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('plans', function (Blueprint $table) {
        $table->id();
        
        $table->string('name');          // Ej: "Plan Emprendedor", "Plan Empresario"
        $table->string('slug')->unique(); // Ej: "basic", "pro"
        
        // LOS LÍMITES
        $table->integer('max_stores');     // Ej: 1, 5, 999
        $table->integer('max_employees');  // Ej: 2, 10, 999
        
        $table->decimal('price', 10, 2);   // Para mostrar el precio
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
