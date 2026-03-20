<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attribute_values' => ['nullable', 'array'],
            'attribute_values.*' => ['nullable'],
            'product_variant_id' => ['nullable', 'integer'],
            'price' => ['nullable'],
            'margin' => ['nullable', 'numeric'],
            'cost_reference' => ['nullable'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'attribute_group_ids' => ['nullable', 'array'],
            'attribute_group_ids.*' => ['integer', 'exists:attribute_groups,id'],
            'variant_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'remove_variant_image' => ['nullable', 'boolean'],
        ];
    }
}
