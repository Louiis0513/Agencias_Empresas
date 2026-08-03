<?php

namespace Tests\Feature;

use App\Models\CuentaContable;
use App\Models\Impuesto;
use App\Models\Store;
use App\Models\User;
use App\Services\ImpuestoService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImpuestoCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    private CuentaContable $ventas;

    private CuentaContable $compras;

    private CuentaContable $devVentas;

    private CuentaContable $devCompras;

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

        $this->ventas = $this->crearCuenta($this->store, '24080501', 'IVA por pagar');
        $this->compras = $this->crearCuenta($this->store, '24080101', 'IVA descontable');
        $this->devVentas = $this->crearCuenta($this->store, '24080502', 'IVA devolución ventas');
        $this->devCompras = $this->crearCuenta($this->store, '24080102', 'IVA devolución compras');
    }

    public function test_crea_y_lista_impuesto(): void
    {
        $impuesto = $this->servicio()->crear($this->store, $this->datosIva());

        $this->assertSame(1, $impuesto->codigo);
        $this->assertSame(Impuesto::TIPO_IVA, $impuesto->tipo);
        $this->assertSame('19.0000', (string) $impuesto->tarifa);
        $this->assertTrue($impuesto->en_uso);
        $this->assertSame($this->ventas->id, $impuesto->cuenta_ventas_id);

        $this->actingAs($this->user)
            ->get(route('stores.contabilidad.impuestos', $this->store))
            ->assertOk()
            ->assertSee('Impuestos')
            ->assertSee('IVA 19%')
            ->assertSee('24080501');
    }

    public function test_actualiza_impuesto_y_toggle_en_uso(): void
    {
        $impuesto = $this->servicio()->crear($this->store, $this->datosIva());

        $actualizado = $this->servicio()->actualizar($this->store, $impuesto, [
            ...$this->datosIva(),
            'nombre' => 'IVA 19% general',
            'en_uso' => false,
            'tarifa' => 19,
        ]);

        $this->assertSame('IVA 19% general', $actualizado->nombre);
        $this->assertFalse($actualizado->en_uso);
    }

    public function test_rechaza_cuenta_de_otra_tienda(): void
    {
        $otra = Store::factory()->create();
        $cuentaAjena = $this->crearCuenta($otra, '24080599', 'IVA ajeno');
        $datos = $this->datosIva();
        $datos['cuenta_ventas_id'] = $cuentaAjena->id;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('pertenecer a esta tienda');

        $this->servicio()->crear($this->store, $datos);
    }

    public function test_asegurar_defaults_crea_cadena_puc_e_impuestos_sin_puc_previo(): void
    {
        $storeVacia = Store::factory()->create(['user_id' => $this->user->id]);
        DB::table('store_user')->insert([
            'store_id' => $storeVacia->id,
            'user_id' => $this->user->id,
            'role_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, CuentaContable::query()->deStore($storeVacia)->count());

        $stats = $this->servicio()->asegurarDefaults($storeVacia);

        $this->assertSame(22, $stats['creadas']);
        $this->assertSame([], $stats['errores']);
        $this->assertSame(22, Impuesto::query()->deStore($storeVacia)->count());

        $this->assertDatabaseHas('cuentas_contables', [
            'store_id' => $storeVacia->id,
            'codigo' => '1',
        ]);
        $this->assertDatabaseHas('cuentas_contables', [
            'store_id' => $storeVacia->id,
            'codigo' => '135515',
        ]);
        $this->assertDatabaseHas('cuentas_contables', [
            'store_id' => $storeVacia->id,
            'codigo' => '24080601',
            'es_auxiliar' => true,
            'nivel_agrupacion' => CuentaContable::NIVEL_TRANSACCIONAL,
        ]);
        $this->assertDatabaseHas('cuentas_contables', [
            'store_id' => $storeVacia->id,
            'codigo' => '246401',
            'nivel_agrupacion' => CuentaContable::NIVEL_TRANSACCIONAL,
        ]);

        $iva19 = Impuesto::query()->deStore($storeVacia)->where('codigo', 1)->first();
        $iva0 = Impuesto::query()->deStore($storeVacia)->where('codigo', 22)->first();
        $this->assertNotNull($iva19);
        $this->assertNotNull($iva0);
        $this->assertSame($iva19->cuenta_ventas_id, $iva0->cuenta_ventas_id);

        $stats2 = $this->servicio()->asegurarDefaults($storeVacia);
        $this->assertSame(0, $stats2['creadas']);
        $this->assertSame(22, $stats2['omitidas']);
        $this->assertSame(22, Impuesto::query()->deStore($storeVacia)->count());
    }

    public function test_index_dispara_defaults(): void
    {
        $storeVacia = Store::factory()->create(['user_id' => $this->user->id]);
        DB::table('store_user')->insert([
            'store_id' => $storeVacia->id,
            'user_id' => $this->user->id,
            'role_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->user)
            ->get(route('stores.contabilidad.impuestos', $storeVacia))
            ->assertOk()
            ->assertSee('IVA 19%')
            ->assertSee('Retefuente 4%')
            ->assertSee('24080601');

        $this->assertSame(22, Impuesto::query()->deStore($storeVacia)->count());
    }

    public function test_filtra_impuestos_por_cuenta_contable(): void
    {
        $stats = $this->servicio()->asegurarDefaults($this->store);
        $this->assertSame([], $stats['errores']);

        $cuenta = CuentaContable::query()
            ->deStore($this->store)
            ->where('codigo', '24080601')
            ->firstOrFail();

        $filtrados = $this->servicio()->listar($this->store, [
            'cuenta_contable_id' => $cuenta->id,
        ]);

        $this->assertGreaterThanOrEqual(2, $filtrados->total());
        foreach ($filtrados as $impuesto) {
            $ids = [
                $impuesto->cuenta_ventas_id,
                $impuesto->cuenta_compras_id,
                $impuesto->cuenta_devolucion_ventas_id,
                $impuesto->cuenta_devolucion_compras_id,
            ];
            $this->assertContains($cuenta->id, $ids);
        }

        $this->actingAs($this->user)
            ->get(route('stores.contabilidad.impuestos', [
                'store' => $this->store,
                'cuenta_contable_id' => $cuenta->id,
            ]))
            ->assertOk()
            ->assertSee('Mostrando impuestos que usan la cuenta')
            ->assertSee('24080601')
            ->assertSee('IVA 19%')
            ->assertSee('IVA 0%');
    }

    public function test_propietario_puede_guardar_por_http(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('stores.contabilidad.impuestos.store', $this->store), $this->datosIva());

        $response->assertRedirect(route('stores.contabilidad.impuestos', $this->store));
        $this->assertDatabaseHas('impuestos', [
            'store_id' => $this->store->id,
            'nombre' => 'IVA 19%',
            'tipo' => Impuesto::TIPO_IVA,
        ]);
    }

    private function servicio(): ImpuestoService
    {
        return app(ImpuestoService::class);
    }

    private function crearCuenta(Store $store, string $codigo, string $nombre): CuentaContable
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

    private function datosIva(): array
    {
        return [
            'en_uso' => true,
            'nombre' => 'IVA 19%',
            'tipo' => Impuesto::TIPO_IVA,
            'por_valor' => false,
            'tarifa' => 19,
            'cuenta_ventas_id' => $this->ventas->id,
            'cuenta_compras_id' => $this->compras->id,
            'cuenta_devolucion_ventas_id' => $this->devVentas->id,
            'cuenta_devolucion_compras_id' => $this->devCompras->id,
        ];
    }
}
