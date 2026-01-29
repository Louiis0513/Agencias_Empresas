<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos para marcar pagos revertidos (trazabilidad bancaria).
     */
    public function up(): void
    {
        Schema::table('account_payable_payments', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable()->after('notes');
            $table->foreignId('reversal_user_id')->nullable()->after('reversed_at')->constrained('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('account_payable_payments', function (Blueprint $table) {
            $table->dropForeign(['reversal_user_id']);
            $table->dropColumn(['reversed_at', 'reversal_user_id']);
        });
    }
};
