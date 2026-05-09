<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Comprobante de ingreso') }} {{ $comprobanteIngreso->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5pt;
            color: #111827;
            margin: 0;
            padding: 8mm 11mm;
        }
        .title-blue { color: #1e40af; font-weight: bold; margin: 0; }
        .muted { color: #6b7280; font-size: 8.5pt; }
        .box {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 6px 10px;
            vertical-align: top;
        }
        .w-50 { width: 50%; }
        .w-100 { width: 100%; }
        table.grid { width: 100%; border-collapse: separate; border-spacing: 5px 5px; margin: 0 -5px; }
        table.grid td { padding: 0; }
        .label {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            color: #6b7280;
            margin: 0 0 3px 0;
        }
        .strong { font-weight: bold; }
        table.data {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }
        table.data th {
            background: #f3f4f6;
            text-align: left;
            padding: 5px 8px;
            border-bottom: 1px solid #d1d5db;
            font-size: 7.5pt;
            text-transform: uppercase;
        }
        table.data th.num { text-align: right; }
        table.data td {
            padding: 5px 8px;
            border-bottom: 1px solid #e5e7eb;
        }
        table.data td.num { text-align: right; }
        .detail-caption { font-size: 8.5pt; font-weight: bold; margin: 8px 0 4px 0; color: #374151; }
        .reserve {
            border: 1px dashed #d1d5db;
            background: #f9fafb;
            min-height: 52px;
        }
        .two-col td { width: 50%; vertical-align: top; }
        .valor-big { font-size: 14pt; font-weight: bold; }
        .badge { font-size: 7.5pt; border: 1px solid #e5e7eb; padding: 2px 6px; border-radius: 8px; color: #4b5563; display: inline-block; margin-top: 4px; }
        .footer-print { font-size: 6.5pt; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 8px; margin-top: 10px; }
        .sign-grid td { width: 25%; vertical-align: top; }
        .sign-box {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 3px 4px;
            min-height: 46px;
            text-align: center;
        }
        .sign-box .sig-label { font-size: 6.5pt; font-weight: bold; text-transform: uppercase; color: #6b7280; }
        .sign-line { border-top: 1px solid #d1d5db; margin-top: 20px; }
        .sign-client {
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 6px 8px;
            min-height: 68px;
        }
        .warn { color: #b91c1c; font-size: 8.5pt; padding: 6px; border: 1px solid #fecaca; background: #fef2f2; margin-bottom: 6px; border-radius: 4px; }
    </style>
</head>
<body>

@if($c->isReversed())
    <div class="warn">{{ __('Este comprobante fue revertido y no tiene efecto contable.') }}</div>
@endif

<table class="w-100" style="margin-bottom: 6px;"><tr>
    <td class="box w-50">
        <p class="title-blue" style="font-size: 12pt;">{{ $store->name }}</p>
        @if($store->rut_nit)
            <p class="muted" style="margin: 4px 0 0 0;">{{ __('NIT.') }} {{ $store->rut_nit }}</p>
        @endif
        @if($dirTel !== '')
            <p class="muted" style="margin: 3px 0 0 0;">{{ $dirTel }}</p>
        @endif
    </td>
    <td class="box w-50">
        <p class="title-blue" style="font-size: 12pt;">{{ __('Comprobante de ingreso') }}</p>
        <p class="strong" style="margin: 6px 0 0 0; font-size: 10pt;">{{ __('No.') }} {{ $c->number }}</p>
        <span class="badge">{{ $tipoEtiqueta }}</span>
    </td>
</tr></table>

<table class="w-100 box" style="border-collapse: collapse; margin-bottom: 6px;"><tr>
    <td style="width: 18%; vertical-align: top; padding: 7px 9px;">
        <p class="label">{{ __('Fecha') }}</p>
        <p class="strong">{{ $c->date->format('d/m/Y') }}</p>
    </td>
    <td style="width: 52%; vertical-align: top; border-left: 1px solid #e5e7eb; padding: 7px 9px;">
        <p class="label">{{ __('Recibido de') }}</p>
        <p class="strong">{{ $customer?->name ?? '—' }}</p>
        <table class="w-100" style="margin-top: 5px; font-size: 8.5pt;"><tr>
            <td style="width: 33%; vertical-align: top;">
                <span class="muted">{{ __('CC') }}</span><br>
                <span class="strong">{{ $customer?->document_number ?? '—' }}</span>
            </td>
            <td style="width: 42%; vertical-align: top;">
                <span class="muted">{{ __('Dirección') }}</span><br>
                <span>{{ $customer?->address ?? '—' }}</span>
            </td>
            <td style="width: 25%; vertical-align: top;">
                <span class="muted">{{ __('Ciudad') }}</span><br>
                <span>—</span>
            </td>
        </tr></table>
    </td>
    <td style="width: 30%; vertical-align: top; border-left: 1px solid #e5e7eb; text-align: right; padding: 7px 9px;">
        <p class="label">{{ __('Valor') }}</p>
        <p class="valor-big">{{ money($c->total_amount, $cur) }}</p>
    </td>
</tr></table>

<p class="detail-caption">{{ $detalleSubtitulo }}</p>
<table class="data">
    <thead>
        <tr>
            <th>{{ __('Descripción') }}</th>
            <th class="num" style="width: 28%;">{{ __('Valor') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($detalleLineasVista as $fila)
            <tr>
                <td>{{ $fila['descripcion'] }}</td>
                <td class="num">{{ money($fila['valor'], $cur) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="w-100" style="margin-top: 6px; border-collapse: separate; border-spacing: 4px 0;"><tr class="two-col">
    <td class="reserve box" style="border-style: dashed;"></td>
    <td>
        <table class="data" style="margin: 0;">
            <thead>
                <tr>
                    <th>{{ __('Forma de pago') }}</th>
                    <th>{{ __('Identificación') }}</th>
                    <th class="num" style="width: 26%;">{{ __('Valor') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($c->destinos as $d)
                    <tr>
                        <td>{{ $d->bolsillo->name ?? '—' }}</td>
                        <td style="color: #9ca3af;">—</td>
                        <td class="num">{{ money((float) $d->amount, $cur) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </td>
</tr></table>

<div class="box" style="margin-top: 6px; background: #f9fafb;">
    <p class="label">{{ __('Valor (en letras)') }}</p>
    <p class="strong" style="margin: 3px 0 0 0; line-height: 1.3;">{{ $valorEnLetras }}</p>
</div>

@if($c->aplicaciones->isNotEmpty())
    <p class="detail-caption">{{ __('Aplicado a cuentas por cobrar') }}</p>
    <table class="data">
        <thead>
            <tr>
                <th>{{ __('Factura') }}</th>
                <th class="num" style="width: 35%;">{{ __('Monto aplicado') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($c->aplicaciones as $ap)
                <tr>
                    <td>{{ __('Factura #:id', ['id' => $ap->accountReceivable->invoice->id]) }}</td>
                    <td class="num">{{ money((float) $ap->amount, $cur) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<table class="w-100" style="margin-top: 8px;"><tr>
    <td style="width: 58%; vertical-align: top;">
        <table class="w-100 sign-grid" style="border-collapse: separate; border-spacing: 2px;"><tr>
            @foreach ([__('Preparado'), __('Aprobado'), __('Contabilizado'), __('Revisado')] as $label)
                <td>
                    <div class="sign-box">
                        <div class="sig-label">{{ $label }}</div>
                        <div class="sign-line"></div>
                    </div>
                </td>
            @endforeach
        </tr></table>
    </td>
    <td style="width: 42%; vertical-align: top; padding-left: 5px;">
        <div class="sign-client">
            <p class="label" style="text-align: center; margin-bottom: 4px;">{{ __('Firma de recibido') }}</p>
            <div class="sign-line" style="margin-top: 22px;"></div>
            <p class="muted" style="text-align: center; margin: 4px 0 0 0; font-size: 7.5pt;">{{ __('C.C. o NIT') }}</p>
        </div>
    </td>
</tr></table>

<div class="footer-print">
    {{ __('Impreso con :name — v:version — :url', [
        'name' => config('centradia.print_name'),
        'version' => config('centradia.print_version'),
        'url' => config('centradia.print_url'),
    ]) }}
</div>

</body>
</html>
