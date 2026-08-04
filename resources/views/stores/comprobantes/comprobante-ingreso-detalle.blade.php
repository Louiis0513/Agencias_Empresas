<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-white leading-tight">
                {{ $c->tipo_comprobante_id ? __('Recibo de caja') : __('Comprobante de ingreso') }} · {{ $c->number }}
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

            <article class="rounded-xl border border-gray-200 bg-white text-gray-900 shadow-sm overflow-hidden">
                <div class="p-6 sm:p-8 space-y-4">

                    {{-- Cabecera 3 cols: logo | datos empresa | título+número --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-start border-b border-gray-200 pb-4">
                        <div class="flex items-center justify-center min-h-[5.5rem]">
                            @if($store->logo_path)
                                <img src="{{ asset('storage/'.$store->logo_path) }}" alt="{{ $store->name }}"
                                     class="h-28 w-auto max-w-[220px] object-contain">
                            @endif
                        </div>
                        <div class="text-center text-xs sm:text-sm text-gray-700 space-y-0.5">
                            <p class="font-bold uppercase text-gray-900">{{ $store->name }}</p>
                            @if($store->rut_nit)
                                <p>{{ __('NIT') }} {{ $store->rut_nit }}</p>
                            @endif
                            @if($store->address)
                                <p>{{ $store->address }}</p>
                            @endif
                            @if($store->phone || ($store->mobile ?? null))
                                <p>{{ __('Teléfono') }} {{ $store->phone ?: $store->mobile }}</p>
                            @endif
                            @if($ciudadEmpresa !== '')
                                <p>{{ $ciudadEmpresa }}</p>
                            @endif
                        </div>
                        <div class="border border-gray-300 rounded text-center px-3 py-3">
                            <p class="text-base sm:text-lg font-bold text-gray-900">{{ $c->tipo_comprobante_id ? __('Recibo de caja') : __('Comprobante de ingreso') }}</p>
                            <p class="mt-1 text-sm font-semibold text-gray-800">{{ __('No.') }} {{ $c->number }}</p>
                        </div>
                    </div>

                    {{-- Cliente 2/3 + Fecha 1/3 --}}
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                        <div class="lg:col-span-2 border border-gray-300 rounded overflow-hidden text-sm">
                            <table class="w-full">
                                <tbody>
                                    <tr class="border-b border-gray-200">
                                        <td class="bg-gray-100 px-3 py-1.5 font-semibold text-gray-700 w-36 whitespace-nowrap">{{ __('Señores') }}</td>
                                        <td class="px-3 py-1.5 font-bold text-gray-900" colspan="3">
                                            {{ $customer?->nombre ?? '—' }}
                                        </td>
                                    </tr>
                                    <tr class="border-b border-gray-200">
                                        <td class="bg-gray-100 px-3 py-1.5 font-semibold text-gray-700">{{ __('NIT') }}</td>
                                        <td class="px-3 py-1.5 text-gray-800">
                                            {{ $customer?->numero_identificacion ?? '—' }}
                                            @if($customer?->digito_verificacion)
                                                -{{ $customer->digito_verificacion }}
                                            @endif
                                        </td>
                                        <td class="bg-gray-100 px-3 py-1.5 font-semibold text-gray-700 w-24">{{ __('Teléfono') }}</td>
                                        <td class="px-3 py-1.5 text-gray-800">{{ $customer?->telefono ?? '—' }}</td>
                                    </tr>
                                    <tr class="border-b border-gray-200">
                                        <td class="bg-gray-100 px-3 py-1.5 font-semibold text-gray-700">{{ __('Dirección') }}</td>
                                        <td class="px-3 py-1.5 text-gray-800" colspan="3">{{ $customer?->direccion ?? '—' }}</td>
                                    </tr>
                                    @if($c->centroCosto)
                                        <tr>
                                            <td class="bg-gray-100 px-3 py-1.5 font-semibold text-gray-700">{{ __('Centro de costos') }}</td>
                                            <td class="px-3 py-1.5 text-gray-800" colspan="3">
                                                {{ $c->centroCosto->codigo }} — {{ $c->centroCosto->nombre }}
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                        <div class="border border-gray-300 rounded overflow-hidden text-sm self-start">
                            <div class="bg-gray-100 px-3 py-1.5 font-semibold text-gray-700 text-center border-b border-gray-200">
                                {{ __('Fecha de recibo') }}
                            </div>
                            <div class="px-3 py-4 text-center font-bold text-gray-900 text-base">
                                {{ $c->date->format('d/m/Y') }}
                            </div>
                        </div>
                    </div>

                    {{-- Tabla ítems --}}
                    <div class="overflow-x-auto border border-gray-300 rounded">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-100 border-b border-gray-300">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-bold uppercase text-gray-800 w-14">{{ __('Ítem') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold uppercase text-gray-800">{{ __('Documento') }}</th>
                                    <th class="px-3 py-2 text-left text-xs font-bold uppercase text-gray-800">{{ __('Descripción') }}</th>
                                    <th class="px-3 py-2 text-right text-xs font-bold uppercase text-gray-800 w-36">{{ __('Valor') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($detalleLineasVista as $fila)
                                    <tr>
                                        <td class="px-3 py-2 text-gray-700">{{ $fila['item'] }}</td>
                                        <td class="px-3 py-2 text-gray-800">{{ $fila['documento'] !== '' ? $fila['documento'] : '—' }}</td>
                                        <td class="px-3 py-2 text-gray-800">{{ $fila['descripcion'] }}</td>
                                        <td class="px-3 py-2 text-right font-medium tabular-nums text-gray-900">{{ money($fila['valor'], $cur) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Totales + pie --}}
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 text-sm">
                        <div class="space-y-3">
                            <p>
                                <span class="font-semibold text-gray-700">{{ __('Total ítems') }}:</span>
                                <span class="text-gray-900">{{ $totalItems }}</span>
                            </p>
                            <div>
                                <p class="font-semibold text-gray-700">{{ __('Valor en letras') }}:</p>
                                <p class="mt-0.5 text-gray-900">{{ $valorEnLetras }}</p>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-700">{{ __('Condiciones de pago') }}:</p>
                                <div class="mt-0.5 flex flex-wrap justify-between gap-2 text-gray-900">
                                    <span>{{ $condicionPago }}</span>
                                    <span class="tabular-nums font-medium">{{ money($condicionPagoMonto, $cur) }}</span>
                                </div>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-700">{{ __('Observaciones') }}:</p>
                                <p class="mt-0.5 text-gray-800 whitespace-pre-line min-h-[1.5rem]">{{ filled($c->notes) ? $c->notes : '' }}</p>
                            </div>
                        </div>
                        <div class="space-y-2 lg:justify-self-end w-full max-w-xs">
                            <div class="flex border border-gray-300 overflow-hidden rounded">
                                <div class="bg-gray-100 px-3 py-2 font-semibold text-gray-800 flex-1">{{ __('Total pago') }}</div>
                                <div class="px-3 py-2 font-bold tabular-nums text-gray-900 bg-gray-50 text-right min-w-[8rem]">
                                    {{ money($c->total_amount, $cur) }}
                                </div>
                            </div>
                        </div>
                    </div>

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
