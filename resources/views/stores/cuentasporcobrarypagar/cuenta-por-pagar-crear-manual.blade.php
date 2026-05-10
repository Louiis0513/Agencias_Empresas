<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                {{ __('Registrar CxP manual') }} — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'por-pagar']) }}" wire:navigate class="text-sm text-gray-400 hover:text-brand transition">
                ← {{ __('Movimientos') }} · {{ __('Por pagar') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <p class="text-sm text-gray-400 mb-4">
                {{ __('Obligación sin compra registrada (ej. cuenta de cobro, honorarios). No calcula retenciones ni nómina; conserva trazabilidad para pagos.') }}
            </p>
            @if(session('error'))
                <div class="mb-4 bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg">
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl p-6">
                <form method="POST" action="{{ route('stores.accounts-payables.store-manual', $store) }}">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('Acreedor') }} *</label>
                            <input type="text" name="creditor_name" value="{{ old('creditor_name') }}" required
                                   class="w-full rounded-md border-white/10 bg-white/5 text-gray-100"
                                   placeholder="{{ __('Nombre de quien emitió el documento') }}">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('Documento (CC / NIT)') }}</label>
                                <input type="text" name="creditor_document" value="{{ old('creditor_document') }}"
                                       class="w-full rounded-md border-white/10 bg-white/5 text-gray-100" maxlength="64">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('Ref. cuenta de cobro / documento') }}</label>
                                <input type="text" name="document_reference" value="{{ old('document_reference') }}"
                                       class="w-full rounded-md border-white/10 bg-white/5 text-gray-100" maxlength="120">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('Concepto / descripción') }}</label>
                            <textarea name="description" rows="2" class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">{{ old('description') }}</textarea>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('Monto total') }} *</label>
                                <input type="text" name="total_amount" value="{{ old('total_amount') }}" required inputmode="decimal"
                                       class="w-full rounded-md border-white/10 bg-white/5 text-gray-100" placeholder="0">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-1">{{ __('Vencimiento') }}</label>
                                <input type="date" name="due_date" value="{{ old('due_date') }}"
                                       class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                            </div>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-3">
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)]">
                            {{ __('Registrar CxP') }}
                        </button>
                        <a href="{{ route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'por-pagar']) }}" wire:navigate
                           class="px-4 py-2 rounded-lg border border-white/15 text-gray-300 hover:bg-white/5">{{ __('Cancelar') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
