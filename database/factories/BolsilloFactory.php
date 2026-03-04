<?php

namespace Database\Factories;

use App\Models\Bolsillo;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Bolsillo>
 */
class BolsilloFactory extends Factory
{
    protected $model = Bolsillo::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'name' => $this->faker->words(2, true),
            'detalles' => $this->faker->optional()->sentence(),
            'saldo' => 0,
            'is_bank_account' => $this->faker->boolean(30),
            'is_active' => true,
        ];
    }
}

