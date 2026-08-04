<?php

namespace App\Http\Requests;

use App\Models\ComprobanteIngreso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReciboCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $store = $this->route('store');
        $modo = $this->input('modo');

        $rules = [
            'modo' => ['required', 'string', Rule::in(ComprobanteIngreso::MODOS)],
            'tipo_comprobante_id' => [
                'required',
                'integer',
                Rule::exists('tipos_comprobante', 'id')->where(
                    fn ($q) => $q->where('store_id', $store->id)->where('familia', 'RC')->where('activo', true)
                ),
            ],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'valor_recibido' => ['required', 'numeric', 'min:0.01'],
            'bolsillo_id' => [
                'required',
                'integer',
                Rule::exists('bolsillos', 'id')->where(
                    fn ($q) => $q->where('store_id', $store->id)->where('is_active', true)->whereNotNull('cuenta_contable_id')
                ),
            ],
            'centro_costo_id' => [
                'nullable',
                'integer',
                Rule::exists('centros_costo', 'id')->where(fn ($q) => $q->where('store_id', $store->id)),
            ],
            'tercero_id' => [
                Rule::requiredIf(in_array($modo, [ComprobanteIngreso::MODO_ABONO, ComprobanteIngreso::MODO_ANTICIPO], true)),
                'nullable',
                'integer',
                Rule::exists('terceros', 'id')->where(fn ($q) => $q->where('store_id', $store->id)),
            ],
            // Interno: generado en prepareForValidation para el Service.
            'condiciones_pago' => ['required', 'array', 'min:1'],
            'condiciones_pago.*.bolsillo_id' => ['required', 'integer'],
            'condiciones_pago.*.amount' => ['required', 'numeric', 'min:0.01'],
            'aplicaciones' => ['nullable', 'array'],
            'aplicaciones.*.account_receivable_id' => [
                'required',
                'integer',
                Rule::exists('accounts_receivable', 'id')->where(fn ($q) => $q->where('store_id', $store->id)),
            ],
            'aplicaciones.*.account_receivable_cuota_id' => [
                'nullable',
                'integer',
                Rule::exists('account_receivable_cuotas', 'id'),
            ],
            'aplicaciones.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];

        if ($modo === ComprobanteIngreso::MODO_OTRO_INGRESO) {
            $rules['notes'] = ['required', 'string', 'max:2000'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        foreach (['centro_costo_id', 'tercero_id', 'notes', 'valor_recibido', 'bolsillo_id'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        $valor = (float) ($this->input('valor_recibido') ?? 0);
        $bolsilloId = (int) ($this->input('bolsillo_id') ?? 0);

        // Compat: si aún llega condiciones_pago multi, tomar la primera / sumar no — UI nueva envía bolsillo único.
        if ($bolsilloId <= 0 && is_array($this->input('condiciones_pago'))) {
            foreach ($this->input('condiciones_pago') as $d) {
                $id = (int) ($d['bolsillo_id'] ?? 0);
                if ($id > 0) {
                    $bolsilloId = $id;
                    break;
                }
            }
            if ($bolsilloId > 0) {
                $this->merge(['bolsillo_id' => $bolsilloId]);
            }
        }

        if ($bolsilloId > 0 && $valor > 0) {
            $this->merge([
                'condiciones_pago' => [
                    [
                        'bolsillo_id' => $bolsilloId,
                        'amount' => $valor,
                    ],
                ],
            ]);
        }

        $aplicaciones = $this->input('aplicaciones');
        if (is_array($aplicaciones)) {
            $limpios = [];
            foreach ($aplicaciones as $a) {
                $amt = (float) ($a['amount'] ?? 0);
                $arId = (int) ($a['account_receivable_id'] ?? 0);
                $cuotaId = (int) ($a['account_receivable_cuota_id'] ?? 0);
                if ($amt > 0 && $arId > 0) {
                    $row = [
                        'account_receivable_id' => $arId,
                        'amount' => $amt,
                    ];
                    if ($cuotaId > 0) {
                        $row['account_receivable_cuota_id'] = $cuotaId;
                    }
                    $limpios[] = $row;
                }
            }
            $this->merge(['aplicaciones' => $limpios]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $valor = (float) ($this->input('valor_recibido') ?? 0);
            $aplicaciones = $this->input('aplicaciones', []);
            $totalAp = array_sum(array_map(fn ($a) => (float) ($a['amount'] ?? 0), is_array($aplicaciones) ? $aplicaciones : []));
            if ($totalAp - $valor > 0.015) {
                $validator->errors()->add('aplicaciones', 'La suma de abonos no puede superar el valor recibido.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'modo.required' => 'Debes elegir el tipo de recibo (abono, anticipo u otro ingreso).',
            'tipo_comprobante_id.required' => 'Debes seleccionar el tipo de recibo de caja.',
            'tercero_id.required' => 'Debes seleccionar el cliente.',
            'notes.required' => 'El concepto / observaciones es obligatorio en otro ingreso.',
            'bolsillo_id.required' => 'Debes elegir una forma de pago.',
            'valor_recibido.required' => 'Debes indicar el valor recibido.',
            'valor_recibido.min' => 'El valor recibido debe ser mayor a 0.',
        ];
    }
}
