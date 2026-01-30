<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evita duplicados: una compra solo puede tener una cuenta por pagar.
     */
    public function up(): void
    {
        $duplicates = \DB::table('accounts_payables')
            ->select('purchase_id')
            ->groupBy('purchase_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('purchase_id');

        foreach ($duplicates as $purchaseId) {
            $ids = \DB::table('accounts_payables')
                ->where('purchase_id', $purchaseId)
                ->orderBy('id')
                ->pluck('id');
            \DB::table('accounts_payables')
                ->whereIn('id', $ids->slice(1)->values())
                ->delete();
        }

        Schema::table('accounts_payables', function (Blueprint $table) {
            $table->unique('purchase_id');
        });
    }

    public function down(): void
    {
        Schema::table('accounts_payables', function (Blueprint $table) {
            $table->dropUnique(['purchase_id']);
        });
    }
};
