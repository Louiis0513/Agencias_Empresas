<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\TipoComprobante;
use App\Models\User;
use App\Services\TipoComprobanteService;
use App\Support\CatalogoComprobantesContablesPredeterminados;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TipoComprobanteCcCatalogTest extends TestCase
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

    public function test_index_asegura_catalogo_cc_y_no_lista_otras_familias(): void
    {
        $esperados = count(CatalogoComprobantesContablesPredeterminados::tipos());

        $response = $this->actingAs($this->user)
            ->get(route('stores.contabilidad.tipos', $this->store));

        $response->assertOk()
            ->assertSee('Tipos de comprobante contable')
            ->assertSee('Ajustes contables')
            ->assertSee('Saldos iniciales')
            ->assertSee('Ajuste de tesorería')
            ->assertSee('Traslado de dinero')
            ->assertDontSee('Factura de venta')
            ->assertDontSee('Recibo de caja');

        $this->assertSame(
            $esperados,
            TipoComprobante::query()
                ->deStore($this->store)
                ->where('familia', TipoComprobante::FAMILIA_CC)
                ->count()
        );

        $this->assertTrue(
            TipoComprobante::query()
                ->deStore($this->store)
                ->where('familia', TipoComprobante::FAMILIA_FV)
                ->where('codigo', '1')
                ->exists()
        );
    }

    public function test_asegurar_defaults_es_idempotente_y_no_renombra_existentes(): void
    {
        TipoComprobante::create([
            'store_id' => $this->store->id,
            'familia' => TipoComprobante::FAMILIA_CC,
            'codigo' => '1',
            'nombre' => 'Comprobante contable',
            'titulo' => 'Comprobante contable',
            'prefijo' => 'CC',
            'numeracion_automatica' => true,
            'siguiente_numero' => 1,
            'activo' => true,
        ]);

        $service = app(TipoComprobanteService::class);
        $primera = $service->asegurarTiposPorDefecto($this->store);
        $segunda = $service->asegurarTiposPorDefecto($this->store);

        $this->assertContains('CC:1', $primera['omitidas']);
        $this->assertNotContains('CC:1', $primera['creadas']);
        $this->assertSame([], $segunda['creadas']);

        $cc1 = TipoComprobante::query()
            ->deStore($this->store)
            ->where('familia', TipoComprobante::FAMILIA_CC)
            ->where('codigo', '1')
            ->first();

        $this->assertSame('Comprobante contable', $cc1->nombre);
    }

    public function test_crear_tipo_cc_custom_desde_ui(): void
    {
        $this->actingAs($this->user)
            ->get(route('stores.contabilidad.tipos', $this->store))
            ->assertOk();

        $response = $this->actingAs($this->user)
            ->post(route('stores.contabilidad.tipos.store', $this->store), [
                'titulo' => 'Ajustes especiales',
                'codigo' => '42',
                'numeracion_automatica' => '1',
                'siguiente_numero' => 1,
                'activo' => '1',
                'maneja_centro_costos' => '0',
            ]);

        $response->assertRedirect(route('stores.contabilidad.tipos', $this->store));

        $tipo = TipoComprobante::query()
            ->deStore($this->store)
            ->where('familia', TipoComprobante::FAMILIA_CC)
            ->where('codigo', '42')
            ->first();

        $this->assertNotNull($tipo);
        $this->assertSame('Ajustes especiales', $tipo->titulo);
        $this->assertSame('Ajustes especiales', $tipo->nombre);
        $this->assertSame('CC', $tipo->prefijo);
    }

    public function test_rechaza_actualizar_tipo_que_no_es_cc(): void
    {
        $this->actingAs($this->user)
            ->get(route('stores.contabilidad.tipos', $this->store))
            ->assertOk();

        $fv = TipoComprobante::query()
            ->deStore($this->store)
            ->where('familia', TipoComprobante::FAMILIA_FV)
            ->where('codigo', '1')
            ->firstOrFail();

        $this->actingAs($this->user)
            ->put(route('stores.contabilidad.tipos.update', [$this->store, $fv]), [
                'titulo' => 'Hack',
                'codigo' => '1',
                'activo' => '1',
            ])
            ->assertNotFound();
    }
}
