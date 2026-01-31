<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Migra datos existentes de account_payable_payments a comprobantes_egreso.
     * Mantiene trazabilidad; account_payable_payments se deja para referencia histórica.
     */
    public function up(): void
    {
        $payments = DB::table('account_payable_payments')->get();

        foreach ($payments as $payment) {
            $accountPayable = DB::table('accounts_payables')->find($payment->account_payable_id);
            if (! $accountPayable) {
                continue;
            }

            $purchase = DB::table('purchases')->find($accountPayable->purchase_id);
            $proveedor = $purchase ? DB::table('proveedores')->find($purchase->proveedor_id) : null;
            $beneficiaryName = $proveedor ? $proveedor->nombre : 'Proveedor';

            $storeId = $payment->store_id;
            $nextNumber = DB::table('comprobantes_egreso')
                ->where('store_id', $storeId)
                ->count() + 1;
            $number = 'CE-' . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);

            $comprobanteId = DB::table('comprobantes_egreso')->insertGetId([
                'store_id' => $payment->store_id,
                'number' => $number,
                'total_amount' => $payment->amount,
                'payment_date' => $payment->payment_date,
                'notes' => $payment->notes,
                'type' => 'PAGO_CUENTA',
                'beneficiary_name' => $beneficiaryName,
                'user_id' => $payment->user_id,
                'reversed_at' => $payment->reversed_at,
                'reversal_user_id' => $payment->reversal_user_id,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at,
            ]);

            DB::table('comprobante_egreso_destinos')->insert([
                'comprobante_egreso_id' => $comprobanteId,
                'type' => 'CUENTA_POR_PAGAR',
                'account_payable_id' => $payment->account_payable_id,
                'amount' => $payment->amount,
                'created_at' => $payment->created_at,
                'updated_at' => $payment->updated_at,
            ]);

            $parts = DB::table('account_payable_payment_parts')
                ->where('account_payable_payment_id', $payment->id)
                ->get();

            foreach ($parts as $part) {
                DB::table('comprobante_egreso_origenes')->insert([
                    'comprobante_egreso_id' => $comprobanteId,
                    'bolsillo_id' => $part->bolsillo_id,
                    'amount' => $part->amount,
                    'reference' => null,
                    'created_at' => $part->created_at,
                    'updated_at' => $part->updated_at,
                ]);
            }

            DB::table('movimientos_bolsillo')
                ->where('account_payable_payment_id', $payment->id)
                ->update(['comprobante_egreso_id' => $comprobanteId]);
        }
    }

    public function down(): void
    {
        DB::table('movimientos_bolsillo')->whereNotNull('comprobante_egreso_id')->update(['comprobante_egreso_id' => null]);
        DB::table('comprobante_egreso_origenes')->truncate();
        DB::table('comprobante_egreso_destinos')->truncate();
        DB::table('comprobantes_egreso')->truncate();
    }
};
