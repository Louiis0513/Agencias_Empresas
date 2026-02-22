<div>
    <x-modal name="create-store-plan" focusable maxWidth="2xl" contentClass="bg-white dark:bg-gray-800">
        <form wire:submit="save" class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                Crear plan
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Define un plan de suscripción o membresía (ej. mensualidad gym, tiquetera de clases).
            </p>

            <div class="mt-6 space-y-4">
                <div>
                    <x-input-label for="plan_name" value="Nombre *" />
                    <x-text-input wire:model="name" id="plan_name" class="block mt-1 w-full" type="text" placeholder="Ej: Mensualidad, Tiquetera 12 clases" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="plan_description" value="Descripción" />
                    <textarea wire:model="description" id="plan_description" rows="3" class="block mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Descripción opcional del plan"></textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-1" />
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="plan_price" value="Precio *" />
                        <x-text-input wire:model="price" id="plan_price" class="block mt-1 w-full" type="number" min="0" step="0.01" placeholder="0.00" />
                        <x-input-error :messages="$errors->get('price')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="plan_duration_days" value="Duración (días) *" />
                        <x-text-input wire:model="duration_days" id="plan_duration_days" class="block mt-1 w-full" type="number" min="1" placeholder="30" />
                        <x-input-error :messages="$errors->get('duration_days')" class="mt-1" />
                    </div>
                </div>

                <div>
                    <x-input-label for="plan_daily_entries_limit" value="Límite de entradas por día" />
                    <x-text-input wire:model="daily_entries_limit" id="plan_daily_entries_limit" class="block mt-1 w-full" type="number" min="1" placeholder="Vacío = ilimitado" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">1 = una vez al día. Vacío = ilimitado por día.</p>
                    <x-input-error :messages="$errors->get('daily_entries_limit')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="plan_total_entries_limit" value="Límite total de entradas" />
                    <x-text-input wire:model="total_entries_limit" id="plan_total_entries_limit" class="block mt-1 w-full" type="number" min="1" placeholder="Vacío = ilimitado" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Ej: 12 = tiquetera de 12 clases. Vacío = ilimitado.</p>
                    <x-input-error :messages="$errors->get('total_entries_limit')" class="mt-1" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <x-secondary-button type="button"
                                    x-on:click="$dispatch('close-modal', 'create-store-plan')">
                    Cancelar
                </x-secondary-button>
                <x-primary-button type="submit" wire:loading.attr="disabled">
                    Crear plan
                </x-primary-button>
            </div>
        </form>
    </x-modal>
</div>
