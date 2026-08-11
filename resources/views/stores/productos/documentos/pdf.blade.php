<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $documento->numero }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111827; margin: 0; padding: 8mm 10mm; }
        .muted { color: #6b7280; font-size: 8pt; }
        table { border-collapse: collapse; width: 100%; }
        .header-doc { border: 1px solid #9ca3af; text-align: center; padding: 8px 6px; margin-bottom: 12px; }
        .meta td { padding: 3px 0; vertical-align: top; }
        .data th {
            background: #f3f4f6; text-align: left; padding: 5px 6px; border: 1px solid #d1d5db;
            font-size: 7.5pt; text-transform: uppercase;
        }
        .data th.num, .data td.num { text-align: right; }
        .data td { padding: 5px 6px; border: 1px solid #d1d5db; font-size: 8.5pt; vertical-align: top; }
        .total-box { margin-top: 12px; border: 1px solid #9ca3af; width: 45%; margin-left: auto; }
        .total-box td { padding: 6px 8px; }
        .total-box .lbl { background: #f3f4f6; font-weight: bold; }
        .total-box .val { text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header-doc">
        <div style="font-size: 14pt; font-weight: bold;">{{ $documento->numero }}</div>
        <div class="muted">{{ $documento->tituloTipoDocumento() }}</div>
    </div>

    <table class="meta" style="margin-bottom: 14px;">
        <tr>
            <td style="width: 55%;">
                <strong>{{ $store->name }}</strong><br>
                <span class="muted">{{ $store->nit ?? '' }}</span>
            </td>
            <td>
                <strong>Fecha:</strong> {{ $documento->fecha->format('d/m/Y') }}<br>
                <strong>Tercero:</strong> {{ $documento->tercero_nombre ?: '—' }}
            </td>
        </tr>
    </table>

    @if($documento->observaciones)
        <p><strong>Observaciones:</strong> {{ $documento->observaciones }}</p>
    @endif

    <table class="data">
        <thead>
            <tr>
                <th>#</th>
                <th>Código</th>
                <th>Descripción</th>
                <th>Bodega</th>
                <th class="num">Cantidad</th>
                <th class="num">Costo unit.</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($documento->lineas as $linea)
                <tr>
                    <td>{{ $linea->orden }}</td>
                    <td>{{ $linea->product?->codigo }}</td>
                    <td>{{ $linea->descripcion }}</td>
                    <td>{{ $linea->bodega?->codigo ?? '—' }}</td>
                    <td class="num">{{ number_format((float) $linea->cantidad, 2, '.', ',') }}</td>
                    <td class="num">{{ number_format((float) $linea->costo_unitario, 2, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $linea->costo_total, 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="total-box">
        <tr>
            <td class="lbl">Valor total inventario</td>
            <td class="val">$ {{ number_format((float) $documento->total, 2, ',', '.') }}</td>
        </tr>
    </table>
</body>
</html>
