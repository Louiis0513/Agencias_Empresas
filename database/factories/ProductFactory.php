<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories.Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);

        return [
            'store_id' => Store::factory(),
            'category_id' => Category::factory(),
            'name' => $name,
            'barcode' => $this->faker->ean13(),
            'sku' => strtoupper($this->faker->unique()->bothify('SKU-####')),
            'image_path' => null,
            'price' => $this->faker->randomFloat(2, 1, 500),
            'cost' => $this->faker->randomFloat(2, 1, 300),
            'stock' => $this->faker->numberBetween(0, 100),
            'location' => $this->faker->optional()->lexify('A-##'),
            'type' => 'simple',
            'is_active' => true,
            'in_showcase' => true,
        ];
    }

    /**
     * Estado para productos por lote/variantes (tipo variable).
     */
    public function variable(): self
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'variable',
                'stock' => 0,
            ];
        });
    }
}

