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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            
            // El ID de la tienda a la que pertenece este cliente
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            
            // AQUÍ ESTÁ EL TRUCO: 
            // Es 'nullable' porque al principio el cliente no tiene usuario (es fantasma).
            // Cuando se registre, aquí guardaremos su ID de usuario.
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');

            $table->string('name');
            
            // El email es vital para poder "fusionar" la cuenta después.
            // Lo ponemos 'index' para que la búsqueda sea instantánea.
            $table->string('email')->nullable()->index(); 
            
            // Datos extra opcionales
            $table->string('phone')->nullable();
            $table->string('document_number')->nullable(); // DNI, Cédula, Pasaporte
            $table->text('address')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
