<?php

namespace App\Http\Requests;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDefinirComprobantesCentroCostoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Store|null $store */
        $store = $this->route('store');
        $storeId = $store?->id;

        return [
            'tipos' => ['required', 'array', 'min:1'],
            'tipos.*.id' => [
                'required',
                'integer',
                Rule::exists('tipos_comprobante', 'id')->where(
                    fn ($q) => $q->where('store_id', $storeId)
                ),
            ],
            'tipos.*.maneja_centro_costos' => ['nullable', 'boolean'],
            'tipos.*.centro_costo_obligatorio' => ['nullable', 'boolean'],
            'tipos.*.centro_costo_default_id' => [
                'nullable',
                'integer',
                Rule::exists('centros_costo', 'id')->where(
                    fn ($q) => $q
                        ->where('store_id', $storeId)
                        ->where('activo', true)
                        ->whereNotNull('parent_id')
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $tipos = collect($this->input('tipos', []))
            ->map(function ($fila) {
                $fila['maneja_centro_costos'] = filter_var($fila['maneja_centro_costos'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $fila['centro_costo_obligatorio'] = filter_var($fila['centro_costo_obligatorio'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $fila['centro_costo_default_id'] = ($fila['centro_costo_default_id'] ?? '') === ''
                    ? null
                    : $fila['centro_costo_default_id'];

                return $fila;
            })
            ->all();

        $this->merge(['tipos' => $tipos]);
    }

    public function messages(): array
    {
        return [
            'tipos.required' => 'No hay tipos de comprobante para guardar.',
            'tipos.*.id.exists' => 'Un tipo de comprobante no es válido para esta tienda.',
            'tipos.*.centro_costo_default_id.exists' => 'El valor por defecto debe ser un subcentro activo de la tienda.',
        ];
    }
}
