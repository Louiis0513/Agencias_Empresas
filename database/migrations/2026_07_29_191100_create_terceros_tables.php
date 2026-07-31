<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terceros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tipo_persona', 20)->default('natural'); // natural|juridica
            $table->string('tipo_identificacion', 16)->nullable(); // CC|NIT|CE|PAS|TI|RC|OTRO
            $table->string('numero_identificacion', 64)->nullable();
            $table->string('digito_verificacion', 2)->nullable();
            $table->string('nombre');
            $table->string('nombre_comercial')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('telefono', 40)->nullable();
            $table->string('telefono_secundario', 40)->nullable();
            $table->text('direccion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['store_id', 'tipo_identificacion', 'numero_identificacion'],
                'terceros_store_tipo_numero_unique'
            );
            $table->index(['store_id', 'activo']);
            $table->index(['store_id', 'nombre']);
        });

        Schema::create('tercero_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tercero_id')->constrained('terceros')->cascadeOnDelete();
            $table->string('rol', 20); // cliente|proveedor|trabajador|otro
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tercero_id', 'rol']);
            $table->index(['rol', 'activo']);
        });

        Schema::create('tercero_contactos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tercero_id')->constrained('terceros')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('cargo')->nullable();
            $table->string('email')->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('celular', 40)->nullable();
            $table->boolean('es_principal')->default(false);
            $table->boolean('es_facturacion')->default(false);
            $table->boolean('es_cartera')->default(false);
            $table->boolean('es_compras')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['tercero_id', 'activo']);
        });

        Schema::create('tercero_direcciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tercero_id')->constrained('terceros')->cascadeOnDelete();
            $table->string('tipo', 24)->default('fiscal'); // fiscal|facturacion|entrega|correspondencia
            $table->string('linea');
            $table->string('ciudad')->nullable();
            $table->string('departamento')->nullable();
            $table->string('pais')->nullable()->default('Colombia');
            $table->boolean('es_principal')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['tercero_id', 'tipo']);
        });

        Schema::create('tercero_cliente_perfiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tercero_id')->constrained('terceros')->cascadeOnDelete();
            $table->boolean('credito_habilitado')->default(false);
            $table->decimal('cupo_credito', 15, 2)->nullable();
            $table->unsignedInteger('dias_plazo')->nullable();
            $table->boolean('bloqueado_ventas')->default(false);
            $table->string('motivo_bloqueo')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique('tercero_id');
        });

        Schema::create('tercero_cliente_gym_perfiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tercero_cliente_perfil_id')
                ->constrained('tercero_cliente_perfiles')
                ->cascadeOnDelete();
            $table->string('gender', 8)->nullable();
            $table->string('blood_type', 8)->nullable();
            $table->string('eps')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 40)->nullable();
            $table->timestamps();

            $table->unique('tercero_cliente_perfil_id', 'gym_perfil_cliente_unique');
        });

        Schema::create('tercero_proveedor_perfiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tercero_id')->constrained('terceros')->cascadeOnDelete();
            $table->unsignedInteger('plazo_pago_dias')->nullable();
            $table->boolean('preferido')->default(false);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique('tercero_id');
        });

        Schema::create('tercero_trabajador_perfiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tercero_id')->constrained('terceros')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('cargo')->nullable();
            $table->date('fecha_ingreso')->nullable();
            $table->string('estado_laboral', 20)->default('activo'); // activo|retirado|suspendido
            $table->timestamps();

            $table->unique('tercero_id');
        });

        Schema::create('contratos_laborales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tercero_id')->constrained('terceros')->cascadeOnDelete();
            $table->string('tipo_contrato', 40)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->decimal('salario_base', 15, 2)->nullable();
            $table->string('jornada', 40)->nullable();
            $table->string('cargo')->nullable();
            $table->string('estado', 20)->default('vigente'); // vigente|finalizado|anulado
            $table->timestamps();

            $table->index(['tercero_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos_laborales');
        Schema::dropIfExists('tercero_trabajador_perfiles');
        Schema::dropIfExists('tercero_proveedor_perfiles');
        Schema::dropIfExists('tercero_cliente_gym_perfiles');
        Schema::dropIfExists('tercero_cliente_perfiles');
        Schema::dropIfExists('tercero_direcciones');
        Schema::dropIfExists('tercero_contactos');
        Schema::dropIfExists('tercero_roles');
        Schema::dropIfExists('terceros');
    }
};
