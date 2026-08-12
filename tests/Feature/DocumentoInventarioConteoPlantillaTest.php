<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\CategoriaContable;
use App\Models\CuentaContable;
use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\DocumentoInventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DocumentoInventarioConteoPlantillaTest extends TestCase
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
            'name' => 'Tienda Plantilla Conteo',
        ]);
        DB::table('store_user')->insert([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'role_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cuenta = CuentaContable::create([
            'store_id' => $this->store->id,
            'codigo' => '14350101',
            'nombre' => 'Mercancías',
            'clase' => CuentaContable::claseDesdeCodigo('14350101'),
            'activo' => true,
            'nivel_agrupacion' => CuentaContable::NIVEL_TRANSACCIONAL,
            'es_auxiliar' => true,
            'origen' => CuentaContable::ORIGEN_MANUAL,
        ]);

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
            'codigo' => 'P-XL',
            'nombre' => 'Producto plantilla',
            'es_inventariable' => true,
            'is_active' => true,
        ]);

        $this->bodega = Bodega::create([
            'store_id' => $this->store->id,
            'codigo' => '01',
            'nombre' => 'Principal',
            'activo' => true,
        ]);

        MovimientoInventario::create([
            'store_id' => $this->store->id,
            'product_id' => $this->producto->id,
            'bodega_id' => $this->bodega->id,
            'fecha' => '2026-08-01',
            'clase_movimiento' => MovimientoInventario::CLASE_SALDO_INICIAL,
            'direccion' => MovimientoInventario::DIRECCION_ENTRADA,
            'cantidad' => 10,
            'costo_unitario_entrada' => 1000,
            'valor_entrada' => 10000,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_descarga_plantilla_por_bodega(): void
    {
        $this->actingAs($this->user)
            ->get(route('stores.products.documentos.conteo.plantilla', [
                'store' => $this->store,
                'bodega_id' => $this->bodega->id,
            ]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_parsea_plantilla_y_devuelve_lineas_para_formulario(): void
    {
        $path = $this->crearExcelTemporal([
            ['codigo', 'nombre', 'bodega_codigo', 'stock_sistema', 'cantidad_contada'],
            ['P-XL', 'Producto plantilla', '01', 10, 12],
        ]);

        $lineas = app(DocumentoInventarioService::class)->parsearPlantillaConteoFisico($this->store, $path);

        $this->assertCount(1, $lineas);
        $this->assertSame($this->producto->id, $lineas[0]['product_id']);
        $this->assertSame($this->bodega->id, $lineas[0]['bodega_id']);
        $this->assertSame(12.0, $lineas[0]['cantidad_contada']);

        @unlink($path);
    }

    public function test_http_parse_plantilla(): void
    {
        $path = $this->crearExcelTemporal([
            ['codigo', 'nombre', 'bodega_codigo', 'stock_sistema', 'cantidad_contada'],
            ['P-XL', 'Producto plantilla', '01', 10, 8],
        ]);

        $uploaded = new UploadedFile($path, 'conteo.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $payload = $this->actingAs($this->user)
            ->postJson(route('stores.products.documentos.conteo.plantilla.parse', $this->store), [
                'archivo' => $uploaded,
            ])
            ->assertOk()
            ->json();

        $this->assertCount(1, $payload['lineas']);
        $this->assertSame(8, (int) $payload['lineas'][0]['cantidad_contada']);

        @unlink($path);
    }

    /**
     * @param  list<list<mixed>>  $rows
     */
    private function crearExcelTemporal(array $rows): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $r => $cols) {
            foreach ($cols as $c => $value) {
                $sheet->setCellValue([$c + 1, $r + 1], $value);
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'conteo_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $path;
    }
}
