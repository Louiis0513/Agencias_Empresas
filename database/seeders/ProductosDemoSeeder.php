<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\AttributeGroup;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $storeId = 1;

        $this->seedProductosSimples($storeId);
        $this->seedProductosPorLoteConVariantes($storeId);
    }

    /**
     * Crea productos simples de limpieza con atributos comunes.
     */
    private function seedProductosSimples(int $storeId): void
    {
        // Reutilizamos la categoría "Abarrotes" y el grupo de atributos "Abarrotes"
        // definidos en DemoDataSeeder para mantener coherencia.
        $categoriaAbarrotes = Category::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Abarrotes'],
            ['parent_id' => null]
        );

        $grupoAbarrotes = AttributeGroup::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Abarrotes'],
            ['position' => 1]
        );

        // Atributos exclusivos del grupo Abarrotes (coherentes con DemoDataSeeder)
        $atributoMarca = Attribute::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Marca Abarrotes'],
            ['is_required' => false]
        );

        $atributoMaterial = Attribute::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Material Abarrotes'],
            ['is_required' => false]
        );

        $atributoColor = Attribute::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Color Abarrotes'],
            ['is_required' => false]
        );

        // Vincular atributos al grupo de Abarrotes
        $grupoAbarrotes->attributes()->syncWithoutDetaching([
            $atributoMarca->id => ['position' => 1, 'is_required' => false],
            $atributoColor->id => ['position' => 2, 'is_required' => false],
            $atributoMaterial->id => ['position' => 3, 'is_required' => false],
        ]);

        // Vincular atributos a la categoría Abarrotes
        $categoriaAbarrotes->attributes()->syncWithoutDetaching([
            $atributoMarca->id => ['position' => 1, 'is_required' => false],
            $atributoColor->id => ['position' => 2, 'is_required' => false],
            $atributoMaterial->id => ['position' => 3, 'is_required' => false],
        ]);

        $nombresProductos = [
            'Jabón',
            'Escoba',
            'Trapero',
            'Balde',
            'Cepillo de ropa',
            'Guantes de limpieza',
            'Toalla de microfibra',
            'Plumero',
            'Recogedor',
            'Esponja',
            'Ambientador',
            'Detergente líquido',
            'Cloro',
            'Bolsa de basura',
            'Paño de cocina',
            'Servilletas de papel',
            'Papel higiénico',
            'Shampoo',
            'Acondicionador',
            'Crema corporal',
            'Pasta dental',
            'Cepillo dental',
            'Enjuague bucal',
            'Rasuradora',
            'Peine',
            'Gel para cabello',
            'Alcohol antiséptico',
            'Desinfectante multiusos',
            'Limpiavidrios',
            'Lavaloza',
        ];

        $marcas = ['Genérico', 'Premium', 'Eco', 'MaxClean', 'HomeCare'];
        $materiales = ['Plástico', 'Madera', 'Algodón', 'Sintético', 'Papel'];
        $colores = ['Rojo', 'Azul', 'Verde', 'Blanco', 'Amarillo'];

        foreach ($nombresProductos as $index => $nombre) {
            $product = Product::create([
                'store_id'    => $storeId,
                'category_id' => $categoriaAbarrotes->id,
                'name'        => $nombre,
                'barcode'     => null,
                'sku'         => 'SIM-' . strtoupper(Str::slug($nombre)) . '-' . ($index + 1),
                'price'       => rand(1000, 20000) / 100,
                'cost'        => rand(500, 15000) / 100,
                // Sin stock inicial para entorno de demo
                'stock'       => 0,
                'location'    => 'A-' . rand(1, 10),
                'type'        => 'simple',
                'is_active'   => true,
                // Visibles en vitrina por defecto
                'in_showcase' => true,
            ]);

            ProductAttributeValue::create([
                'product_id'   => $product->id,
                'attribute_id' => $atributoMarca->id,
                'value'        => $marcas[array_rand($marcas)],
            ]);

            ProductAttributeValue::create([
                'product_id'   => $product->id,
                'attribute_id' => $atributoMaterial->id,
                'value'        => $materiales[array_rand($materiales)],
            ]);

            ProductAttributeValue::create([
                'product_id'   => $product->id,
                'attribute_id' => $atributoColor->id,
                'value'        => $colores[array_rand($colores)],
            ]);
        }
    }

    /**
     * Crea productos por lote con variantes usando el campo JSON features de ProductVariant.
     */
    private function seedProductosPorLoteConVariantes(int $storeId): void
    {
        // Reutilizamos la categoría "Ropa" y el grupo "Para Ropa"
        // definidos en DemoDataSeeder para mantener la misma estructura.
        $categoriaRopa = Category::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Ropa'],
            ['parent_id' => null]
        );

        $grupoRopa = AttributeGroup::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Para Ropa'],
            ['position' => 2]
        );

        // Atributos exclusivos del grupo "Para Ropa" (coherentes con DemoDataSeeder)
        $attrMarca = Attribute::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Marca Ropa'],
            ['is_required' => false]
        );

        $attrTalla = Attribute::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Talla Ropa'],
            ['is_required' => false]
        );

        $attrMaterial = Attribute::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Material Ropa'],
            ['is_required' => false]
        );

        $attrColor = Attribute::firstOrCreate(
            ['store_id' => $storeId, 'name' => 'Color Ropa'],
            ['is_required' => false]
        );

        // Vincular atributos al grupo "Para Ropa"
        $grupoRopa->attributes()->syncWithoutDetaching([
            $attrMarca->id => ['position' => 1, 'is_required' => false],
            $attrTalla->id => ['position' => 2, 'is_required' => false],
            $attrMaterial->id => ['position' => 3, 'is_required' => false],
            $attrColor->id => ['position' => 4, 'is_required' => false],
        ]);

        // Vincular atributos a la categoría "Ropa"
        $categoriaRopa->attributes()->syncWithoutDetaching([
            $attrMarca->id => ['position' => 1, 'is_required' => false],
            $attrTalla->id => ['position' => 2, 'is_required' => false],
            $attrMaterial->id => ['position' => 3, 'is_required' => false],
            $attrColor->id => ['position' => 4, 'is_required' => false],
        ]);

        $productosRopa = [
            [
                'nombre'      => 'Suéter',
                'colores'     => ['Rojo', 'Azul', 'Negro'],
                'tallas'      => ['S', 'M', 'L', 'XL'],
                'material'    => 'Algodón/Poliéster',
                'marca'       => 'UrbanWear',
                'precio_base' => 35.00,
            ],
            [
                'nombre'      => 'Blusa',
                'colores'     => ['Blanco', 'Azul', 'Verde'],
                'tallas'      => ['S', 'M', 'L'],
                'material'    => 'Algodón',
                'marca'       => 'FashionLine',
                'precio_base' => 25.00,
            ],
            [
                'nombre'      => 'Ropa interior',
                'colores'     => ['Negro', 'Blanco', 'Beige'],
                'tallas'      => ['S', 'M', 'L', 'XL'],
                'material'    => 'Algodón/Spandex',
                'marca'       => 'ComfortFit',
                'precio_base' => 15.00,
            ],
            [
                'nombre'      => 'Zapatos',
                'colores'     => ['Negro', 'Marrón', 'Blanco'],
                'tallas'      => ['38', '40', '42', '44'],
                'material'    => 'Cuero/Sintético',
                'marca'       => 'StepUp',
                'precio_base' => 60.00,
            ],
            [
                'nombre'      => 'Pantalón jeans',
                'colores'     => ['Azul claro', 'Azul oscuro', 'Negro'],
                'tallas'      => ['28', '30', '32', '34'],
                'material'    => 'Denim',
                'marca'       => 'ClassicDenim',
                'precio_base' => 40.00,
            ],
            [
                'nombre'      => 'Camiseta',
                'colores'     => ['Blanco', 'Negro', 'Gris'],
                'tallas'      => ['S', 'M', 'L', 'XL'],
                'material'    => 'Algodón',
                'marca'       => 'BasicStyle',
                'precio_base' => 20.00,
            ],
            [
                'nombre'      => 'Chaqueta',
                'colores'     => ['Negro', 'Azul', 'Verde'],
                'tallas'      => ['M', 'L', 'XL'],
                'material'    => 'Poliéster',
                'marca'       => 'StreetWear',
                'precio_base' => 70.00,
            ],
            [
                'nombre'      => 'Falda',
                'colores'     => ['Negro', 'Rojo', 'Azul'],
                'tallas'      => ['S', 'M', 'L'],
                'material'    => 'Algodón/Poliéster',
                'marca'       => 'ElegantFit',
                'precio_base' => 30.00,
            ],
            [
                'nombre'      => 'Shorts',
                'colores'     => ['Azul', 'Negro', 'Beige'],
                'tallas'      => ['S', 'M', 'L', 'XL'],
                'material'    => 'Algodón',
                'marca'       => 'ActiveWear',
                'precio_base' => 22.00,
            ],
            [
                'nombre'      => 'Vestido',
                'colores'     => ['Rojo', 'Negro', 'Azul'],
                'tallas'      => ['S', 'M', 'L'],
                'material'    => 'Algodón/Poliéster',
                'marca'       => 'GlamLine',
                'precio_base' => 55.00,
            ],
        ];

        foreach ($productosRopa as $i => $data) {
            $product = Product::create([
                'store_id'    => $storeId,
                'category_id' => $categoriaRopa->id,
                'name'        => $data['nombre'],
                'barcode'     => null,
                'sku'         => 'LOT-' . strtoupper(Str::slug($data['nombre'])) . '-' . ($i + 1),
                'price'       => $data['precio_base'],
                'cost'        => $data['precio_base'] * 0.6,
                'stock'       => 0,
                'location'    => 'R-' . rand(1, 10),
                'type'        => 'batch',
                'is_active'   => true,
                // Productos variables también visibles en vitrina
                'in_showcase' => true,
            ]);

            // Crear hasta 3 combinaciones de variantes por producto
            $combinaciones = [];
            foreach ($data['colores'] as $color) {
                foreach ($data['tallas'] as $talla) {
                    $combinaciones[] = [
                        'color' => $color,
                        'talla' => $talla,
                    ];
                }
            }
            $combinaciones = array_slice($combinaciones, 0, 3);

            foreach ($combinaciones as $idx => $comb) {
                $features = [
                    (string) $attrColor->id    => $comb['color'],
                    (string) $attrTalla->id    => $comb['talla'],
                    (string) $attrMaterial->id => $data['material'],
                    (string) $attrMarca->id    => $data['marca'],
                ];

                ProductVariant::create([
                    'product_id'     => $product->id,
                    'features'       => $features,
                    'cost_reference' => $data['precio_base'] * 0.6,
                    'price'          => $data['precio_base'] + ($idx * 2),
                    'barcode'        => null,
                    'sku'            => 'VAR-' . strtoupper(Str::slug($data['nombre'])) . '-' . ($idx + 1),
                    'image_path'     => null,
                    'is_active'      => true,
                    // Variantes visibles en vitrina por defecto
                    'in_showcase'    => true,
                ]);
            }
        }
    }
}

