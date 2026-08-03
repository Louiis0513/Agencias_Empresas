<?php

namespace App\Http\Requests;

use App\Models\CuentaContable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCuentaHijoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cuenta_padre_id' => ['required', 'integer', 'exists:cuentas_contables,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'sufijo' => ['nullable', 'string', 'max:2', 'regex:/^\d{1,2}$/'],
            'categoria' => ['nullable', 'string', 'max:80'],
            'clase' => ['nullable', 'string', 'max:80'],
            'relacion_con' => ['nullable', 'string', 'max:120'],
            'maneja_vencimientos' => ['nullable', 'string', 'max:80'],
            'diferencia_fiscal' => ['nullable', 'boolean'],
            'activo' => ['nullable', 'boolean'],
            'nivel_agrupacion' => ['nullable', 'string', 'max:40', Rule::in(['', CuentaContable::NIVEL_TRANSACCIONAL])],
            'confirmar_traslado' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['diferencia_fiscal', 'activo', 'confirmar_traslado'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        if ($this->input('nivel_agrupacion') === '') {
            $this->merge(['nivel_agrupacion' => null]);
        }
    }

    public function messages(): array
    {
        return [
            'cuenta_padre_id.required' => 'Debes elegir la cuenta padre.',
            'nombre.required' => 'El nombre es obligatorio.',
            'sufijo.regex' => 'El sufijo debe ser numérico (ej. 01).',
        ];
    }
}
