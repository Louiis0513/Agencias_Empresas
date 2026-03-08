<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Campos para negocio tipo gym: género, tipo sangre, EPS, fecha nacimiento, contacto emergencia.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('gender', 5)->nullable()->after('address')->comment('M/F/NN');
            $table->string('blood_type', 20)->nullable()->after('gender');
            $table->string('eps', 255)->nullable()->after('blood_type');
            $table->date('birth_date')->nullable()->after('eps');
            $table->string('emergency_contact_name', 255)->nullable()->after('birth_date');
            $table->string('emergency_contact_phone', 30)->nullable()->after('emergency_contact_name')->comment('Solo números');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'gender',
                'blood_type',
                'eps',
                'birth_date',
                'emergency_contact_name',
                'emergency_contact_phone',
            ]);
        });
    }
};
