<?php

namespace Database\Factories;

use App\Models\AttributeGroup;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AttributeGroup>
 */
class AttributeGroupFactory extends Factory
{
    protected $model = AttributeGroup::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => $this->faker->words(2, true),
            'position' => $this->faker->numberBetween(1, 50),
        ];
    }
}

