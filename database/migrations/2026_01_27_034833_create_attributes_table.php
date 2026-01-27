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
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Ej: "Talla", "Color", "Material"
            $table->string('code')->nullable(); // Código único: "size", "color", "material"
            $table->enum('type', ['text', 'number', 'select', 'boolean'])->default('text');
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['store_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
