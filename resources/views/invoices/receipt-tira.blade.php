<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Factura #{{ $invoice->id }}</title>
    <style>
        @page { margin: 4mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', Courier, monospace; font-size: 5.5pt; line-height: 1.15; color: #000; }
        .receipt { width: 50mm; max-width: 50mm; padding: 2mm; overflow: hidden; box-sizing: border-box; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .sep { border: none; border-top: 1px dashed #000; margin: 3px 0; }
        .line { margin: 1px 0; word-break: break-word; overflow-wrap: break-word; }
        table.items { width: 46mm; max-width: 46mm; border-collapse: separate; border-spacing: 0 1px; font-size: 5pt; table-layout: fixed; }
        table.items th { text-align: left; padding: 0 2px 0 0; }
        table.items td { padding: 0 2px 0 0; vertical-align: top; overflow: hidden; }
        table.items .col-desc { width: 20mm; max-width: 20mm; word-wrap: break-word; overflow-wrap: break-word; word-break: break-word; }
        table.items .col-cant { width: 8mm; text-align: right; }
        table.items .col-precio { width: 9mm; text-align: right; }
        table.items .col-total { width: 9mm; text-align: right; }
        .barcode-wrap { text-align: center; margin: 4px 0; }
        .barcode-wrap img { max-width: 44mm; height: 32px; }
        .barcode-text { font-size: 5pt; letter-spacing: 1px; margin-top: 1px; }
    </style>
</head>
<body>
    <div class="receipt">
        {{-- Encabezado tienda --}}
        <div class="center bold line" style="font-size: 7pt;">{{ $store->name }}</div>
        @if($store->rut_nit)
            <div class="center line">Nit. {{ $store->rut_nit }}</div>
        @endif
        @if($store->regimen)
            <div class="center line">{{ $store->regimen }}</div>
        @endif
        @if($store->domain)
            <div class="center line">{{ $store->domain }}</div>
        @endif
        @if($store->department || $store->city)
            <div class="center line">{{ trim(($store->department ?? '') . ($store->department && $store->city ? ' - ' : '') . ($store->city ?? '')) }}</div>
        @endif
        @if($store->address || $store->phone)
            <div class="center line">{{ trim(($store->address ?? '') . ($store->phone ? ' Tel:' . $store->phone : '')) }}</div>
        @endif

        {{-- Datos factura --}}
        <div class="line" style="margin-top: 6px;">Factura de Venta: {{ $invoice->id }}</div>
        <div class="line">Fecha: {{ $invoice->created_at->format('d/m/Y') }} Hora: {{ $invoice->created_at->format('h:i:s A') }}</div>
        <div class="line">Cajero: {{ $invoice->user?->name ?? 'N/A' }}</div>
        <div class="line">Cliente: {{ $invoice->customer?->name ?? 'Clientes Varios' }}</div>

        <div class="sep"></div>

        {{-- Items --}}
        <table class="items">
            <thead>
                <tr>
                    <th class="col-desc">DESCRIPCION</th>
                    <th class="col-cant">CANT</th>
                    <th class="col-precio">PRECIO</th>
                    <th class="col-total">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->details as $detail)
                <tr>
                    <td class="col-desc">{{ $detail->receipt_description ?? format_product_name_for_receipt($detail->product_name) }}</td>
                    <td class="col-cant">{{ $detail->quantity }}</td>
                    <td class="col-precio">{{ money($detail->unit_price, $store->currency ?? 'COP', false) }}</td>
                    <td class="col-total">{{ money($detail->subtotal, $store->currency ?? 'COP', false) }}</td>
                </tr>
                @if((float) ($detail->discount_amount ?? 0) > 0)
                <tr>
                    <td class="col-desc" colspan="4">Desc. item: -{{ money($detail->discount_amount, $store->currency ?? 'COP', false) }}</td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>

        <div class="sep"></div>

        {{-- Totales --}}
        <div class="line">Subtotal: {{ money($invoice->subtotal, $store->currency ?? 'COP', false) }}</div>
        @if($invoice->discount > 0)
            <div class="line">Descuento: {{ money($invoice->discount, $store->currency ?? 'COP', false) }}</div>
        @endif
        <div class="line bold">TOTAL: {{ money($invoice->total, $store->currency ?? 'COP', false) }}</div>

        <div class="sep"></div>

        {{-- Pie --}}
        <div class="center line" style="font-size: 5pt; margin-top: 4px;">GRACIAS POR SU COMPRA PARA CAMBIOS RECUERDE TRAER SU FACTURA</div>
        <div class="center line" style="font-size: 5pt;">TIQUETE - ORIGINAL</div>
        <div class="center line" style="font-size: 5pt;">Factura Impresa por el software pos-{{ config('app.name') }}</div>

        @if(!empty($barcodeBase64))
        <div class="barcode-wrap">
            <img src="{{ $barcodeBase64 }}" alt="Barcode {{ $invoice->id }}" style="max-width: 85%; height: 32px;">
            <div class="barcode-text">*{{ $invoice->id }}*</div>
        </div>
        @endif
    </div>
</body>
</html>
