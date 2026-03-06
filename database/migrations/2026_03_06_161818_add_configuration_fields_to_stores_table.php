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
        Schema::table('stores', function (Blueprint $table) {
            $table->string('rut_nit')->nullable()->after('slug');
            $table->string('currency', 10)->nullable()->after('rut_nit');
            $table->string('timezone', 50)->nullable()->after('currency');
            $table->string('date_format', 20)->nullable()->after('timezone');
            $table->string('time_format', 5)->nullable()->after('date_format');
            $table->string('country')->nullable()->after('time_format');
            $table->string('department')->nullable()->after('country');
            $table->string('city')->nullable()->after('department');
            $table->string('address')->nullable()->after('city');
            $table->string('phone')->nullable()->after('address');
            $table->string('mobile')->nullable()->after('phone');
            $table->string('domain')->nullable()->after('mobile');
            $table->string('regimen')->nullable()->after('domain');
            $table->string('logo_path')->nullable()->after('regimen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn([
                'rut_nit', 'currency', 'timezone', 'date_format', 'time_format',
                'country', 'department', 'city', 'address', 'phone', 'mobile',
                'domain', 'regimen', 'logo_path',
            ]);
        });
    }
};
