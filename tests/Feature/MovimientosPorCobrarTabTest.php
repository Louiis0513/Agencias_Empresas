<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MovimientosPorCobrarTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_pestana_por_cobrar_muestra_listado_o_vacio(): void
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

        $response = $this->actingAs($user)->get(route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'por-cobrar']));

        $response->assertOk();
        $response->assertSee(__('Saldo pendiente de cobro'), false);
        $response->assertSee(__('No hay cuentas por cobrar.'), false);
    }

    public function test_indice_cuentas_por_cobrar_redirige_a_movimientos_pestana_por_cobrar(): void
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

        $response = $this->actingAs($user)->get(route('stores.accounts-receivables', $store));

        $response->assertRedirect(route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'por-cobrar']));
    }
}
