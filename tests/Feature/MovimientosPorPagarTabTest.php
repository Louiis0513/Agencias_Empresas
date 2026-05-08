<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MovimientosPorPagarTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_pestana_por_pagar_muestra_listado_o_vacio(): void
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

        $response = $this->actingAs($user)->get(route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'por-pagar']));

        $response->assertOk();
        $response->assertSee(__('Deuda total pendiente'), false);
        $response->assertSee(__('No hay cuentas por pagar.'), false);
    }

    public function test_indice_cuentas_por_pagar_redirige_a_movimientos_pestana_por_pagar(): void
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

        $response = $this->actingAs($user)->get(route('stores.accounts-payables', $store));

        $response->assertRedirect(route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'por-pagar']));
    }
}
