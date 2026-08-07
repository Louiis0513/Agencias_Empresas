<?php

namespace App\Http\Requests;

use App\Models\Impuesto;
use App\Models\ListaPrecio;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $store = $this->route('store');
        $tipo = $this->input('tipo', Product::TIPO_PRODUCTO);
        /** @var Product|null $product */
        $product = $this->route('product');
        $isUpdate = $product instanceof Product;

        $codigoUnique = Rule::unique('products', 'codigo')->where('store_id', $store->id);
        if ($isUpdate) {
            $codigoUnique = $codigoUnique->ignore($product->id);
        }

        $maxImagenes = Product::MAX_IMAGENES;
        if ($isUpdate) {
            $existentes = (int) $product->images()->count();
            $maxImagenes = max(0, Product::MAX_IMAGENES - $existentes);
        }

        return [
            'tipo' => ['required', 'string', Rule::in(Product::TIPOS)],
            'categoria_contable_id' => [
                'required',
                'integer',
                Rule::exists('categorias_contables', 'id')
                    ->where('store_id', $store->id)
                    ->where('activo', true)
                    ->where('tipo', $tipo),
            ],
            'codigo' => [
                'required',
                'string',
                'min:1',
                'max:30',
                $codigoUnique,
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'codigo_barras' => ['nullable', 'string', 'max:64'],
            'unidad_medida_dian' => [
                'required',
                'string',
                'max:40',
                Rule::exists('unidades_medida_fe', 'codigo'),
            ],
            'es_inventariable' => ['nullable', 'boolean'],
            'visible_en_ventas' => ['nullable', 'boolean'],
            'impuesto_cargo_id' => [
                'nullable',
                'integer',
                Rule::exists('impuestos', 'id')
                    ->where('store_id', $store->id)
                    ->where('en_uso', true)
                    ->whereIn('tipo', [Impuesto::TIPO_IVA, Impuesto::TIPO_IMPOCONSUMO]),
            ],
            'impuesto_retencion_id' => [
                'nullable',
                'integer',
                Rule::exists('impuestos', 'id')
                    ->where('store_id', $store->id)
                    ->where('en_uso', true)
                    ->where('tipo', Impuesto::TIPO_RETEFUENTE),
            ],
            'valor_impuesto_cargo' => ['nullable', 'numeric', 'min:0'],
            'aplica_impuesto_bolsas' => ['nullable', 'boolean'],
            'referencia' => ['nullable', 'string', 'max:120'],
            'unidad_medida_factura' => ['nullable', 'string', 'max:60'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'marca' => ['nullable', 'string', 'max:120'],
            'modelo' => ['nullable', 'string', 'max:120'],
            'codigo_arancelario' => ['nullable', 'string', 'max:30'],
            'precio_incluye_iva' => ['nullable', 'boolean'],
            'precios' => ['nullable', 'array'],
            'precios.*' => ['nullable', 'numeric', 'min:0'],
            'imagenes' => ['nullable', 'array', 'max:'.$maxImagenes],
            'imagenes.*' => ['nullable', 'image', 'mimes:jpeg,jpg,png', 'max:'.Product::MAX_IMAGEN_KB],
            'guardar_y_nuevo' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['es_inventariable', 'visible_en_ventas', 'precio_incluye_iva', 'aplica_impuesto_bolsas', 'guardar_y_nuevo'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        foreach (['codigo_barras', 'referencia', 'descripcion', 'marca', 'modelo', 'codigo_arancelario', 'impuesto_cargo_id', 'impuesto_retencion_id', 'stock_minimo', 'valor_impuesto_cargo'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if (! $this->filled('unidad_medida_factura')) {
            $this->merge(['unidad_medida_factura' => 'unidad']);
        }

        if (! $this->filled('unidad_medida_dian')) {
            $this->merge(['unidad_medida_dian' => Product::UNIDAD_MEDIDA_DIAN_DEFAULT]);
        }
    }

    public function messages(): array
    {
        return [
            'tipo.required' => 'Debes indicar si es producto o servicio.',
            'categoria_contable_id.required' => 'La categoría es obligatoria.',
            'categoria_contable_id.exists' => 'La categoría no es válida para este tipo.',
            'codigo.required' => 'El código es obligatorio.',
            'codigo.unique' => 'Ya existe un ítem con ese código en la tienda.',
            'codigo.max' => 'El código admite máximo 30 caracteres.',
            'nombre.required' => 'El nombre es obligatorio.',
            'unidad_medida_dian.required' => 'La unidad de medida DIAN es obligatoria.',
            'unidad_medida_dian.exists' => 'La unidad de medida DIAN no es válida.',
            'valor_impuesto_cargo.required' => 'El valor del impuesto cargo es obligatorio cuando el impuesto es por valor.',
            'imagenes.max' => 'Máximo '.Product::MAX_IMAGENES.' imágenes por producto o servicio.',
            'imagenes.*.max' => 'Cada imagen debe pesar máximo 1 MB.',
            'imagenes.*.mimes' => 'Las imágenes deben ser PNG o JPG.',
            'imagenes.*.image' => 'El archivo debe ser una imagen válida (PNG o JPG).',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $store = $this->route('store');

            $cargoId = $this->input('impuesto_cargo_id');
            if ($cargoId) {
                $cargo = Impuesto::query()
                    ->deStore($store)
                    ->whereKey((int) $cargoId)
                    ->first(['id', 'por_valor']);

                if ($cargo?->por_valor) {
                    if ($this->input('valor_impuesto_cargo') === null || $this->input('valor_impuesto_cargo') === '') {
                        $validator->errors()->add(
                            'valor_impuesto_cargo',
                            'El valor del impuesto cargo es obligatorio cuando el impuesto es por valor.'
                        );
                    }
                }
            }

            $precios = $this->input('precios', []);
            if (! is_array($precios) || $precios === []) {
                return;
            }

            $ids = array_map('intval', array_keys($precios));
            $validos = ListaPrecio::query()
                ->deStore($store)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->all();

            foreach ($ids as $listaId) {
                if (! in_array($listaId, $validos, true)) {
                    $validator->errors()->add('precios.'.$listaId, 'La lista de precios no pertenece a esta tienda.');
                }
            }
        });
    }
}
