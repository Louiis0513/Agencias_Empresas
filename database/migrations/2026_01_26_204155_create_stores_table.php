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
    Schema::create('stores', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique(); // Identificador único en la URL
        // Relacionamos al creador/dueño principal
        $table->foreignId('user_id')->constrained()->onDelete('cascade'); 
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stores');
    }
};
