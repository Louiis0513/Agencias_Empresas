<div x-on:open-customer-detail-modal.window="$wire.loadCustomer($event.detail.id || $event.detail.customerId || $event.detail)">
    <x-modal name="customer-detail" focusable maxWidth="2xl">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                {{ __('Detalles del Cliente') }}
            </h2>

            @if($this->customer)
                <div class="space-y-4">
                    {{-- Información Básica --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $this->customer->name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Documento</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $this->customer->document_number ?? '-' }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $this->customer->email ?? '-' }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Teléfono</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $this->customer->phone ?? '-' }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dirección</label>
                            <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $this->customer->address ?? '-' }}</p>
                        </div>
                    </div>

                    {{-- Información de Vinculación --}}
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Información de Vinculación</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Usuario Vinculado</label>
                                @if($this->customer->user)
                                    <div class="mt-1">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            {{ $this->customer->user->name }}
                                        </span>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $this->customer->user->email }}</p>
                                    </div>
                                @else
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No vinculado</p>
                                @endif
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tienda</label>
                                <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $this->customer->store->name }}</p>
                            </div>
                        </div>
                    </div>

                    {{-- Información de Fechas --}}
                    <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Información de Fechas</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Fecha de Creación</label>
                                <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $this->customer->created_at->format('d/m/Y H:i') }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Última Actualización</label>
                                <p class="mt-1 text-sm text-gray-900 dark:text-gray-100">{{ $this->customer->updated_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <p class="text-gray-500 dark:text-gray-400">Cargando información del cliente...</p>
            @endif

            <div class="mt-6 flex justify-end">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'customer-detail')">
                    {{ __('Cerrar') }}
                </x-secondary-button>
            </div>
        </div>
    </x-modal>
</div>
