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
        Schema::table('customers', function (Blueprint $table) {
            // Email único por tienda (solo si no es null)
            $table->unique(['store_id', 'email'], 'customers_store_email_unique');
            
            // Documento único por tienda (solo si no es null)
            $table->unique(['store_id', 'document_number'], 'customers_store_document_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique('customers_store_email_unique');
            $table->dropUnique('customers_store_document_unique');
        });
    }
};
