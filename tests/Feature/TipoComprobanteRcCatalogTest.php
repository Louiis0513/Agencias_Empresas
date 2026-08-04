<?php

namespace Tests\Feature;

use App\Models\CuentaContable;
use App\Models\Store;
use App\Models\TipoComprobante;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TipoComprobanteRcCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->store = Store::factory()->create(['user_id' => $this->user->id]);
        DB::table('store_user')->insert([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'role_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_index_asegura_rc1_y_no_lista_cc(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('stores.contabilidad.recibos-caja', $this->store));

        $response->assertOk()
            ->assertSee('Tipos de recibo de caja')
            ->assertSee('Recibo de caja')
            ->assertDontSee('Ajustes contables')
            ->assertDontSee('Saldos iniciales');

        $this->assertTrue(
            TipoComprobante::query()
                ->deStore($this->store)
                ->where('familia', TipoComprobante::FAMILIA_RC)
                ->where('codigo', '1')
                ->exists()
        );
    }

    public function test_crear_rc_custom_con_cuenta_anticipos(): void
    {
        $cuenta = $this->crearCuentaAuxiliar($this->store, '28050501', 'Anticipos de clientes');

        $this->actingAs($this->user)
            ->get(route('stores.contabilidad.recibos-caja', $this->store))
            ->assertOk();

        $response = $this->actingAs($this->user)
            ->post(route('stores.contabilidad.recibos-caja.store', $this->store), [
                'titulo' => 'Recibos anticipos',
                'codigo' => '2',
                'numeracion_automatica' => '1',
                'siguiente_numero' => 1,
                'activo' => '1',
                'maneja_centro_costos' => '0',
                'cuenta_anticipos_id' => $cuenta->id,
            ]);

        $response->assertRedirect(route('stores.contabilidad.recibos-caja', $this->store));

        $tipo = TipoComprobante::query()
            ->deStore($this->store)
            ->where('familia', TipoComprobante::FAMILIA_RC)
            ->where('codigo', '2')
            ->first();

        $this->assertNotNull($tipo);
        $this->assertSame('Recibos anticipos', $tipo->titulo);
        $this->assertSame('RC', $tipo->prefijo);
        $this->assertSame($cuenta->id, $tipo->cuenta_anticipos_id);
    }

    public function test_rechaza_actualizar_tipo_que_no_es_rc(): void
    {
        $this->actingAs($this->user)
            ->get(route('stores.contabilidad.recibos-caja', $this->store))
            ->assertOk();

        $cc = TipoComprobante::query()
            ->deStore($this->store)
            ->where('familia', TipoComprobante::FAMILIA_CC)
            ->where('codigo', '1')
            ->firstOrFail();

        $this->actingAs($this->user)
            ->put(route('stores.contabilidad.recibos-caja.update', [$this->store, $cc]), [
                'titulo' => 'Hack',
                'codigo' => '1',
                'activo' => '1',
            ])
            ->assertNotFound();
    }

    private function crearCuentaAuxiliar(Store $store, string $codigo, string $nombre): CuentaContable
    {
        return CuentaContable::create([
            'store_id' => $store->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'clase' => CuentaContable::claseDesdeCodigo($codigo),
            'activo' => true,
            'nivel_agrupacion' => CuentaContable::NIVEL_TRANSACCIONAL,
            'es_auxiliar' => true,
            'origen' => CuentaContable::ORIGEN_MANUAL,
        ]);
    }
}
