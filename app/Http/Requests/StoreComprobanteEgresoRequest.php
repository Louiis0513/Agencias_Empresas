<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComprobanteEgresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'destinos' => ['required', 'array', 'min:1'],
            'destinos.*.amount' => ['required', 'numeric', 'min:0.01'],
            'destinos.*.account_payable_id' => ['nullable', 'exists:accounts_payables,id'],
            'destinos.*.concepto' => ['nullable', 'string', 'max:255'],
            'destinos.*.beneficiario' => ['nullable', 'string', 'max:255'],
            'origenes' => ['required', 'array', 'min:1'],
            'origenes.*.bolsillo_id' => ['required', 'exists:bolsillos,id'],
            'origenes.*.amount' => ['required', 'numeric', 'min:0.01'],
            'origenes.*.reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $destinos = $this->input('destinos', []);
            foreach ($destinos as $i => $d) {
                $hasAccountPayable = ! empty($d['account_payable_id'] ?? null);
                $hasConcepto = ! empty(trim($d['concepto'] ?? ''));
                if (! $hasAccountPayable && ! $hasConcepto) {
                    $validator->errors()->add("destinos.{$i}.concepto", 'El concepto es requerido cuando no hay cuenta por pagar.');
                }
            }

            $origenes = $this->input('origenes', []);
            $sumaOrigenes = collect($origenes)->sum(fn ($p) => (float) ($p['amount'] ?? 0));
            $sumaDestinos = collect($destinos)->sum(fn ($p) => (float) ($p['amount'] ?? 0));

            if (abs($sumaOrigenes - $sumaDestinos) > 0.01) {
                $validator->errors()->add('origenes', 'La suma de los montos de origen debe coincidir con la suma de los montos de destino.');
            }
        });
    }
}
