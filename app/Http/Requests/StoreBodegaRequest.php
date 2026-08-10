<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBodegaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:32'],
            'nombre' => ['required', 'string', 'max:255'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('activo')) {
            $this->merge(['activo' => $this->boolean('activo')]);
        }
    }

    public function messages(): array
    {
        return [
            'codigo.required' => 'El código de la bodega es obligatorio.',
            'nombre.required' => 'El nombre de la bodega es obligatorio.',
        ];
    }
}
