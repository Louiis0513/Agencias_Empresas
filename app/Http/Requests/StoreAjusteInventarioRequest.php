<?php

namespace App\Http\Requests;

use App\Models\DocumentoInventarioLinea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAjusteInventarioRequest extends FormRequest
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
            'lineas.*.cuenta_contable_id' => ['required', 'integer'],
            'lineas.*.direccion' => [
                'required',
                'string',
                Rule::in([
                    DocumentoInventarioLinea::DIRECCION_AUMENTA,
                    DocumentoInventarioLinea::DIRECCION_DISMINUYE,
                ]),
            ],
            'lineas.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'lineas.*.costo_unitario' => ['required', 'numeric', 'gt:0'],
            'lineas.*.descripcion' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha.required' => 'La fecha del ajuste es obligatoria.',
            'lineas.required' => 'Debe agregar al menos una línea de producto.',
            'lineas.min' => 'Debe agregar al menos una línea de producto.',
            'lineas.*.product_id.required' => 'Cada línea debe tener un producto.',
            'lineas.*.cuenta_contable_id.required' => 'Cada línea debe tener cuenta contable de contrapartida.',
            'lineas.*.direccion.required' => 'Indica si cada línea aumenta o disminuye.',
            'lineas.*.direccion.in' => 'La dirección debe ser Aumenta o Disminuye.',
            'lineas.*.cantidad.gt' => 'La cantidad debe ser mayor a 0.',
            'lineas.*.costo_unitario.gt' => 'El costo unitario debe ser mayor a 0.',
        ];
    }
}
