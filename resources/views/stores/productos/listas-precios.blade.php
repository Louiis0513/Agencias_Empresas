<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Listas de precios — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.configuracion', $store) }}?panel=productos" class="text-sm text-gray-400 hover:text-brand transition">
                ← Configuración
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-950/30 px-4 py-3 text-emerald-200">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 rounded-xl border border-red-500/30 bg-red-950/30 px-4 py-3 text-red-200">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 rounded-xl border border-red-500/30 bg-red-950/30 px-4 py-3 text-red-200">
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-4 rounded-xl border border-sky-500/30 bg-sky-950/40 px-4 py-3 text-sm text-sky-100">
                Define hasta 12 listas de precios de venta. Solo las <strong>activas</strong> aparecen al crear o editar productos.
            </div>

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-white/5">
                        <thead>
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Nº</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Nombre</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase">Activa</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-400 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            @foreach($items as $item)
                                <tr class="hover:bg-white/5 transition text-sm text-gray-300" x-data="{
                                    editing: false,
                                    nombre: @js($item->nombre),
                                    activo: {{ $item->activo ? 'true' : 'false' }}
                                }">
                                    <td class="px-4 py-3 font-mono text-gray-400">{{ $item->numero }}</td>
                                    <td class="px-4 py-3">
                                        <template x-if="!editing">
                                            <span x-text="nombre"></span>
                                        </template>
                                        <template x-if="editing">
                                            <input type="text" form="lista-form-{{ $item->id }}" name="nombre" x-model="nombre" maxlength="120" required
                                                   class="w-full max-w-md rounded-md border-white/10 bg-white/5 text-gray-100 text-sm">
                                        </template>
                                    </td>
                                    <td class="px-4 py-3">
                                        <template x-if="!editing">
                                            <span :class="activo ? 'text-emerald-400' : 'text-gray-500'" x-text="activo ? 'Sí' : 'No'"></span>
                                        </template>
                                        <template x-if="editing">
                                            <label class="inline-flex items-center gap-2">
                                                <input type="hidden" form="lista-form-{{ $item->id }}" name="activo" value="0">
                                                <input type="checkbox" form="lista-form-{{ $item->id }}" name="activo" value="1" x-model="activo"
                                                       class="rounded border-white/20 bg-white/5">
                                                <span class="text-xs text-gray-400">En uso</span>
                                            </label>
                                        </template>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @storeCan($store, 'products.listas-precios.edit')
                                        <template x-if="!editing">
                                            <button type="button" @click="editing = true" class="text-brand hover:underline text-sm">Editar</button>
                                        </template>
                                        <template x-if="editing">
                                            <div class="inline-flex items-center gap-3">
                                                <button type="button" @click="editing = false; nombre = @js($item->nombre); activo = {{ $item->activo ? 'true' : 'false' }}"
                                                        class="text-gray-400 hover:underline text-sm">Cancelar</button>
                                                <button type="submit" form="lista-form-{{ $item->id }}" class="text-brand hover:underline text-sm font-medium">Guardar</button>
                                            </div>
                                        </template>
                                        <form id="lista-form-{{ $item->id }}" method="POST"
                                              action="{{ route('stores.products.listas-precios.update', [$store, $item]) }}" class="hidden">
                                            @csrf
                                            @method('PUT')
                                        </form>
                                        @else
                                        <span class="text-gray-600 text-xs">—</span>
                                        @endstoreCan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
