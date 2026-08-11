<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\CategoriaContable;
use App\Models\CuentaContable;
use App\Models\DocumentoInventario;
use App\Models\DocumentoInventarioLinea;
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

class DocumentoInventarioAjusteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    private CuentaContable $cuentaInventario;

    private CuentaContable $cuentaGasto;

    private Product $producto;

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
        $this->cuentaGasto = $this->crearCuenta($this->store, '61359501', 'Ajuste de inventario gasto');

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
            'codigo' => 'P-AJ',
            'nombre' => 'Producto ajuste',
            'es_inventariable' => true,
        ]);
    }

    public function test_contabiliza_ajuste_aumenta_y_disminuye(): void
    {
        $documento = app(DocumentoInventarioService::class)->contabilizarAjuste(
            $this->store,
            $this->user->id,
            [
                'fecha' => '2026-08-10',
                'tercero_nombre' => 'Corrección',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'direccion' => DocumentoInventarioLinea::DIRECCION_AUMENTA,
                        'cuenta_contable_id' => $this->cuentaGasto->id,
                        'cantidad' => 5,
                        'costo_unitario' => 1000,
                    ],
                    [
                        'product_id' => $this->producto->id,
                        'direccion' => DocumentoInventarioLinea::DIRECCION_DISMINUYE,
                        'cuenta_contable_id' => $this->cuentaGasto->id,
                        'cantidad' => 2,
                        'costo_unitario' => 1000,
                    ],
                ],
            ]
        );

        $this->assertSame(DocumentoInventario::TIPO_AJUSTE, $documento->tipo_documento);
        $this->assertSame('7000.00', (string) $documento->total);
        $this->assertSame('7000.00', (string) $documento->total_debito);
        $this->assertSame('7000.00', (string) $documento->total_credito);
        $this->assertCount(2, $documento->lineas);

        $movimientosInv = MovimientoInventario::query()
            ->where('documento_type', DocumentoInventario::class)
            ->where('documento_id', $documento->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $movimientosInv);
        $this->assertSame(MovimientoInventario::CLASE_AJUSTE_ENTRADA, $movimientosInv[0]->clase_movimiento);
        $this->assertSame(MovimientoInventario::DIRECCION_ENTRADA, $movimientosInv[0]->direccion);
        $this->assertSame(MovimientoInventario::CLASE_AJUSTE_SALIDA, $movimientosInv[1]->clase_movimiento);
        $this->assertSame(MovimientoInventario::DIRECCION_SALIDA, $movimientosInv[1]->direccion);

        $movimientosCont = MovimientoContable::query()
            ->where('documento_inventario_id', $documento->id)
            ->orderBy('orden')
            ->get();

        $this->assertCount(4, $movimientosCont);

        // Aumenta: Dr inventario / Cr gasto
        $this->assertSame($this->cuentaInventario->id, $movimientosCont[0]->cuenta_contable_id);
        $this->assertSame('5000.00', (string) $movimientosCont[0]->debito);
        $this->assertSame($this->cuentaGasto->id, $movimientosCont[1]->cuenta_contable_id);
        $this->assertSame('5000.00', (string) $movimientosCont[1]->credito);

        // Disminuye: Dr gasto / Cr inventario
        $this->assertSame($this->cuentaGasto->id, $movimientosCont[2]->cuenta_contable_id);
        $this->assertSame('2000.00', (string) $movimientosCont[2]->debito);
        $this->assertSame($this->cuentaInventario->id, $movimientosCont[3]->cuenta_contable_id);
        $this->assertSame('2000.00', (string) $movimientosCont[3]->credito);

        $this->assertSame(DocumentoInventarioLinea::DIRECCION_AUMENTA, $documento->lineas[0]->direccion);
        $this->assertSame(DocumentoInventarioLinea::DIRECCION_DISMINUYE, $documento->lineas[1]->direccion);
        $this->assertSame('Aumenta', $documento->lineas[0]->etiquetaDireccion());
        $this->assertSame('Disminuye', $documento->lineas[1]->etiquetaDireccion());
    }

    public function test_rechaza_producto_no_inventariable(): void
    {
        $servicio = Product::factory()->create([
            'store_id' => $this->store->id,
            'codigo' => 'S-1',
            'nombre' => 'Servicio',
            'tipo' => Product::TIPO_SERVICIO,
            'es_inventariable' => false,
            'categoria_contable_id' => $this->producto->categoria_contable_id,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('no es inventariable');

        app(DocumentoInventarioService::class)->contabilizarAjuste(
            $this->store,
            $this->user->id,
            [
                'fecha' => '2026-08-10',
                'lineas' => [
                    [
                        'product_id' => $servicio->id,
                        'direccion' => DocumentoInventarioLinea::DIRECCION_AUMENTA,
                        'cuenta_contable_id' => $this->cuentaGasto->id,
                        'cantidad' => 1,
                        'costo_unitario' => 100,
                    ],
                ],
            ]
        );
    }

    public function test_rechaza_bodega_inactiva(): void
    {
        $this->store->update(['maneja_bodegas' => true]);
        $bodega = Bodega::create([
            'store_id' => $this->store->id,
            'codigo' => '01',
            'nombre' => 'Inactiva',
            'activo' => false,
        ]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('bodega');

        app(DocumentoInventarioService::class)->contabilizarAjuste(
            $this->store,
            $this->user->id,
            [
                'fecha' => '2026-08-10',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'bodega_id' => $bodega->id,
                        'direccion' => DocumentoInventarioLinea::DIRECCION_AUMENTA,
                        'cuenta_contable_id' => $this->cuentaGasto->id,
                        'cantidad' => 1,
                        'costo_unitario' => 100,
                    ],
                ],
            ]
        );
    }

    public function test_propietario_puede_contabilizar_ajuste_por_http(): void
    {
        $payload = $this->actingAs($this->user)
            ->postJson(route('stores.products.documentos.ajuste.store', $this->store), [
                'fecha' => '2026-08-10',
                'lineas' => [
                    [
                        'product_id' => $this->producto->id,
                        'direccion' => DocumentoInventarioLinea::DIRECCION_AUMENTA,
                        'cuenta_contable_id' => $this->cuentaGasto->id,
                        'cantidad' => 1,
                        'costo_unitario' => 2500,
                    ],
                ],
            ])
            ->assertOk()
            ->json();

        $this->assertMatchesRegularExpression('/^A-\d+$/', (string) ($payload['documento']['numero'] ?? ''));
        $this->assertDatabaseHas('documentos_inventario', [
            'store_id' => $this->store->id,
            'tipo_documento' => DocumentoInventario::TIPO_AJUSTE,
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
