<?php

namespace Tests\Feature;

use App\Models\AccountPayable;
use App\Models\Bolsillo;
use App\Models\ComprobanteEgreso;
use App\Models\Proveedor;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\User;
use App\Services\AccountPayableService;
use App\Services\SesionCajaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManualAccountPayableTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Store} */
    private function seedStoreWithOwner(): array
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        DB::table('store_user')->insert([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'role_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $store];
    }

    /**
     * @return array{0: Bolsillo, 1: \App\Models\SesionCaja}
     */
    private function seedOpenCajaConSaldo(Store $store, User $user, float $saldo): array
    {
        $bolsillo = Bolsillo::create([
            'store_id' => $store->id,
            'name' => 'Caja test CxP',
            'saldo' => $saldo,
        ]);

        $sesion = app(SesionCajaService::class)->abrirSesion($store, $user->id, [
            $bolsillo->id => $saldo,
        ]);

        return [$bolsillo, $sesion];
    }

    public function test_post_registra_cxp_manual_y_redirige_al_detalle(): void
    {
        [$user, $store] = $this->seedStoreWithOwner();

        $response = $this->actingAs($user)->post(route('stores.accounts-payables.store-manual', $store), [
            'creditor_name' => 'María Prestador',
            'creditor_document' => '123456',
            'document_reference' => 'CC-2026-01',
            'description' => 'Honorarios enero',
            'total_amount' => '150000',
            'due_date' => now()->addWeek()->toDateString(),
        ]);

        $ap = AccountPayable::where('store_id', $store->id)->first();
        $this->assertNotNull($ap);
        $response->assertRedirect(route('stores.accounts-payables.show', [$store, $ap]));

        $this->assertSame(AccountPayable::SOURCE_MANUAL, $ap->source);
        $this->assertNull($ap->purchase_id);
        $this->assertSame('150000.00', (string) $ap->total_amount);
        $this->assertSame('150000.00', (string) $ap->balance);
        $this->assertSame(AccountPayable::STATUS_PENDIENTE, $ap->status);
    }

    public function test_pago_cxp_manual_liquida_sin_actualizar_compra(): void
    {
        [$user, $store] = $this->seedStoreWithOwner();
        [$bolsillo] = $this->seedOpenCajaConSaldo($store, $user, 500000.0);

        /** @var AccountPayableService $svc */
        $svc = app(AccountPayableService::class);
        $ap = $svc->registrarCuentaPorPagarManual($store, [
            'creditor_name' => 'Acreedor X',
            'total_amount' => 80000,
        ]);

        $purchase = Purchase::create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'proveedor_id' => null,
            'status' => Purchase::STATUS_APROBADO,
            'purchase_type' => Purchase::TYPE_ACTIVO,
            'payment_status' => Purchase::PAYMENT_PENDIENTE,
            'payment_type' => Purchase::PAYMENT_TYPE_CREDITO,
            'total' => '99999.00',
        ]);

        $svc->registrarPago($store, $ap->id, $user->id, [
            'payment_date' => now()->toDateString(),
            'parts' => [
                ['bolsillo_id' => $bolsillo->id, 'amount' => 80000],
            ],
        ]);

        $ap->refresh();
        $purchase->refresh();

        $this->assertSame(AccountPayable::STATUS_PAGADO, $ap->status);
        $this->assertSame('0.00', (string) $ap->balance);
        $this->assertSame(Purchase::PAYMENT_PENDIENTE, $purchase->payment_status);

        $this->assertSame(1, ComprobanteEgreso::deTienda($store->id)->count());
    }

    public function test_pago_cxp_de_compra_marca_compra_como_pagada(): void
    {
        [$user, $store] = $this->seedStoreWithOwner();
        [$bolsillo] = $this->seedOpenCajaConSaldo($store, $user, 500000.0);

        $proveedor = Proveedor::create([
            'store_id' => $store->id,
            'nombre' => 'Prov SA',
            'nit' => '900123',
        ]);

        $purchase = Purchase::create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'proveedor_id' => $proveedor->id,
            'status' => Purchase::STATUS_APROBADO,
            'purchase_type' => Purchase::TYPE_ACTIVO,
            'payment_status' => Purchase::PAYMENT_PENDIENTE,
            'payment_type' => Purchase::PAYMENT_TYPE_CREDITO,
            'total' => '75000.00',
        ]);

        $ap = AccountPayable::create([
            'store_id' => $store->id,
            'purchase_id' => $purchase->id,
            'source' => AccountPayable::SOURCE_COMPRA,
            'total_amount' => 75000,
            'balance' => 75000,
            'status' => AccountPayable::STATUS_PENDIENTE,
        ]);

        /** @var AccountPayableService $svc */
        $svc = app(AccountPayableService::class);
        $svc->registrarPago($store, $ap->id, $user->id, [
            'payment_date' => now()->toDateString(),
            'parts' => [
                ['bolsillo_id' => $bolsillo->id, 'amount' => 75000],
            ],
        ]);

        $ap->refresh();
        $purchase->refresh();

        $this->assertSame(AccountPayable::STATUS_PAGADO, $ap->status);
        $this->assertSame(Purchase::PAYMENT_PAGADO, $purchase->payment_status);
    }

    public function test_pestana_por_pagar_muestra_acreedor_cxp_manual(): void
    {
        [$user, $store] = $this->seedStoreWithOwner();
        app(AccountPayableService::class)->registrarCuentaPorPagarManual($store, [
            'creditor_name' => 'Pedro Independiente',
            'total_amount' => 10000,
        ]);

        $response = $this->actingAs($user)->get(route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'por-pagar']));

        $response->assertOk();
        $response->assertSee('Pedro Independiente', false);
    }
}
