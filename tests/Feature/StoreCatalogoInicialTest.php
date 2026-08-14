<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\CategoriaContable;
use App\Models\CentroCosto;
use App\Models\CuentaContable;
use App\Models\FormaPago;
use App\Models\Impuesto;
use App\Models\ListaPrecio;
use App\Models\Plan;
use App\Models\Store;
use App\Models\TipoComprobante;
use App\Models\User;
use App\Services\DocumentoInventarioService;
use App\Services\StoreCatalogoInicialService;
use App\Services\StoreService;
use App\Support\CatalogoImpuestosPredeterminados;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreCatalogoInicialTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_store_bootstrap_catalogos_minimos(): void
    {
        $store = $this->crearTiendaViaServicio();

        $this->assertSame(9, CuentaContable::query()->deStore($store)->whereIn('codigo', ['1', '2', '3', '4', '5', '6', '7', '8', '9'])->count());
        $this->assertTrue(CuentaContable::query()->deStore($store)->where('codigo', '143501')->exists());
        $this->assertTrue(
            CuentaContable::query()->deStore($store)
                ->where('codigo', DocumentoInventarioService::CODIGO_PUENTE_SALDOS_INICIALES)
                ->exists()
        );

        $productos = CategoriaContable::query()
            ->deStore($store)
            ->with(['cuentaInventario', 'cuentaCosto', 'cuentaIngreso', 'cuentaDevolucion'])
            ->where('tipo', CategoriaContable::TIPO_PRODUCTO)
            ->first();
        $servicios = CategoriaContable::query()
            ->deStore($store)
            ->with(['cuentaInventario', 'cuentaCosto', 'cuentaIngreso', 'cuentaDevolucion'])
            ->where('tipo', CategoriaContable::TIPO_SERVICIO)
            ->first();

        $this->assertNotNull($productos);
        $this->assertNotNull($servicios);
        $this->assertSame('Inventario – productos', $productos->cuentaInventario?->nombre);
        $this->assertSame('Inventario – servicios', $servicios->cuentaInventario?->nombre);
        $this->assertNotSame($productos->cuenta_inventario_id, $servicios->cuenta_inventario_id);
        $this->assertNotSame($productos->cuenta_costo_id, $servicios->cuenta_costo_id);
        $this->assertNotSame($productos->cuenta_ingreso_id, $servicios->cuenta_ingreso_id);
        $this->assertNotSame($productos->cuenta_devolucion_id, $servicios->cuenta_devolucion_id);

        $this->assertSame(count(CatalogoImpuestosPredeterminados::impuestos()), Impuesto::query()->deStore($store)->count());
        $this->assertSame(3, FormaPago::query()->deStore($store)->count());
        $this->assertTrue(FormaPago::query()->deStore($store)->where('nombre', 'Efectivo')->exists());

        $this->assertTrue(
            TipoComprobante::query()->deStore($store)
                ->where('familia', TipoComprobante::FAMILIA_FV)
                ->where('codigo', '1')
                ->exists()
        );
        $this->assertTrue(
            TipoComprobante::query()->deStore($store)
                ->where('familia', TipoComprobante::FAMILIA_CC)
                ->where('codigo', '1')
                ->exists()
        );

        $this->assertSame(ListaPrecio::MAX_POR_TIENDA, ListaPrecio::query()->deStore($store)->count());
        $this->assertSame(2, ListaPrecio::query()->deStore($store)->where('activo', true)->count());

        $centro = CentroCosto::query()->deStore($store)->centros()->where('codigo', '01')->first();
        $this->assertNotNull($centro);
        $this->assertSame('General', $centro->nombre);
        $this->assertTrue(
            CentroCosto::query()->deStore($store)->where('parent_id', $centro->id)->where('nombre', 'General')->exists()
        );

        $this->assertFalse((bool) $store->fresh()->maneja_bodegas);
        $this->assertSame(0, Bodega::query()->deStore($store)->count());
    }

    public function test_bootstrap_es_idempotente(): void
    {
        $store = $this->crearTiendaViaServicio();

        $cuentas = CuentaContable::query()->deStore($store)->count();
        $impuestos = Impuesto::query()->deStore($store)->count();
        $formas = FormaPago::query()->deStore($store)->count();
        $categorias = CategoriaContable::query()->deStore($store)->count();
        $tipos = TipoComprobante::query()->deStore($store)->count();
        $listas = ListaPrecio::query()->deStore($store)->count();
        $centros = CentroCosto::query()->deStore($store)->count();

        app(StoreCatalogoInicialService::class)->bootstrap($store);

        $this->assertSame($cuentas, CuentaContable::query()->deStore($store)->count());
        $this->assertSame($impuestos, Impuesto::query()->deStore($store)->count());
        $this->assertSame($formas, FormaPago::query()->deStore($store)->count());
        $this->assertSame($categorias, CategoriaContable::query()->deStore($store)->count());
        $this->assertSame($tipos, TipoComprobante::query()->deStore($store)->count());
        $this->assertSame($listas, ListaPrecio::query()->deStore($store)->count());
        $this->assertSame($centros, CentroCosto::query()->deStore($store)->count());
    }

    public function test_factory_no_ejecuta_bootstrap(): void
    {
        $store = Store::factory()->create();

        $this->assertSame(0, CuentaContable::query()->deStore($store)->count());
        $this->assertSame(0, Impuesto::query()->deStore($store)->count());
        $this->assertSame(0, CategoriaContable::query()->deStore($store)->count());
        $this->assertSame(0, ListaPrecio::query()->deStore($store)->count());
        $this->assertSame(0, CentroCosto::query()->deStore($store)->count());
    }

    private function crearTiendaViaServicio(): Store
    {
        $plan = Plan::create([
            'name' => 'Basico',
            'slug' => 'basic-bootstrap',
            'max_stores' => 3,
            'max_employees' => 10,
            'price' => 0,
        ]);
        $user = User::factory()->create(['plan_id' => $plan->id]);

        return app(StoreService::class)->createStore($user, [
            'name' => 'Tienda Bootstrap',
        ]);
    }
}
