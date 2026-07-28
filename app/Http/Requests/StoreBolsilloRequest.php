<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBolsilloRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'detalles' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];

        if ($this->isMethod('post')) {
            $rules['saldo'] = ['nullable', 'numeric', 'min:0'];
            $rules['tipo_disponible'] = ['nullable', Rule::in(['efectivo', 'corriente_cop', 'ahorro', 'divisas'])];
            $rules['is_bank_account'] = ['nullable', 'boolean']; // legacy fallback
        }

        return $rules;
    }
}
