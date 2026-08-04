<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('comprobantes_ingreso', 'monto_anticipo')) {
            Schema::table('comprobantes_ingreso', function (Blueprint $table) {
                $table->decimal('monto_anticipo', 15, 2)->default(0)->after('total_amount');
            });
        }

        if (! Schema::hasColumn('comprobante_ingreso_destinos', 'forma_pago_id')) {
            Schema::table('comprobante_ingreso_destinos', function (Blueprint $table) {
                $table->unsignedBigInteger('forma_pago_id')->nullable()->after('bolsillo_id');
            });
        }

        if (! $this->hasForeignKey('comprobante_ingreso_destinos', 'ci_destinos_forma_pago_fk')) {
            Schema::table('comprobante_ingreso_destinos', function (Blueprint $table) {
                $table->foreign('forma_pago_id', 'ci_destinos_forma_pago_fk')
                    ->references('id')
                    ->on('formas_pago')
                    ->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('comprobante_ingreso_aplicaciones', 'account_receivable_cuota_id')) {
            Schema::table('comprobante_ingreso_aplicaciones', function (Blueprint $table) {
                $table->unsignedBigInteger('account_receivable_cuota_id')->nullable()->after('account_receivable_id');
            });
        }

        if (! $this->hasForeignKey('comprobante_ingreso_aplicaciones', 'ci_aplic_cuota_fk')) {
            Schema::table('comprobante_ingreso_aplicaciones', function (Blueprint $table) {
                $table->foreign('account_receivable_cuota_id', 'ci_aplic_cuota_fk')
                    ->references('id')
                    ->on('account_receivable_cuotas')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('comprobante_ingreso_aplicaciones', 'account_receivable_cuota_id')) {
            Schema::table('comprobante_ingreso_aplicaciones', function (Blueprint $table) {
                $table->dropForeign('ci_aplic_cuota_fk');
                $table->dropColumn('account_receivable_cuota_id');
            });
        }

        if (Schema::hasColumn('comprobante_ingreso_destinos', 'forma_pago_id')) {
            Schema::table('comprobante_ingreso_destinos', function (Blueprint $table) {
                $table->dropForeign('ci_destinos_forma_pago_fk');
                $table->dropColumn('forma_pago_id');
            });
        }

        if (Schema::hasColumn('comprobantes_ingreso', 'monto_anticipo')) {
            Schema::table('comprobantes_ingreso', function (Blueprint $table) {
                $table->dropColumn('monto_anticipo');
            });
        }
    }

    private function hasForeignKey(string $table, string $name): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'sqlite') {
            // Fresh sqlite test DB: if column just added without FK, allow create; if migrate re-run, swallow.
            return false;
        }

        $connection = Schema::getConnection();
        $dbName = $connection->getDatabaseName();
        $exists = $connection->selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$dbName, $table, $name, 'FOREIGN KEY']
        );

        return (bool) $exists;
    }
};
