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
        $this->expectExceptionMessage('debe ser auxiliar');

        $this->servicio()->crear($this->store, $datos);
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
