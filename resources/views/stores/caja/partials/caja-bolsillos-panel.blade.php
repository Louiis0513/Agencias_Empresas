@php($usaCrudCajaLocal = ! ($canAccessStoreConfig ?? false))
<div class="space-y-6 mt-10 pt-8 border-t border-white/10">
    <h3 class="text-lg font-semibold text-white">{{ __('Bolsillos y saldos') }}</h3>

    @if(session('success'))
        <div class="mb-4 bg-green-100 dark:bg-green-900/30 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-red-100 dark:bg-red-900/30 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded relative" role="alert">
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    {{-- Total caja (suma de todos los bolsillos) --}}
    <div class="p-4 bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-lg">
        <p class="text-sm text-indigo-700 dark:text-indigo-300">{{ __('Total caja (suma de bolsillos)') }}</p>
        <p class="text-2xl font-bold text-indigo-900 dark:text-indigo-100">{{ money($totalCaja, $store->currency ?? 'COP') }}</p>
        <p class="text-xs text-indigo-600 dark:text-indigo-400 mt-1">{{ __('Sin bolsillos, la caja está vacía.') }}</p>
        @if($canAccessStoreConfig ?? false)
            <div class="mt-4 pt-4 border-t border-indigo-200/40 dark:border-indigo-700/50">
                <p class="text-sm text-indigo-700 dark:text-indigo-300 mb-2">{{ __('Los medios de pago y bolsillos se administran en la configuración de la tienda.') }}</p>
                <a href="{{ route('stores.configuracion', $store) }}?panel=caja" wire:navigate class="inline-flex items-center px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.25)] hover:shadow-[0_0_20px_rgba(34,114,255,0.35)] font-medium text-sm">
                    {{ __('Ir a Configuración — Caja') }}
                </a>
            </div>
        @endif
    </div>

    <div class="flex flex-wrap gap-2 justify-between items-center">
        <form method="GET" action="{{ route('stores.cajas.movimientos', $store) }}" class="flex gap-2 flex-wrap">
            @foreach(request()->except(['bolsillo_search', 'bolsillo_page']) as $key => $value)
                @if(is_array($value))
                    @continue
                @endif
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <input type="text" name="bolsillo_search" value="{{ request('bolsillo_search') }}" placeholder="{{ __('Buscar bolsillo') }}" class="rounded-md border-white/10 bg-white/5 text-gray-100 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
            <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">{{ __('Buscar') }}</button>
            @if(request('bolsillo_search'))
                <a href="{{ route('stores.cajas.movimientos', ['store' => $store, 'tab' => $tab ?? 'ingresos']) }}" wire:navigate class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">{{ __('Limpiar') }}</a>
            @endif
        </form>
        @if($usaCrudCajaLocal)
            <button type="button" x-on:click="$dispatch('open-modal', 'create-bolsillo')" class="inline-flex items-center px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] font-medium">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                {{ __('Crear bolsillo') }}
            </button>
        @endif
    </div>

    @if($bolsillosListado->count() > 0)
        <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
            <div class="p-6">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/5">
                        <thead class="border-b border-white/5">
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
                            @foreach($bolsillosListado as $b)
                                <tr class="hover:bg-white/5 transition">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('stores.cajas.bolsillos.show', [$store, $b]) }}" wire:navigate class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline">
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
                                    <td class="px-4 py-3 text-right text-sm font-medium">
                                        <a href="{{ route('stores.cajas.bolsillos.show', [$store, $b]) }}" wire:navigate class="text-indigo-600 dark:text-indigo-400 hover:underline mr-3">{{ __('Movimientos') }}</a>
                                        @if($usaCrudCajaLocal)
                                            <button type="button" x-on:click="$dispatch('open-edit-bolsillo-modal', { id: {{ $b->id }} })" class="text-indigo-600 dark:text-indigo-400 hover:underline mr-3">{{ __('Editar') }}</button>
                                            <form method="POST" action="{{ route('stores.cajas.bolsillos.destroy', [$store, $b]) }}" class="inline" onsubmit="return confirm('¿Eliminar el bolsillo «{{ $b->name }}»? Debe tener saldo 0.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">{{ __('Eliminar') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $bolsillosListado->links('pagination::tailwind') }}</div>
            </div>
        </div>
    @else
        <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
            <div class="p-6 text-center py-12">
                <p class="text-gray-400">{{ __('No hay bolsillos. La caja está vacía.') }}</p>
                @if($usaCrudCajaLocal)
                    <div class="mt-4">
                        <button type="button" x-on:click="$dispatch('open-modal', 'create-bolsillo')" class="inline-flex items-center px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            {{ __('Crear bolsillo') }}
                        </button>
                    </div>
                @elseif($canAccessStoreConfig ?? false)
                    <div class="mt-4">
                        <a href="{{ route('stores.configuracion', $store) }}?panel=caja" wire:navigate class="inline-flex items-center px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] font-medium">
                            {{ __('Crear bolsillos en Configuración') }}
                        </a>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
