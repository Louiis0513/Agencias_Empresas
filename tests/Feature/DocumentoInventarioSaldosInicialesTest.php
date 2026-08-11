<?php

namespace Tests\Feature;

use App\Models\CategoriaContable;
use App\Models\CuentaContable;
use App\Models\DocumentoInventario;
use App\Models\MovimientoContable;
use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\DocumentoInventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentoInventarioSaldosInicialesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    private CuentaContable $cuentaInventario;

    private Product $productoA;

    private Product $productoB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->store = Store::factory()->create([
            'user_id' => $this->user->id,
            'maneja_bodegas' => false,
        ]);
        DB::table('store_user')->insert([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'role_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->cuentaInventario = $this->crearCuenta($this->store, '14350501', 'Mercancías no fabricadas');

        $categoria = CategoriaContable::create([
            'store_id' => $this->store->id,
            'codigo' => '1',
            'nombre' => 'Productos',
            'tipo' => CategoriaContable::TIPO_PRODUCTO,
            'cuenta_inventario_id' => $this->cuentaInventario->id,
            'activo' => true,
        ]);

        $this->productoA = Product::factory()->conCategoria($categoria)->create([
            'store_id' => $this->store->id,
            'codigo' => 'P-001',
            'nombre' => 'Producto uno',
            'es_inventariable' => true,
        ]);

        $this->productoB = Product::factory()->conCategoria($categoria)->create([
            'store_id' => $this->store->id,
            'codigo' => 'P-002',
            'nombre' => 'Producto dos',
            'es_inventariable' => true,
        ]);
    }

    public function test_contabiliza_saldos_iniciales_con_asiento_y_entradas(): void
    {
        $documento = app(DocumentoInventarioService::class)->contabilizarSaldosIniciales(
            $this->store,
            $this->user->id,
            [
                'fecha' => '2026-08-01',
                'tercero_nombre' => 'Apertura',
                'observaciones' => 'Carga inicial',
                'lineas' => [
                    [
                        'product_id' => $this->productoA->id,
                        'cantidad' => 10,
                        'costo_unitario' => 1000,
                        'descripcion' => 'Producto uno',
                    ],
                    [
                        'product_id' => $this->productoB->id,
                        'cantidad' => 2,
                        'costo_unitario' => 5000,
                        'descripcion' => 'Producto dos',
                    ],
                ],
            ]
        );

        $this->assertSame(DocumentoInventario::ESTADO_CONTABILIZADO, $documento->estado);
        $this->assertSame(DocumentoInventario::TIPO_SALDO_INICIAL, $documento->tipo_documento);
        $this->assertMatchesRegularExpression('/^A-\d+$/', $documento->numero);
        $this->assertSame('20000.00', (string) $documento->total);
        $this->assertSame('20000.00', (string) $documento->total_debito);
        $this->assertSame('20000.00', (string) $documento->total_credito);
        $this->assertCount(2, $documento->lineas);

        $movimientosInv = MovimientoInventario::query()
            ->where('store_id', $this->store->id)
            ->where('documento_type', DocumentoInventario::class)
            ->where('documento_id', $documento->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $movimientosInv);
        $this->assertTrue($movimientosInv->every(fn ($m) => $m->direccion === MovimientoInventario::DIRECCION_ENTRADA));
        $this->assertTrue($movimientosInv->every(fn ($m) => $m->clase_movimiento === MovimientoInventario::CLASE_SALDO_INICIAL));
        $this->assertSame('10000.00', (string) $movimientosInv[0]->valor_entrada);
        $this->assertSame('10000.00', (string) $movimientosInv[1]->valor_entrada);

        $movimientosCont = MovimientoContable::query()
            ->where('documento_inventario_id', $documento->id)
            ->orderBy('orden')
            ->get();

        // 2 productos × (Dr inventario + Cr puente)
        $this->assertCount(4, $movimientosCont);
        $this->assertTrue($movimientosCont->every(fn ($m) => $m->comprobante_contable_id === null));

        $puente = CuentaContable::query()
            ->deStore($this->store)
            ->where('codigo', DocumentoInventarioService::CODIGO_PUENTE_SALDOS_INICIALES)
            ->first();
        $this->assertNotNull($puente);

        $debitos = $movimientosCont->filter(fn ($m) => (float) $m->debito > 0)->values();
        $creditos = $movimientosCont->filter(fn ($m) => (float) $m->credito > 0)->values();

        $this->assertCount(2, $debitos);
        $this->assertCount(2, $creditos);
        $this->assertTrue($debitos->every(fn ($m) => $m->cuenta_contable_id === $this->cuentaInventario->id));
        $this->assertTrue($creditos->every(fn ($m) => $m->cuenta_contable_id === $puente->id));
        $this->assertSame('10000.00', (string) $debitos[0]->debito);
        $this->assertSame('10000.00', (string) $debitos[1]->debito);
        $this->assertSame('10000.00', (string) $creditos[0]->credito);
        $this->assertSame('10000.00', (string) $creditos[1]->credito);
        $this->assertSame(
            'Prod: P-001 Cant: 10.00',
            $debitos[0]->detalle_contable
        );
        $this->assertSame(
            'Prod: P-002 Cant: 2.00',
            $debitos[1]->detalle_contable
        );

        // Pares intercalados: Dr, Cr, Dr, Cr
        $this->assertSame($this->cuentaInventario->id, $movimientosCont[0]->cuenta_contable_id);
        $this->assertSame($puente->id, $movimientosCont[1]->cuenta_contable_id);
        $this->assertSame($this->cuentaInventario->id, $movimientosCont[2]->cuenta_contable_id);
        $this->assertSame($puente->id, $movimientosCont[3]->cuenta_contable_id);
    }

    public function test_propietario_puede_contabilizar_por_http(): void
    {
        $payload = $this->actingAs($this->user)
            ->postJson(route('stores.products.documentos.saldos-iniciales.store', $this->store), [
                'fecha' => '2026-08-01',
                'lineas' => [
                    [
                        'product_id' => $this->productoA->id,
                        'cantidad' => 1,
                        'costo_unitario' => 1500,
                    ],
                    [
                        'product_id' => $this->productoB->id,
                        'cantidad' => 3,
                        'costo_unitario' => 2000,
                    ],
                ],
            ])
            ->assertOk()
            ->json();

        $this->assertMatchesRegularExpression('/^A-\d+$/', (string) ($payload['documento']['numero'] ?? ''));

        $this->assertDatabaseCount('documentos_inventario', 1);
        $this->assertDatabaseCount('movimientos_inventario', 2);
        $this->assertDatabaseCount('movimientos_contables', 4);
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
