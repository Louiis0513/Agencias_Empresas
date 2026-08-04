<?php

namespace Tests\Feature;

use App\Models\AccountReceivable;
use App\Models\AccountReceivableCuota;
use App\Models\Bolsillo;
use App\Models\ComprobanteIngreso;
use App\Models\CuentaContable;
use App\Models\FormaPago;
use App\Models\Invoice;
use App\Models\Store;
use App\Models\Tercero;
use App\Models\TipoComprobante;
use App\Models\User;
use App\Services\CentroCostoService;
use App\Services\FormaPagoService;
use App\Services\SesionCajaService;
use App\Services\TerceroService;
use App\Services\TipoComprobanteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReciboCajaElaborarTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    private Bolsillo $bolsillo;

    private Bolsillo $bolsilloBanco;

    private FormaPago $formaEfectivo;

    private FormaPago $formaTransferencia;

    private FormaPago $formaCredito;

    private TipoComprobante $tipoRc;

    private Tercero $cliente;

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

        app(TipoComprobanteService::class)->asegurarTiposPorDefecto($this->store);
        $this->tipoRc = TipoComprobante::query()
            ->deStore($this->store)
            ->where('familia', TipoComprobante::FAMILIA_RC)
            ->where('codigo', '1')
            ->firstOrFail();

        $cuentaCaja = $this->crearCuenta($this->store, '11050501', 'Caja general');
        $cuentaBanco = $this->crearCuenta($this->store, '11100501', 'Bancos moneda nacional');
        $cuentaClientes = $this->crearCuenta($this->store, '13050501', 'Clientes nacionales');

        $this->bolsillo = Bolsillo::create([
            'store_id' => $this->store->id,
            'cuenta_contable_id' => $cuentaCaja->id,
            'name' => 'Caja general',
            'saldo' => 0,
            'is_active' => true,
        ]);
        $this->bolsilloBanco = Bolsillo::create([
            'store_id' => $this->store->id,
            'cuenta_contable_id' => $cuentaBanco->id,
            'name' => 'Banco',
            'saldo' => 0,
            'is_active' => true,
        ]);

        app(SesionCajaService::class)->abrirSesion($this->store, $this->user->id, [
            $this->bolsillo->id => 0,
            $this->bolsilloBanco->id => 0,
        ]);

        $fp = app(FormaPagoService::class);
        $this->formaEfectivo = $fp->crear($this->store, [
            'nombre' => 'Efectivo',
            'aplica_a' => FormaPago::APLICA_AMBOS,
            'cuenta_contable_id' => $cuentaCaja->id,
            'medio_pago_dian' => '10',
            'en_uso' => true,
        ]);
        $this->formaTransferencia = $fp->crear($this->store, [
            'nombre' => 'Transferencia',
            'aplica_a' => FormaPago::APLICA_AMBOS,
            'cuenta_contable_id' => $cuentaBanco->id,
            'medio_pago_dian' => '45',
            'en_uso' => true,
        ]);
        $this->formaCredito = $fp->crear($this->store, [
            'nombre' => 'Crédito',
            'aplica_a' => FormaPago::APLICA_CARTERA,
            'cuenta_contable_id' => $cuentaClientes->id,
            'medio_pago_dian' => '1',
            'en_uso' => true,
        ]);

        $this->cliente = app(TerceroService::class)->crear($this->store, [
            'nombre' => 'Cliente RC',
            'tipo_identificacion' => 'CC',
            'numero_identificacion' => '1099001',
            'roles' => [Tercero::ROL_CLIENTE],
        ]);
    }

    public function test_create_muestra_ui_siigo_una_forma_y_valor(): void
    {
        $this->actingAs($this->user)
            ->get(route('stores.recibos-caja.create', $this->store))
            ->assertOk()
            ->assertSee('Pago o abono a deuda')
            ->assertSee('Anticipo')
            ->assertSee('Otro ingreso')
            ->assertSee('Forma de pago')
            ->assertSee('Valor recibido')
            ->assertSee('Saldo actual COP')
            ->assertSee('Caja general')
            ->assertSee('11050501')
            ->assertSee('Banco')
            ->assertSee('11100501')
            ->assertDontSee('Agregar')
            ->assertDontSee('Dónde ingresa el dinero');
    }

    public function test_cuentas_pendientes_devuelve_saldo_actual(): void
    {
        $this->crearCxC($this->cliente, 100000);
        $this->crearCxC($this->cliente, 50000);

        $this->actingAs($this->user)
            ->getJson(route('stores.recibos-caja.cuentas-pendientes', [
                'store' => $this->store,
                'tercero_id' => $this->cliente->id,
            ]))
            ->assertOk()
            ->assertJsonPath('saldo_actual', 150000)
            ->assertJsonCount(2, 'data');
    }

    public function test_abono_multi_cxc_una_forma_numera_rc(): void
    {
        $ar1 = $this->crearCxC($this->cliente, 100000);
        $ar2 = $this->crearCxC($this->cliente, 50000);
        $cuota1 = $ar1->cuotas()->first();
        $cuota2 = $ar2->cuotas()->first();

        $response = $this->actingAs($this->user)
            ->post(route('stores.recibos-caja.store', $this->store), [
                'modo' => ComprobanteIngreso::MODO_ABONO,
                'tipo_comprobante_id' => $this->tipoRc->id,
                'date' => now()->toDateString(),
                'tercero_id' => $this->cliente->id,
                'bolsillo_id' => $this->bolsillo->id,
                'valor_recibido' => 60000,
                'notes' => 'Abono parcial multi',
                'aplicaciones' => [
                    [
                        'account_receivable_id' => $ar1->id,
                        'account_receivable_cuota_id' => $cuota1->id,
                        'amount' => 40000,
                    ],
                    [
                        'account_receivable_id' => $ar2->id,
                        'account_receivable_cuota_id' => $cuota2->id,
                        'amount' => 20000,
                    ],
                ],
            ]);

        $ci = ComprobanteIngreso::deTienda($this->store->id)->latest('id')->first();
        $this->assertNotNull($ci);
        $response->assertRedirect(route('stores.comprobantes-ingreso.show', [$this->store, $ci]));

        $this->assertSame(ComprobanteIngreso::TYPE_COBRO_CUENTA, $ci->type);
        $this->assertSame('RC-0001', $ci->number);
        $this->assertSame('60000.00', (string) $ci->total_amount);
        $this->assertSame('0.00', (string) $ci->monto_anticipo);
        $this->assertCount(1, $ci->destinos);
        $this->assertSame($this->bolsillo->id, $ci->destinos[0]->bolsillo_id);
        $this->assertSame($this->formaEfectivo->id, $ci->destinos[0]->forma_pago_id);

        $this->assertSame('60000.00', (string) $ar1->fresh()->balance);
        $this->assertSame('30000.00', (string) $ar2->fresh()->balance);
        $this->assertSame('60000.00', (string) $this->bolsillo->fresh()->saldo);

        $this->actingAs($this->user)
            ->get(route('stores.comprobantes-ingreso.show', [$this->store, $ci]))
            ->assertOk()
            ->assertSee('Recibo de caja')
            ->assertSee('Condiciones de pago')
            ->assertSee('Efectivo')
            ->assertSee('Total pago')
            ->assertSee('Ítem')
            ->assertSee('Documento')
            ->assertDontSee('Preparado')
            ->assertDontSee('Firma de recibido')
            ->assertDontSee('Abono a deuda');
    }

    public function test_abono_parcial_deja_monto_anticipo(): void
    {
        $ar = $this->crearCxC($this->cliente, 100000);
        $cuota = $ar->cuotas()->first();

        $this->actingAs($this->user)
            ->post(route('stores.recibos-caja.store', $this->store), [
                'modo' => ComprobanteIngreso::MODO_ABONO,
                'tipo_comprobante_id' => $this->tipoRc->id,
                'date' => now()->toDateString(),
                'tercero_id' => $this->cliente->id,
                'bolsillo_id' => $this->bolsillo->id,
                'valor_recibido' => 50000,
                'aplicaciones' => [
                    [
                        'account_receivable_id' => $ar->id,
                        'account_receivable_cuota_id' => $cuota->id,
                        'amount' => 30000,
                    ],
                ],
            ])
            ->assertRedirect();

        $ci = ComprobanteIngreso::deTienda($this->store->id)->latest('id')->first();
        $this->assertNotNull($ci);
        $this->assertSame(ComprobanteIngreso::TYPE_COBRO_CUENTA, $ci->type);
        $this->assertSame('50000.00', (string) $ci->total_amount);
        $this->assertSame('20000.00', (string) $ci->monto_anticipo);
        $this->assertSame('70000.00', (string) $ar->fresh()->balance);
        $this->assertSame('30000.00', (string) $cuota->fresh()->amount_paid);
    }

    public function test_aplica_a_cuota_concreta(): void
    {
        $ar = $this->crearCxCConDosCuotas($this->cliente, 40000, 60000);
        $cuotas = $ar->cuotas()->orderBy('sequence')->get();
        $cuota2 = $cuotas[1];

        $this->actingAs($this->user)
            ->post(route('stores.recibos-caja.store', $this->store), [
                'modo' => ComprobanteIngreso::MODO_ABONO,
                'tipo_comprobante_id' => $this->tipoRc->id,
                'date' => now()->toDateString(),
                'tercero_id' => $this->cliente->id,
                'bolsillo_id' => $this->bolsillo->id,
                'valor_recibido' => 25000,
                'aplicaciones' => [
                    [
                        'account_receivable_id' => $ar->id,
                        'account_receivable_cuota_id' => $cuota2->id,
                        'amount' => 25000,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertSame('0.00', (string) $cuotas[0]->fresh()->amount_paid);
        $this->assertSame('25000.00', (string) $cuota2->fresh()->amount_paid);
        $this->assertSame('75000.00', (string) $ar->fresh()->balance);
    }

    public function test_otro_ingreso_guarda_concepto_y_rc(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('stores.recibos-caja.store', $this->store), [
                'modo' => ComprobanteIngreso::MODO_OTRO_INGRESO,
                'tipo_comprobante_id' => $this->tipoRc->id,
                'date' => now()->toDateString(),
                'notes' => 'Reembolso administrativo',
                'bolsillo_id' => $this->bolsillo->id,
                'valor_recibido' => 15000,
            ]);

        $ci = ComprobanteIngreso::deTienda($this->store->id)->latest('id')->first();
        $this->assertNotNull($ci);
        $response->assertRedirect(route('stores.comprobantes-ingreso.show', [$this->store, $ci]));
        $this->assertSame(ComprobanteIngreso::TYPE_INGRESO_MANUAL, $ci->type);
        $this->assertSame('RC-0001', $ci->number);
        $this->assertSame($this->bolsillo->id, $ci->destinos->first()->bolsillo_id);
        $this->assertSame($this->formaEfectivo->id, $ci->destinos->first()->forma_pago_id);
        $this->assertSame('15000.00', (string) $this->bolsillo->fresh()->saldo);
    }

    public function test_anticipo_no_toca_cartera(): void
    {
        $ar = $this->crearCxC($this->cliente, 80000);
        $balanceAntes = (string) $ar->balance;

        $this->actingAs($this->user)
            ->post(route('stores.recibos-caja.store', $this->store), [
                'modo' => ComprobanteIngreso::MODO_ANTICIPO,
                'tipo_comprobante_id' => $this->tipoRc->id,
                'date' => now()->toDateString(),
                'tercero_id' => $this->cliente->id,
                'notes' => 'Anticipo obra',
                'bolsillo_id' => $this->bolsillo->id,
                'valor_recibido' => 25000,
            ])
            ->assertRedirect();

        $ci = ComprobanteIngreso::deTienda($this->store->id)->latest('id')->first();
        $this->assertNotNull($ci);
        $this->assertSame(ComprobanteIngreso::TYPE_ANTICIPO, $ci->type);
        $this->assertSame('25000.00', (string) $ci->monto_anticipo);
        $this->assertSame($balanceAntes, (string) $ar->fresh()->balance);
    }

    public function test_bolsillo_sin_cuenta_rechazado(): void
    {
        $sinCuenta = Bolsillo::create([
            'store_id' => $this->store->id,
            'cuenta_contable_id' => null,
            'name' => 'Sin cuenta',
            'saldo' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($this->user)
            ->from(route('stores.recibos-caja.create', $this->store))
            ->post(route('stores.recibos-caja.store', $this->store), [
                'modo' => ComprobanteIngreso::MODO_OTRO_INGRESO,
                'tipo_comprobante_id' => $this->tipoRc->id,
                'date' => now()->toDateString(),
                'notes' => 'No debería',
                'bolsillo_id' => $sinCuenta->id,
                'valor_recibido' => 1000,
            ])
            ->assertSessionHasErrors('bolsillo_id');

        $this->assertSame(0, ComprobanteIngreso::deTienda($this->store->id)->count());
    }

    public function test_tipo_inactivo_rechazado(): void
    {
        $this->tipoRc->update(['activo' => false]);

        $this->actingAs($this->user)
            ->from(route('stores.recibos-caja.create', $this->store))
            ->post(route('stores.recibos-caja.store', $this->store), [
                'modo' => ComprobanteIngreso::MODO_OTRO_INGRESO,
                'tipo_comprobante_id' => $this->tipoRc->id,
                'date' => now()->toDateString(),
                'notes' => 'No debería guardar',
                'bolsillo_id' => $this->bolsillo->id,
                'valor_recibido' => 1000,
            ])
            ->assertSessionHasErrors('tipo_comprobante_id');

        $this->assertSame(0, ComprobanteIngreso::deTienda($this->store->id)->count());
    }

    public function test_centro_obligatorio_cuando_el_tipo_lo_exige(): void
    {
        $this->tipoRc->update([
            'maneja_centro_costos' => true,
            'centro_costo_obligatorio' => true,
        ]);
        $centro = app(CentroCostoService::class)->crearCentro($this->store, [
            'codigo' => 'ADM',
            'nombre' => 'Administración',
        ]);
        $sub = $centro->hijos()->firstOrFail();

        $this->actingAs($this->user)
            ->from(route('stores.recibos-caja.create', $this->store))
            ->post(route('stores.recibos-caja.store', $this->store), [
                'modo' => ComprobanteIngreso::MODO_OTRO_INGRESO,
                'tipo_comprobante_id' => $this->tipoRc->id,
                'date' => now()->toDateString(),
                'notes' => 'Sin centro',
                'bolsillo_id' => $this->bolsillo->id,
                'valor_recibido' => 5000,
            ])
            ->assertRedirect(route('stores.recibos-caja.create', $this->store))
            ->assertSessionHas('error');

        $this->actingAs($this->user)
            ->post(route('stores.recibos-caja.store', $this->store), [
                'modo' => ComprobanteIngreso::MODO_OTRO_INGRESO,
                'tipo_comprobante_id' => $this->tipoRc->id,
                'date' => now()->toDateString(),
                'notes' => 'Con centro',
                'centro_costo_id' => $sub->id,
                'bolsillo_id' => $this->bolsillo->id,
                'valor_recibido' => 5000,
            ])
            ->assertRedirect();

        $ci = ComprobanteIngreso::deTienda($this->store->id)->latest('id')->first();
        $this->assertNotNull($ci);
        $this->assertSame($sub->id, $ci->centro_costo_id);
    }

    private function crearCxC(Tercero $cliente, float $monto): AccountReceivable
    {
        $invoice = Invoice::create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'tercero_id' => $cliente->id,
            'subtotal' => $monto,
            'tax' => 0,
            'discount' => 0,
            'total' => $monto,
            'status' => 'PENDING',
            'payment_method' => null,
        ]);

        $ar = AccountReceivable::create([
            'store_id' => $this->store->id,
            'invoice_id' => $invoice->id,
            'tercero_id' => $cliente->id,
            'total_amount' => $monto,
            'balance' => $monto,
            'due_date' => now()->addDays(15)->toDateString(),
            'status' => AccountReceivable::STATUS_PENDIENTE,
        ]);

        AccountReceivableCuota::create([
            'account_receivable_id' => $ar->id,
            'sequence' => 1,
            'amount' => $monto,
            'amount_paid' => 0,
            'due_date' => $ar->due_date,
        ]);

        return $ar->load('cuotas');
    }

    private function crearCxCConDosCuotas(Tercero $cliente, float $c1, float $c2): AccountReceivable
    {
        $total = $c1 + $c2;
        $invoice = Invoice::create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'tercero_id' => $cliente->id,
            'subtotal' => $total,
            'tax' => 0,
            'discount' => 0,
            'total' => $total,
            'status' => 'PENDING',
            'payment_method' => null,
        ]);

        $ar = AccountReceivable::create([
            'store_id' => $this->store->id,
            'invoice_id' => $invoice->id,
            'tercero_id' => $cliente->id,
            'total_amount' => $total,
            'balance' => $total,
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => AccountReceivable::STATUS_PENDIENTE,
        ]);

        AccountReceivableCuota::create([
            'account_receivable_id' => $ar->id,
            'sequence' => 1,
            'amount' => $c1,
            'amount_paid' => 0,
            'due_date' => now()->addDays(15)->toDateString(),
        ]);
        AccountReceivableCuota::create([
            'account_receivable_id' => $ar->id,
            'sequence' => 2,
            'amount' => $c2,
            'amount_paid' => 0,
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        return $ar->load('cuotas');
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
}
