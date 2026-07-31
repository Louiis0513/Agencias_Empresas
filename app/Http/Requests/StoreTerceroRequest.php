<?php

namespace App\Http\Requests;

use App\Models\Tercero;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTerceroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->filled('email') ? mb_strtolower(trim((string) $this->email)) : null,
            'activo' => $this->boolean('activo'),
        ]);
    }

    public function rules(): array
    {
        $store = $this->route('store');
        $tercero = $this->route('tercero');

        return [
            'tipo_persona' => ['required', Rule::in(Tercero::TIPOS_PERSONA)],
            'tipo_identificacion' => ['nullable', Rule::in(Tercero::TIPOS_IDENTIFICACION)],
            'numero_identificacion' => [
                'nullable', 'string', 'max:64',
                Rule::unique('terceros', 'numero_identificacion')
                    ->where('store_id', $store->id)
                    ->ignore($tercero?->id),
            ],
            'digito_verificacion' => ['nullable', 'string', 'max:2'],
            'nombre' => ['required', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:40'],
            'telefono_secundario' => ['nullable', 'string', 'max:40'],
            'direccion' => ['nullable', 'string', 'max:1000'],
            'activo' => ['boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(Tercero::ROLES)],

            'cliente.credito_habilitado' => ['nullable', 'boolean'],
            'cliente.cupo_credito' => ['nullable', 'numeric', 'min:0'],
            'cliente.dias_plazo' => ['nullable', 'integer', 'min:0'],
            'cliente.bloqueado_ventas' => ['nullable', 'boolean'],
            'cliente.motivo_bloqueo' => ['nullable', 'string', 'max:255'],
            'cliente.observaciones' => ['nullable', 'string'],
            'gym.gender' => ['nullable', 'string', 'max:8'],
            'gym.blood_type' => ['nullable', 'string', 'max:8'],
            'gym.eps' => ['nullable', 'string', 'max:255'],
            'gym.birth_date' => ['nullable', 'date'],
            'gym.emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'gym.emergency_contact_phone' => ['nullable', 'string', 'max:40'],

            'proveedor.plazo_pago_dias' => ['nullable', 'integer', 'min:0'],
            'proveedor.preferido' => ['nullable', 'boolean'],
            'proveedor.observaciones' => ['nullable', 'string'],
            'productos' => ['nullable', 'array'],
            'productos.*' => [
                'integer',
                Rule::exists('products', 'id')->where('store_id', $store->id),
            ],
            'productos_seleccionados_presentes' => ['nullable', 'boolean'],

            'trabajador.role_id' => [
                Rule::requiredIf(fn () => in_array(Tercero::ROL_TRABAJADOR, $this->input('roles', []), true)),
                'nullable',
                'integer',
                Rule::exists('roles', 'id')->where('store_id', $store->id),
            ],
            'trabajador.cargo' => ['nullable', 'string', 'max:255'],
            'trabajador.fecha_ingreso' => ['nullable', 'date'],
            'trabajador.estado_laboral' => ['nullable', Rule::in(['activo', 'retirado', 'suspendido'])],

            'contactos' => ['nullable', 'array'],
            'contactos.*.nombre' => [
                'nullable',
                'required_with:contactos.*.telefono,contactos.*.email,contactos.*.parentesco,contactos.*.tipo_contacto',
                'string',
                'max:255',
            ],
            'contactos.*.telefono' => ['nullable', 'string', 'max:40'],
            'contactos.*.email' => ['nullable', 'email', 'max:255'],
            'contactos.*.parentesco' => ['nullable', 'string', 'max:100'],
            'contactos.*.tipo_contacto' => ['nullable', Rule::in(['principal', 'facturacion', 'cartera', 'compras', 'emergencia', 'otro'])],

            'contactos_nuevos' => ['nullable', 'array'],
            'contactos_nuevos.*.nombre' => [
                'nullable',
                'required_with:contactos_nuevos.*.telefono,contactos_nuevos.*.email,contactos_nuevos.*.parentesco,contactos_nuevos.*.tipo_contacto',
                'string',
                'max:255',
            ],
            'contactos_nuevos.*.telefono' => ['nullable', 'string', 'max:40'],
            'contactos_nuevos.*.email' => ['nullable', 'email', 'max:255'],
            'contactos_nuevos.*.parentesco' => ['nullable', 'string', 'max:100'],
            'contactos_nuevos.*.tipo_contacto' => ['nullable', Rule::in(['principal', 'facturacion', 'cartera', 'compras', 'emergencia', 'otro'])],
        ];
    }
}
