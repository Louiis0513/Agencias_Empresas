<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreComprobanteIngresoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.bolsillo_id' => ['required', 'exists:bolsillos,id'],
            'parts.*.amount' => ['required', 'numeric', 'min:0.01'],
            'parts.*.reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $parts = $this->input('parts', []);
            $sumaPartes = collect($parts)->sum(fn ($p) => (float) ($p['amount'] ?? 0));
            if ($sumaPartes <= 0) {
                $validator->errors()->add('parts', 'Indique al menos un bolsillo con monto mayor a cero.');
            }
        });
    }
}
