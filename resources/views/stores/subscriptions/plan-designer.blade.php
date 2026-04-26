<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-3">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Diseñador de planes - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.subscriptions.plans', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver a Planes
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if(session('success'))
                <div class="bg-green-100 dark:bg-green-900/30 border border-green-400 text-green-700 dark:text-green-300 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-dark-card border border-white/5 rounded-xl p-4">
                <div class="flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('stores.subscriptions.plans.designer.bulk', $store) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="disabled">
                        <button type="submit" class="px-3 py-2 rounded-lg bg-red-600/20 text-red-300 text-sm hover:bg-red-600/30">
                            Bloquear todo
                        </button>
                    </form>
                    <form method="POST" action="{{ route('stores.subscriptions.plans.designer.bulk', $store) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="status" value="included">
                        <button type="submit" class="px-3 py-2 rounded-lg bg-emerald-600/20 text-emerald-300 text-sm hover:bg-emerald-600/30">
                            Habilitar todo
                        </button>
                    </form>
                </div>
            </div>

            @foreach($catalog as $moduleData)
                <div class="bg-dark-card border border-white/5 rounded-xl overflow-hidden">
                    <div class="px-4 py-3 border-b border-white/5">
                        <h3 class="text-base font-semibold text-white">
                            Módulo: {{ str_replace('-', ' ', $moduleData['module']) }}
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-white/5">
                            <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Feature</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Permisos</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Plan asignado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @foreach($moduleData['features'] as $feature)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-gray-100">
                                            <div class="font-medium">{{ $feature['name'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $feature['slug'] }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-300">
                                            {{ implode(', ', $feature['permissions']) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <form method="POST" action="{{ route('stores.subscriptions.plans.designer.feature', $store) }}" class="flex gap-2 items-center">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="feature_id" value="{{ $feature['id'] }}">
                                                <select name="status" class="rounded border-white/10 bg-white/5 text-sm text-white">
                                                    @foreach(['included' => 'Básico', 'premium' => 'Premium', 'addon' => 'Addon', 'disabled' => 'Bloqueado'] as $value => $label)
                                                        <option value="{{ $value }}" @selected($feature['status'] === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <button type="submit" class="px-2 py-1 rounded bg-brand/20 text-brand text-xs">Guardar</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>

