<?php

namespace Tests\Feature;

use App\Models\Bolsillo;
use App\Models\ComprobanteIngreso;
use App\Models\ComprobanteIngresoDestino;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MovimientosIngresosTabTest extends TestCase
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

    public function test_movimientos_muestra_una_fila_por_destino_de_ingreso(): void
    {
        [$user, $store] = $this->seedStoreWithOwner();

        $b1 = Bolsillo::create([
            'store_id' => $store->id,
            'name' => 'Efectivo Test',
        ]);
        $b2 = Bolsillo::create([
            'store_id' => $store->id,
            'name' => 'Banco Test',
        ]);

        $ci = ComprobanteIngreso::create([
            'store_id' => $store->id,
            'number' => 'CI-099',
            'total_amount' => '150.00',
            'date' => now()->toDateString(),
            'notes' => 'Ingreso movimientos feature',
            'type' => ComprobanteIngreso::TYPE_INGRESO_MANUAL,
            'user_id' => $user->id,
        ]);

        ComprobanteIngresoDestino::create([
            'comprobante_ingreso_id' => $ci->id,
            'bolsillo_id' => $b1->id,
            'amount' => '60.00',
            'reference' => 'REF-LINE-A',
        ]);
        ComprobanteIngresoDestino::create([
            'comprobante_ingreso_id' => $ci->id,
            'bolsillo_id' => $b2->id,
            'amount' => '90.00',
            'reference' => 'REF-LINE-B',
        ]);

        $response = $this->actingAs($user)->get(route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'ingresos']));

        $response->assertOk();
        $response->assertSee('REF-LINE-A', false);
        $response->assertSee('REF-LINE-B', false);
        $response->assertSee('Efectivo Test', false);
        $response->assertSee('Banco Test', false);
    }

    public function test_movimientos_excluye_destinos_de_comprobantes_revertidos(): void
    {
        [$user, $store] = $this->seedStoreWithOwner();

        $b1 = Bolsillo::create([
            'store_id' => $store->id,
            'name' => 'Efectivo Rev',
        ]);

        $ci = ComprobanteIngreso::create([
            'store_id' => $store->id,
            'number' => 'CI-100',
            'total_amount' => '10.00',
            'date' => now()->toDateString(),
            'notes' => 'Revertido no debe listarse',
            'type' => ComprobanteIngreso::TYPE_INGRESO_MANUAL,
            'user_id' => $user->id,
            'reversed_at' => now(),
        ]);

        ComprobanteIngresoDestino::create([
            'comprobante_ingreso_id' => $ci->id,
            'bolsillo_id' => $b1->id,
            'amount' => '10.00',
            'reference' => 'SOLO-REV',
        ]);

        $response = $this->actingAs($user)->get(route('stores.cajas.movimientos', ['store' => $store]));

        $response->assertOk();
        $response->assertDontSee('SOLO-REV', false);
        $response->assertSee(__('No hay ingresos registrados en este periodo.'), false);
    }

    public function test_indice_caja_redirige_a_movimientos(): void
    {
        [$user, $store] = $this->seedStoreWithOwner();

        $response = $this->actingAs($user)->get(route('stores.cajas', $store));

        $response->assertRedirect(route('stores.cajas.movimientos', $store));
    }

    public function test_movimientos_incluye_panel_bolsillos(): void
    {
        [$user, $store] = $this->seedStoreWithOwner();

        Bolsillo::create([
            'store_id' => $store->id,
            'name' => 'Panel Bol Test',
        ]);

        $response = $this->actingAs($user)->get(route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'ingresos']));

        $response->assertOk();
        $response->assertSee(__('Bolsillos y saldos'), false);
        $response->assertSee('Panel Bol Test', false);
    }
}
