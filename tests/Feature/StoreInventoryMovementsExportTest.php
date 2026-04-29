<?php

namespace Tests\Feature;

use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class StoreInventoryMovementsExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_movements_excel_contains_rows_and_respects_product_filter(): void
    {
        $owner = User::factory()->create(['name' => 'Dueño Test']);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        DB::table('store_user')->insert([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productA = Product::factory()->create([
            'store_id' => $store->id,
            'name' => 'Producto Alpha Export',
        ]);
        $productB = Product::factory()->create([
            'store_id' => $store->id,
            'name' => 'Producto Beta Export',
        ]);

        MovimientoInventario::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'product_id' => $productA->id,
            'type' => MovimientoInventario::TYPE_ENTRADA,
            'quantity' => 3,
            'description' => 'Entrada prueba export',
            'unit_cost' => 10.5,
        ]);

        MovimientoInventario::create([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'product_id' => $productB->id,
            'type' => MovimientoInventario::TYPE_SALIDA,
            'quantity' => 1,
            'description' => 'Salida prueba export',
            'unit_cost' => null,
        ]);

        $response = $this->actingAs($owner)->get(
            route('stores.products.export-inventory-movements-excel', ['store' => $store])
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $binary = $response->streamedContent();
        $this->assertNotEmpty($binary);

        $path = tempnam(sys_get_temp_dir(), 'movxlsx');
        $this->assertNotFalse($path);
        file_put_contents($path, $binary);
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        $this->assertStringContainsString('Movimientos de inventario', (string) $sheet->getCell('A1')->getValue());
        $this->assertSame('Producto', (string) $sheet->getCell('C5')->getValue());

        $firstProductCell = (string) $sheet->getCell('C6')->getValue();
        $secondProductCell = (string) $sheet->getCell('C7')->getValue();
        $this->assertStringContainsString('Producto', $firstProductCell);
        $this->assertStringContainsString('Producto', $secondProductCell);

        $filtered = $this->actingAs($owner)->get(
            route('stores.products.export-inventory-movements-excel', [
                'store' => $store,
                'product_id' => $productA->id,
            ])
        );
        $filtered->assertOk();
        $path2 = tempnam(sys_get_temp_dir(), 'movxlsx2');
        $this->assertNotFalse($path2);
        file_put_contents($path2, $filtered->streamedContent());
        $sheet2 = IOFactory::load($path2)->getActiveSheet();
        @unlink($path2);

        $this->assertStringContainsString('Producto Alpha Export', (string) $sheet2->getCell('C6')->getValue());
        $this->assertEmpty((string) $sheet2->getCell('C7')->getValue());
    }
}
