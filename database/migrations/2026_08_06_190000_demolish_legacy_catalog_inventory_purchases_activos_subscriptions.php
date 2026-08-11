<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Demolición controlada: elimina tablas del catálogo comercial, inventario físico,
 * compras, activos y suscripciones de negocio; deja products mínimo.
 *
 * No toca: cuentas_contables, categorias_contables, impuestos, tipos_comprobante.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            $this->demoler();
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }
    }

    private function demoler(): void
    {
        // --- Datos: desbloquear FKs restrictivas / CxP ligadas a compras ---
        if (Schema::hasTable('invoice_details') && Schema::hasColumn('invoice_details', 'store_plan_id')) {
            DB::table('invoice_details')->update(['store_plan_id' => null]);
        }

        if (Schema::hasTable('accounts_payables') && Schema::hasColumn('accounts_payables', 'purchase_id')) {
            $payableIds = DB::table('accounts_payables')
                ->whereNotNull('purchase_id')
                ->pluck('id');

            if ($payableIds->isNotEmpty()) {
                $paymentIds = DB::table('account_payable_payments')
                    ->whereIn('account_payable_id', $payableIds)
                    ->pluck('id');

                if ($paymentIds->isNotEmpty() && Schema::hasTable('account_payable_payment_parts')) {
                    DB::table('account_payable_payment_parts')->whereIn('account_payable_payment_id', $paymentIds)->delete();
                }
                DB::table('account_payable_payments')->whereIn('account_payable_id', $payableIds)->delete();

                if (Schema::hasTable('comprobante_egreso_destinos')) {
                    DB::table('comprobante_egreso_destinos')
                        ->whereIn('account_payable_id', $payableIds)
                        ->delete();
                }

                DB::table('accounts_payables')->whereIn('id', $payableIds)->delete();
            }
        }

        // Cotizaciones: soltar FK a variantes antes de dropear product_variants
        if (Schema::hasTable('cotizacion_items') && Schema::hasColumn('cotizacion_items', 'product_variant_id')) {
            try {
                Schema::table('cotizacion_items', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('product_variant_id');
                });
            } catch (\Throwable $e) {
                // columna puede existir sin FK con ese nombre; dropear columna
                Schema::table('cotizacion_items', function (Blueprint $table) {
                    if (Schema::hasColumn('cotizacion_items', 'product_variant_id')) {
                        $table->dropColumn('product_variant_id');
                    }
                });
            }
        }

        // --- Inventario ---
        Schema::dropIfExists('movimientos_inventario');
        Schema::dropIfExists('batch_items');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('product_items');
        Schema::dropIfExists('product_variants');

        // --- products: soltar FK a categories antes de dropear catálogo ---
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'category_id')) {
            try {
                Schema::table('products', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('category_id');
                });
            } catch (\Throwable $e) {
                Schema::table('products', function (Blueprint $table) {
                    $table->dropColumn('category_id');
                });
            }
        }

        // --- Catálogo comercial ---
        Schema::dropIfExists('product_attribute_options');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('category_attribute');
        Schema::dropIfExists('attribute_group_attribute');
        Schema::dropIfExists('attribute_options');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('attribute_groups');
        Schema::dropIfExists('categories');

        // --- Documentos soporte / compras ---
        Schema::dropIfExists('support_document_inventory_items');
        Schema::dropIfExists('support_document_service_items');
        Schema::dropIfExists('support_documents');
        Schema::dropIfExists('support_document_sequences');
        Schema::dropIfExists('purchase_details');
        Schema::dropIfExists('purchases');

        // --- Activos ---
        Schema::dropIfExists('movimientos_activo');
        Schema::dropIfExists('activos');
        Schema::dropIfExists('activo_locations');

        // --- Suscripciones de negocio (no SaaS plan_features) ---
        Schema::dropIfExists('subscription_entries');
        Schema::dropIfExists('customer_subscriptions');
        Schema::dropIfExists('store_plans');
        Schema::dropIfExists('panel_suscripciones_configs');

        // --- accounts_payables: quitar purchase_id ---
        if (Schema::hasTable('accounts_payables') && Schema::hasColumn('accounts_payables', 'purchase_id')) {
            try {
                Schema::table('accounts_payables', function (Blueprint $table) {
                    $table->dropConstrainedForeignId('purchase_id');
                });
            } catch (\Throwable $e) {
                try {
                    Schema::table('accounts_payables', function (Blueprint $table) {
                        $table->dropUnique(['purchase_id']);
                    });
                } catch (\Throwable $ignored) {
                    // índice puede no existir o tener otro nombre
                }

                try {
                    Schema::table('accounts_payables', function (Blueprint $table) {
                        if (Schema::hasColumn('accounts_payables', 'purchase_id')) {
                            $table->dropColumn('purchase_id');
                        }
                    });
                } catch (\Throwable $ignored) {
                    // sqlite/mysql pueden fallar si el índice residual bloquea; no abortar demolición
                }
            }
        }

        // --- invoice_details: quitar columnas de suscripción ---
        if (Schema::hasTable('invoice_details')) {
            if (Schema::hasColumn('invoice_details', 'store_plan_id')) {
                try {
                    Schema::table('invoice_details', function (Blueprint $table) {
                        $table->dropConstrainedForeignId('store_plan_id');
                    });
                } catch (\Throwable $e) {
                    Schema::table('invoice_details', function (Blueprint $table) {
                        $table->dropColumn('store_plan_id');
                    });
                }
            }
            if (Schema::hasColumn('invoice_details', 'subscription_starts_at')) {
                Schema::table('invoice_details', function (Blueprint $table) {
                    $table->dropColumn('subscription_starts_at');
                });
            }
        }

        // --- products: esquema mínimo ---
        if (Schema::hasTable('products')) {
            $dropCols = collect([
                'barcode', 'sku', 'image_path', 'price', 'cost', 'margin', 'stock',
                'quantity_mode', 'quantity_step', 'location', 'type', 'in_showcase',
            ])->filter(fn ($c) => Schema::hasColumn('products', $c))->values()->all();

            if ($dropCols !== []) {
                Schema::table('products', function (Blueprint $table) use ($dropCols) {
                    $table->dropColumn($dropCols);
                });
            }

            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'name')) {
                    $table->string('name')->default('');
                }
                if (! Schema::hasColumn('products', 'is_active')) {
                    $table->boolean('is_active')->default(true);
                }
                if (! Schema::hasColumn('products', 'categoria_contable_id')) {
                    $table->foreignId('categoria_contable_id')
                        ->nullable()
                        ->constrained('categorias_contables')
                        ->nullOnDelete();
                }
            });
        }

        // Pivot producto-tercero si existe con nombre viejo
        Schema::dropIfExists('producto_proveedor');
        // Mantener producto_tercero si existe (solo ids)
    }

    public function down(): void
    {
        // Demolición irreversible en este corte; no se reconstruye el esquema legado.
    }
};
