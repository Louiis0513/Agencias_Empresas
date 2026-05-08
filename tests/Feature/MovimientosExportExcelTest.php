<?php

namespace Tests\Feature;

use App\Models\Bolsillo;
use App\Models\ComprobanteEgreso;
use App\Models\ComprobanteEgresoOrigen;
use App\Models\ComprobanteIngreso;
use App\Models\ComprobanteIngresoDestino;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class MovimientosExportExcelTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Store} */
    private function seedStoreWithOwner(): array
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        DB::table('store_user')->insert([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'role_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $store];
    }

    public function test_export_movimientos_excel_ok_y_contiene_hojas_y_referencias(): void
    {
        [$user, $store] = $this->seedStoreWithOwner();

        $bIng = Bolsillo::create([
            'store_id' => $store->id,
            'name' => 'Caja ingreso export',
        ]);
        $bEgr = Bolsillo::create([
            'store_id' => $store->id,
            'name' => 'Caja egreso export',
        ]);

        $ci = ComprobanteIngreso::create([
            'store_id' => $store->id,
            'number' => 'CI-XLS',
            'total_amount' => '50.00',
            'date' => now()->toDateString(),
            'notes' => 'Nota ingreso excel',
            'type' => ComprobanteIngreso::TYPE_INGRESO_MANUAL,
            'user_id' => $user->id,
        ]);
        ComprobanteIngresoDestino::create([
            'comprobante_ingreso_id' => $ci->id,
            'bolsillo_id' => $bIng->id,
            'amount' => '50.00',
            'reference' => 'REF-XLS-ING',
        ]);

        $ce = ComprobanteEgreso::create([
            'store_id' => $store->id,
            'number' => 'CE-XLS',
            'total_amount' => '25.00',
            'payment_date' => now()->toDateString(),
            'notes' => 'Nota egreso excel',
            'type' => ComprobanteEgreso::TYPE_GASTO_DIRECTO,
            'beneficiary_name' => 'Proveedor XLS',
            'user_id' => $user->id,
        ]);
        ComprobanteEgresoOrigen::create([
            'comprobante_egreso_id' => $ce->id,
            'bolsillo_id' => $bEgr->id,
            'amount' => '25.00',
            'reference' => 'REF-XLS-EGR',
        ]);

        $mes = now()->format('Y-m');

        $response = $this->actingAs($user)->get(
            route('stores.cajas.movimientos.export-excel', ['store' => $store, 'export_mes' => $mes])
        );

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $binary = $response->streamedContent();
        $this->assertNotEmpty($binary);

        $path = tempnam(sys_get_temp_dir(), 'movxlsx');
        $this->assertNotFalse($path);
        file_put_contents($path, $binary);
        $spreadsheet = IOFactory::load($path);
        @unlink($path);

        $this->assertSame('Resumen', $spreadsheet->getSheet(0)->getTitle());
        $this->assertSame('Ingresos', $spreadsheet->getSheet(1)->getTitle());
        $this->assertSame('Egresos', $spreadsheet->getSheet(2)->getTitle());
        $this->assertNotNull($spreadsheet->getSheetByName('Por cobrar'));
        $this->assertNotNull($spreadsheet->getSheetByName('Por pagar'));

        $ingSheet = $spreadsheet->getSheet(1);
        $flatIng = '';
        foreach ($ingSheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $flatIng .= (string) $cell->getValue().'|';
            }
        }
        $this->assertStringContainsString('REF-XLS-ING', $flatIng);
        $this->assertStringContainsString('CI-XLS', $flatIng);

        $egrSheet = $spreadsheet->getSheet(2);
        $flatEgr = '';
        foreach ($egrSheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $flatEgr .= (string) $cell->getValue().'|';
            }
        }
        $this->assertStringContainsString('REF-XLS-EGR', $flatEgr);
        $this->assertStringContainsString('CE-XLS', $flatEgr);
    }
}
