<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Membresías - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300">
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

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
                        <div></div>
                        <button type="button" x-on:click="$dispatch('open-modal', 'create-invoice')"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Suscribir cliente
                        </button>
                    </div>

                    @if($subscriptions->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-900">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Cliente</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Plan</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha inicio</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Fecha vencimiento</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Entradas usadas</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Última entrada</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Estado</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($subscriptions as $subscription)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-4 py-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $subscription->customer?->name ?? '—' }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $subscription->storePlan?->name ?? '—' }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $subscription->starts_at?->format('d/m/Y') ?? '—' }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $subscription->expires_at?->format('d/m/Y') ?? '—' }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-900 dark:text-gray-100">{{ $subscription->entries_used }}</td>
                                            <td class="px-4 py-4 text-sm text-gray-600 dark:text-gray-400">{{ $subscription->last_entry_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                            <td class="px-4 py-4 text-sm">
                                                @if($subscription->isActive())
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300">Activa</span>
                                                @else
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Vencida</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-center text-gray-500 dark:text-gray-400 py-8">
                            No hay membresías registradas.
                            <button type="button" x-on:click="$dispatch('open-modal', 'create-invoice')" class="text-indigo-600 dark:text-indigo-400 hover:underline bg-transparent border-0 p-0 cursor-pointer font-inherit">Suscribir a un cliente</button>
                        </p>
                    @endif
                </div>
            </div>
        </div>
        <livewire:create-invoice-modal :store-id="$store->id" />
    </div>
</x-app-layout>
