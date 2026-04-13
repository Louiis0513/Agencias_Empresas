<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Editar compra con documento soporte — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.product-purchases.documento-soporte.create', $store) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver a nueva compra (documento soporte)
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="{ paymentStatus: 'PAGADO' }">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <p class="mb-4 text-sm text-gray-400 border border-gray-200 dark:border-gray-700 rounded-lg px-4 py-3">
                Vista prototipo de edición: misma estructura que el alta. Los datos reales se cargarán cuando exista persistencia (fase 2).
            </p>

            @if($proveedores->isEmpty())
            <div class="mb-4 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-amber-700 dark:text-amber-300">
                <p class="font-medium">Sin proveedores registrados.</p>
                <a href="{{ route('stores.proveedores', $store) }}" class="mt-2 inline-block text-sm font-medium text-amber-600 dark:text-amber-400 hover:underline">
                    Ir a proveedores →
                </a>
            </div>
            @endif

            <form action="#" method="post" class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6" @submit.prevent>
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="md:col-span-2">
                        <h3 class="text-sm font-semibold text-gray-200 border-b border-white/10 pb-2 mb-3">Documento soporte</h3>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Prefijo</label>
                        <input type="text" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3" placeholder="DS">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número / consecutivo</label>
                        <input type="text" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3" placeholder="—">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha de emisión</label>
                        <input type="date" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CUDS <span class="text-gray-500 font-normal">(opcional)</span></label>
                        <input type="text" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Proveedor</label>
                        <select class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3" {{ $proveedores->isEmpty() ? 'disabled' : '' }}>
                            @if($proveedores->isEmpty())
                                <option value="">—</option>
                            @else
                                <option value="">Seleccione</option>
                                @foreach($proveedores as $prov)
                                    <option value="{{ $prov->id }}">{{ $prov->nombre }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Forma de pago</label>
                        <select x-model="paymentStatus" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100 py-2 px-3">
                            <option value="PAGADO">Contado</option>
                            <option value="PENDIENTE">A crédito</option>
                        </select>
                    </div>
                </div>

                <div class="mb-6 rounded-lg border border-white/10 p-4 text-sm text-gray-400">
                    Detalle de líneas: pendiente de enlace con datos guardados.
                </div>

                <div class="flex flex-wrap gap-3 justify-end border-t border-white/10 pt-6">
                    <a href="{{ route('stores.product-purchases', $store) }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                        Volver al listado
                    </a>
                    <button type="button" disabled class="px-4 py-2 rounded-md bg-gray-500 text-gray-300 cursor-not-allowed">
                        Actualizar — próximamente
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
