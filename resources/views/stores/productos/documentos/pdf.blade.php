<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $documento->tituloTipoDocumento() }} No. {{ $documento->numeroImpresion() }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5pt;
            color: #111827;
            margin: 0;
            padding: 8mm 8mm;
        }
        .muted { color: #6b7280; font-size: 7.5pt; }
        .strong { font-weight: bold; }
        .w-100 { width: 100%; }
        table { border-collapse: collapse; }
        .header-doc {
            border: 1px solid #9ca3af;
            text-align: center;
            padding: 8px 6px;
        }
        .fecha-box {
            border: 1px solid #9ca3af;
            margin-top: 6px;
        }
        .fecha-box .lbl {
            background: #e5e7eb;
            text-align: center;
            font-size: 7.5pt;
            font-weight: bold;
            padding: 4px 6px;
            border-bottom: 1px solid #9ca3af;
        }
        .fecha-box .val {
            text-align: center;
            padding: 10px 6px;
            font-size: 10pt;
            font-weight: bold;
        }
        .data th {
            background: #e5e7eb;
            text-align: left;
            padding: 5px 4px;
            border: 1px solid #d1d5db;
            font-size: 6.5pt;
            text-transform: uppercase;
        }
        .data th.num, .data td.num { text-align: right; }
        .data th.center, .data td.center { text-align: center; }
        .data td {
            padding: 4px;
            border: 1px solid #d1d5db;
            font-size: 7.5pt;
            vertical-align: top;
        }
        .data tr:nth-child(even) td { background: #f9fafb; }
        .logo { max-height: 130px; max-width: 240px; display: block; margin: 0 auto; }
        .footer-print {
            font-size: 6.5pt;
            color: #9ca3af;
            border-top: 1px solid #e5e7eb;
            padding-top: 6px;
            margin-top: 12px;
        }
        .obs { margin: 8px 0; font-size: 8pt; }
    </style>
</head>
<body>

{{-- Cabecera 3 columnas: logo | empresa | título + fecha --}}
<table class="w-100" style="margin-bottom: 10px;">
    <tr>
        <td style="width: 22%; vertical-align: middle; text-align: center; padding-right: 6px;">
            @if(! empty($logoAbsPath))
                <img src="{{ $logoAbsPath }}" class="logo" alt="">
            @endif
        </td>
        <td style="width: 48%; vertical-align: top; text-align: center; padding: 0 6px;">
            <p class="strong" style="margin: 0; font-size: 10pt; text-transform: uppercase;">{{ $store->name }}</p>
            @if($store->rut_nit)
                <p class="muted" style="margin: 2px 0 0 0;">NIT {{ $store->rut_nit }}</p>
            @endif
            @if($store->address)
                <p class="muted" style="margin: 2px 0 0 0;">{{ $store->address }}</p>
            @endif
            @if($store->phone || ($store->mobile ?? null))
                <p class="muted" style="margin: 2px 0 0 0;">
                    Tel: {{ implode(' - ', array_filter([$store->phone, $store->mobile ?? null])) }}
                </p>
            @endif
            @if($ciudadEmpresa !== '')
                <p class="muted" style="margin: 2px 0 0 0;">{{ $ciudadEmpresa }}</p>
            @endif
        </td>
        <td style="width: 30%; vertical-align: top;">
            <div class="header-doc">
                <p class="strong" style="margin: 0; font-size: 9pt; line-height: 1.25;">
                    {{ $documento->tituloTipoDocumento() }}
                </p>
                <p class="strong" style="margin: 6px 0 0 0; font-size: 10pt;">
                    No. {{ $documento->numeroImpresion() }}
                </p>
            </div>
            <div class="fecha-box">
                <div class="lbl">Fecha Comprobante</div>
                <div class="val">{{ $documento->fecha->format('Y-m-d') }}</div>
            </div>
        </td>
    </tr>
</table>

@if($documento->tercero_nombre)
    <p class="obs"><span class="strong">Tercero:</span> {{ $documento->tercero_nombre }}</p>
@endif
@if($documento->observaciones)
    <p class="obs"><span class="strong">Observaciones:</span> {{ $documento->observaciones }}</p>
@endif

<table class="w-100 data">
    <thead>
        <tr>
            <th class="center" style="width: 4%;">Ítem</th>
            <th style="width: 9%;">Producto</th>
            <th style="width: 22%;">Descripción</th>
            <th style="width: 10%;">Referencia de fábrica</th>
            <th style="width: 10%;">Bodega</th>
            <th class="center" style="width: 10%;">Aumenta/Disminuye</th>
            <th class="num" style="width: 9%;">Cantidad</th>
            <th class="num" style="width: 11%;">Costo total</th>
            <th style="width: 15%;">Nombre de Cuenta contable</th>
        </tr>
    </thead>
    <tbody>
        @foreach($documento->lineas as $linea)
            <tr>
                <td class="center">{{ $linea->orden }}</td>
                <td>{{ $linea->product?->codigo }}</td>
                <td>{{ $linea->descripcion }}</td>
                <td>{{ $linea->product?->referencia ?: '—' }}</td>
                <td>{{ $linea->bodega?->nombre ?? ($linea->bodega?->codigo ?? 'Sin asignar') }}</td>
                <td class="center">{{ $naturaleza }}</td>
                <td class="num">{{ number_format((float) $linea->cantidad, 2, '.', ',') }}</td>
                <td class="num">{{ number_format((float) $linea->costo_total, 2, ',', '.') }}</td>
                <td>{{ $linea->product?->categoriaContable?->cuentaInventario?->nombre ?? '—' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="footer-print">
    {{ $documento->numero }} · {{ $documento->tituloTipoDocumento() }} · {{ $store->name }}
</div>

</body>
</html>
