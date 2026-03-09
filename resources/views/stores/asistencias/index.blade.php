<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center flex-wrap gap-2">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Asistencias - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="{ showForm: false, customerId: {{ $customerId ? (int) $customerId : 'null' }} }"
         @filter-customer-selected.window="customerId = $event.detail.customer_id"
         @filter-customer-cleared.window="customerId = null">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 bg-green-100 dark:bg-green-900/30 border border-green-400 text-green-700 dark:text-green-300 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-100 dark:bg-red-900/30 border border-red-400 text-red-700 dark:text-red-300 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            {{-- Contadores --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 md:gap-4 mb-6 px-1 md:px-0">
                <div class="flex items-center gap-3 md:gap-4 rounded-xl border border-white/5 bg-slate-800/50 px-3 py-3 md:px-5 md:py-4 shadow-sm">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 shrink-0 items-center justify-center rounded-full bg-slate-700/80 text-base md:text-lg font-bold text-slate-100">
                        {{ $counters['asistencias_hoy'] ?? 0 }}
                    </div>
                    <div>
                        <p class="text-xs md:text-sm font-semibold uppercase tracking-wider text-slate-300">Asistencias Hoy</p>
                        <p class="text-xs text-slate-400">personas ingresaron</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 md:gap-4 rounded-xl border border-white/5 bg-slate-800/50 px-3 py-3 md:px-5 md:py-4 shadow-sm">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 shrink-0 items-center justify-center rounded-full bg-slate-700/80 text-base md:text-lg font-bold text-slate-100">
                        {{ $counters['asistencias_semana'] ?? 0 }}
                    </div>
                    <div>
                        <p class="text-xs md:text-sm font-semibold uppercase tracking-wider text-slate-300">Esta Semana</p>
                        <p class="text-xs text-slate-400">total semanal</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 md:gap-4 rounded-xl border border-brand/20 bg-brand/10 px-3 py-3 md:px-5 md:py-4 shadow-sm">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 shrink-0 items-center justify-center rounded-full bg-brand text-base md:text-lg font-bold text-white shadow-sm">
                        {{ $counters['active_clients'] ?? 0 }}
                    </div>
                    <p class="text-xs md:text-sm font-semibold uppercase tracking-wider text-blue-200">Miembros Activos</p>
                </div>
                <div class="flex items-center gap-3 md:gap-4 rounded-xl border border-white/5 bg-slate-800/50 px-3 py-3 md:px-5 md:py-4 shadow-sm">
                    <div class="flex h-10 w-10 md:h-12 md:w-12 shrink-0 items-center justify-center rounded-full bg-slate-700/80 text-base md:text-lg font-bold text-slate-100">
                        {{ $counters['promedio_diario'] ?? 0 }}
                    </div>
                    <div>
                        <p class="text-xs md:text-sm font-semibold uppercase tracking-wider text-slate-300">Promedio Diario</p>
                        <p class="text-xs text-slate-400">asistencias/día</p>
                    </div>
                </div>
            </div>

            {{-- Panel Registrar Asistencia --}}
            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                            <svg class="w-5 h-5 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            Registrar Asistencia
                        </h3>
                        <div class="flex items-center gap-3">
                            <button type="button" x-show="!showForm" x-on:click="showForm = true" x-cloak
                                class="inline-flex items-center px-4 py-2 bg-brand text-white text-sm font-medium rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                Registrar asistencia
                            </button>
                            <button type="button" x-show="showForm" x-on:click="showForm = false"
                                class="inline-flex items-center px-4 py-2 border border-white/10 rounded-xl text-sm font-medium text-gray-300 bg-white/5 hover:bg-white/10 transition">
                                Cerrar
                            </button>
                        </div>
                    </div>
                    <div x-show="showForm" x-cloak class="mt-6">
                        @livewire('registrar-asistencia-form', ['storeId' => $store->id])
                    </div>
                </div>
            </div>

            {{-- Filtros y tabla --}}
            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <form method="GET" action="{{ route('stores.asistencias', $store) }}" class="mb-6">
                        <input type="hidden" name="customer_id" :value="customerId || ''">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:flex-wrap">
                            <div class="flex-1 min-w-[200px]">
                                @livewire('customer-search-select', [
                                    'storeId' => $store->id,
                                    'selectedCustomerId' => $customerId,
                                    'emitEventName' => 'filter-customer-selected',
                                    'emitClearEventName' => 'filter-customer-cleared',
                                ])
                            </div>
                            <div>
                                <label for="from" class="block text-xs font-medium text-gray-400 uppercase mb-1">Desde</label>
                                <input type="date" name="from" id="from" value="{{ $from instanceof \Carbon\Carbon ? $from->format('Y-m-d') : '' }}"
                                    class="rounded-lg border border-white/10 bg-slate-900/60 text-gray-100 text-sm px-3 py-2 w-full min-w-[140px]">
                            </div>
                            <div>
                                <label for="to" class="block text-xs font-medium text-gray-400 uppercase mb-1">Hasta</label>
                                <input type="date" name="to" id="to" value="{{ $to instanceof \Carbon\Carbon ? $to->format('Y-m-d') : '' }}"
                                    class="rounded-lg border border-white/10 bg-slate-900/60 text-gray-100 text-sm px-3 py-2 w-full min-w-[140px]">
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="submit" class="px-4 py-2 bg-brand text-white font-semibold text-xs rounded-xl uppercase tracking-wider shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">
                                    Filtrar
                                </button>
                                <a href="{{ route('stores.asistencias', $store) }}" class="px-4 py-2 border border-white/10 text-xs font-semibold rounded-xl uppercase tracking-wider text-gray-200 hover:bg-white/5">
                                    Limpiar
                                </a>
                            </div>
                        </div>
                    </form>

                    @if($entries->count() > 0)
                        <p class="text-xs text-gray-400 mb-3">
                            Mostrando <span class="font-semibold text-gray-200">{{ $entries->total() }}</span> registros
                        </p>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5 bg-slate-900/40">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Fecha y hora</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Cliente</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Plan</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($entries as $entry)
                                        <tr class="hover:bg-white/5 transition">
                                            <td class="px-4 py-4 text-sm text-gray-100 whitespace-nowrap">{{ $entry->recorded_at->format('d/m/Y H:i') }}</td>
                                            <td class="px-4 py-4 text-sm font-medium text-gray-100">{{ $entry->customer?->name ?? '—' }}</td>
                                            <td class="px-4 py-4">
                                                @if($entry->customerSubscription?->storePlan?->name)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-brand/20 text-blue-200">
                                                        {{ $entry->customerSubscription->storePlan->name }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $entries->withQueryString()->links() }}
                        </div>
                    @else
                        <p class="text-center text-gray-400 py-8">
                            No hay registros de asistencia con los filtros indicados.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
