<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap justify-between items-center gap-3" @if(!$comprobante->isReversed()) x-data="anularComprobanteModal()" x-init="init()" @endif>
            <h2 class="font-semibold text-xl text-white leading-tight">
                {{ $tituloDocumento }} · {{ $comprobante->number }}
            </h2>
            <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                <a href="{{ route('stores.comprobantes-egreso.pdf', [$store, $comprobante]) }}"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-2 rounded-lg border border-white/20 bg-white/10 px-3 py-2 text-sm font-semibold text-gray-100 hover:bg-white/15 transition">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    {{ __('PDF / Imprimir') }}
                </a>
                @if(!$comprobante->isReversed())
                    <a href="{{ route('stores.comprobantes-egreso.edit', [$store, $comprobante]) }}"
                       class="inline-flex items-center px-3 py-2 sm:px-4 rounded-xl bg-brand text-white shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] text-sm font-medium">
                        {{ __('Editar') }}
                    </a>
                    <button type="button"
                            @click="$refs.modalAnular?.showModal()"
                            class="px-3 py-2 sm:px-4 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium">
                        {{ __('Anular') }}
                    </button>
                @endif
                <a href="{{ route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'egresos']) }}" wire:navigate class="text-sm text-gray-400 hover:text-brand transition">
                    ← {{ __('Movimientos') }}
                </a>
            </div>
            @if(!$comprobante->isReversed())
            <dialog x-ref="modalAnular" class="rounded-xl shadow-xl max-w-lg w-full p-0 backdrop:bg-black/50"
                    @click.self="$refs.modalAnular.close()">
                <div class="bg-white text-gray-900 p-6">
                    <h3 class="text-lg font-semibold mb-2">{{ __('Anular comprobante :num', ['num' => $comprobante->number]) }}</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        {{ __('Indica a qué bolsillos se devolverá el dinero. La suma debe coincidir con el total:') }}
                        <strong>{{ money($comprobante->total_amount, $store->currency ?? 'COP', false) }}</strong>
                    </p>
                    <form method="POST" action="{{ route('stores.comprobantes-egreso.anular', [$store, $comprobante]) }}" id="form-anular">
                        @csrf
                        <div class="space-y-2 mb-4" id="origenes-reverso-container">
                            @foreach($comprobante->origenes as $i => $o)
                            <div class="origen-reverso-row flex flex-wrap gap-2 items-center">
                                <select name="origenes[{{ $i }}][bolsillo_id]" class="flex-1 min-w-[160px] rounded-lg border border-gray-300 bg-white text-gray-900 text-sm px-2 py-2" required>
                                    <option value="">{{ __('Bolsillo') }}</option>
                                    @foreach($bolsillos as $b)
                                        <option value="{{ $b->id }}" {{ $o->bolsillo_id == $b->id ? 'selected' : '' }}>{{ $b->name }} ({{ money($b->saldo, $store->currency ?? 'COP', false) }})</option>
                                    @endforeach
                                </select>
                                <input type="text" name="origenes[{{ $i }}][reference]" value="{{ $o->reference }}" class="w-28 rounded-lg border border-gray-300 text-sm px-2 py-2" placeholder="{{ __('Ref.') }}">
                                <input type="number" name="origenes[{{ $i }}][amount]" step="0.01" min="0.01" value="{{ $o->amount }}" class="w-24 rounded-lg border border-gray-300 text-sm px-2 py-2 origen-amount" required placeholder="{{ __('Monto') }}">
                                <button type="button" class="remove-origen-reverso text-red-600 hover:text-red-800 text-sm {{ $comprobante->origenes->count() > 1 ? '' : 'hidden' }}">✕</button>
                            </div>
                            @endforeach
                        </div>
                        <button type="button" id="add-origen-reverso" class="mb-4 text-sm text-brand font-medium hover:underline">+ {{ __('Agregar bolsillo') }}</button>
                        <p class="text-xs text-amber-700 mb-4" x-show="!sumaCoincide" x-transition>
                            {{ __('La suma debe coincidir con el total (:total).', ['total' => money($comprobante->total_amount, $store->currency ?? 'COP', false)]) }} (<span x-text="sumaOrigenes.toFixed(2)"></span>)
                        </p>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="$refs.modalAnular.close()" class="px-3 py-2 text-gray-700 hover:bg-gray-100 rounded-lg text-sm">
                                {{ __('Cancelar') }}
                            </button>
                            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium disabled:opacity-50"
                                    :disabled="!sumaCoincide">
                                {{ __('Confirmar anulación') }}
                            </button>
                        </div>
                    </form>
                </div>
            </dialog>
            @endif
        </div>
    </x-slot>

    <div class="py-8 sm:py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-4 py-3 rounded-lg" role="alert">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            @if($c->isReversed())
                <div class="mb-4 rounded-lg border border-red-400/40 bg-red-500/10 px-4 py-3 text-sm text-red-200">
                    {{ __('Este comprobante fue anulado o revertido y no tiene efecto contable.') }}
                </div>
            @endif

            <article class="rounded-xl border border-gray-200 bg-white text-gray-900 shadow-sm overflow-hidden">
                <div class="p-6 sm:p-8 space-y-5">

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
                            <p class="text-lg sm:text-xl font-bold text-blue-800 leading-tight">{{ $tituloDocumento }}</p>
                            <p class="mt-3 text-base font-bold text-gray-900">{{ __('No.') }} {{ $c->number }}</p>
                            <p class="mt-2 inline-flex rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                                {{ $tipoEtiqueta }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 p-4 sm:p-5">
                        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12 lg:items-start">
                            <div class="lg:col-span-2">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Fecha') }}</p>
                                <p class="mt-1 text-sm font-bold text-gray-900">{{ $c->payment_date->format('d/m/Y') }}</p>
                            </div>
                            <div class="lg:col-span-7 border-t border-gray-100 pt-4 lg:border-t-0 lg:pt-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Pagado a') }}</p>
                                <p class="mt-1 text-sm font-bold text-gray-900">{{ $pagadoNombre }}</p>
                                <div class="mt-3 grid grid-cols-1 gap-2 text-xs text-gray-600 sm:grid-cols-3">
                                    <div>
                                        <span class="font-medium text-gray-500">{{ __('NIT') }}</span>
                                        <span class="block text-gray-800">{{ $pagadoNit }}</span>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <span class="font-medium text-gray-500">{{ __('Dirección') }}</span>
                                        <span class="block text-gray-800">{{ $pagadoDireccion }}</span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-gray-500">{{ __('Ciudad') }}</span>
                                        <span class="block text-gray-800">{{ $pagadoCiudad }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="lg:col-span-3 border-t border-gray-100 pt-4 lg:border-t-0 lg:pt-0 lg:text-right">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Valor') }}</p>
                                <p class="mt-1 text-2xl font-bold text-gray-900 tabular-nums">{{ money($c->total_amount, $cur) }}</p>
                            </div>
                        </div>
                    </div>

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
                                    @foreach($detalleLineasVista as $idx => $fila)
                                        <tr>
                                            <td class="px-3 py-2.5 text-gray-800">
                                                <span class="block">{{ $fila['descripcion'] }}</span>
                                                @if($c->type !== \App\Models\ComprobanteEgreso::TYPE_PAGO_CUENTA && isset($c->destinos[$idx]) && $c->destinos[$idx]->isCuentaPorPagar() && $c->destinos[$idx]->accountPayable)
                                                    <a href="{{ route('stores.accounts-payables.show', [$store, $c->destinos[$idx]->accountPayable]) }}" class="text-xs text-blue-700 hover:underline mt-1 inline-block">{{ __('Ver CxP') }}</a>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2.5 text-right font-medium tabular-nums text-gray-900">{{ money($fila['valor'], $cur) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-stretch">
                        <div class="min-h-[100px] lg:min-h-[140px] rounded-lg border border-dashed border-gray-300 bg-gray-50/80" aria-hidden="true"></div>
                        <div class="rounded-lg border border-gray-200 overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold uppercase text-gray-800">{{ __('Forma de pago') }}</th>
                                        <th scope="col" class="px-3 py-2.5 text-left text-xs font-bold uppercase text-gray-800 w-36">{{ __('Identificación') }}</th>
                                        <th scope="col" class="px-3 py-2.5 text-right text-xs font-bold uppercase text-gray-800 w-32">{{ __('Valor') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach($c->origenes as $o)
                                        <tr>
                                            <td class="px-3 py-2.5 text-gray-800">{{ $o->bolsillo->name ?? '—' }}</td>
                                            <td class="px-3 py-2.5 text-gray-600">{{ $o->reference ?? '—' }}</td>
                                            <td class="px-3 py-2.5 text-right font-medium tabular-nums text-gray-900">{{ money((float) $o->amount, $cur) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-gray-50/50 p-4">
                        <p class="text-xs font-semibold uppercase text-gray-500">{{ __('Valor (en letras)') }}</p>
                        <p class="mt-2 text-sm font-bold leading-relaxed text-gray-900">{{ $valorEnLetras }}</p>
                    </div>

                    <p class="text-xs text-gray-500">{{ __('Registrado por') }}: <span class="font-medium text-gray-700">{{ $c->user->name ?? '—' }}</span></p>

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

    @if(!$comprobante->isReversed())
    <script>
        function anularComprobanteModal() {
            const totalComprobante = {{ $comprobante->total_amount }};
            const bolsillos = @json($bolsillos->map(fn($b) => ['id' => $b->id, 'name' => $b->name, 'saldo' => $b->saldo]));
            return {
                get sumaOrigenes() {
                    return Array.from(document.querySelectorAll('.origen-amount'))
                        .reduce((sum, el) => sum + parseFloat(el?.value || 0), 0);
                },
                get sumaCoincide() {
                    return Math.abs(this.sumaOrigenes - totalComprobante) < 0.01;
                },
                init() {
                    this.$nextTick(() => this.bindOrigenesReverso());
                },
                bindOrigenesReverso() {
                    const container = document.getElementById('origenes-reverso-container');
                    const addBtn = document.getElementById('add-origen-reverso');
                    if (!container || !addBtn) return;

                    addBtn.onclick = () => {
                        const idx = container.querySelectorAll('.origen-reverso-row').length;
                        const optLabel = {!! json_encode(__('Bolsillo')) !!};
                        const phRef = {!! json_encode(__('Ref.')) !!};
                        const phMonto = {!! json_encode(__('Monto')) !!};
                        const row = document.createElement('div');
                        row.className = 'origen-reverso-row flex flex-wrap gap-2 items-center';
                        row.innerHTML =
                            '<select name="origenes[' + idx + '][bolsillo_id]" class="flex-1 min-w-[160px] rounded-lg border border-gray-300 bg-white text-gray-900 text-sm px-2 py-2" required>' +
                            '<option value="">' + optLabel + '</option>' +
                            bolsillos.map(b => '<option value="' + b.id + '">' + b.name + ' (' + parseFloat(b.saldo).toFixed(2) + ')</option>').join('') +
                            '</select>' +
                            '<input type="text" name="origenes[' + idx + '][reference]" class="w-28 rounded-lg border border-gray-300 text-sm px-2 py-2" placeholder="' + phRef + '">' +
                            '<input type="number" name="origenes[' + idx + '][amount]" step="0.01" min="0.01" class="w-24 rounded-lg border border-gray-300 text-sm px-2 py-2 origen-amount" required placeholder="' + phMonto + '">' +
                            '<button type="button" class="remove-origen-reverso text-red-600 hover:text-red-800 text-sm">✕</button>';
                        container.appendChild(row);
                        this.toggleRemoveButtons();
                        row.querySelector('.remove-origen-reverso').onclick = () => { row.remove(); this.toggleRemoveButtons(); };
                    };

                    container.querySelectorAll('.remove-origen-reverso').forEach(btn => {
                        btn.onclick = () => {
                            btn.closest('.origen-reverso-row').remove();
                            this.toggleRemoveButtons();
                        };
                    });
                },
                toggleRemoveButtons() {
                    const rows = document.querySelectorAll('.origen-reverso-row');
                    rows.forEach((r) => {
                        const btn = r.querySelector('.remove-origen-reverso');
                        if (btn) btn.classList.toggle('hidden', rows.length <= 1);
                    });
                }
            };
        }
    </script>
    @endif
</x-app-layout>
