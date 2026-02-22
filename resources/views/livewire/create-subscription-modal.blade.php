<div>
    <x-modal name="create-subscription" focusable maxWidth="2xl" contentClass="bg-white dark:bg-gray-800">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Suscribir cliente
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Asigna un plan de membresía a un cliente con fecha de inicio.
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label value="Cliente *" />
                    <livewire:customer-search-select :store-id="$storeId" :selected-customer-id="$customer_id" emit-event-name="customer-selected" />
                    <x-input-error :messages="$errors->get('customer_id')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="subscription_plan_id" value="Plan *" />
                    <select wire:model="plan_id" id="subscription_plan_id"
                            class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Seleccione un plan</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }} ({{ number_format($plan->price, 2) }} — {{ $plan->duration_days }} días)</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('plan_id')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="subscription_starts_at" value="Fecha de inicio *" />
                    <x-text-input wire:model="starts_at" id="subscription_starts_at" class="block mt-1 w-full" type="date" />
                    <x-input-error :messages="$errors->get('starts_at')" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button"
                                    x-on:click="$dispatch('close-modal', 'create-subscription')">
                    Cancelar
                </x-secondary-button>
                <x-primary-button type="submit" wire:loading.attr="disabled">
                    Suscribir
                </x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
