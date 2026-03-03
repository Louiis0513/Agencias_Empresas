<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Vitrina virtual - {{ $store->name }}
            </h2>
            <a href="{{ route('stores.dashboard', $store) }}" class="text-sm text-gray-400 hover:text-brand transition" wire:navigate>
                ← Volver al Resumen
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (session('success'))
                <div class="rounded-lg bg-emerald-500/20 border border-emerald-500/50 text-emerald-200 px-4 py-3">
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-lg bg-red-500/20 border border-red-500/50 text-red-200 px-4 py-3">
                    <ul class="list-disc list-inside text-sm">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Preview --}}
            <div class="bg-dark-card border border-white/5 rounded-xl overflow-hidden">
                <div class="p-4 border-b border-white/5">
                    <h3 class="font-medium text-white">Vista previa</h3>
                    <p class="text-sm text-gray-400">Así se verá tu vitrina (imágenes y slug actuales).</p>
                </div>
                <div class="p-4">
                    @if ($vitrinaConfig->slug)
                        <a href="{{ url('/vitrina/'.$vitrinaConfig->slug) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-brand hover:underline mb-4">
                            Abrir vitrina en nueva pestaña
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                        </a>
                        <iframe src="{{ url('/vitrina/'.$vitrinaConfig->slug) }}" class="w-full h-[480px] border border-white/10 rounded-lg bg-white" title="Preview vitrina"></iframe>
                    @else
                        <p class="text-gray-400 text-sm">Guarda un slug abajo y vuelve a cargar la página para ver la vista previa aquí.</p>
                        <p class="text-gray-500 text-xs mt-2">Mientras tanto, las imágenes que subas se usarán al publicar la vitrina.</p>
                    @endif
                </div>
            </div>

            <form action="{{ route('stores.vitrina.update', $store) }}" method="POST" enctype="multipart/form-data" class="space-y-8">
                @csrf
                @method('PUT')

                {{-- Slug --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">URL de la vitrina</h3>
                    <label class="block text-sm text-gray-400 mb-2">Slug (ej: mi-panaderia)</label>
                    <input type="text" name="slug" value="{{ old('slug', $vitrinaConfig->slug) }}" placeholder="mi-tienda" class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2">
                    @if ($vitrinaConfig->slug)
                        <p class="mt-2 text-sm text-gray-500">URL: {{ url('/vitrina/'.$vitrinaConfig->slug) }}</p>
                    @endif
                </div>

                {{-- Información del negocio (descripción, horario) --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Información del negocio</h3>
                    <label class="block text-sm text-gray-400 mb-2">Descripción o eslogan (máx. 300 caracteres)</label>
                    <textarea name="description" rows="2" maxlength="300" placeholder="Ej: Sitio web de entretenimiento, Panadería artesana y repostería" class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2">{{ old('description', $vitrinaConfig->description) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">Se muestra debajo del nombre del negocio en la vitrina.</p>
                    <label class="block text-sm text-gray-400 mt-4 mb-2">Horario (texto libre, máx. 500 caracteres)</label>
                    <textarea name="schedule" rows="3" maxlength="500" placeholder="Lun a Sáb · 8:00 am – 7:00 pm&#10;Dom y festivos: 8:00 am – 1:00 pm" class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2">{{ old('schedule', $vitrinaConfig->schedule) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">Puedes escribir varias líneas. Ej: Lun a Sáb · 8:00 am – 7:00 pm / Dom y festivos: 8:00 am – 1:00 pm</p>
                </div>

                {{-- Qué mostrar en catálogo --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Qué mostrar en el catálogo</h3>
                    <label class="flex items-center gap-2 text-gray-300">
                        <input type="hidden" name="show_products" value="0">
                        <input type="checkbox" name="show_products" value="1" {{ old('show_products', $vitrinaConfig->show_products) ? 'checked' : '' }}>
                        Mostrar productos
                    </label>
                    <label class="flex items-center gap-2 text-gray-300 mt-2">
                        <input type="hidden" name="show_plans" value="0">
                        <input type="checkbox" name="show_plans" value="1" {{ old('show_plans', $vitrinaConfig->show_plans) ? 'checked' : '' }}>
                        Mostrar planes / suscripciones
                    </label>
                </div>

                {{-- Imágenes --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Imágenes de la vitrina</h3>
                    @php
                        $coverUrl = $vitrinaConfig->cover_image_path ? asset('storage/'.$vitrinaConfig->cover_image_path) : null;
                        $logoUrl = $vitrinaConfig->logo_image_path ? asset('storage/'.$vitrinaConfig->logo_image_path) : null;
                        $bgUrl = $vitrinaConfig->background_image_path ? asset('storage/'.$vitrinaConfig->background_image_path) : null;
                    @endphp
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Portada (foto superior)</label>
                            @if ($coverUrl)
                                <div class="mb-2"><img src="{{ $coverUrl }}" alt="Portada" class="max-h-32 rounded-lg border border-white/10"></div>
                                <label class="flex items-center gap-2 text-gray-400 text-sm"><input type="hidden" name="delete_cover" value="0"><input type="checkbox" name="delete_cover" value="1"> Eliminar y subir otra</label>
                            @endif
                            <input type="file" name="cover_image" accept="image/*" class="mt-2 block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-brand file:text-white">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Logo (círculo central)</label>
                            @if ($logoUrl)
                                <div class="mb-2"><img src="{{ $logoUrl }}" alt="Logo" class="h-24 w-24 rounded-full object-cover border border-white/10"></div>
                                <label class="flex items-center gap-2 text-gray-400 text-sm"><input type="hidden" name="delete_logo" value="0"><input type="checkbox" name="delete_logo" value="1"> Eliminar y subir otra</label>
                            @endif
                            <input type="file" name="logo_image" accept="image/*" class="mt-2 block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-brand file:text-white">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Fondo general (zona inferior)</label>
                            @if ($bgUrl)
                                <div class="mb-2"><img src="{{ $bgUrl }}" alt="Fondo" class="max-h-32 rounded-lg border border-white/10"></div>
                                <label class="flex items-center gap-2 text-gray-400 text-sm"><input type="hidden" name="delete_background" value="0"><input type="checkbox" name="delete_background" value="1"> Eliminar y subir otra</label>
                            @endif
                            <input type="file" name="background_image" accept="image/*" class="mt-2 block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-brand file:text-white">
                        </div>
                    </div>
                </div>

                {{-- WhatsApp (máx. 5) --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">WhatsApp (máx. 5)</h3>
                    <p class="text-sm text-gray-400 mb-4">Número con código de país, ej: +573001234567. Opcionalmente vincula a una sede.</p>
                    @php $wa = old('whatsapp_contacts', $vitrinaConfig->whatsapp_contacts ?? []); @endphp
                    @for ($i = 0; $i < 5; $i++)
                        <div class="flex flex-wrap gap-4 items-center mb-3">
                            <input type="text" name="whatsapp_contacts[{{ $i }}][value]" value="{{ $wa[$i]['value'] ?? '' }}" placeholder="+57..." class="flex-1 min-w-[180px] rounded-lg border-white/10 bg-white/5 text-white px-4 py-2">
                            <select name="whatsapp_contacts[{{ $i }}][location_name]" class="rounded-lg border-white/10 bg-white/5 text-white px-4 py-2">
                                <option value="">Todas (general)</option>
                                @foreach ($vitrinaConfig->locations ?? [] as $loc)
                                    <option value="{{ $loc['name'] ?? '' }}" {{ (($wa[$i]['location_index'] ?? null) === $loop->index) ? 'selected' : '' }}>{{ $loc['name'] ?? 'Sede' }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endfor
                </div>

                {{-- Teléfonos (máx. 5) --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Teléfonos para llamar (máx. 5)</h3>
                    @php $ph = old('phone_contacts', $vitrinaConfig->phone_contacts ?? []); @endphp
                    @for ($i = 0; $i < 5; $i++)
                        <div class="flex flex-wrap gap-4 items-center mb-3">
                            <input type="text" name="phone_contacts[{{ $i }}][value]" value="{{ $ph[$i]['value'] ?? '' }}" placeholder="+57..." class="flex-1 min-w-[180px] rounded-lg border-white/10 bg-white/5 text-white px-4 py-2">
                            <select name="phone_contacts[{{ $i }}][location_name]" class="rounded-lg border-white/10 bg-white/5 text-white px-4 py-2">
                                <option value="">Todas (general)</option>
                                @foreach ($vitrinaConfig->locations ?? [] as $loc)
                                    <option value="{{ $loc['name'] ?? '' }}" {{ (($ph[$i]['location_index'] ?? null) === $loop->index) ? 'selected' : '' }}>{{ $loc['name'] ?? 'Sede' }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endfor
                </div>

                {{-- Ubicaciones (máx. 5) --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Ubicaciones / sedes (máx. 5)</h3>
                    <p class="text-sm text-gray-400 mb-4">Pega el código iframe de Google Maps (Compartir → Incorporar un mapa). Se guardará solo el enlace del mapa para mostrarlo y el botón "Cómo llegar".</p>
                    @php $locs = old('locations', $vitrinaConfig->locations ?? []); @endphp
                    @for ($i = 0; $i < 5; $i++)
                        <div class="border border-white/10 rounded-lg p-4 mb-4">
                            <input type="text" name="locations[{{ $i }}][name]" value="{{ $locs[$i]['name'] ?? '' }}" placeholder="Nombre de la sede" class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2 mb-2">
                            <input type="text" name="locations[{{ $i }}][address]" value="{{ $locs[$i]['address'] ?? '' }}" placeholder="Dirección" class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2 mb-2">
                            <textarea name="locations[{{ $i }}][map_iframe]" rows="3" placeholder="Pega aquí el iframe completo de Google Maps (Compartir → Incorporar un mapa)" class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2 font-mono text-sm">{{ old('locations.'.$i.'.map_iframe', isset($locs[$i]['map_iframe_src']) && $locs[$i]['map_iframe_src'] ? '<iframe src="'.e($locs[$i]['map_iframe_src']).'" width="600" height="450" style="border:0;"></iframe>' : '') }}</textarea>
                        </div>
                    @endfor
                </div>

                {{-- Productos en vitrina --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Productos que aparecen en la vitrina</h3>
                    <p class="text-sm text-gray-400 mb-4">Marca los productos que quieres mostrar en el catálogo público.</p>
                    @forelse ($products as $product)
                        <label class="flex items-center gap-2 py-2 text-gray-300">
                            <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" {{ (is_array(old('product_ids')) && in_array($product->id, old('product_ids'))) || (!request()->old() && $product->in_showcase) ? 'checked' : '' }}>
                            <span>{{ $product->name }}</span>
                            <span class="text-gray-500 text-sm">${{ number_format($product->price, 0) }}</span>
                        </label>
                    @empty
                        <p class="text-gray-500 text-sm">No hay productos. Crea productos en el menú Productos.</p>
                    @endforelse
                </div>

                {{-- Planes en vitrina --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Planes que aparecen en la vitrina</h3>
                    @forelse ($storePlans as $plan)
                        <label class="flex items-center gap-2 py-2 text-gray-300">
                            <input type="checkbox" name="store_plan_ids[]" value="{{ $plan->id }}" {{ (is_array(old('store_plan_ids')) && in_array($plan->id, old('store_plan_ids'))) || (!request()->old() && $plan->in_showcase) ? 'checked' : '' }}>
                            <span>{{ $plan->name }}</span>
                            <span class="text-gray-500 text-sm">${{ number_format($plan->price, 0) }}</span>
                        </label>
                    @empty
                        <p class="text-gray-500 text-sm">No hay planes. Crea planes en Suscripciones → Planes.</p>
                    @endforelse
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2.5 bg-brand text-white font-medium rounded-xl hover:opacity-90 transition">
                        Guardar vitrina
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
