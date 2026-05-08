{{--
    Gestión de bolsillos desde Configuración → Caja.
    Requiere: $store, $bolsillosConfig (paginador)
--}}
<div class="space-y-6">
    <div class="rounded-xl border border-white/10 bg-dark-card p-6">
        <h3 class="font-medium text-white mb-1">{{ __('Medios de pago y bolsillos') }}</h3>
        <p class="text-sm text-gray-400 mb-6">{{ __('Define cajas en efectivo, cuentas bancarias u otros medios donde se registran ingresos y egresos.') }}</p>

        <div class="mb-6 flex flex-wrap gap-2 justify-between items-center">
            <form method="GET" action="{{ route('stores.configuracion', $store) }}" class="flex gap-2 flex-wrap items-center">
                <input type="hidden" name="panel" value="caja">
                <input type="text" name="bolsillo_search" value="{{ request('bolsillo_search') }}" placeholder="{{ __('Buscar bolsillo') }}"
                       class="rounded-lg border-white/10 bg-white/5 text-gray-100 px-3 py-2 shadow-sm focus:ring-brand focus:border-brand min-w-[200px]">
                <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] font-medium text-sm">{{ __('Buscar') }}</button>
                @if(request('bolsillo_search'))
                    <a href="{{ route('stores.configuracion', $store) }}?panel=caja" wire:navigate class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 text-sm">{{ __('Limpiar') }}</a>
                @endif
            </form>
            @storeCan($store, 'caja.bolsillos.create')
                <button type="button" x-data x-on:click="$dispatch('open-modal', 'create-bolsillo')"
                        class="inline-flex items-center px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] font-medium text-sm shrink-0">
                    <svg class="w-4 h-4 mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ __('Crear bolsillo') }}
                </button>
            @endstoreCan
        </div>

        @if($bolsillosConfig->count() > 0)
            <div class="overflow-x-auto rounded-lg border border-white/5">
                <table class="min-w-full divide-y divide-white/5">
                    <thead class="border-b border-white/5 bg-white/[0.02]">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Nombre') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Detalles') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Saldo') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Tipo') }}</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">{{ __('Estado') }}</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">{{ __('Acciones') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        @foreach($bolsillosConfig as $b)
                            <tr class="hover:bg-white/5 transition">
                                <td class="px-4 py-3">
                                    <a href="{{ route('stores.cajas.bolsillos.show', [$store, $b]) }}" wire:navigate class="text-sm font-medium text-brand hover:underline">
                                        {{ $b->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-400 max-w-xs truncate" title="{{ $b->detalles }}">{{ $b->detalles ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-100">{{ money($b->saldo, $store->currency ?? 'COP') }}</td>
                                <td class="px-4 py-3 text-sm text-gray-400">{{ $b->is_bank_account ? __('Cuenta bancaria') : __('Efectivo') }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $b->is_active ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ $b->is_active ? __('Activo') : __('Inactivo') }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right text-sm font-medium whitespace-nowrap">
                                    <a href="{{ route('stores.cajas.bolsillos.show', [$store, $b]) }}" wire:navigate class="text-brand hover:underline mr-3">{{ __('Movimientos') }}</a>
                                    @storeCan($store, 'caja.bolsillos.edit')
                                        <button type="button" x-data x-on:click="$dispatch('open-edit-bolsillo-modal', { id: {{ $b->id }} })" class="text-brand hover:underline mr-3">{{ __('Editar') }}</button>
                                    @endstoreCan
                                    @storeCan($store, 'caja.bolsillos.destroy')
                                        <form method="POST" action="{{ route('stores.cajas.bolsillos.destroy', [$store, $b]) }}" class="inline"
                                              onsubmit="return confirm(@json(__('¿Eliminar el bolsillo «:name»? Debe tener saldo 0.', ['name' => $b->name])));">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-400 hover:underline">{{ __('Eliminar') }}</button>
                                        </form>
                                    @endstoreCan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $bolsillosConfig->links() }}</div>
        @else
            <div class="text-center py-12 rounded-lg border border-dashed border-white/15">
                <p class="text-gray-400">{{ __('No hay bolsillos. Crea efectivo, otras cajas o cuentas bancarias para registrar movimientos.') }}</p>
                @storeCan($store, 'caja.bolsillos.create')
                    <div class="mt-4">
                        <button type="button" x-data x-on:click="$dispatch('open-modal', 'create-bolsillo')"
                                class="inline-flex items-center px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] font-medium text-sm">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            {{ __('Crear bolsillo') }}
                        </button>
                    </div>
                @endstoreCan
            </div>
        @endif
    </div>
</div>
