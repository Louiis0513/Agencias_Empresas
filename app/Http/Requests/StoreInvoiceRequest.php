<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
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
            'customer_id' => [
                'nullable',
                Rule::exists('customers', 'id')->where('store_id', $store->id),
            ],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax' => ['sometimes', 'numeric', 'min:0'],
            'discount' => ['sometimes', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'string', 'in:PAID,PENDING,VOID'],
            'payment_method' => ['required', 'string', 'in:CASH,CARD,TRANSFER'],
            // Validación del array de items/details
            'details' => ['required', 'array', 'min:1'],
            'details.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where('store_id', $store->id),
            ],
            'details.*.quantity' => ['required', 'integer', 'min:1'],
            'details.*.unit_price' => ['required', 'numeric', 'min:0'],
            'details.*.subtotal' => ['required', 'numeric', 'min:0'],
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
            'customer_id.exists' => 'El cliente especificado no existe en esta tienda.',
            'subtotal.required' => 'El subtotal es obligatorio.',
            'subtotal.numeric' => 'El subtotal debe ser un número.',
            'subtotal.min' => 'El subtotal no puede ser negativo.',
            'tax.numeric' => 'El impuesto debe ser un número.',
            'tax.min' => 'El impuesto no puede ser negativo.',
            'discount.numeric' => 'El descuento debe ser un número.',
            'discount.min' => 'El descuento no puede ser negativo.',
            'total.required' => 'El total es obligatorio.',
            'total.numeric' => 'El total debe ser un número.',
            'total.min' => 'El total no puede ser negativo.',
            'status.required' => 'El estado es obligatorio.',
            'status.in' => 'El estado debe ser PAID, PENDING o VOID.',
            'payment_method.required' => 'El método de pago es obligatorio.',
            'payment_method.in' => 'El método de pago debe ser CASH, CARD o TRANSFER.',
            'details.required' => 'Debe incluir al menos un detalle.',
            'details.min' => 'Debe incluir al menos un detalle.',
            'details.*.product_id.required' => 'El producto es obligatorio para cada detalle.',
            'details.*.product_id.exists' => 'Uno de los productos especificados no existe en esta tienda.',
            'details.*.quantity.required' => 'La cantidad es obligatoria para cada detalle.',
            'details.*.quantity.integer' => 'La cantidad debe ser un número entero.',
            'details.*.quantity.min' => 'La cantidad debe ser al menos 1.',
            'details.*.unit_price.required' => 'El precio unitario es obligatorio para cada detalle.',
            'details.*.unit_price.numeric' => 'El precio unitario debe ser un número.',
            'details.*.unit_price.min' => 'El precio unitario no puede ser negativo.',
            'details.*.subtotal.required' => 'El subtotal es obligatorio para cada detalle.',
            'details.*.subtotal.numeric' => 'El subtotal debe ser un número.',
            'details.*.subtotal.min' => 'El subtotal no puede ser negativo.',
        ];
    }
}
