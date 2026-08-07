<?php

namespace Database\Factories;

use App\Models\CategoriaContable;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'categoria_contable_id' => null,
            'tipo' => Product::TIPO_PRODUCTO,
            'codigo' => strtoupper($this->faker->unique()->bothify('P-####??')),
            'nombre' => $this->faker->unique()->words(3, true),
            'unidad_medida_dian' => Product::UNIDAD_MEDIDA_DIAN_DEFAULT,
            'es_inventariable' => true,
            'visible_en_ventas' => true,
            'unidad_medida_factura' => 'unidad',
            'precio_incluye_iva' => false,
            'is_active' => true,
        ];
    }

    public function servicio(): static
    {
        return $this->state(fn () => [
            'tipo' => Product::TIPO_SERVICIO,
            'es_inventariable' => false,
            'stock_minimo' => null,
            'marca' => null,
            'modelo' => null,
            'codigo_arancelario' => null,
        ]);
    }

    public function conCategoria(CategoriaContable $categoria): static
    {
        return $this->state(fn () => [
            'categoria_contable_id' => $categoria->id,
            'tipo' => $categoria->tipo === CategoriaContable::TIPO_SERVICIO
                ? Product::TIPO_SERVICIO
                : Product::TIPO_PRODUCTO,
            'es_inventariable' => $categoria->tipo !== CategoriaContable::TIPO_SERVICIO,
        ]);
    }
}
