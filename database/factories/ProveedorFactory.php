<?php

namespace Database\Factories;

use App\Models\Proveedor;
use App\Models\Store;
use App\Models\Tercero;
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
            'tipo_persona' => Tercero::TIPO_PERSONA_JURIDICA,
            'tipo_identificacion' => Tercero::ID_NIT,
            'numero_identificacion' => $this->faker->numerify('#########'),
            'nombre' => $company,
            'telefono' => $this->faker->optional()->numerify('+57##########'),
            'telefono_secundario' => $this->faker->optional()->phoneNumber(),
            'email' => $this->faker->optional()->unique()->companyEmail(),
            'direccion' => $this->faker->optional()->address(),
            'activo' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Proveedor $proveedor) {
            if (! $proveedor->roles()->where('rol', Tercero::ROL_PROVEEDOR)->exists()) {
                $proveedor->roles()->create([
                    'rol' => Tercero::ROL_PROVEEDOR,
                    'activo' => true,
                ]);
            }
            if (! $proveedor->perfilProveedor) {
                $proveedor->perfilProveedor()->create([
                    'preferido' => false,
                ]);
            }
        });
    }
}
