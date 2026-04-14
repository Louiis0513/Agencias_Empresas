@php
    use App\Models\SupportDocument;
    $currency = $store->currency ?? 'COP';
    $numeroDoc = $document->doc_prefix.'-'.$document->doc_number;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Documento soporte {{ $numeroDoc }}</title>
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
        .watermark { font-size: 6pt; font-weight: bold; }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="center bold line" style="font-size: 7pt;">{{ $store->name }}</div>
        @if($store->rut_nit)
            <div class="center line">Nit. {{ $store->rut_nit }}</div>
        @endif
        @if($store->regimen)
            <div class="center line">{{ $store->regimen }}</div>
        @endif
        @if($store->department || $store->city)
            <div class="center line">{{ trim(($store->department ?? '').($store->department && $store->city ? ' - ' : '').($store->city ?? '')) }}</div>
        @endif
        @if($store->address || $store->phone)
            <div class="center line">{{ trim(($store->address ?? '').($store->phone ? ' Tel:'.$store->phone : '')) }}</div>
        @endif

        <div class="sep"></div>

        @if($document->status === SupportDocument::STATUS_BORRADOR)
            <div class="center watermark line" style="margin-top: 4px;">**** BORRADOR ****</div>
        @endif
        @if($document->status === SupportDocument::STATUS_ANULADO)
            <div class="center watermark line" style="margin-top: 4px;">*** ANULADO ***</div>
        @endif

        <div class="center bold line" style="margin-top: 6px; font-size: 6pt;">DOCUMENTO SOPORTE</div>
        <div class="line">N°: {{ $numeroDoc }}</div>
        <div class="line">Fecha emisión: {{ $document->issue_date->format('d/m/Y') }}</div>
        <div class="line">Registro: {{ $document->created_at->format('d/m/Y H:i') }}</div>
        <div class="line">Estado: {{ $document->status }}</div>

        <div class="sep"></div>

        <div class="bold line">Proveedor / vendedor</div>
        <div class="line">{{ $document->proveedor?->nombre ?? 'Sin proveedor' }}</div>
        @if($document->proveedor?->nit)
            <div class="line">NIT: {{ $document->proveedor->nit }}</div>
        @endif
        @if($document->proveedor?->direccion)
            <div class="line">{{ $document->proveedor->direccion }}</div>
        @endif

        <div class="sep"></div>

        <div class="bold line">Condición de pago</div>
        @if($document->payment_status === SupportDocument::PAYMENT_PENDIENTE)
            <div class="line">
                Crédito @if($document->due_date) — Vence {{ $document->due_date->format('d/m/Y') }} @endif
            </div>
        @else
            <div class="line">Contado</div>
            @if($document->comprobanteEgreso && $document->comprobanteEgreso->origenes->isNotEmpty())
                @php
                    $origenesPago = $document->comprobanteEgreso->origenes;
                    $hayEfectivo = $origenesPago->contains(function ($o) {
                        $b = $o->bolsillo;

                        return ! $b || ! $b->is_bank_account;
                    });
                @endphp
                @if($hayEfectivo)
                    <div class="line">Efectivo</div>
                @endif
                @foreach($origenesPago as $origen)
                    @php
                        $bolsilloOrigen = $origen->bolsillo;
                        $esTransferencia = $bolsilloOrigen && $bolsilloOrigen->is_bank_account;
                        $refPago = trim((string) ($origen->reference ?? ''));
                    @endphp
                    @if($esTransferencia)
                        <div class="line">
                            Transferencia bancaria
                            @if($refPago !== '')
                                — Ref. {{ $refPago }}
                            @endif
                        </div>
                    @endif
                @endforeach
            @elseif($document->payment_status === SupportDocument::PAYMENT_PAGADO && ! $document->comprobante_egreso_id)
                <div class="line" style="font-size: 5pt;">Al aprobar se registrará la condición de pago.</div>
            @endif
        @endif

        <div class="sep"></div>

        @if($document->inventoryItems->isNotEmpty() || $document->serviceItems->isNotEmpty())
            <table class="items">
                <thead>
                    <tr>
                        <th class="col-desc">DESCRIPCION</th>
                        <th class="col-cant">CANT</th>
                        <th class="col-precio">COSTO</th>
                        <th class="col-total">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($document->inventoryItems as $line)
                        <tr>
                            <td class="col-desc">{{ $line->description ?: ($line->product?->name ?? 'Ítem') }}</td>
                            <td class="col-cant">{{ $line->quantity }}</td>
                            <td class="col-precio">{{ money($line->unit_cost, $currency, false) }}</td>
                            <td class="col-total">{{ money($line->line_total, $currency, false) }}</td>
                        </tr>
                    @endforeach
                    @foreach($document->serviceItems as $line)
                        <tr>
                            <td class="col-desc">{{ $line->service_name }}{{ $line->description ? ' — '.$line->description : '' }}</td>
                            <td class="col-cant">{{ $line->quantity }}</td>
                            <td class="col-precio">{{ money($line->unit_cost, $currency, false) }}</td>
                            <td class="col-total">{{ money($line->line_total, $currency, false) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="sep"></div>

        <div class="line">Subtotal bruto: {{ money($document->subtotal, $currency, false) }}</div>
        <div class="line">IVA: {{ money($document->tax_total, $currency, false) }}</div>
        <div class="line bold">TOTAL: {{ money($document->total, $currency, false) }}</div>

        @if($document->notes)
            <div class="sep"></div>
            <div class="line bold">Observaciones</div>
            <div class="line">{{ $document->notes }}</div>
        @endif

        <div class="sep"></div>

        <div class="center line" style="font-size: 5pt; margin-top: 4px;">DOCUMENTO SOPORTE DE COMPRA — TIQUETE</div>
        <div class="center line" style="font-size: 5pt;">Impreso por {{ config('app.name') }}</div>

        @if(!empty($barcodeBase64))
            <div class="barcode-wrap">
                <img src="{{ $barcodeBase64 }}" alt="Barcode {{ $document->id }}" style="max-width: 85%; height: 32px;">
                <div class="barcode-text">*{{ $document->id }}*</div>
            </div>
        @endif
    </div>
</body>
</html>
