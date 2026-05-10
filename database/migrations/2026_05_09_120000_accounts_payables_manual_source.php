<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts_payables', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
        });

        Schema::table('accounts_payables', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_id')->nullable()->change();
        });

        Schema::table('accounts_payables', function (Blueprint $table) {
            $table->foreign('purchase_id')
                ->references('id')
                ->on('purchases')
                ->cascadeOnDelete();
            $table->string('source', 30)->default('COMPRA')->after('purchase_id');
            $table->string('creditor_name')->nullable()->after('source');
            $table->string('creditor_document', 64)->nullable()->after('creditor_name');
            $table->string('document_reference', 120)->nullable()->after('creditor_document');
            $table->text('description')->nullable()->after('document_reference');
        });

        DB::table('accounts_payables')->update(['source' => 'COMPRA']);
    }

    public function down(): void
    {
        if (DB::table('accounts_payables')->whereNull('purchase_id')->exists()) {
            throw new RuntimeException('No se puede revertir: existen CxP manuales sin compra. Elimínelas primero.');
        }

        Schema::table('accounts_payables', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
        });

        Schema::table('accounts_payables', function (Blueprint $table) {
            $table->dropColumn(['source', 'creditor_name', 'creditor_document', 'document_reference', 'description']);
        });

        Schema::table('accounts_payables', function (Blueprint $table) {
            $table->unsignedBigInteger('purchase_id')->nullable(false)->change();
        });

        Schema::table('accounts_payables', function (Blueprint $table) {
            $table->foreign('purchase_id')
                ->references('id')
                ->on('purchases')
                ->cascadeOnDelete();
        });
    }
};
