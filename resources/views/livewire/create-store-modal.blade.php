<div>
    {{-- 1. BOTÓN ACTIVADOR (El que se ve en el Dashboard) --}}
    <button x-data="" 
            x-on:click.prevent="$dispatch('open-modal', 'create-store')"
            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Crear Nueva Tienda
    </button>

    {{-- 2. VENTANA MODAL --}}
    <x-modal name="create-store" focusable>
        <div class="p-6">
            
            {{-- Encabezado --}}
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Crear una nueva tienda') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Dale un nombre a tu nuevo negocio para comenzar.') }}
            </p>

            {{-- 3. INFORMACIÓN DEL PLAN (Limpio: Usando Computed Properties) --}}
            <div class="mt-4 text-sm text-gray-500 dark:text-gray-400 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-100 dark:border-gray-600">
                
                <div class="flex justify-between items-center mb-2">
                    {{-- Usamos $this->plan para acceder a la propiedad computada --}}
                    <span class="text-gray-700 dark:text-gray-300">
                        Plan Actual: <strong>{{ $this->plan->name ?? 'Sin Plan' }}</strong>
                    </span>
                    
                    @if($this->storeCount < $this->storeLimit)
                        <span class="text-green-700 bg-green-100 border border-green-200 text-xs font-bold px-2 py-1 rounded-full">
                            Disponible
                        </span>
                    @else
                        {{-- Enlace falso por ahora, luego iría a la pasarela de pago --}}
                        <a href="#" class="text-indigo-600 hover:text-indigo-500 hover:underline text-xs font-bold transition">
                            Mejorar Plan &rarr;
                        </a>
                    @endif
                </div>

                {{-- Barra de Progreso --}}
                <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-600 overflow-hidden">
                    <div class="{{ $this->storeCount >= $this->storeLimit ? 'bg-red-500' : 'bg-indigo-600' }} h-2.5 rounded-full transition-all duration-500" 
                         style="width: {{ $this->progressPercent }}%">
                    </div>
                </div>
                
                <p class="mt-2 text-xs text-right">
                    Has usado <span class="font-bold text-gray-900 dark:text-gray-100">{{ $this->storeCount }}</span> 
                    de <span class="font-bold text-gray-900 dark:text-gray-100">{{ $this->storeLimit }}</span> tiendas.
                </p>
            </div>

            {{-- 4. FORMULARIO --}}
            <div class="mt-6">
                <x-input-label for="name" value="{{ __('Nombre de la Tienda') }}" />

                <x-text-input wire:model="name" 
                              id="name" 
                              class="block mt-1 w-full" 
                              type="text" 
                              placeholder="Ej: Restaurante La Plaza"
                              @keydown.enter="$wire.save()" 
                              autofocus />

                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            {{-- 5. BOTONES DE ACCIÓN --}}
            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancelar') }}
                </x-secondary-button>

                {{-- Deshabilitamos el botón si llegó al límite para evitar clics innecesarios --}}
                @if($this->storeCount >= $this->storeLimit)
                    <x-primary-button class="ms-3 opacity-50 cursor-not-allowed" disabled>
                        {{ __('Límite Alcanzado') }}
                    </x-primary-button>
                @else
                    <x-primary-button class="ms-3" wire:click="save" wire:loading.attr="disabled">
                        {{ __('Crear Tienda') }}
                    </x-primary-button>
                @endif
            </div>
            
            {{-- Si hay errores de validación (ej: nombre duplicado), reabrimos el modal --}}
            @if($errors->any())
                <div x-init="$dispatch('open-modal', 'create-store')"></div>
            @endif
        </div>
    </x-modal>
</div>