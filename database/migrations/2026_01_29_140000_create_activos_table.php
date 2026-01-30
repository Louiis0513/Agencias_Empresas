<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla espejo de products para Activos Fijos.
     * Products = Estantería (para vender). Activos = Escritorio (para usar).
     * Ambos se compran en purchases; el destino es diferente.
     */
    public function up(): void
    {
        Schema::create('activos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');

            $table->string('name');
            $table->string('code')->nullable()->comment('Código/SKU del activo');
            $table->text('description')->nullable();

            $table->unsignedInteger('quantity')->default(0)->comment('Cantidad recibida (como stock en products)');
            $table->string('location')->nullable()->comment('Ubicación física: escritorio, almacén, etc.');

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activos');
    }
};
