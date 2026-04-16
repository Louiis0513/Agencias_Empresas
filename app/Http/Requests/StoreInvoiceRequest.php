<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Support\Quantity;
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
            'payment_method' => ['nullable', 'string', 'in:CASH,CARD,TRANSFER,MIXED'],
            // Validación del array de items/details
            'details' => ['required', 'array', 'min:1'],
            'details.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where('store_id', $store->id),
            ],
            'details.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'details.*.unit_price' => ['required', 'numeric', 'min:0'],
            'details.*.discount_type' => ['sometimes', 'string', 'in:amount,percent'],
            'details.*.discount_value' => ['sometimes', 'numeric', 'min:0'],
            'details.*.discount_amount' => ['sometimes', 'numeric', 'min:0'],
            'details.*.subtotal_before_discount' => ['sometimes', 'numeric', 'min:0'],
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
            'payment_method.in' => 'El método de pago debe ser CASH, CARD, TRANSFER o MIXED.',
            'details.required' => 'Debe incluir al menos un detalle.',
            'details.min' => 'Debe incluir al menos un detalle.',
            'details.*.product_id.required' => 'El producto es obligatorio para cada detalle.',
            'details.*.product_id.exists' => 'Uno de los productos especificados no existe en esta tienda.',
            'details.*.quantity.required' => 'La cantidad es obligatoria para cada detalle.',
            'details.*.quantity.numeric' => 'La cantidad debe ser un número válido.',
            'details.*.quantity.min' => 'La cantidad debe ser mayor que 0.',
            'details.*.unit_price.required' => 'El precio unitario es obligatorio para cada detalle.',
            'details.*.unit_price.numeric' => 'El precio unitario debe ser un número.',
            'details.*.unit_price.min' => 'El precio unitario no puede ser negativo.',
            'details.*.discount_type.in' => 'El tipo de descuento por detalle debe ser amount o percent.',
            'details.*.discount_value.numeric' => 'El valor de descuento por detalle debe ser numérico.',
            'details.*.discount_value.min' => 'El valor de descuento por detalle no puede ser negativo.',
            'details.*.discount_amount.numeric' => 'El monto de descuento por detalle debe ser numérico.',
            'details.*.discount_amount.min' => 'El monto de descuento por detalle no puede ser negativo.',
            'details.*.subtotal_before_discount.numeric' => 'El subtotal bruto por detalle debe ser numérico.',
            'details.*.subtotal_before_discount.min' => 'El subtotal bruto por detalle no puede ser negativo.',
            'details.*.subtotal.required' => 'El subtotal es obligatorio para cada detalle.',
            'details.*.subtotal.numeric' => 'El subtotal debe ser un número.',
            'details.*.subtotal.min' => 'El subtotal no puede ser negativo.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $store = $this->route('store');
            $details = $this->input('details', []);
            if (! $store || ! is_array($details) || empty($details)) {
                return;
            }

            $productIds = collect($details)->pluck('product_id')->filter()->unique()->values()->all();
            if (empty($productIds)) {
                return;
            }

            $products = Product::where('store_id', $store->id)
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            foreach ($details as $index => $detail) {
                $productId = (int) ($detail['product_id'] ?? 0);
                $qty = $detail['quantity'] ?? null;
                if ($productId < 1 || $qty === null || ! isset($products[$productId])) {
                    continue;
                }

                $product = $products[$productId];
                $mode = $product->isSerialized() ? Product::QUANTITY_MODE_UNIT : ($product->quantity_mode ?? Product::QUANTITY_MODE_UNIT);
                if (! Quantity::isValidForMode($qty, $mode, false)) {
                    $validator->errors()->add("details.{$index}.quantity", $mode === Product::QUANTITY_MODE_DECIMAL
                        ? 'La cantidad debe ser decimal con máximo 2 decimales (mínimo 0.01).'
                        : 'La cantidad debe ser un entero mayor o igual a 1 para este producto.');
                }
            }
        });
    }
}
