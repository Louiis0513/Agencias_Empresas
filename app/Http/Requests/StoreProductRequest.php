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
            'attribute_option_ids' => ['nullable', 'array'],
            'attribute_option_ids.*' => ['integer', 'exists:attribute_options,id'],
            'attribute_values' => ['nullable', 'array'],
            'attribute_values.*' => ['nullable'],
            'product_variant_id' => ['nullable', 'integer'],
            'price' => ['nullable'],
            'cost_reference' => ['nullable'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'attribute_group_ids' => ['nullable', 'array'],
            'attribute_group_ids.*' => ['integer', 'exists:attribute_groups,id'],
        ];
    }
}
