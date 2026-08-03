<?php

namespace Tests\Feature;

use App\Models\CentroCosto;
use App\Models\Store;
use App\Models\User;
use App\Services\CentroCostoService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CentroCostoCatalogTest extends TestCase
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

    public function test_crear_centro_genera_subcentro_general(): void
    {
        $centro = $this->servicio()->crearCentro($this->store, [
            'codigo' => 'ADM',
            'nombre' => 'Administración',
        ]);

        $this->assertNull($centro->parent_id);
        $this->assertSame('ADM', $centro->codigo);

        $sub = CentroCosto::query()
            ->deStore($this->store)
            ->where('parent_id', $centro->id)
            ->first();

        $this->assertNotNull($sub);
        $this->assertSame('ADM-01', $sub->codigo);
        $this->assertSame('General', $sub->nombre);
        $this->assertTrue($sub->activo);
    }

    public function test_crear_subcentro_bajo_centro(): void
    {
        $centro = $this->servicio()->crearCentro($this->store, [
            'codigo' => 'VEN',
            'nombre' => 'Ventas',
        ]);

        $sub = $this->servicio()->crearSubcentro($this->store, [
            'parent_id' => $centro->id,
            'codigo' => 'VEN-02',
            'nombre' => 'Sucursal norte',
        ]);

        $this->assertSame($centro->id, $sub->parent_id);
        $this->assertSame('VEN-02', $sub->codigo);
    }

    public function test_opciones_para_asiento_solo_activos_con_subcentros(): void
    {
        $this->servicio()->crearCentro($this->store, [
            'codigo' => 'A',
            'nombre' => 'Área A',
        ]);
        $inactivo = $this->servicio()->crearCentro($this->store, [
            'codigo' => 'B',
            'nombre' => 'Área B',
        ]);
        $this->servicio()->actualizar($this->store, $inactivo, [
            'codigo' => 'B',
            'nombre' => 'Área B',
            'activo' => false,
        ]);

        $opciones = $this->servicio()->opcionesParaAsiento($this->store);

        $this->assertCount(1, $opciones);
        $this->assertSame('A', $opciones[0]['codigo']);
        $this->assertNotEmpty($opciones[0]['subcentros']);
    }

    public function test_index_muestra_catalogo(): void
    {
        $this->servicio()->crearCentro($this->store, [
            'codigo' => 'OPS',
            'nombre' => 'Operaciones',
        ]);

        $this->actingAs($this->user)
            ->get(route('stores.contabilidad.centros-costo', $this->store))
            ->assertOk()
            ->assertSee('Centros de costo')
            ->assertSee('Operaciones')
            ->assertSee('OPS-01')
            ->assertSee('Definir comprobantes');
    }

    public function test_definir_comprobantes_guarda_matriz_global(): void
    {
        $centro = $this->servicio()->crearCentro($this->store, [
            'codigo' => 'ADM',
            'nombre' => 'Administración',
        ]);
        $sub = $centro->hijos()->first();

        $matriz = $this->servicio()->matrizDefinirComprobantes($this->store);
        $tipoCc = $matriz->get('CC')->first();
        $this->assertNotNull($tipoCc);

        $n = $this->servicio()->guardarDefinirComprobantes($this->store, [
            [
                'id' => $tipoCc->id,
                'maneja_centro_costos' => true,
                'centro_costo_obligatorio' => true,
                'centro_costo_default_id' => $sub->id,
            ],
        ]);

        $this->assertSame(1, $n);
        $tipoCc->refresh();
        $this->assertTrue($tipoCc->maneja_centro_costos);
        $this->assertTrue($tipoCc->centro_costo_obligatorio);
        $this->assertSame($sub->id, $tipoCc->centro_costo_default_id);

        $this->actingAs($this->user)
            ->get(route('stores.contabilidad.centros-costo', [$this->store, 'tab' => 'definir']))
            ->assertOk()
            ->assertSee('Definir comprobantes')
            ->assertSee('COMPROBANTES CONTABLES')
            ->assertSee('Maneja centro de costos');
    }

    public function test_rechaza_codigo_duplicado(): void
    {
        $this->servicio()->crearCentro($this->store, [
            'codigo' => 'X1',
            'nombre' => 'Uno',
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('código');

        $this->servicio()->crearCentro($this->store, [
            'codigo' => 'X1',
            'nombre' => 'Dos',
        ]);
    }

    private function servicio(): CentroCostoService
    {
        return app(CentroCostoService::class);
    }
}
