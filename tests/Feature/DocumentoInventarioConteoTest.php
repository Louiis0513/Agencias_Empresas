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

class DocumentoInventarioConteoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    private Product $producto;

    private Bodega $bodega;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->store = Store::factory()->create([
            'user_id' => $this->user->id,
            'maneja_bodegas' => true,
            'name' => 'Tienda Conteo',
        ]);
        DB::table('store_user')->insert([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'role_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cuenta = $this->crearCuenta($this->store, '14350101', 'Mercancías');

        $categoria = CategoriaContable::create([
            'store_id' => $this->store->id,
            'codigo' => '1',
            'nombre' => 'Productos',
            'tipo' => CategoriaContable::TIPO_PRODUCTO,
            'cuenta_inventario_id' => $cuenta->id,
            'activo' => true,
        ]);

        $this->producto = Product::factory()->conCategoria($categoria)->create([
            'store_id' => $this->store->id,
            'codigo' => 'P-CF',
            'nombre' => 'Producto conteo',
            'es_inventariable' => true,
        ]);

        $this->bodega = Bodega::create([
            'store_id' => $this->store->id,
            'codigo' => '01',
            'nombre' => 'Principal',
            'activo' => true,
        ]);
    }

    public function test_contado_mayor_que_sistema_genera_entrada(): void
    {
        $this->crearStock($this->bodega->id, 10);

        $documento = app(DocumentoInventarioService::class)->registrarConteoFisico(
            $this->store,
            $this->user->id,
            [
                'fecha' => '2026-08-11',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'bodega_id' => $this->bodega->id,
                        'cantidad_contada' => 13,
                    ],
                ],
            ]
        );

        $this->assertSame(DocumentoInventario::TIPO_CONTEO_FISICO, $documento->tipo_documento);
        $this->assertMatchesRegularExpression('/^CF-\d+$/', $documento->numero);
        $this->assertCount(1, $documento->lineas);
        $this->assertSame('10.0000', (string) $documento->lineas[0]->cantidad_sistema);
        $this->assertSame('13.0000', (string) $documento->lineas[0]->cantidad_contada);
        $this->assertSame('3.0000', (string) $documento->lineas[0]->cantidad);

        $mov = MovimientoInventario::query()
            ->where('documento_type', DocumentoInventario::class)
            ->where('documento_id', $documento->id)
            ->first();

        $this->assertNotNull($mov);
        $this->assertSame(MovimientoInventario::CLASE_CONTEO_ENTRADA, $mov->clase_movimiento);
        $this->assertSame(MovimientoInventario::DIRECCION_ENTRADA, $mov->direccion);
        $this->assertNull($mov->costo_unitario_entrada);
        $this->assertNull($mov->valor_entrada);
        $this->assertSame(0, MovimientoContable::query()->where('documento_inventario_id', $documento->id)->count());
    }

    public function test_contado_menor_que_sistema_genera_salida(): void
    {
        $this->crearStock($this->bodega->id, 100);

        $documento = app(DocumentoInventarioService::class)->registrarConteoFisico(
            $this->store,
            $this->user->id,
            [
                'fecha' => '2026-08-11',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'bodega_id' => $this->bodega->id,
                        'cantidad_contada' => 97,
                    ],
                ],
            ]
        );

        $mov = MovimientoInventario::query()
            ->where('documento_id', $documento->id)
            ->first();

        $this->assertSame(MovimientoInventario::CLASE_CONTEO_SALIDA, $mov->clase_movimiento);
        $this->assertSame(MovimientoInventario::DIRECCION_SALIDA, $mov->direccion);
        $this->assertSame('3.0000', (string) $mov->cantidad);
        $this->assertNull($mov->costo_unitario_entrada);
        $this->assertNull($mov->valor_entrada);
    }

    public function test_sin_diferencia_rechaza(): void
    {
        $this->crearStock($this->bodega->id, 5);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No hay diferencias que registrar');

        app(DocumentoInventarioService::class)->registrarConteoFisico(
            $this->store,
            $this->user->id,
            [
                'fecha' => '2026-08-11',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'bodega_id' => $this->bodega->id,
                        'cantidad_contada' => 5,
                    ],
                ],
            ]
        );
    }

    public function test_sin_asignar_y_recalcula_stock_al_guardar(): void
    {
        $this->crearStock(null, 15);

        $documento = app(DocumentoInventarioService::class)->registrarConteoFisico(
            $this->store,
            $this->user->id,
            [
                'fecha' => '2026-08-11',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'bodega_id' => null,
                        'cantidad_contada' => 20,
                    ],
                ],
            ]
        );

        $this->assertNull($documento->lineas[0]->bodega_id);
        $this->assertSame('15.0000', (string) $documento->lineas[0]->cantidad_sistema);
        $this->assertSame('5.0000', (string) $documento->lineas[0]->cantidad);

        $mov = MovimientoInventario::query()->where('documento_id', $documento->id)->first();
        $this->assertNull($mov->bodega_id);
        $this->assertSame(MovimientoInventario::DIRECCION_ENTRADA, $mov->direccion);
    }

    public function test_propietario_puede_registrar_conteo_por_http(): void
    {
        $this->crearStock($this->bodega->id, 4);

        $payload = $this->actingAs($this->user)
            ->postJson(route('stores.products.documentos.conteo.store', $this->store), [
                'fecha' => '2026-08-11',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'bodega_id' => $this->bodega->id,
                        'cantidad_contada' => 6,
                    ],
                ],
            ])
            ->assertOk()
            ->json();

        $this->assertMatchesRegularExpression('/^CF-\d+$/', (string) ($payload['documento']['numero'] ?? ''));
        $this->assertDatabaseHas('documentos_inventario', [
            'store_id' => $this->store->id,
            'tipo_documento' => DocumentoInventario::TIPO_CONTEO_FISICO,
        ]);
        $this->assertSame(0, MovimientoContable::query()->count());
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
