<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaldosInicialesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fecha' => ['required', 'date'],
            'tercero_nombre' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string', 'max:5000'],
            'lineas' => ['required', 'array', 'min:1'],
            'lineas.*.product_id' => ['required', 'integer'],
            'lineas.*.bodega_id' => ['nullable', 'integer'],
            'lineas.*.centro_costo_id' => ['nullable', 'integer'],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'lineas.*.costo_unitario' => ['required', 'numeric', 'gt:0'],
            'lineas.*.descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha de saldos iniciales es obligatoria.',
            'lineas.required' => 'Debe agregar al menos una línea de producto.',
            'lineas.min' => 'Debe agregar al menos una línea de producto.',
            'lineas.*.product_id.required' => 'Cada línea debe tener un producto.',
            'lineas.*.cantidad.gt' => 'La cantidad debe ser mayor a 0.',
            'lineas.*.costo_unitario.gt' => 'El costo unitario debe ser mayor a 0.',
        ];
    }
}
