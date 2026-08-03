<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCentroCostoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $store = $this->route('store');

        return [
            'codigo' => ['required', 'string', 'max:32'],
            'nombre' => ['required', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:centros_costo,id',
            ],
            'es_subcentro' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['activo', 'es_subcentro'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        if ($this->input('parent_id') === '') {
            $this->merge(['parent_id' => null]);
        }
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'El código es obligatorio.',
            'nombre.required' => 'El nombre es obligatorio.',
            'parent_id.exists' => 'El centro padre no es válido.',
        ];
    }
}
