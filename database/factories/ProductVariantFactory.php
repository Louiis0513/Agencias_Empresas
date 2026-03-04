<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->variable(),
            'features' => [
                // Valores genéricos; en los seeders de demo se ajustarán
                (string) $this->faker->numberBetween(1, 10) => $this->faker->randomElement(['S', 'M', 'L']),
                (string) $this->faker->numberBetween(11, 20) => $this->faker->safeColorName(),
            ],
            'cost_reference' => $this->faker->randomFloat(2, 1, 300),
            'price' => $this->faker->randomFloat(2, 1, 500),
            'barcode' => $this->faker->ean13(),
            'sku' => strtoupper($this->faker->unique()->bothify('VAR-####')),
            'image_path' => null,
            'is_active' => true,
            'in_showcase' => true,
        ];
    }
}

