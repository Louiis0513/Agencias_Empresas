<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $comprobanteIngreso->tipo_comprobante_id ? __('Recibo de caja') : __('Comprobante de ingreso') }} {{ $comprobanteIngreso->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9pt;
            color: #111827;
            margin: 0;
            padding: 8mm 10mm;
        }
        .muted { color: #6b7280; font-size: 8pt; }
        .strong { font-weight: bold; }
        .w-100 { width: 100%; }
        table { border-collapse: collapse; }
        .label-cell {
            background: #f3f4f6;
            font-size: 8pt;
            font-weight: bold;
            color: #374151;
            padding: 4px 6px;
            border: 1px solid #d1d5db;
            white-space: nowrap;
            width: 110px;
        }
        .value-cell {
            padding: 4px 6px;
            border: 1px solid #d1d5db;
            font-size: 9pt;
        }
        .header-doc {
            border: 1px solid #9ca3af;
            text-align: center;
            padding: 8px 6px;
        }
        .data th {
            background: #f3f4f6;
            text-align: left;
            padding: 5px 6px;
            border: 1px solid #d1d5db;
            font-size: 7.5pt;
            text-transform: uppercase;
        }
        .data th.num, .data td.num { text-align: right; }
        .data td {
            padding: 5px 6px;
            border: 1px solid #d1d5db;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .total-box {
            border: 1px solid #9ca3af;
            width: 100%;
        }
        .total-box td { padding: 6px 8px; font-size: 9pt; }
        .total-box .lbl { background: #f3f4f6; font-weight: bold; width: 45%; }
        .total-box .val { text-align: right; font-weight: bold; background: #f9fafb; }
        .sign-box {
            border: 1px solid #d1d5db;
            padding: 3px 4px;
            min-height: 44px;
            text-align: center;
        }
        .sign-box .sig-label { font-size: 6.5pt; font-weight: bold; text-transform: uppercase; color: #6b7280; }
        .sign-line { border-top: 1px solid #d1d5db; margin-top: 20px; }
        .sign-client {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            min-height: 64px;
        }
        .footer-print { font-size: 6.5pt; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 6px; margin-top: 10px; }
        .warn { color: #b91c1c; font-size: 8.5pt; padding: 6px; border: 1px solid #fecaca; background: #fef2f2; margin-bottom: 6px; }
        .badge { font-size: 7pt; border: 1px solid #e5e7eb; padding: 1px 5px; color: #4b5563; display: inline-block; margin-top: 3px; }
        .logo { max-height: 110px; max-width: 220px; }
    </style>
</head>
<body>

@if($c->isReversed())
    <div class="warn">{{ __('Este comprobante fue revertido y no tiene efecto contable.') }}</div>
@endif

{{-- Cabecera 3 columnas --}}
<table class="w-100" style="margin-bottom: 8px;">
    <tr>
        <td style="width: 28%; vertical-align: middle; text-align: center; padding-right: 6px;">
            @if($logoAbsPath)
                <img src="{{ $logoAbsPath }}" class="logo" alt="">
            @endif
        </td>
        <td style="width: 44%; vertical-align: top; text-align: center; padding: 0 4px;">
            <p class="strong" style="margin: 0; font-size: 9.5pt; text-transform: uppercase;">{{ $store->name }}</p>
            @if($store->rut_nit)
                <p class="muted" style="margin: 2px 0 0 0;">{{ __('NIT') }} {{ $store->rut_nit }}</p>
            @endif
            @if($store->address)
                <p class="muted" style="margin: 2px 0 0 0;">{{ $store->address }}</p>
            @endif
            @if($store->phone || ($store->mobile ?? null))
                <p class="muted" style="margin: 2px 0 0 0;">{{ __('Teléfono') }} {{ $store->phone ?: $store->mobile }}</p>
            @endif
            @if($ciudadEmpresa !== '')
                <p class="muted" style="margin: 2px 0 0 0;">{{ $ciudadEmpresa }}</p>
            @endif
        </td>
        <td style="width: 28%; vertical-align: top;">
            <div class="header-doc">
                <p class="strong" style="margin: 0; font-size: 11pt;">{{ $c->tipo_comprobante_id ? __('Recibo de caja') : __('Comprobante de ingreso') }}</p>
                <p class="strong" style="margin: 6px 0 0 0; font-size: 10pt;">{{ __('No.') }} {{ $c->number }}</p>
            </div>
        </td>
    </tr>
</table>

{{-- Cliente 2/3 + Fecha 1/3 --}}
<table class="w-100" style="margin-bottom: 8px;">
    <tr>
        <td style="width: 70%; vertical-align: top; padding-right: 6px;">
            <table class="w-100">
                <tr>
                    <td class="label-cell">{{ __('Señores') }}</td>
                    <td class="value-cell strong" colspan="3">{{ $customer?->nombre ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label-cell">{{ __('NIT') }}</td>
                    <td class="value-cell">
                        {{ $customer?->numero_identificacion ?? '—' }}@if($customer?->digito_verificacion)-{{ $customer->digito_verificacion }}@endif
                    </td>
                    <td class="label-cell" style="width: 70px;">{{ __('Teléfono') }}</td>
                    <td class="value-cell">{{ $customer?->telefono ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label-cell">{{ __('Dirección') }}</td>
                    <td class="value-cell" colspan="3">{{ $customer?->direccion ?? '—' }}</td>
                </tr>
                @if($c->centroCosto)
                    <tr>
                        <td class="label-cell">{{ __('Centro de costos') }}</td>
                        <td class="value-cell" colspan="3">{{ $c->centroCosto->codigo }} — {{ $c->centroCosto->nombre }}</td>
                    </tr>
                @endif
            </table>
        </td>
        <td style="width: 30%; vertical-align: top;">
            <table class="w-100">
                <tr>
                    <td class="label-cell" style="text-align: center; width: auto;">{{ __('Fecha de recibo') }}</td>
                </tr>
                <tr>
                    <td class="value-cell strong" style="text-align: center; padding: 14px 6px; font-size: 11pt;">
                        {{ $c->date->format('d/m/Y') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

{{-- Tabla detalle --}}
<table class="w-100 data" style="margin-bottom: 8px;">
    <thead>
        <tr>
            <th style="width: 8%;">{{ __('Ítem') }}</th>
            <th style="width: 32%;">{{ __('Documento') }}</th>
            <th>{{ __('Descripción') }}</th>
            <th class="num" style="width: 20%;">{{ __('Valor') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($detalleLineasVista as $fila)
            <tr>
                <td>{{ $fila['item'] }}</td>
                <td>{{ $fila['documento'] !== '' ? $fila['documento'] : '—' }}</td>
                <td>{{ $fila['descripcion'] }}</td>
                <td class="num">{{ money($fila['valor'], $cur) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

{{-- Pie: totales / letras / condiciones / observaciones --}}
<table class="w-100" style="margin-bottom: 8px;">
    <tr>
        <td style="width: 58%; vertical-align: top; padding-right: 8px;">
            <p style="margin: 0 0 6px 0;">
                <span class="strong">{{ __('Total ítems') }}:</span> {{ $totalItems }}
            </p>
            <p style="margin: 0 0 2px 0;" class="strong">{{ __('Valor en letras') }}:</p>
            <p style="margin: 0 0 8px 0;">{{ $valorEnLetras }}</p>
            <p style="margin: 0 0 2px 0;" class="strong">{{ __('Condiciones de pago') }}:</p>
            <table class="w-100" style="margin: 0 0 8px 0;">
                <tr>
                    <td style="padding: 0;">{{ $condicionPago }}</td>
                    <td style="padding: 0; text-align: right; font-weight: bold;">{{ money($condicionPagoMonto, $cur) }}</td>
                </tr>
            </table>
            <p style="margin: 0 0 2px 0;" class="strong">{{ __('Observaciones') }}:</p>
            <p style="margin: 0;">{{ filled($c->notes) ? $c->notes : '' }}</p>
        </td>
        <td style="width: 42%; vertical-align: top;">
            <table class="total-box">
                <tr>
                    <td class="lbl">{{ __('Total pago') }}</td>
                    <td class="val">{{ money($c->total_amount, $cur) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<div class="footer-print">
    {{ __('Impreso con :name — v:version — :url', [
        'name' => config('centradia.print_name'),
        'version' => config('centradia.print_version'),
        'url' => config('centradia.print_url'),
    ]) }}
</div>

</body>
</html>
