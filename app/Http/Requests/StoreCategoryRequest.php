<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'attribute_group_ids' => ['nullable', 'array'],
            'attribute_group_ids.*' => ['integer', 'exists:attribute_groups,id'],
        ];
    }
}
