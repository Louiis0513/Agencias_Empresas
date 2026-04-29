<?php

namespace Tests\Feature;

use App\Livewire\CreateMovimientoInventarioModal;
use App\Models\Category;
use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ManualSerializedInventorySalidaTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Store} */
    protected function createStoreWithOwner(): array
    {
        $owner = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $owner->id]);
        DB::table('store_user')->insert([
            'store_id' => $store->id,
            'user_id' => $owner->id,
            'role_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$owner, $store];
    }

    public function test_livewire_save_serialized_salida_marks_product_item_withdrawn(): void
    {
        [$owner, $store] = $this->createStoreWithOwner();
        $category = Category::factory()->create(['store_id' => $store->id]);

        $product = Product::factory()->create([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'type' => MovimientoInventario::PRODUCT_TYPE_SERIALIZED,
            'stock' => 1,
        ]);

        ProductItem::create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'serial_number' => 'TEST-SN-1',
            'batch' => 'INI-TEST',
            'cost' => 10,
            'status' => ProductItem::STATUS_AVAILABLE,
        ]);

        Livewire::actingAs($owner)
            ->test(CreateMovimientoInventarioModal::class, ['storeId' => $store->id])
            ->set('wizardStep', 2)
            ->set('type', MovimientoInventario::TYPE_SALIDA)
            ->set('product_id', $product->id)
            ->set('serials_selected', ['TEST-SN-1'])
            ->set('description', 'Prueba retiro')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('product_items', [
            'product_id' => $product->id,
            'serial_number' => 'TEST-SN-1',
            'status' => ProductItem::STATUS_WITHDRAWN,
        ]);

        $product->refresh();
        $this->assertSame(0.0, (float) $product->stock);
    }
}
