<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Gestor de Vida Útil: control_type (SERIALIZADO/LOTE), serial, model, brand,
     * condition, status, location_id, assigned_to_user_id, warranty_expiry.
     */
    public function up(): void
    {
        Schema::table('activos', function (Blueprint $table) {
            // Tipo de control: SERIALIZADO (1 ítem con serial) vs LOTE (N ítems sin serial)
            $table->string('control_type', 20)->default('LOTE')->after('store_id')
                ->comment('SERIALIZADO: qty=1, tiene serial. LOTE: qty>=1, sin serial.');

            // Identificación única (solo SERIALIZADO)
            $table->string('serial_number')->nullable()->after('code')
                ->comment('Número de serie del fabricante (solo control SERIALIZADO)');

            // Datos del fabricante
            $table->string('model')->nullable()->after('serial_number');
            $table->string('brand')->nullable()->after('model');

            // Ubicación estructurada (Fase 1)
            $table->foreignId('location_id')->nullable()->after('location')
                ->constrained('activo_locations')->nullOnDelete();

            // Responsable / Custodia (Fase 2)
            $table->foreignId('assigned_to_user_id')->nullable()->after('location_id')
                ->constrained('users')->nullOnDelete();

            // Estado físico y operativo
            $table->string('condition', 20)->nullable()->after('assigned_to_user_id')
                ->comment('NUEVO, BUENO, REGULAR, MALO');
            $table->string('status', 30)->default('ACTIVO')->after('condition')
                ->comment('ACTIVO, EN_MANTENIMIENTO, BAJA, PRESTADO');

            // Garantía (Fase 2 - historial mantenimiento)
            $table->date('warranty_expiry')->nullable()->after('status');

            // Plantilla para SERIALIZADO: instancias apuntan al catálogo (name, model, brand)
            $table->foreignId('activo_template_id')->nullable()->after('warranty_expiry')
                ->constrained('activos')->nullOnDelete();
        });

        // Serial único por tienda (dos tiendas podrían tener mismo serial de distintos fabricantes)
        Schema::table('activos', function (Blueprint $table) {
            $table->unique(['store_id', 'serial_number'], 'activos_store_serial_unique');
        });
    }

    public function down(): void
    {
        Schema::table('activos', function (Blueprint $table) {
            $table->dropUnique('activos_store_serial_unique');
            $table->dropForeign(['location_id']);
            $table->dropForeign(['assigned_to_user_id']);
            $table->dropForeign(['activo_template_id']);
            $table->dropColumn([
                'control_type', 'serial_number', 'model', 'brand',
                'location_id', 'assigned_to_user_id', 'condition', 'status', 'warranty_expiry',
                'activo_template_id',
            ]);
        });
    }
};
