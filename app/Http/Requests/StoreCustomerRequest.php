<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $store = $this->route('store');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('customers', 'email')
                    ->where('store_id', $store->id)
                    ->whereNotNull('email')
                    ->ignore($this->route('customer')),
            ],
            'phone' => ['required', 'string', 'max:255'],
            'document_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('customers', 'document_number')
                    ->where('store_id', $store->id)
                    ->whereNotNull('document_number')
                    ->ignore($this->route('customer')),
            ],
            'address' => ['nullable', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del cliente es obligatorio.',
            'name.max' => 'El nombre no puede exceder 255 caracteres.',
            'email.required' => 'El email del cliente es obligatorio.',
            'email.email' => 'Debe ser un correo electrónico válido.',
            'email.unique' => 'Ya existe un cliente con este correo electrónico en esta tienda.',
            'phone.required' => 'El teléfono del cliente es obligatorio.',
            'phone.max' => 'El teléfono no puede exceder 255 caracteres.',
            'document_number.required' => 'El número de documento es obligatorio.',
            'document_number.unique' => 'Ya existe un cliente con este número de documento en esta tienda.',
        ];
    }
}
