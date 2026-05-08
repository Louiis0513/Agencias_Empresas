<?php

namespace Tests\Feature;

use App\Models\Bolsillo;
use App\Models\ComprobanteEgreso;
use App\Models\ComprobanteEgresoOrigen;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MovimientosEgresosTabTest extends TestCase
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

    public function test_movimientos_muestra_una_fila_por_origen_de_egreso(): void
    {
        [$user, $store] = $this->seedStoreWithOwner();

        $b1 = Bolsillo::create([
            'store_id' => $store->id,
            'name' => 'Caja egreso A',
        ]);
        $b2 = Bolsillo::create([
            'store_id' => $store->id,
            'name' => 'Caja egreso B',
        ]);

        $ce = ComprobanteEgreso::create([
            'store_id' => $store->id,
            'number' => 'CE-099',
            'total_amount' => '150.00',
            'payment_date' => now()->toDateString(),
            'notes' => 'Egreso movimientos feature',
            'type' => ComprobanteEgreso::TYPE_GASTO_DIRECTO,
            'beneficiary_name' => 'Proveedor test',
            'user_id' => $user->id,
        ]);

        ComprobanteEgresoOrigen::create([
            'comprobante_egreso_id' => $ce->id,
            'bolsillo_id' => $b1->id,
            'amount' => '60.00',
            'reference' => 'REF-EG-A',
        ]);
        ComprobanteEgresoOrigen::create([
            'comprobante_egreso_id' => $ce->id,
            'bolsillo_id' => $b2->id,
            'amount' => '90.00',
            'reference' => 'REF-EG-B',
        ]);

        $response = $this->actingAs($user)->get(route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'egresos']));

        $response->assertOk();
        $response->assertSee('REF-EG-A', false);
        $response->assertSee('REF-EG-B', false);
        $response->assertSee('Caja egreso A', false);
        $response->assertSee('Caja egreso B', false);
    }

    public function test_movimientos_excluye_origenes_de_comprobantes_revertidos(): void
    {
        [$user, $store] = $this->seedStoreWithOwner();

        $b1 = Bolsillo::create([
            'store_id' => $store->id,
            'name' => 'Caja egreso rev',
        ]);

        $ce = ComprobanteEgreso::create([
            'store_id' => $store->id,
            'number' => 'CE-100',
            'total_amount' => '10.00',
            'payment_date' => now()->toDateString(),
            'notes' => 'Revertido egreso',
            'type' => ComprobanteEgreso::TYPE_GASTO_DIRECTO,
            'beneficiary_name' => 'X',
            'user_id' => $user->id,
            'reversed_at' => now(),
        ]);

        ComprobanteEgresoOrigen::create([
            'comprobante_egreso_id' => $ce->id,
            'bolsillo_id' => $b1->id,
            'amount' => '10.00',
            'reference' => 'SOLO-REV-EG',
        ]);

        $response = $this->actingAs($user)->get(route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'egresos']));

        $response->assertOk();
        $response->assertDontSee('SOLO-REV-EG', false);
        $response->assertSee(__('No hay egresos registrados en este periodo.'), false);
    }
}
