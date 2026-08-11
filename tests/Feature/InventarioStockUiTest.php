<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\CategoriaContable;
use App\Models\CuentaContable;
use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventarioStockUiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Store $store;

    private Product $producto;

    private Bodega $bodegaA;

    private Bodega $bodegaB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->store = Store::factory()->create([
            'user_id' => $this->user->id,
            'maneja_bodegas' => true,
            'name' => 'Tienda Stock UI',
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
            'codigo' => 'P-STK',
            'nombre' => 'Producto stock',
            'es_inventariable' => true,
            'tipo' => Product::TIPO_PRODUCTO,
        ]);

        $this->bodegaA = Bodega::create([
            'store_id' => $this->store->id,
            'codigo' => '01',
            'nombre' => 'Principal',
            'activo' => true,
        ]);

        $this->bodegaB = Bodega::create([
            'store_id' => $this->store->id,
            'codigo' => '02',
            'nombre' => 'Secundaria',
            'activo' => true,
        ]);
    }

    public function test_stock_total_y_por_bodega_desde_ledger(): void
    {
        $this->crearMovimiento($this->bodegaA->id, 10, MovimientoInventario::DIRECCION_ENTRADA);
        $this->crearMovimiento($this->bodegaA->id, 2, MovimientoInventario::DIRECCION_SALIDA);
        $this->crearMovimiento($this->bodegaB->id, 5, MovimientoInventario::DIRECCION_ENTRADA);

        $svc = app(InventarioService::class);

        $this->assertSame(13.0, $svc->stockTotal($this->store, $this->producto));

        $porBodega = $svc->stockPorBodega($this->store, $this->producto);
        $this->assertCount(2, $porBodega);

        $map = collect($porBodega)->keyBy('bodega_id');
        $this->assertSame(8.0, $map[$this->bodegaA->id]['cantidad']);
        $this->assertSame(5.0, $map[$this->bodegaB->id]['cantidad']);
    }

    public function test_detalle_muestra_stock_total_y_desglose(): void
    {
        $this->crearMovimiento($this->bodegaA->id, 4, MovimientoInventario::DIRECCION_ENTRADA);
        $this->crearMovimiento($this->bodegaB->id, 6, MovimientoInventario::DIRECCION_ENTRADA);

        $response = $this->actingAs($this->user)
            ->get(route('stores.products.show', [$this->store, $this->producto]));

        $response->assertOk();
        $response->assertSee('Stock actual: 10,00', false);
        $response->assertSee('Principal', false);
        $response->assertSee('Secundaria', false);
        $response->assertSee('4,00', false);
        $response->assertSee('6,00', false);
    }

    private function crearMovimiento(?int $bodegaId, float $cantidad, string $direccion): void
    {
        MovimientoInventario::create([
            'store_id' => $this->store->id,
            'product_id' => $this->producto->id,
            'bodega_id' => $bodegaId,
            'fecha' => now()->toDateString(),
            'clase_movimiento' => $direccion === MovimientoInventario::DIRECCION_ENTRADA
                ? MovimientoInventario::CLASE_AJUSTE_ENTRADA
                : MovimientoInventario::CLASE_AJUSTE_SALIDA,
            'direccion' => $direccion,
            'cantidad' => $cantidad,
            'costo_unitario_entrada' => $direccion === MovimientoInventario::DIRECCION_ENTRADA ? 1000 : null,
            'valor_entrada' => $direccion === MovimientoInventario::DIRECCION_ENTRADA ? $cantidad * 1000 : null,
            'documento_etiqueta' => 'TEST',
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
