<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConteoFisicoRequest extends FormRequest
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
            'lineas.*.cantidad_contada' => ['required', 'numeric', 'gte:0'],
            'lineas.*.descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha del conteo físico es obligatoria.',
            'lineas.required' => 'Debe agregar al menos una línea de producto.',
            'lineas.min' => 'Debe agregar al menos una línea de producto.',
            'lineas.*.product_id.required' => 'Cada línea debe tener un producto.',
            'lineas.*.cantidad_contada.gte' => 'Las existencias contadas no pueden ser negativas.',
        ];
    }
}
