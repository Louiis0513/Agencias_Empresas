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
        Schema::create('support_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->onDelete('set null');

            $table->string('status', 20)->default('BORRADOR');
            $table->string('payment_status', 20)->default('PAGADO');
            $table->date('due_date')->nullable();

            $table->string('doc_prefix', 20)->default('DSE');
            $table->unsignedBigInteger('doc_number');
            $table->date('issue_date');

            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_total', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('notes')->nullable();

            $table->index(['store_id']);
            $table->index(['proveedor_id']);
            $table->index(['status']);
            $table->index(['issue_date']);
            $table->unique(['store_id', 'doc_prefix', 'doc_number'], 'support_documents_store_prefix_number_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('support_documents');
    }
};
