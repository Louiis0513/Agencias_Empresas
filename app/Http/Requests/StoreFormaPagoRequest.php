<?php

namespace App\Http\Requests;

use App\Models\FormaPago;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormaPagoRequest extends FormRequest
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
            'aplica_a' => ['required', 'string', Rule::in(FormaPago::APLICA_A)],
            'cuenta_contable_id' => [
                'required',
                'integer',
                Rule::exists('cuentas_contables', 'id')->where('store_id', $store->id),
            ],
            'medio_pago_dian' => [
                'nullable',
                'string',
                Rule::in(array_keys(FormaPago::MEDIOS_PAGO_DIAN)),
            ],
            'es_pago_en_linea' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['en_uso', 'es_pago_en_linea'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        if ($this->input('codigo') === '') {
            $this->merge(['codigo' => null]);
        }

        if ($this->input('medio_pago_dian') === '') {
            $this->merge(['medio_pago_dian' => null]);
        }
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre de la forma de pago es obligatorio.',
            'aplica_a.required' => 'Debes seleccionar a quién aplica la forma de pago.',
            'aplica_a.in' => 'El alcance «aplica a» no es válido.',
            'cuenta_contable_id.required' => 'Debes seleccionar la cuenta contable.',
            'medio_pago_dian.in' => 'El medio de pago DIAN no es válido.',
        ];
    }
}
