<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->date('vence_at')->nullable()->after('nota');
            $table->string('estado', 32)->default('borrador')->after('vence_at');
            $table->foreignId('invoice_id')->nullable()->after('estado')->constrained('invoices')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropColumn(['vence_at', 'estado', 'invoice_id']);
        });
    }
};
