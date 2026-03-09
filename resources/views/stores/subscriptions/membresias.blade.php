<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Membresías - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data>
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

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6 space-y-6">
                    {{-- Tarjetas de métricas --}}
                    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 md:gap-4 px-1 md:px-0">
                        <div class="flex items-center gap-3 md:gap-4 rounded-xl border border-white/5 bg-slate-800/50 px-3 py-3 md:px-5 md:py-4 shadow-sm">
                            <div class="flex h-10 w-10 md:h-12 md:w-12 shrink-0 items-center justify-center rounded-full bg-slate-700/80 text-base md:text-lg font-bold text-slate-100">
                                {{ $counters['total_clients'] ?? 0 }}
                            </div>
                            <p class="text-xs md:text-sm font-semibold uppercase tracking-wider text-slate-300">Total Clientes</p>
                        </div>
                        <div class="flex items-center gap-3 md:gap-4 rounded-xl border border-emerald-500/20 bg-emerald-950/40 px-3 py-3 md:px-5 md:py-4 shadow-sm">
                            <div class="flex h-10 w-10 md:h-12 md:w-12 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-base md:text-lg font-bold text-white shadow-sm">
                                {{ $counters['active_clients'] ?? 0 }}
                            </div>
                            <p class="text-xs md:text-sm font-semibold uppercase tracking-wider text-emerald-200">Activos</p>
                        </div>
                        <div class="flex items-center gap-3 md:gap-4 rounded-xl border border-amber-500/20 bg-amber-950/40 px-3 py-3 md:px-5 md:py-4 shadow-sm">
                            <div class="flex h-10 w-10 md:h-12 md:w-12 shrink-0 items-center justify-center rounded-full bg-amber-500 text-base md:text-lg font-bold text-white shadow-sm">
                                {{ $counters['expiring_clients'] ?? 0 }}
                            </div>
                            <p class="text-xs md:text-sm font-semibold uppercase tracking-wider text-amber-200">Por vencer</p>
                        </div>
                        <div class="flex items-center gap-3 md:gap-4 rounded-xl border border-rose-500/20 bg-rose-950/40 px-3 py-3 md:px-5 md:py-4 shadow-sm">
                            <div class="flex h-10 w-10 md:h-12 md:w-12 shrink-0 items-center justify-center rounded-full bg-rose-500 text-base md:text-lg font-bold text-white shadow-sm">
                                {{ $counters['expired_clients'] ?? 0 }}
                            </div>
                            <p class="text-xs md:text-sm font-semibold uppercase tracking-wider text-rose-200">Vencidos</p>
                        </div>
                    </div>

                    {{-- Filtros y acciones --}}
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                        <form method="GET" action="{{ route('stores.subscriptions.memberships', $store) }}" class="w-full">
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 flex-1">
                                    <div>
                                        <label for="status" class="block text-xs font-medium text-gray-400 uppercase mb-1">Estado</label>
                                        <select id="status" name="status" class="w-full rounded-lg border border-white/10 bg-slate-900/60 text-sm text-gray-100 px-3 py-2">
                                            <option value="all" @selected(($statusFilter ?? 'all') === 'all')>Todos (histórico)</option>
                                            <option value="active" @selected(($statusFilter ?? 'all') === 'active')>Activos</option>
                                            <option value="expiring" @selected(($statusFilter ?? 'all') === 'expiring')>Por vencer</option>
                                            <option value="expired" @selected(($statusFilter ?? 'all') === 'expired')>Vencidos</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="per_page" class="block text-xs font-medium text-gray-400 uppercase mb-1">Por página</label>
                                        <select id="per_page" name="per_page" class="w-full rounded-lg border border-white/10 bg-slate-900/60 text-sm text-gray-100 px-3 py-2">
                                            @foreach([10,25,50,100] as $size)
                                                <option value="{{ $size }}" @selected(($perPage ?? 25) === $size)>{{ $size }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="name" class="block text-xs font-medium text-gray-400 uppercase mb-1">Nombre</label>
                                        <input id="name" name="name" type="text" value="{{ $nameFilter ?? '' }}" placeholder="Buscar por nombre" class="w-full rounded-lg border border-white/10 bg-slate-900/60 text-sm text-gray-100 px-3 py-2 placeholder-gray-500">
                                    </div>
                                    <div>
                                        <label for="document" class="block text-xs font-medium text-gray-400 uppercase mb-1">DNI / Documento</label>
                                        <input id="document" name="document" type="text" value="{{ $documentFilter ?? '' }}" placeholder="Buscar por documento" class="w-full rounded-lg border border-white/10 bg-slate-900/60 text-sm text-gray-100 px-3 py-2 placeholder-gray-500">
                                    </div>
                                    <div>
                                        <label for="phone" class="block text-xs font-medium text-gray-400 uppercase mb-1">Teléfono</label>
                                        <input id="phone" name="phone" type="text" value="{{ $phoneFilter ?? '' }}" placeholder="Buscar por teléfono" class="w-full rounded-lg border border-white/10 bg-slate-900/60 text-sm text-gray-100 px-3 py-2 placeholder-gray-500">
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-brand text-white font-semibold text-xs rounded-xl uppercase tracking-wider shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">
                                        Filtrar
                                    </button>
                                    @if(($statusFilter ?? 'all') !== 'all' || ($nameFilter ?? '') !== '' || ($documentFilter ?? '') !== '' || ($phoneFilter ?? '') !== '' || ($perPage ?? 25) !== 25)
                                        <a href="{{ route('stores.subscriptions.memberships', $store) }}" class="inline-flex items-center px-4 py-2 border border-white/10 text-xs font-semibold rounded-xl uppercase tracking-wider text-gray-200 hover:bg-white/5">
                                            Limpiar
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </form>

                        <div class="flex justify-end">
                            <button type="button" x-on:click="$dispatch('open-modal', 'create-invoice')"
                                    class="inline-flex items-center px-4 py-2 bg-brand text-white font-semibold text-xs rounded-xl uppercase tracking-wider shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                Suscribir cliente
                            </button>
                        </div>
                    </div>

                    {{-- Contenido: cards móvil + tabla tablet/desktop --}}
                    @if($subscriptions->count() > 0)
                        {{-- Vista móvil: cards --}}
                        <div class="block md:hidden space-y-3">
                            @foreach($subscriptions as $subscription)
                                @php
                                    $now = \Carbon\Carbon::now();
                                    $daysLeft = $subscription->expires_at ? (int) $now->diffInDays($subscription->expires_at, false) : null;
                                    $status = $subscription->getStatus();
                                    $isExpiring = $status === 'active' && $daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 6;
                                @endphp
                                <div class="rounded-xl border border-white/5 bg-slate-800/50 p-4 space-y-3">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="font-semibold text-gray-100">{{ $subscription->customer?->name ?? '—' }}</p>
                                        @if($status === 'active' && $isExpiring)
                                            <span class="shrink-0 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">Por vencer</span>
                                        @elseif($status === 'active')
                                            <span class="shrink-0 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">Activa</span>
                                        @elseif($status === 'pending')
                                            <span class="shrink-0 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300">Pendiente</span>
                                        @else
                                            <span class="shrink-0 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Vencida</span>
                                        @endif
                                    </div>
                                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                                        <span class="text-gray-400">Plan</span>
                                        <span class="text-gray-200">{{ $subscription->storePlan?->name ?? '—' }}</span>
                                        <span class="text-gray-400">Inicio</span>
                                        <span class="text-gray-200">{{ $subscription->starts_at?->format('d/m/Y') ?? '—' }}</span>
                                        <span class="text-gray-400">Vencimiento</span>
                                        <span class="text-gray-200">{{ $subscription->expires_at?->format('d/m/Y') ?? '—' }}</span>
                                        <span class="text-gray-400">Días rest.</span>
                                        <span>
                                            @if($daysLeft === null)
                                                <span class="text-gray-400">—</span>
                                            @else
                                                @if($status === 'expired')
                                                    <span class="text-rose-400 font-medium">{{ $daysLeft }}d</span>
                                                @elseif($isExpiring)
                                                    <span class="text-amber-300 font-medium">{{ $daysLeft }}d</span>
                                                @elseif($status === 'active')
                                                    <span class="text-emerald-300 font-medium">{{ $daysLeft }}d</span>
                                                @else
                                                    <span class="text-gray-300">{{ $daysLeft }}d</span>
                                                @endif
                                            @endif
                                        </span>
                                        <span class="text-gray-400">Entradas</span>
                                        <span class="text-gray-200">{{ $subscription->entries_used }}</span>
                                        <span class="text-gray-400">Última entrada</span>
                                        <span class="text-gray-200">{{ $subscription->last_entry_at?->format('d/m/Y H:i') ?? '—' }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        {{-- Vista tablet/desktop: tabla --}}
                        <div class="hidden md:block overflow-x-auto">
                            <table class="min-w-full divide-y divide-white/5">
                                <thead class="border-b border-white/5 bg-slate-900/40">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Cliente</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Plan</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Inicio</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Vencimiento</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Días rest.</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Entradas usadas</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Última entrada</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach($subscriptions as $subscription)
                                        @php
                                            $now = \Carbon\Carbon::now();
                                            $daysLeft = $subscription->expires_at ? (int) $now->diffInDays($subscription->expires_at, false) : null;
                                            $status = $subscription->getStatus();
                                            $isExpiring = $status === 'active' && $daysLeft !== null && $daysLeft >= 0 && $daysLeft <= 6;
                                        @endphp
                                        <tr class="hover:bg-white/5 transition">
                                            <td class="px-4 py-4 text-sm font-medium text-gray-100">
                                                {{ $subscription->customer?->name ?? '—' }}
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-300">
                                                {{ $subscription->storePlan?->name ?? '—' }}
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-200">
                                                {{ $subscription->starts_at?->format('d/m/Y') ?? '—' }}
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-200">
                                                {{ $subscription->expires_at?->format('d/m/Y') ?? '—' }}
                                            </td>
                                            <td class="px-4 py-4 text-sm">
                                                @if($daysLeft === null)
                                                    <span class="text-gray-400">—</span>
                                                @else
                                                    @if($status === 'expired')
                                                        <span class="text-rose-400 font-medium">{{ $daysLeft }}d</span>
                                                    @elseif($isExpiring)
                                                        <span class="text-amber-300 font-medium">{{ $daysLeft }}d</span>
                                                    @elseif($status === 'active')
                                                        <span class="text-emerald-300 font-medium">{{ $daysLeft }}d</span>
                                                    @else
                                                        <span class="text-gray-300">{{ $daysLeft }}d</span>
                                                    @endif
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-200">
                                                {{ $subscription->entries_used }}
                                            </td>
                                            <td class="px-4 py-4 text-sm text-gray-400">
                                                {{ $subscription->last_entry_at?->format('d/m/Y H:i') ?? '—' }}
                                            </td>
                                            <td class="px-4 py-4 text-sm">
                                                @if($status === 'active' && $isExpiring)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300">
                                                        Por vencer
                                                    </span>
                                                @elseif($status === 'active')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">
                                                        Activa
                                                    </span>
                                                @elseif($status === 'pending')
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-300">
                                                        Pendiente
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                        Vencida
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex flex-col sm:flex-row items-center justify-between gap-3">
                            <p class="text-xs text-gray-400">
                                Mostrando
                                <span class="font-semibold text-gray-200">{{ $subscriptions->firstItem() }}</span>
                                -
                                <span class="font-semibold text-gray-200">{{ $subscriptions->lastItem() }}</span>
                                de
                                <span class="font-semibold text-gray-200">{{ $subscriptions->total() }}</span>
                                resultados
                            </p>
                            <div>
                                {{ $subscriptions->appends(request()->except('page'))->links() }}
                            </div>
                        </div>
                    @else
                        <p class="text-center text-gray-400 py-8">
                            No hay membresías registradas con los filtros actuales.
                            <button type="button" x-on:click="$dispatch('open-modal', 'create-invoice')" class="text-indigo-400 hover:underline bg-transparent border-0 p-0 cursor-pointer font-inherit">
                                Suscribir a un cliente
                            </button>
                        </p>
                    @endif
                </div>
            </div>
        </div>
        <livewire:create-invoice-modal :store-id="$store->id" />
    </div>
</x-app-layout>
