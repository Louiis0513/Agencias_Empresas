<?php

namespace App\Http\Requests;

use App\Models\Store;
use App\Models\TipoComprobante;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComprobanteContableRequest extends FormRequest
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

        $exigeCentro = false;
        $manejaCentro = false;
        $tipoId = (int) $this->input('tipo_comprobante_id');
        if ($storeId && $tipoId > 0) {
            $tipo = TipoComprobante::query()
                ->where('store_id', $storeId)
                ->where('familia', 'CC')
                ->where('activo', true)
                ->whereKey($tipoId)
                ->first(['maneja_centro_costos', 'centro_costo_obligatorio']);
            $manejaCentro = (bool) ($tipo?->maneja_centro_costos);
            $exigeCentro = $tipo?->exigeCentroCostos() ?? false;
        }

        $centroRule = ! $manejaCentro
            ? ['nullable']
            : ($exigeCentro
                ? [
                    'required',
                    'integer',
                    Rule::exists('centros_costo', 'id')->where(
                        fn ($q) => $q
                            ->where('store_id', $storeId)
                            ->where('activo', true)
                            ->whereNotNull('parent_id')
                    ),
                ]
                : [
                    'nullable',
                    'integer',
                    Rule::exists('centros_costo', 'id')->where(
                        fn ($q) => $q
                            ->where('store_id', $storeId)
                            ->where('activo', true)
                            ->whereNotNull('parent_id')
                    ),
                ]);

        return [
            'tipo_comprobante_id' => [
                'required',
                'integer',
                Rule::exists('tipos_comprobante', 'id')->where(
                    fn ($q) => $q
                        ->where('store_id', $storeId)
                        ->where('familia', 'CC')
                        ->where('activo', true)
                ),
            ],
            'numero' => ['nullable', 'string', 'max:64'],
            'fecha' => ['required', 'date'],
            'tercero_id' => [
                'nullable',
                'integer',
                Rule::exists('terceros', 'id')->where(
                    fn ($q) => $q->where('store_id', $storeId)->where('activo', true)
                ),
            ],
            'descripcion' => ['required', 'string', 'max:2000'],
            'lineas' => ['required', 'array', 'min:2'],
            'lineas.*.cuenta_contable_id' => [
                'required',
                'integer',
                Rule::exists('cuentas_contables', 'id')->where(
                    fn ($q) => $q
                        ->where('store_id', $storeId)
                        ->where('activo', true)
                        ->where('es_auxiliar', true)
                        ->where('nivel_agrupacion', 'Transaccional')
                ),
            ],
            'lineas.*.tercero_id' => [
                'nullable',
                'integer',
                Rule::exists('terceros', 'id')->where(
                    fn ($q) => $q->where('store_id', $storeId)->where('activo', true)
                ),
            ],
            'lineas.*.centro_costo_id' => $centroRule,
            'lineas.*.detalle_contable' => ['nullable', 'string', 'max:255'],
            'lineas.*.descripcion' => ['nullable', 'string', 'max:255'],
            'lineas.*.debito' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
            'lineas.*.credito' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999.99'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $lineas = collect($this->input('lineas', []))
            ->map(function ($linea) {
                $linea['debito'] = $linea['debito'] ?? 0;
                $linea['credito'] = $linea['credito'] ?? 0;
                $linea['tercero_id'] = ($linea['tercero_id'] ?? '') === ''
                    ? null
                    : $linea['tercero_id'];
                $linea['centro_costo_id'] = ($linea['centro_costo_id'] ?? '') === ''
                    ? null
                    : $linea['centro_costo_id'];

                return $linea;
            })
            ->all();

        $this->merge([
            'numero' => $this->input('numero') === '' ? null : $this->input('numero'),
            'tercero_id' => $this->input('tercero_id') === '' ? null : $this->input('tercero_id'),
            'lineas' => $lineas,
        ]);
    }

    public function messages(): array
    {
        return [
            'tipo_comprobante_id.required' => 'Debes seleccionar un tipo de comprobante CC.',
            'tipo_comprobante_id.exists' => 'El tipo CC no es válido para esta tienda.',
            'fecha.required' => 'La fecha contable es obligatoria.',
            'descripcion.required' => 'La glosa general es obligatoria.',
            'lineas.required' => 'Debes agregar las líneas del asiento.',
            'lineas.min' => 'El asiento debe tener al menos dos líneas.',
            'lineas.*.cuenta_contable_id.required' => 'Cada línea debe tener una cuenta.',
            'lineas.*.cuenta_contable_id.exists' => 'Una cuenta no es auxiliar, no está activa o no pertenece a la tienda.',
            'lineas.*.centro_costo_id.required' => 'Cada línea debe tener un subcentro de costo.',
            'lineas.*.centro_costo_id.exists' => 'El subcentro de costo no es válido o no está activo.',
            'lineas.*.debito.numeric' => 'Los débitos deben ser valores numéricos.',
            'lineas.*.credito.numeric' => 'Los créditos deben ser valores numéricos.',
        ];
    }
}
