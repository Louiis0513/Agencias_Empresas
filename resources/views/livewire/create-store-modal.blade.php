<div>
    {{-- 1. BOTÓN ACTIVADOR (El que se ve en el Dashboard) --}}
    <button x-data="" 
            x-on:click.prevent="$dispatch('open-modal', 'create-store')"
            class="bg-brand text-white font-bold py-2 px-4 rounded-xl shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] transition flex items-center gap-2">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        Crear Nueva Tienda
    </button>

    {{-- 2. VENTANA MODAL --}}
    <x-modal name="create-store" focusable>
        <div class="p-6">
            
            {{-- Encabezado --}}
            <h2 class="text-lg font-medium text-white">
                {{ __('Crear una nueva tienda') }}
            </h2>
            <p class="mt-1 text-sm text-gray-400">
                {{ __('Dale un nombre a tu nuevo negocio para comenzar.') }}
            </p>

            {{-- 3. INFORMACIÓN DEL PLAN (Limpio: Usando Computed Properties) --}}
            <div class="mt-4 text-sm text-gray-400 p-3 bg-white/5 rounded-lg border border-white/10">
                
                <div class="flex justify-between items-center mb-2">
                    {{-- Usamos $this->plan para acceder a la propiedad computada --}}
                    <span class="text-gray-300">
                        Plan Actual: <strong class="text-white">{{ $this->plan->name ?? 'Sin Plan' }}</strong>
                    </span>
                    
                    @if($this->storeCount < $this->storeLimit)
                        <span class="text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 text-xs font-bold px-2 py-1 rounded-full">
                            Disponible
                        </span>
                    @else
                        <a href="#" class="text-brand hover:text-white hover:underline text-xs font-bold transition">
                            Mejorar Plan &rarr;
                        </a>
                    @endif
                </div>

                {{-- Barra de Progreso --}}
                <div class="w-full bg-white/10 rounded-full h-2.5 overflow-hidden">
                    <div class="{{ $this->storeCount >= $this->storeLimit ? 'bg-red-500' : 'bg-brand' }} h-2.5 rounded-full transition-all duration-500" 
                         style="width: {{ $this->progressPercent }}%">
                    </div>
                </div>
                
                <p class="mt-2 text-xs text-right text-gray-400">
                    Has usado <span class="font-bold text-white">{{ $this->storeCount }}</span> 
                    de <span class="font-bold text-white">{{ $this->storeLimit }}</span> tiendas.
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