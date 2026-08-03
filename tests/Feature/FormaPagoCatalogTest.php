<?php

namespace Tests\Feature;

use App\Models\CuentaContable;
use App\Models\FormaPago;
use App\Models\Store;
use App\Models\User;
use App\Services\FormaPagoService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FormaPagoCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    private CuentaContable $caja;

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

        $this->caja = $this->crearCuenta($this->store, '11050501', 'Caja general');
    }

    public function test_crea_y_lista_forma_pago(): void
    {
        $forma = $this->servicio()->crear($this->store, $this->datosEfectivo());

        $this->assertSame(1, $forma->codigo);
        $this->assertSame(FormaPago::APLICA_AMBOS, $forma->aplica_a);
        $this->assertSame('10', $forma->medio_pago_dian);
        $this->assertTrue($forma->en_uso);
        $this->assertSame($this->caja->id, $forma->cuenta_contable_id);

        $this->actingAs($this->user)
            ->get(route('stores.contabilidad.formas-pago', $this->store))
            ->assertOk()
            ->assertSee('Formas de pago')
            ->assertSee('Efectivo')
            ->assertSee('11050501');
    }

    public function test_actualiza_forma_pago_y_toggle_en_uso(): void
    {
        $forma = $this->servicio()->crear($this->store, $this->datosEfectivo());

        $actualizado = $this->servicio()->actualizar($this->store, $forma, [
            ...$this->datosEfectivo(),
            'nombre' => 'Efectivo mostrador',
            'en_uso' => false,
            'medio_pago_dian' => '10',
        ]);

        $this->assertSame('Efectivo mostrador', $actualizado->nombre);
        $this->assertFalse($actualizado->en_uso);
    }

    public function test_rechaza_cuenta_de_otra_tienda(): void
    {
        $otra = Store::factory()->create();
        $cuentaAjena = $this->crearCuenta($otra, '11050599', 'Caja ajena');
        $datos = $this->datosEfectivo();
        $datos['cuenta_contable_id'] = $cuentaAjena->id;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('debe ser auxiliar');

        $this->servicio()->crear($this->store, $datos);
    }

    public function test_asegurar_defaults_crea_auxiliares_bajo_padres(): void
    {
        $this->crearPadre($this->store, '110505', 'Caja general');
        $this->crearPadre($this->store, '111005', 'Moneda nacional');
        $this->crearPadre($this->store, '130505', 'Clientes nacionales');

        $stats = $this->servicio()->asegurarDefaults($this->store);

        $this->assertSame([], $stats['errores'], 'Errores defaults: '.implode(' | ', $stats['errores']));
        $this->assertSame(3, $stats['creadas']);
        $this->assertDatabaseHas('formas_pago', [
            'store_id' => $this->store->id,
            'nombre' => 'Efectivo',
        ]);
        $this->assertDatabaseHas('formas_pago', [
            'store_id' => $this->store->id,
            'nombre' => 'Transferencia',
        ]);
        $this->assertDatabaseHas('formas_pago', [
            'store_id' => $this->store->id,
            'nombre' => 'Crédito',
            'aplica_a' => FormaPago::APLICA_CARTERA,
        ]);
    }

    public function test_propietario_puede_guardar_por_http(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('stores.contabilidad.formas-pago.store', $this->store), $this->datosEfectivo());

        $response->assertRedirect(route('stores.contabilidad.formas-pago', $this->store));
        $this->assertDatabaseHas('formas_pago', [
            'store_id' => $this->store->id,
            'nombre' => 'Efectivo',
            'aplica_a' => FormaPago::APLICA_AMBOS,
        ]);
    }

    private function servicio(): FormaPagoService
    {
        return app(FormaPagoService::class);
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

    private function crearPadre(Store $store, string $codigo, string $nombre): CuentaContable
    {
        return CuentaContable::create([
            'store_id' => $store->id,
            'codigo' => $codigo,
            'nombre' => $nombre,
            'clase' => CuentaContable::claseDesdeCodigo($codigo),
            'activo' => true,
            'nivel_agrupacion' => null,
            'es_auxiliar' => false,
            'origen' => CuentaContable::ORIGEN_PLANTILLA,
        ]);
    }

    private function datosEfectivo(): array
    {
        return [
            'en_uso' => true,
            'nombre' => 'Efectivo',
            'aplica_a' => FormaPago::APLICA_AMBOS,
            'cuenta_contable_id' => $this->caja->id,
            'medio_pago_dian' => '10',
            'es_pago_en_linea' => false,
        ];
    }
}
