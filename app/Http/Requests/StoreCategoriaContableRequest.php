<?php

namespace App\Http\Requests;

use App\Models\CategoriaContable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoriaContableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'codigo' => ['nullable', 'string', 'max:32'],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', Rule::in(CategoriaContable::TIPOS)],
            'cuenta_inventario_id' => ['nullable', 'integer', 'exists:cuentas_contables,id'],
            'cuenta_costo_id' => ['nullable', 'integer', 'exists:cuentas_contables,id'],
            'cuenta_ingreso_id' => ['required', 'integer', 'exists:cuentas_contables,id'],
            'cuenta_devolucion_id' => ['required', 'integer', 'exists:cuentas_contables,id'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('activo')) {
            $this->merge(['activo' => $this->boolean('activo')]);
        }

        foreach (['cuenta_inventario_id', 'cuenta_costo_id', 'cuenta_ingreso_id', 'cuenta_devolucion_id', 'codigo'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'tipo.required' => 'Debes indicar si es producto o servicio.',
            'tipo.in' => 'El tipo debe ser producto o servicio.',
            'cuenta_ingreso_id.required' => 'Debes seleccionar la cuenta de ingreso.',
            'cuenta_devolucion_id.required' => 'Debes seleccionar la cuenta de devoluciones.',
        ];
    }
}
