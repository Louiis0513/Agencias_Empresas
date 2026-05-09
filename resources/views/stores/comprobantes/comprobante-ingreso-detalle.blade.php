<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-white leading-tight">
                {{ __('Comprobante de ingreso') }} · {{ $c->number }}
            </h2>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('stores.comprobantes-ingreso.pdf', [$store, $comprobanteIngreso]) }}"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm font-semibold text-gray-100 hover:bg-white/15 transition">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    {{ __('PDF / Imprimir') }}
                </a>
                <a href="{{ route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'ingresos']) }}" wire:navigate class="text-sm text-gray-400 hover:text-brand transition">← {{ __('Movimientos') }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-lg" role="alert">
                    {{ session('success') }}
                </div>
            @endif

            @if($c->isReversed())
                <div class="mb-4 rounded-lg border border-red-400/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                    {{ __('Este comprobante fue revertido y no tiene efecto contable.') }}
                </div>
            @endif

            {{-- Documento tipo formulario impreso --}}
            <article class="rounded-xl border border-gray-200 bg-white text-gray-900 shadow-sm overflow-hidden">
                <div class="p-6 sm:p-8 space-y-5">

                    {{-- Cabecera: tienda | documento --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="rounded-lg border border-gray-200 p-4 sm:p-5">
                            <p class="text-lg sm:text-xl font-bold text-blue-800 leading-tight">{{ $store->name }}</p>
                            @if($store->rut_nit)
                                <p class="mt-2 text-sm text-gray-600">{{ __('NIT.') }} {{ $store->rut_nit }}</p>
                            @endif
                            @if($dirTel !== '')
                                <p class="mt-1 text-xs sm:text-sm text-gray-500">{{ $dirTel }}</p>
                            @endif
                        </div>
                        <div class="rounded-lg border border-gray-200 p-4 sm:p-5 text-right sm:text-left">
                            <p class="text-lg sm:text-xl font-bold text-blue-800">{{ __('Comprobante de ingreso') }}</p>
                            <p class="mt-3 text-base font-bold text-gray-900">{{ __('No.') }} {{ $c->number }}</p>
                            <p class="mt-2 inline-flex rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                                {{ $tipoEtiqueta }}
                            </p>
                        </div>
                    </div>

                    {{-- Fecha, cliente, valor --}}
                    <div class="rounded-lg border border-gray-200 p-4 sm:p-5">
                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:items-start">
                            <div class="lg:col-span-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Fecha') }}</p>
                                <p class="mt-1 text-sm font-bold text-gray-900">{{ $c->date->format('d/m/Y') }}</p>
                            </div>
                            <div class="lg:col-span-7 border-t border-gray-100 pt-4 lg:border-t-0 lg:pt-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Recibido de') }}</p>
                                <p class="mt-1 text-sm font-bold text-gray-900">
                                    {{ $customer?->name ?? '—' }}
                                </p>
                                <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-gray-600 sm:grid-cols-3">
                                    <div>
                                        <span class="font-medium text-gray-500">{{ __('CC') }}</span>
                                        <span class="block text-gray-800">{{ $customer?->document_number ?? '—' }}</span>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <span class="font-medium text-gray-500">{{ __('Dirección') }}</span>
                                        <span class="block text-gray-800">{{ $customer?->address ?? '—' }}</span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-500">{{ __('Ciudad') }}</span>
                                        <span class="block text-gray-800">—</span>
                                    </div>
                                </div>
                            </div>
                            <div class="lg:col-span-3 border-t border-gray-100 pt-4 lg:border-t-0 lg:pt-0 lg:text-right">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Valor') }}</p>
                                <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums">{{ money($c->total_amount, $cur) }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Tabla detalle --}}
                    <div class="space-y-2">
                        <p class="text-xs font-semibold text-gray-700">{{ $detalleSubtitulo }}</p>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold uppercase text-gray-800">{{ __('Descripción') }}</th>
                                        <th scope="col" class="px-3 py-2.5 text-right text-xs font-bold uppercase text-gray-800 w-36">{{ __('Valor') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach($detalleLineasVista as $fila)
                                        <tr>
                                            <td class="px-3 py-2.5 text-gray-800">{{ $fila['descripcion'] }}</td>
                                            <td class="px-3 py-2.5 text-right font-medium tabular-nums text-gray-900">{{ money($fila['valor'], $cur) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Bloque reservado + forma de pago (bolsillos) --}}
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-stretch">
                        <div class="min-h-[100px] lg:min-h-[140px] rounded-lg border border-dashed border-gray-300 bg-gray-50/80" aria-hidden="true"></div>
                        <div class="rounded-lg border border-gray-200 overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold uppercase text-gray-800">{{ __('Forma de pago') }}</th>
                                        <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold uppercase text-gray-800 w-28">{{ __('Identificación') }}</th>
                                        <th scope="col" class="px-3 py-2.5 text-right text-xs font-bold uppercase text-gray-800 w-32">{{ __('Valor') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach($c->destinos as $d)
                                        <tr>
                                            <td class="px-3 py-2.5 text-gray-800">{{ $d->bolsillo->name ?? '—' }}</td>
                                            <td class="px-3 py-2.5 text-gray-400">—</td>
                                            <td class="px-3 py-2.5 text-right font-medium tabular-nums text-gray-900">{{ money((float) $d->amount, $cur) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Valor en letras --}}
                    <div class="rounded-lg border border-gray-200 bg-gray-50/50 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-500">{{ __('Valor (en letras)') }}</p>
                        <p class="mt-2 text-sm font-bold leading-relaxed text-gray-900">{{ $valorEnLetras }}</p>
                    </div>

                    @if($c->aplicaciones->isNotEmpty())
                        <div class="rounded-lg border border-gray-200 overflow-hidden">
                            <p class="bg-gray-100 px-3 py-2 text-xs font-bold uppercase text-gray-800">{{ __('Aplicado a cuentas por cobrar') }}</p>
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-white border-b border-gray-200">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-600">{{ __('Factura') }}</th>
                                        <th class="px-3 py-2 text-right text-xs font-medium text-gray-600">{{ __('Monto aplicado') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($c->aplicaciones as $ap)
                                        <tr>
                                            <td class="px-3 py-2">
                                                <a href="{{ route('stores.accounts-receivables.show', [$store, $ap->accountReceivable]) }}" class="text-blue-700 hover:underline font-medium">
                                                    {{ __('Factura #:id', ['id' => $ap->accountReceivable->invoice->id]) }}
                                                </a>
                                            </td>
                                            <td class="px-3 py-2 text-right tabular-nums">{{ money((float) $ap->amount, $cur) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- Firmas --}}
                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-12 pt-2">
                        <div class="lg:col-span-7 grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
                            @foreach ([__('Preparado'), __('Aprobado'), __('Contabilizado'), __('Revisado')] as $label)
                                <div class="flex flex-col rounded-lg border border-gray-200 min-h-[88px] p-2">
                                    <span class="text-[10px] font-semibold uppercase text-center text-gray-600">{{ $label }}</span>
                                    <span class="mt-auto border-t border-gray-300 pt-6 block"></span>
                                </div>
                            @endforeach
                        </div>
                        <div class="lg:col-span-5 rounded-lg border border-gray-200 p-4 min-h-[120px] flex flex-col">
                            <p class="text-xs font-bold uppercase text-center text-gray-800">{{ __('Firma de recibido') }}</p>
                            <div class="mt-auto pt-8 border-t border-gray-400"></div>
                            <p class="mt-2 text-center text-[10px] font-medium text-gray-500">{{ __('C.C. o NIT') }}</p>
                        </div>
                    </div>

                    {{-- Pie impresión --}}
                    <footer class="border-t border-gray-200 pt-4 mt-2">
                        <p class="text-[10px] text-gray-400 leading-relaxed">
                            {{ __('Impreso con :name — v:version — :url', [
                                'name' => config('centradia.print_name'),
                                'version' => config('centradia.print_version'),
                                'url' => config('centradia.print_url'),
                            ]) }}
                        </p>
                    </footer>
                </div>
            </article>
        </div>
    </div>
</x-app-layout>
