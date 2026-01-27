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
        Schema::create('attribute_group_attribute', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_group_id')->constrained('attribute_groups')->onDelete('cascade');
            $table->foreignId('attribute_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['attribute_id']); // Cada atributo pertenece a un solo grupo
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attribute_group_attribute');
    }
};
