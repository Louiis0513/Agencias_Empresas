<?php

namespace Tests\Feature;

use App\Models\ComprobanteContable;
use App\Models\CuentaContable;
use App\Models\Store;
use App\Models\TipoComprobante;
use App\Models\User;
use App\Services\AsientoContableService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AsientoContableManualTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    private TipoComprobante $tipo;

    private CuentaContable $caja;

    private CuentaContable $gasto;

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
        $this->tipo = TipoComprobante::create([
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
        $this->caja = $this->crearCuenta($this->store, '11050501', 'Caja general');
        $this->gasto = $this->crearCuenta($this->store, '51959501', 'Gastos diversos');
    }

    public function test_crea_borrador_balanceado_con_lineas(): void
    {
        $comprobante = $this->servicio()->crearBorrador(
            $this->store,
            $this->user->id,
            $this->datosBalanceados()
        );

        $this->assertSame(ComprobanteContable::ESTADO_BORRADOR, $comprobante->estado);
        $this->assertNull($comprobante->numero);
        $this->assertSame('35000.00', (string) $comprobante->total_debito);
        $this->assertSame('35000.00', (string) $comprobante->total_credito);
        $this->assertCount(2, $comprobante->movimientos);
        $this->assertSame('FAC-2026-001', $comprobante->movimientos[0]->detalle_contable);
    }

    public function test_rechaza_asiento_descuadrado(): void
    {
        $datos = $this->datosBalanceados();
        $datos['lineas'][1]['credito'] = 34000;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('El asiento no está cuadrado');

        $this->servicio()->crearBorrador($this->store, $this->user->id, $datos);
    }

    public function test_rechaza_cuenta_de_otra_tienda(): void
    {
        $otra = Store::factory()->create();
        $cuentaAjena = $this->crearCuenta($otra, '11050599', 'Caja ajena');
        $datos = $this->datosBalanceados();
        $datos['lineas'][0]['cuenta_contable_id'] = $cuentaAjena->id;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('debe ser auxiliar');

        $this->servicio()->crearBorrador($this->store, $this->user->id, $datos);
    }

    public function test_contabiliza_asigna_consecutivo_y_bloquea_edicion(): void
    {
        $servicio = $this->servicio();
        $comprobante = $servicio->crearBorrador(
            $this->store,
            $this->user->id,
            $this->datosBalanceados()
        );

        $contabilizado = $servicio->contabilizar($this->store, $comprobante, $this->user->id);

        $this->assertSame(ComprobanteContable::ESTADO_CONTABILIZADO, $contabilizado->estado);
        $this->assertSame('CC-0001', $contabilizado->numero);
        $this->assertNotNull($contabilizado->contabilizado_at);
        $this->assertSame(2, $this->tipo->fresh()->siguiente_numero);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Solo se pueden editar comprobantes en borrador');
        $servicio->actualizarBorrador(
            $this->store,
            $contabilizado,
            $this->datosBalanceados()
        );
    }

    public function test_reversa_creando_asiento_inverso_inmutable(): void
    {
        $servicio = $this->servicio();
        $original = $servicio->crearBorrador(
            $this->store,
            $this->user->id,
            $this->datosBalanceados()
        );
        $original = $servicio->contabilizar($this->store, $original, $this->user->id);

        $reverso = $servicio->reversar($this->store, $original, $this->user->id);
        $original->refresh();

        $this->assertSame(ComprobanteContable::ESTADO_REVERSADO, $original->estado);
        $this->assertSame(ComprobanteContable::ESTADO_CONTABILIZADO, $reverso->estado);
        $this->assertSame(ComprobanteContable::EVENTO_REVERSO, $reverso->evento);
        $this->assertSame($original->id, $reverso->reversa_de_id);
        $this->assertSame('CC-0002', $reverso->numero);
        $this->assertSame('0.00', (string) $reverso->movimientos[0]->debito);
        $this->assertSame('35000.00', (string) $reverso->movimientos[0]->credito);
        $this->assertSame('35000.00', (string) $reverso->movimientos[1]->debito);
        $this->assertSame('0.00', (string) $reverso->movimientos[1]->credito);
    }

    public function test_propietario_puede_abrir_formulario_y_guardar_borrador(): void
    {
        $this->actingAs($this->user)
            ->get(route('stores.contabilidad.comprobantes.create', $this->store))
            ->assertOk()
            ->assertSee('Nuevo asiento manual CC')
            ->assertSee('Detalle contable')
            ->assertSee('Centro de costo')
            ->assertSee('Observaciones')
            ->assertDontSee('Tercero general')
            ->assertDontSee('Glosa general');

        $response = $this->actingAs($this->user)
            ->post(route('stores.contabilidad.comprobantes.store', $this->store), $this->datosBalanceados());

        $comprobante = ComprobanteContable::query()->deStore($this->store)->firstOrFail();
        $response->assertRedirect(
            route('stores.contabilidad.comprobantes.show', [$this->store, $comprobante])
        );

        $this->actingAs($this->user)
            ->get(route('stores.contabilidad.comprobantes.show', [$this->store, $comprobante]))
            ->assertOk()
            ->assertSee('Compra de papelería pagada por caja')
            ->assertSee('35.000,00');
    }

    public function test_libro_diario_excluye_borradores_e_incluye_original_y_reverso(): void
    {
        $servicio = $this->servicio();
        $original = $servicio->crearBorrador(
            $this->store,
            $this->user->id,
            $this->datosBalanceados()
        );
        $this->assertSame(0, $servicio->libroDiario($this->store)->total());

        $original = $servicio->contabilizar($this->store, $original, $this->user->id);
        $this->assertSame(2, $servicio->libroDiario($this->store)->total());

        $servicio->reversar($this->store, $original, $this->user->id);
        $this->assertSame(4, $servicio->libroDiario($this->store)->total());

        $this->actingAs($this->user)
            ->get(route('stores.contabilidad.diario', $this->store))
            ->assertOk()
            ->assertSee('Libro Diario')
            ->assertSee('CC-0001')
            ->assertSee('CC-0002');
    }

    private function servicio(): AsientoContableService
    {
        return app(AsientoContableService::class);
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

    private function datosBalanceados(): array
    {
        return [
            'tipo_comprobante_id' => $this->tipo->id,
            'fecha' => now()->toDateString(),
            'descripcion' => 'Compra de papelería pagada por caja',
            'lineas' => [
                [
                    'cuenta_contable_id' => $this->gasto->id,
                    'detalle_contable' => 'FAC-2026-001',
                    'descripcion' => 'Papelería',
                    'debito' => 35000,
                    'credito' => 0,
                ],
                [
                    'cuenta_contable_id' => $this->caja->id,
                    'descripcion' => 'Salida de caja',
                    'debito' => 0,
                    'credito' => 35000,
                ],
            ],
        ];
    }
}
