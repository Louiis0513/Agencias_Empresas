<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cutover: migra customers/proveedores/workers → terceros y remapea FKs.
 * Entorno de pruebas: elimina tablas legacy al final.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->addTerceroColumns();
        $this->migrateLegacyRows();
        $this->remapForeignKeys();
        $this->finalizeConstraintsAndDropLegacy();
    }

    public function down(): void
    {
        // Cutover irreversible en pruebas: no recrea customers/proveedores/workers.
        throw new RuntimeException('No se puede revertir el cutover de terceros. Restaura un backup.');
    }

    private function addTerceroColumns(): void
    {
        $tablesCustomer = [
            'invoices',
            'accounts_receivable',
            'comprobantes_ingreso',
            'cotizaciones',
            'customer_subscriptions',
            'subscription_entries',
        ];

        foreach ($tablesCustomer as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'tercero_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->foreignId('tercero_id')->nullable()->after('store_id')->constrained('terceros')->nullOnDelete();
                });
            }
        }

        foreach (['purchases', 'comprobantes_egreso', 'support_documents'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'tercero_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->foreignId('tercero_id')->nullable()->after('store_id')->constrained('terceros')->nullOnDelete();
                });
            }
        }

        if (Schema::hasTable('worker_schedules') && ! Schema::hasColumn('worker_schedules', 'tercero_id')) {
            Schema::table('worker_schedules', function (Blueprint $blueprint) {
                $blueprint->foreignId('tercero_id')->nullable()->after('store_id')->constrained('terceros')->nullOnDelete();
            });
        }

        if (Schema::hasTable('accounts_payables') && ! Schema::hasColumn('accounts_payables', 'tercero_id')) {
            Schema::table('accounts_payables', function (Blueprint $blueprint) {
                $blueprint->foreignId('tercero_id')->nullable()->after('store_id')->constrained('terceros')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('producto_tercero')) {
            Schema::create('producto_tercero', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->foreignId('tercero_id')->constrained('terceros')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['product_id', 'tercero_id']);
            });
        }
    }

    private function migrateLegacyRows(): void
    {
        $mapCustomer = [];
        $mapProveedor = [];
        $mapWorker = [];

        if (Schema::hasTable('customers')) {
            foreach (DB::table('customers')->orderBy('id')->get() as $row) {
                $doc = $this->normalizeDoc($row->document_number ?? null);
                $terceroId = $this->findOrCreateTercero(
                    (int) $row->store_id,
                    $doc ? 'CC' : null,
                    $doc,
                    [
                        'user_id' => $row->user_id,
                        'nombre' => $row->name,
                        'email' => $row->email,
                        'telefono' => $row->phone,
                        'direccion' => $row->address,
                        'activo' => true,
                        'tipo_persona' => 'natural',
                    ],
                    'cliente'
                );
                $mapCustomer[(int) $row->id] = $terceroId;
                $this->ensureRol($terceroId, 'cliente');
                $this->ensureClientePerfil($terceroId, $row);
                $this->seedContactoDireccion($terceroId, $row->name, $row->email, $row->phone, $row->address);
            }
        }

        if (Schema::hasTable('proveedores')) {
            foreach (DB::table('proveedores')->orderBy('id')->get() as $row) {
                $doc = $this->normalizeDoc($row->nit ?? null);
                $existing = $doc
                    ? $this->findByDoc((int) $row->store_id, $doc)
                    : null;

                if ($existing) {
                    $terceroId = $existing;
                    $this->fillEmpty($terceroId, [
                        'nombre' => $row->nombre,
                        'email' => $row->email,
                        'telefono' => $row->numero_celular,
                        'telefono_secundario' => $row->telefono,
                        'direccion' => $row->direccion,
                        'tipo_identificacion' => $doc ? 'NIT' : null,
                        'activo' => (bool) $row->estado,
                    ]);
                } else {
                    $terceroId = $this->findOrCreateTercero(
                        (int) $row->store_id,
                        $doc ? 'NIT' : null,
                        $doc,
                        [
                            'nombre' => $row->nombre,
                            'email' => $row->email,
                            'telefono' => $row->numero_celular,
                            'telefono_secundario' => $row->telefono,
                            'direccion' => $row->direccion,
                            'activo' => (bool) $row->estado,
                            'tipo_persona' => $doc && strlen($doc) > 9 ? 'juridica' : 'natural',
                        ],
                        'proveedor'
                    );
                }

                $mapProveedor[(int) $row->id] = $terceroId;
                $this->ensureRol($terceroId, 'proveedor');
                $this->ensureProveedorPerfil($terceroId);
                $this->seedContactoDireccion($terceroId, $row->nombre, $row->email, $row->numero_celular, $row->direccion);
            }
        }

        if (Schema::hasTable('workers')) {
            foreach (DB::table('workers')->orderBy('id')->get() as $row) {
                $doc = $this->normalizeDoc($row->document_number ?? null);
                $existing = $doc
                    ? $this->findByDoc((int) $row->store_id, $doc)
                    : null;

                if ($existing) {
                    $terceroId = $existing;
                    $this->fillEmpty($terceroId, [
                        'user_id' => $row->user_id,
                        'nombre' => $row->name,
                        'email' => $row->email,
                        'telefono' => $row->phone,
                        'direccion' => $row->address,
                        'tipo_identificacion' => $doc ? 'CC' : null,
                    ]);
                } else {
                    $terceroId = $this->findOrCreateTercero(
                        (int) $row->store_id,
                        $doc ? 'CC' : null,
                        $doc,
                        [
                            'user_id' => $row->user_id,
                            'nombre' => $row->name,
                            'email' => $row->email,
                            'telefono' => $row->phone,
                            'direccion' => $row->address,
                            'activo' => true,
                            'tipo_persona' => 'natural',
                        ],
                        'trabajador'
                    );
                }

                $mapWorker[(int) $row->id] = $terceroId;
                $this->ensureRol($terceroId, 'trabajador');
                $this->ensureTrabajadorPerfil($terceroId, $row);
                $this->seedContactoDireccion($terceroId, $row->name, $row->email, $row->phone, $row->address);
            }
        }

        // Persist maps in temp tables for remap
        Schema::dropIfExists('_map_customer_tercero');
        Schema::dropIfExists('_map_proveedor_tercero');
        Schema::dropIfExists('_map_worker_tercero');

        Schema::create('_map_customer_tercero', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->primary();
            $table->unsignedBigInteger('tercero_id');
        });
        Schema::create('_map_proveedor_tercero', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->primary();
            $table->unsignedBigInteger('tercero_id');
        });
        Schema::create('_map_worker_tercero', function (Blueprint $table) {
            $table->unsignedBigInteger('legacy_id')->primary();
            $table->unsignedBigInteger('tercero_id');
        });

        foreach ($mapCustomer as $legacyId => $terceroId) {
            DB::table('_map_customer_tercero')->insert(['legacy_id' => $legacyId, 'tercero_id' => $terceroId]);
        }
        foreach ($mapProveedor as $legacyId => $terceroId) {
            DB::table('_map_proveedor_tercero')->insert(['legacy_id' => $legacyId, 'tercero_id' => $terceroId]);
        }
        foreach ($mapWorker as $legacyId => $terceroId) {
            DB::table('_map_worker_tercero')->insert(['legacy_id' => $legacyId, 'tercero_id' => $terceroId]);
        }
    }

    private function remapForeignKeys(): void
    {
        $customerTables = [
            'invoices' => 'customer_id',
            'accounts_receivable' => 'customer_id',
            'comprobantes_ingreso' => 'customer_id',
            'cotizaciones' => 'customer_id',
            'customer_subscriptions' => 'customer_id',
            'subscription_entries' => 'customer_id',
        ];

        foreach ($customerTables as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            $rows = DB::table($table)->whereNotNull($column)->get(['id', $column]);
            foreach ($rows as $row) {
                $map = DB::table('_map_customer_tercero')->where('legacy_id', $row->{$column})->first();
                if ($map) {
                    DB::table($table)->where('id', $row->id)->update(['tercero_id' => $map->tercero_id]);
                }
            }
        }

        $proveedorTables = [
            'purchases' => 'proveedor_id',
            'comprobantes_egreso' => 'proveedor_id',
            'support_documents' => 'proveedor_id',
        ];

        foreach ($proveedorTables as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }
            $rows = DB::table($table)->whereNotNull($column)->get(['id', $column]);
            foreach ($rows as $row) {
                $map = DB::table('_map_proveedor_tercero')->where('legacy_id', $row->{$column})->first();
                if ($map) {
                    DB::table($table)->where('id', $row->id)->update(['tercero_id' => $map->tercero_id]);
                }
            }
        }

        if (Schema::hasTable('worker_schedules') && Schema::hasColumn('worker_schedules', 'worker_id')) {
            $rows = DB::table('worker_schedules')->whereNotNull('worker_id')->get(['id', 'worker_id']);
            foreach ($rows as $row) {
                $map = DB::table('_map_worker_tercero')->where('legacy_id', $row->worker_id)->first();
                if ($map) {
                    DB::table('worker_schedules')->where('id', $row->id)->update(['tercero_id' => $map->tercero_id]);
                }
            }
        }

        if (Schema::hasTable('producto_proveedor') && Schema::hasTable('producto_tercero')) {
            $rows = DB::table('producto_proveedor')->get();
            foreach ($rows as $row) {
                $map = DB::table('_map_proveedor_tercero')->where('legacy_id', $row->proveedor_id)->first();
                if (! $map) {
                    continue;
                }
                $exists = DB::table('producto_tercero')
                    ->where('product_id', $row->product_id)
                    ->where('tercero_id', $map->tercero_id)
                    ->exists();
                if (! $exists) {
                    DB::table('producto_tercero')->insert([
                        'product_id' => $row->product_id,
                        'tercero_id' => $map->tercero_id,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);
                }
            }
        }

        if (Schema::hasTable('accounts_payables')) {
            if (Schema::hasColumn('purchases', 'tercero_id')) {
                $aps = DB::table('accounts_payables')->whereNotNull('purchase_id')->whereNull('tercero_id')->get();
                foreach ($aps as $ap) {
                    $purchaseTercero = DB::table('purchases')->where('id', $ap->purchase_id)->value('tercero_id');
                    if ($purchaseTercero) {
                        DB::table('accounts_payables')->where('id', $ap->id)->update(['tercero_id' => $purchaseTercero]);
                    }
                }
            }
            $manuals = DB::table('accounts_payables')
                ->whereNull('tercero_id')
                ->whereNotNull('creditor_document')
                ->get();
            foreach ($manuals as $ap) {
                $doc = $this->normalizeDoc($ap->creditor_document);
                if (! $doc) {
                    continue;
                }
                $terceroId = $this->findByDoc((int) $ap->store_id, $doc);
                if ($terceroId) {
                    DB::table('accounts_payables')->where('id', $ap->id)->update(['tercero_id' => $terceroId]);
                }
            }
        }
    }

    private function finalizeConstraintsAndDropLegacy(): void
    {
        if (Schema::hasTable('customer_subscriptions')) {
            DB::table('customer_subscriptions')->whereNull('tercero_id')->delete();
        }
        if (Schema::hasTable('subscription_entries')) {
            DB::table('subscription_entries')->whereNull('tercero_id')->delete();
        }
        if (Schema::hasTable('worker_schedules')) {
            DB::table('worker_schedules')->whereNull('tercero_id')->delete();
        }

        $this->dropLegacyColumn('invoices', 'customer_id');
        $this->dropLegacyColumn('accounts_receivable', 'customer_id');
        $this->dropLegacyColumn('comprobantes_ingreso', 'customer_id');
        $this->dropLegacyColumn('cotizaciones', 'customer_id');
        $this->dropLegacyColumn('customer_subscriptions', 'customer_id');
        $this->dropLegacyColumn('subscription_entries', 'customer_id');
        $this->dropLegacyColumn('purchases', 'proveedor_id');
        $this->dropLegacyColumn('comprobantes_egreso', 'proveedor_id');
        $this->dropLegacyColumn('support_documents', 'proveedor_id');
        $this->dropLegacyColumn('worker_schedules', 'worker_id');

        Schema::dropIfExists('producto_proveedor');
        Schema::dropIfExists('_map_customer_tercero');
        Schema::dropIfExists('_map_proveedor_tercero');
        Schema::dropIfExists('_map_worker_tercero');
        Schema::dropIfExists('workers');
        Schema::dropIfExists('proveedores');
        Schema::dropIfExists('customers');
    }

    private function dropLegacyColumn(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        try {
            $fkNames = DB::select('
                SELECT CONSTRAINT_NAME as name
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?
                  AND REFERENCED_TABLE_NAME IS NOT NULL
            ', [$table, $column]);

            foreach ($fkNames as $fk) {
                $name = $fk->name ?? $fk->CONSTRAINT_NAME ?? null;
                if ($name) {
                    DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
                }
            }
        } catch (\Throwable $e) {
            // SQLite u otros: intentar dropForeign por columna
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($column) {
                    $blueprint->dropForeign([$column]);
                });
            } catch (\Throwable $e2) {
                // continuar al dropColumn
            }
        }

        // SQLite no permite reconstruir la tabla al eliminar una columna si queda
        // un índice explícito que la referencia (p. ej. support_documents_proveedor_id_index).
        try {
            foreach (Schema::getIndexes($table) as $index) {
                $columns = $index['columns'] ?? [];
                $name = $index['name'] ?? null;
                if ($name && strtolower($name) !== 'primary' && in_array($column, $columns, true)) {
                    Schema::table($table, function (Blueprint $blueprint) use ($name) {
                        $blueprint->dropIndex($name);
                    });
                }
            }
        } catch (\Throwable $e) {
            // Algunos motores eliminan automáticamente el índice junto con la FK/columna.
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column) {
            $blueprint->dropColumn($column);
        });
    }

    private function normalizeDoc(?string $doc): ?string
    {
        if ($doc === null) {
            return null;
        }
        $doc = preg_replace('/\s+/', '', trim($doc));

        return $doc === '' ? null : $doc;
    }

    private function findByDoc(int $storeId, string $doc): ?int
    {
        $id = DB::table('terceros')
            ->where('store_id', $storeId)
            ->where('numero_identificacion', $doc)
            ->whereNull('deleted_at')
            ->value('id');

        return $id ? (int) $id : null;
    }

    private function findOrCreateTercero(int $storeId, ?string $tipoId, ?string $doc, array $attrs, string $rol): int
    {
        if ($doc) {
            $existing = $this->findByDoc($storeId, $doc);
            if ($existing) {
                $this->fillEmpty($existing, $attrs);

                return $existing;
            }
        }

        return (int) DB::table('terceros')->insertGetId(array_merge([
            'store_id' => $storeId,
            'tipo_persona' => $attrs['tipo_persona'] ?? 'natural',
            'tipo_identificacion' => $tipoId,
            'numero_identificacion' => $doc,
            'digito_verificacion' => null,
            'nombre' => $attrs['nombre'] ?? 'Sin nombre',
            'nombre_comercial' => null,
            'email' => $attrs['email'] ?? null,
            'telefono' => $attrs['telefono'] ?? null,
            'telefono_secundario' => $attrs['telefono_secundario'] ?? null,
            'direccion' => $attrs['direccion'] ?? null,
            'activo' => $attrs['activo'] ?? true,
            'user_id' => $attrs['user_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ], []));
    }

    private function fillEmpty(int $terceroId, array $attrs): void
    {
        $row = DB::table('terceros')->where('id', $terceroId)->first();
        if (! $row) {
            return;
        }

        $update = [];
        foreach (['user_id', 'nombre', 'email', 'telefono', 'telefono_secundario', 'direccion', 'tipo_identificacion', 'activo'] as $key) {
            if (! array_key_exists($key, $attrs)) {
                continue;
            }
            $current = $row->{$key} ?? null;
            if ($current === null || $current === '') {
                $update[$key] = $attrs[$key];
            }
        }
        if ($update !== []) {
            $update['updated_at'] = now();
            DB::table('terceros')->where('id', $terceroId)->update($update);
        }
    }

    private function ensureRol(int $terceroId, string $rol): void
    {
        $exists = DB::table('tercero_roles')
            ->where('tercero_id', $terceroId)
            ->where('rol', $rol)
            ->exists();
        if (! $exists) {
            DB::table('tercero_roles')->insert([
                'tercero_id' => $terceroId,
                'rol' => $rol,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureClientePerfil(int $terceroId, object $customer): void
    {
        $perfilId = DB::table('tercero_cliente_perfiles')->where('tercero_id', $terceroId)->value('id');
        if (! $perfilId) {
            $perfilId = DB::table('tercero_cliente_perfiles')->insertGetId([
                'tercero_id' => $terceroId,
                'credito_habilitado' => false,
                'bloqueado_ventas' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $hasGym = DB::table('tercero_cliente_gym_perfiles')
            ->where('tercero_cliente_perfil_id', $perfilId)
            ->exists();
        if (! $hasGym) {
            DB::table('tercero_cliente_gym_perfiles')->insert([
                'tercero_cliente_perfil_id' => $perfilId,
                'gender' => $customer->gender ?? null,
                'blood_type' => $customer->blood_type ?? null,
                'eps' => $customer->eps ?? null,
                'birth_date' => $customer->birth_date ?? null,
                'emergency_contact_name' => $customer->emergency_contact_name ?? null,
                'emergency_contact_phone' => $customer->emergency_contact_phone ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureProveedorPerfil(int $terceroId): void
    {
        $exists = DB::table('tercero_proveedor_perfiles')->where('tercero_id', $terceroId)->exists();
        if (! $exists) {
            DB::table('tercero_proveedor_perfiles')->insert([
                'tercero_id' => $terceroId,
                'preferido' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function ensureTrabajadorPerfil(int $terceroId, object $worker): void
    {
        $exists = DB::table('tercero_trabajador_perfiles')->where('tercero_id', $terceroId)->exists();
        if (! $exists) {
            DB::table('tercero_trabajador_perfiles')->insert([
                'tercero_id' => $terceroId,
                'role_id' => $worker->role_id ?? null,
                'estado_laboral' => 'activo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function seedContactoDireccion(int $terceroId, ?string $nombre, ?string $email, ?string $telefono, ?string $direccion): void
    {
        $hasContacto = DB::table('tercero_contactos')->where('tercero_id', $terceroId)->exists();
        if (! $hasContacto && ($email || $telefono || $nombre)) {
            DB::table('tercero_contactos')->insert([
                'tercero_id' => $terceroId,
                'nombre' => $nombre ?: 'Contacto principal',
                'email' => $email,
                'telefono' => $telefono,
                'celular' => $telefono,
                'es_principal' => true,
                'es_facturacion' => true,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $hasDir = DB::table('tercero_direcciones')->where('tercero_id', $terceroId)->exists();
        if (! $hasDir && filled($direccion)) {
            DB::table('tercero_direcciones')->insert([
                'tercero_id' => $terceroId,
                'tipo' => 'fiscal',
                'linea' => $direccion,
                'pais' => 'Colombia',
                'es_principal' => true,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
