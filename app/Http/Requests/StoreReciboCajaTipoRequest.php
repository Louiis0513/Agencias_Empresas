<?php

namespace App\Http\Requests;

use App\Models\TipoComprobante;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReciboCajaTipoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $store = $this->route('store');

        return [
            'familia' => ['nullable', 'string', Rule::in([TipoComprobante::FAMILIA_RC])],
            'codigo' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9]+$/'],
            'nombre' => ['nullable', 'string', 'max:255'],
            'titulo' => ['required', 'string', 'max:255'],
            'prefijo' => ['nullable', 'string', 'max:16'],
            'numeracion_automatica' => ['nullable', 'boolean'],
            'siguiente_numero' => ['nullable', 'integer', 'min:1'],
            'activo' => ['nullable', 'boolean'],
            'maneja_centro_costos' => ['nullable', 'boolean'],
            'centro_costo_obligatorio' => ['nullable', 'boolean'],
            'cuenta_anticipos_id' => [
                'nullable',
                'integer',
                Rule::exists('cuentas_contables', 'id')->where(
                    fn ($q) => $q->where('store_id', $store->id)
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['numeracion_automatica', 'activo', 'maneja_centro_costos', 'centro_costo_obligatorio'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        foreach (['codigo', 'titulo', 'prefijo', 'nombre', 'cuenta_anticipos_id'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        $titulo = $this->input('titulo');
        $nombre = $this->input('nombre');
        if ((! $nombre || $nombre === null) && is_string($titulo) && $titulo !== '') {
            $this->merge(['nombre' => $titulo]);
        }

        $this->merge(['familia' => TipoComprobante::FAMILIA_RC]);

        if ($this->has('prefijo') && is_string($this->input('prefijo')) && $this->input('prefijo') !== null) {
            $this->merge(['prefijo' => strtoupper(trim($this->input('prefijo')))]);
        }

        if ($this->has('codigo') && is_string($this->input('codigo')) && $this->input('codigo') !== null) {
            $this->merge(['codigo' => strtoupper(trim($this->input('codigo')))]);
        }
    }

    public function messages(): array
    {
        return [
            'titulo.required' => 'El título del recibo de caja es obligatorio.',
            'codigo.regex' => 'El código solo puede contener letras y números (ej. 1, 2).',
            'siguiente_numero.min' => 'El consecutivo debe ser al menos 1.',
            'cuenta_anticipos_id.exists' => 'La cuenta de anticipos no es válida para esta tienda.',
            'familia.in' => 'Esta pantalla solo gestiona recibos de caja (RC).',
        ];
    }
}
