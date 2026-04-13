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
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->foreignId('support_document_id')
                ->nullable()
                ->after('invoice_id')
                ->constrained('support_documents')
                ->onDelete('set null');
            $table->index('support_document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('movimientos_inventario', function (Blueprint $table) {
            $table->dropIndex(['support_document_id']);
            $table->dropForeign(['support_document_id']);
            $table->dropColumn('support_document_id');
        });
    }
};
