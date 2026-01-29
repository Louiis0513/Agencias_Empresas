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
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();

            $table->foreignId('store_id')->constrained()->onDelete('cascade');

            $table->string('nombre');
            $table->string('numero_celular')->nullable();
            $table->string('telefono')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('nit')->nullable();
            $table->text('direccion')->nullable();
            $table->boolean('estado')->default(true);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
