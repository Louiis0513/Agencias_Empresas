<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Refactor activos: 1 activo físico = 1 registro.
     * Serial obligatorio y único por tienda; eliminar control_type y template; lifecycle status.
     */
    public function up(): void
    {
        // 1. Drop template self-reference: get actual FK name (can vary) and drop it so we can drop columns/unique later
        $fkRow = DB::selectOne("
            SELECT CONSTRAINT_NAME as name FROM information_schema.KEY_COLUMN_USAGE
            WHERE table_schema = DATABASE() AND table_name = 'activos' AND COLUMN_NAME = 'activo_template_id'
            AND REFERENCED_TABLE_NAME = 'activos' LIMIT 1
        ");
        if ($fkRow && ! empty($fkRow->name)) {
            DB::statement("ALTER TABLE activos DROP FOREIGN KEY " . $fkRow->name);
        }

        // 2. Assign synthetic serials to rows with null serial_number (legacy LOTE)
        if (Schema::hasColumn('activos', 'serial_number')) {
            foreach (DB::table('activos')->whereNull('serial_number')->orderBy('id')->cursor() as $row) {
                DB::table('activos')->where('id', $row->id)->update([
                    'serial_number' => 'MIGR-' . $row->store_id . '-' . $row->id,
                ]);
            }
        }

        // 3. Set quantity = 1 for all (single record per asset)
        if (Schema::hasColumn('activos', 'quantity')) {
            DB::table('activos')->update(['quantity' => 1]);
        }

        // 4. Map old status to new lifecycle status (only if status column exists)
        if (Schema::hasColumn('activos', 'status')) {
            $statusMap = [
                'ACTIVO' => 'OPERATIVO',
                'EN_MANTENIMIENTO' => 'EN_REPARACION',
                'PRESTADO' => 'EN_PRESTAMO',
                'BAJA' => 'DADO_DE_BAJA',
            ];
            foreach ($statusMap as $old => $new) {
                DB::table('activos')->where('status', $old)->update(['status' => $new]);
            }
        }

        // 5. Drop columns first (so no FK depends on them), then drop unique
        $columnsToDrop = array_filter(['control_type', 'activo_template_id'], fn (string $col) => Schema::hasColumn('activos', $col));
        if (! empty($columnsToDrop)) {
            Schema::table('activos', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }

        // 6. Alter columns; do not drop/re-add unique index (can fail with "needed in a foreign key" in some MySQL setups)
        Schema::table('activos', function (Blueprint $table) {
            $table->string('serial_number')->nullable(false)->change();
            $table->unsignedInteger('quantity')->default(1)->change();
            $table->string('status', 30)->default('OPERATIVO')
                ->comment('OPERATIVO, EN_REPARACION, EN_PRESTAMO, DONADO, DADO_DE_BAJA, VENDIDO')->change();
        });
    }

    public function down(): void
    {
        Schema::table('activos', function (Blueprint $table) {
            $table->dropUnique('activos_store_serial_unique');
        });

        Schema::table('activos', function (Blueprint $table) {
            $table->string('serial_number')->nullable()->change();
            $table->string('status', 30)->default('ACTIVO')->change();
            $table->string('control_type', 20)->default('LOTE')->after('store_id');
            $table->foreignId('activo_template_id')->nullable()->after('warranty_expiry')
                ->constrained('activos')->nullOnDelete();
        });

        Schema::table('activos', function (Blueprint $table) {
            $table->unique(['store_id', 'serial_number'], 'activos_store_serial_unique');
        });
    }
};
