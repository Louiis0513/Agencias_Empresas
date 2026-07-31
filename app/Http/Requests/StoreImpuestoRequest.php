<?php

namespace App\Http\Requests;

use App\Models\Impuesto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreImpuestoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $store = $this->route('store');

        return [
            'en_uso' => ['nullable', 'boolean'],
            'codigo' => ['nullable', 'integer', 'min:1'],
            'nombre' => ['required', 'string', 'max:255'],
            'tipo' => ['required', 'string', Rule::in(Impuesto::TIPOS)],
            'por_valor' => ['nullable', 'boolean'],
            'tarifa' => ['required', 'numeric', 'min:0', 'max:9999.9999'],
            'cuenta_ventas_id' => [
                'required',
                'integer',
                Rule::exists('cuentas_contables', 'id')->where('store_id', $store->id),
            ],
            'cuenta_compras_id' => [
                'required',
                'integer',
                Rule::exists('cuentas_contables', 'id')->where('store_id', $store->id),
            ],
            'cuenta_devolucion_ventas_id' => [
                'required',
                'integer',
                Rule::exists('cuentas_contables', 'id')->where('store_id', $store->id),
            ],
            'cuenta_devolucion_compras_id' => [
                'required',
                'integer',
                Rule::exists('cuentas_contables', 'id')->where('store_id', $store->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['en_uso', 'por_valor'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        if ($this->input('codigo') === '') {
            $this->merge(['codigo' => null]);
        }
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del impuesto es obligatorio.',
            'tipo.required' => 'Debes seleccionar el tipo de impuesto.',
            'tipo.in' => 'El tipo de impuesto no es válido.',
            'tarifa.required' => 'La tarifa es obligatoria.',
            'cuenta_ventas_id.required' => 'Debes seleccionar la cuenta de ventas.',
            'cuenta_compras_id.required' => 'Debes seleccionar la cuenta de compras.',
            'cuenta_devolucion_ventas_id.required' => 'Debes seleccionar la cuenta de devolución de ventas.',
            'cuenta_devolucion_compras_id.required' => 'Debes seleccionar la cuenta de devolución de compras.',
        ];
    }
}
