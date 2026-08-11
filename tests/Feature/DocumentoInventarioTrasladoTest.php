<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\CategoriaContable;
use App\Models\CuentaContable;
use App\Models\DocumentoInventario;
use App\Models\MovimientoContable;
use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\DocumentoInventarioService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DocumentoInventarioTrasladoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    private CuentaContable $cuentaInventario;

    private Product $producto;

    private Bodega $bodegaOrigen;

    private Bodega $bodegaDestino;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->store = Store::factory()->create([
            'user_id' => $this->user->id,
            'maneja_bodegas' => true,
            'name' => 'Tienda Traslado',
        ]);
        DB::table('store_user')->insert([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'role_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->cuentaInventario = $this->crearCuenta($this->store, '14350101', 'Mercancías no fabricadas');

        $categoria = CategoriaContable::create([
            'store_id' => $this->store->id,
            'codigo' => '1',
            'nombre' => 'Productos',
            'tipo' => CategoriaContable::TIPO_PRODUCTO,
            'cuenta_inventario_id' => $this->cuentaInventario->id,
            'activo' => true,
        ]);

        $this->producto = Product::factory()->conCategoria($categoria)->create([
            'store_id' => $this->store->id,
            'codigo' => 'P-TR',
            'nombre' => 'Producto traslado',
            'es_inventariable' => true,
        ]);

        $this->bodegaOrigen = Bodega::create([
            'store_id' => $this->store->id,
            'codigo' => '01',
            'nombre' => 'Principal',
            'activo' => true,
        ]);

        $this->bodegaDestino = Bodega::create([
            'store_id' => $this->store->id,
            'codigo' => '02',
            'nombre' => 'Secundaria',
            'activo' => true,
        ]);
    }

    public function test_contabiliza_traslado_con_movimientos_y_asiento_en_cero(): void
    {
        $this->crearStock($this->bodegaOrigen->id, 10);

        $documento = app(DocumentoInventarioService::class)->contabilizarTraslado(
            $this->store,
            $this->user->id,
            [
                'fecha' => '2026-08-11',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'bodega_origen_id' => $this->bodegaOrigen->id,
                        'bodega_destino_id' => $this->bodegaDestino->id,
                        'cantidad' => 3,
                    ],
                ],
            ]
        );

        $this->assertSame(DocumentoInventario::TIPO_TRASLADO, $documento->tipo_documento);
        $this->assertMatchesRegularExpression('/^NT-\d+$/', $documento->numero);
        $this->assertSame('0.00', (string) $documento->total);
        $this->assertSame('0.00', (string) $documento->total_debito);
        $this->assertSame('0.00', (string) $documento->total_credito);
        $this->assertSame('Tienda Traslado', $documento->tercero_nombre);
        $this->assertCount(1, $documento->lineas);
        $this->assertSame($this->bodegaOrigen->id, $documento->lineas[0]->bodega_origen_id);
        $this->assertSame($this->bodegaDestino->id, $documento->lineas[0]->bodega_destino_id);

        $movimientosInv = MovimientoInventario::query()
            ->where('documento_type', DocumentoInventario::class)
            ->where('documento_id', $documento->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $movimientosInv);
        $this->assertSame(MovimientoInventario::CLASE_TRASLADO_SALIDA, $movimientosInv[0]->clase_movimiento);
        $this->assertSame(MovimientoInventario::DIRECCION_SALIDA, $movimientosInv[0]->direccion);
        $this->assertSame($this->bodegaOrigen->id, $movimientosInv[0]->bodega_id);
        $this->assertSame(MovimientoInventario::CLASE_TRASLADO_ENTRADA, $movimientosInv[1]->clase_movimiento);
        $this->assertSame(MovimientoInventario::DIRECCION_ENTRADA, $movimientosInv[1]->direccion);
        $this->assertSame($this->bodegaDestino->id, $movimientosInv[1]->bodega_id);

        $movimientosCont = MovimientoContable::query()
            ->where('documento_inventario_id', $documento->id)
            ->orderBy('orden')
            ->get();

        $this->assertCount(2, $movimientosCont);
        $this->assertTrue($movimientosCont->every(fn ($m) => (float) $m->debito === 0.0 && (float) $m->credito === 0.0));
        $this->assertTrue($movimientosCont->every(fn ($m) => $m->cuenta_contable_id === $this->cuentaInventario->id));
        $this->assertStringContainsString('Bod: 01', (string) $movimientosCont[0]->descripcion);
        $this->assertStringContainsString('Bod: 02', (string) $movimientosCont[1]->descripcion);
    }

    public function test_rechaza_origen_igual_destino(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('origen y destino deben ser distintas');

        app(DocumentoInventarioService::class)->contabilizarTraslado(
            $this->store,
            $this->user->id,
            [
                'fecha' => '2026-08-11',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'bodega_origen_id' => $this->bodegaOrigen->id,
                        'bodega_destino_id' => $this->bodegaOrigen->id,
                        'cantidad' => 1,
                    ],
                ],
            ]
        );
    }

    public function test_rechaza_si_no_maneja_bodegas(): void
    {
        $this->store->update(['maneja_bodegas' => false]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('manejo de bodegas');

        app(DocumentoInventarioService::class)->contabilizarTraslado(
            $this->store,
            $this->user->id,
            [
                'fecha' => '2026-08-11',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'bodega_origen_id' => $this->bodegaOrigen->id,
                        'bodega_destino_id' => $this->bodegaDestino->id,
                        'cantidad' => 1,
                    ],
                ],
            ]
        );
    }

    public function test_traslado_desde_sin_asignar_hacia_bodega(): void
    {
        $this->crearStock(null, 5);

        $documento = app(DocumentoInventarioService::class)->contabilizarTraslado(
            $this->store,
            $this->user->id,
            [
                'fecha' => '2026-08-11',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'bodega_origen_id' => null,
                        'bodega_destino_id' => $this->bodegaDestino->id,
                        'cantidad' => 5,
                    ],
                ],
            ]
        );

        $this->assertDatabaseHas('movimientos_inventario', [
            'store_id' => $this->store->id,
            'product_id' => $this->producto->id,
            'bodega_id' => null,
            'direccion' => MovimientoInventario::DIRECCION_SALIDA,
            'clase_movimiento' => MovimientoInventario::CLASE_TRASLADO_SALIDA,
            'documento_id' => $documento->id,
        ]);

        $this->assertDatabaseHas('movimientos_inventario', [
            'store_id' => $this->store->id,
            'product_id' => $this->producto->id,
            'bodega_id' => $this->bodegaDestino->id,
            'direccion' => MovimientoInventario::DIRECCION_ENTRADA,
            'clase_movimiento' => MovimientoInventario::CLASE_TRASLADO_ENTRADA,
            'documento_id' => $documento->id,
        ]);
    }

    public function test_traslado_desde_bodega_hacia_sin_asignar(): void
    {
        $this->crearStock($this->bodegaOrigen->id, 5);

        $documento = app(DocumentoInventarioService::class)->contabilizarTraslado(
            $this->store,
            $this->user->id,
            [
                'fecha' => '2026-08-11',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'bodega_origen_id' => $this->bodegaOrigen->id,
                        'bodega_destino_id' => null,
                        'cantidad' => 2,
                    ],
                ],
            ]
        );

        $this->assertDatabaseHas('movimientos_inventario', [
            'store_id' => $this->store->id,
            'product_id' => $this->producto->id,
            'bodega_id' => $this->bodegaOrigen->id,
            'direccion' => MovimientoInventario::DIRECCION_SALIDA,
            'documento_id' => $documento->id,
        ]);

        $this->assertDatabaseHas('movimientos_inventario', [
            'store_id' => $this->store->id,
            'product_id' => $this->producto->id,
            'bodega_id' => null,
            'direccion' => MovimientoInventario::DIRECCION_ENTRADA,
            'documento_id' => $documento->id,
        ]);
    }

    public function test_propietario_puede_contabilizar_traslado_por_http(): void
    {
        $this->crearStock($this->bodegaOrigen->id, 5);

        $payload = $this->actingAs($this->user)
            ->postJson(route('stores.products.documentos.traslado.store', $this->store), [
                'fecha' => '2026-08-11',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'bodega_origen_id' => $this->bodegaOrigen->id,
                        'bodega_destino_id' => $this->bodegaDestino->id,
                        'cantidad' => 2,
                    ],
                ],
            ])
            ->assertOk()
            ->json();

        $this->assertMatchesRegularExpression('/^NT-\d+$/', (string) ($payload['documento']['numero'] ?? ''));
        $this->assertDatabaseHas('documentos_inventario', [
            'store_id' => $this->store->id,
            'tipo_documento' => DocumentoInventario::TIPO_TRASLADO,
        ]);
    }

    public function test_rechaza_traslado_si_no_hay_stock_en_origen(): void
    {
        $this->crearStock($this->bodegaOrigen->id, 5);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('solo tiene 5,00 disponible');

        app(DocumentoInventarioService::class)->contabilizarTraslado(
            $this->store,
            $this->user->id,
            [
                'fecha' => '2026-08-11',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'bodega_origen_id' => $this->bodegaOrigen->id,
                        'bodega_destino_id' => $this->bodegaDestino->id,
                        'cantidad' => 20,
                    ],
                ],
            ]
        );
    }

    public function test_rechaza_traslado_desde_sin_asignar_sin_stock_suficiente(): void
    {
        $this->crearStock(null, 5);

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/solo tiene 5,00 disponible.*Sin asignar.*Ajuste de inventario/s');

        app(DocumentoInventarioService::class)->contabilizarTraslado(
            $this->store,
            $this->user->id,
            [
                'fecha' => '2026-08-11',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'bodega_origen_id' => null,
                        'bodega_destino_id' => $this->bodegaDestino->id,
                        'cantidad' => 20,
                    ],
                ],
            ]
        );
    }

    public function test_rechaza_si_varias_lineas_superan_stock_origen(): void
    {
        $this->crearStock($this->bodegaOrigen->id, 5);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('solo tiene');

        app(DocumentoInventarioService::class)->contabilizarTraslado(
            $this->store,
            $this->user->id,
            [
                'fecha' => '2026-08-11',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'bodega_origen_id' => $this->bodegaOrigen->id,
                        'bodega_destino_id' => $this->bodegaDestino->id,
                        'cantidad' => 3,
                    ],
                    [
                        'product_id' => $this->producto->id,
                        'bodega_origen_id' => $this->bodegaOrigen->id,
                        'bodega_destino_id' => $this->bodegaDestino->id,
                        'cantidad' => 3,
                    ],
                ],
            ]
        );
    }

    private function crearStock(?int $bodegaId, float $cantidad): void
    {
        MovimientoInventario::create([
            'store_id' => $this->store->id,
            'product_id' => $this->producto->id,
            'bodega_id' => $bodegaId,
            'fecha' => '2026-08-01',
            'clase_movimiento' => MovimientoInventario::CLASE_SALDO_INICIAL,
            'direccion' => MovimientoInventario::DIRECCION_ENTRADA,
            'cantidad' => $cantidad,
            'costo_unitario_entrada' => 1000,
            'valor_entrada' => $cantidad * 1000,
            'documento_etiqueta' => 'STOCK-TEST',
            'user_id' => $this->user->id,
        ]);
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
