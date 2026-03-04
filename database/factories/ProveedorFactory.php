<?php

namespace Database\Factories;

use App\Models\Proveedor;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Proveedor>
 */
class ProveedorFactory extends Factory
{
    protected $model = Proveedor::class;

    public function definition(): array
    {
        $company = $this->faker->company();

        return [
            'store_id' => Store::factory(),
            'nombre' => $company,
            'numero_celular' => $this->faker->optional()->numerify('+57##########'),
            'telefono' => $this->faker->optional()->phoneNumber(),
            'email' => $this->faker->optional()->unique()->companyEmail(),
            'nit' => $this->faker->numerify('#########-#'),
            'direccion' => $this->faker->optional()->address(),
            'estado' => true,
        ];
    }
}

