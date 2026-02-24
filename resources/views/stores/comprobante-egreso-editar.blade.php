<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Editar Comprobante {{ $comprobante->number }} - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.comprobantes-egreso.show', [$store, $comprobante]) }}" class="text-sm text-gray-400 hover:text-brand transition">
                ← Volver al comprobante
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="mb-4 bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif
            @if($errors->any())
                <div class="mb-4 bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded relative" role="alert">
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6">
                    <form method="POST" action="{{ route('stores.comprobantes-egreso.update', [$store, $comprobante]) }}">
                        @csrf
                        @method('PUT')

                        <p class="text-sm text-gray-400 mb-4">
                            Solo puede editar la fecha y las notas. Los montos y destinos/orígenes no se pueden modificar.
                        </p>

                        <div class="mb-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha</label>
                                <input type="date" name="payment_date" value="{{ old('payment_date', $comprobante->payment_date->format('Y-m-d')) }}"
                                       class="w-full rounded-md border-white/10 bg-white/5 text-gray-100" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
                                <input type="text" name="notes" value="{{ old('notes', $comprobante->notes) }}"
                                       class="w-full rounded-md border-white/10 bg-white/5 text-gray-100" placeholder="Opcional">
                            </div>
                        </div>

                        <div class="mb-6 p-4 border-b border-white/5 rounded-lg">
                            <p class="text-sm text-gray-400 mb-2">Información no editable:</p>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                Monto total: <strong>{{ number_format($comprobante->total_amount, 2) }}</strong> —
                                A quién: <strong>{{ $comprobante->beneficiary_name ?? '—' }}</strong>
                            </p>
                        </div>

                        <div class="flex gap-3">
                            <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">
                                Guardar cambios
                            </button>
                            <a href="{{ route('stores.comprobantes-egreso.show', [$store, $comprobante]) }}"
                               class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-md hover:bg-gray-300 dark:hover:bg-gray-600">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
