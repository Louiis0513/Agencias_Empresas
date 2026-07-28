<?php

namespace App\Http\Requests;

use App\Models\TipoComprobante;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTipoComprobanteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'familia' => ['required', 'string', Rule::in(TipoComprobante::FAMILIAS)],
            'codigo' => ['nullable', 'string', 'max:32'],
            'nombre' => ['required', 'string', 'max:255'],
            'titulo' => ['nullable', 'string', 'max:255'],
            'prefijo' => ['nullable', 'string', 'max:16'],
            'numeracion_automatica' => ['nullable', 'boolean'],
            'siguiente_numero' => ['nullable', 'integer', 'min:1'],
            'activo' => ['nullable', 'boolean'],
            'maneja_centro_costos' => ['nullable', 'boolean'],
            'libro_oficial' => ['nullable', 'string', Rule::in(TipoComprobante::LIBROS_OFICIALES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['numeracion_automatica', 'activo', 'maneja_centro_costos'] as $field) {
            if ($this->has($field)) {
                $this->merge([$field => $this->boolean($field)]);
            }
        }

        foreach (['codigo', 'titulo', 'prefijo', 'libro_oficial'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if ($this->has('familia') && is_string($this->input('familia'))) {
            $this->merge(['familia' => strtoupper(trim($this->input('familia')))]);
        }

        if ($this->has('prefijo') && is_string($this->input('prefijo')) && $this->input('prefijo') !== null) {
            $this->merge(['prefijo' => strtoupper(trim($this->input('prefijo')))]);
        }
    }

    public function messages(): array
    {
        return [
            'familia.required' => 'Debes seleccionar la familia del comprobante.',
            'familia.in' => 'La familia debe ser FV, RC, FC, RP o CC.',
            'nombre.required' => 'El nombre del tipo de comprobante es obligatorio.',
            'siguiente_numero.min' => 'El siguiente número debe ser al menos 1.',
            'libro_oficial.in' => 'El libro oficial debe ser ventas o compras.',
        ];
    }
}
