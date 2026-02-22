<div>
    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Registrar asistencia</h3>

        @if($errorMessage)
            <div class="mb-4 bg-red-100 dark:bg-red-900/30 border border-red-400 text-red-700 dark:text-red-300 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ $errorMessage }}</span>
            </div>
        @endif

        <form wire:submit="submit" class="space-y-4 max-w-xl">
            <div>
                <x-input-label value="Cliente *" />
                <livewire:customer-search-select :store-id="$storeId" :selected-customer-id="$customer_id" emit-event-name="customer-selected" />
                <x-input-error :messages="$errors->get('customer_id')" class="mt-1" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="asistencia_fecha" value="Fecha *" />
                    <x-text-input wire:model="fecha" id="asistencia_fecha" class="block mt-1 w-full" type="date" />
                    <x-input-error :messages="$errors->get('fecha')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="asistencia_hora" value="Hora *" />
                    <x-text-input wire:model="hora" id="asistencia_hora" class="block mt-1 w-full" type="time" />
                    <x-input-error :messages="$errors->get('hora')" class="mt-1" />
                </div>
            </div>

            <div class="pt-2">
                <x-primary-button type="submit" wire:loading.attr="disabled">
                    Registrar asistencia
                </x-primary-button>
            </div>
        </form>
    </div>
</div>
