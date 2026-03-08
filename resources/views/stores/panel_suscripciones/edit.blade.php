<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Panel de suscripciones - {{ $store->name }}
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
            @if (session('error'))
                <div class="rounded-lg bg-red-500/20 border border-red-500/50 text-red-200 px-4 py-3">
                    {{ session('error') }}
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
                    <p class="text-sm text-gray-400">Así se verá tu Panel de suscripciones (imágenes y slug actuales).</p>
                </div>
                <div class="p-4">
                    @if ($panelConfig->slug)
                        <a href="{{ url('/'.$panelConfig->slug.'/PanelSuscripciones') }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 text-brand hover:underline mb-4">
                            Abrir Panel Suscripciones en nueva pestaña
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                        </a>
                        <iframe src="{{ url('/'.$panelConfig->slug.'/PanelSuscripciones') }}" class="w-full h-[480px] border border-white/10 rounded-lg bg-white" title="Preview Panel Suscripciones"></iframe>
                    @else
                        <p class="text-gray-400 text-sm">Guarda un slug abajo y vuelve a cargar la página para ver la vista previa aquí.</p>
                        <p class="text-gray-500 text-xs mt-2">Las imágenes que subas se usarán en el panel al publicar.</p>
                    @endif
                </div>
            </div>

            <form action="{{ route('stores.panel-suscripciones.update', $store) }}" method="POST" enctype="multipart/form-data" class="space-y-8">
                @csrf
                @method('PUT')

                {{-- Slug --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">URL del Panel de suscripciones</h3>
                    <label class="block text-sm text-gray-400 mb-2">Slug (ej: mi-negocio)</label>
                    <input type="text" name="slug" value="{{ old('slug', $panelConfig->slug) }}" placeholder="mi-negocio" class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2">
                    @if ($panelConfig->slug)
                        <p class="mt-2 text-sm text-gray-500">URL: {{ url('/'.$panelConfig->slug.'/PanelSuscripciones') }}</p>
                    @endif
                </div>

                {{-- Información del negocio --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Información del negocio</h3>
                    <label class="block text-sm text-gray-400 mb-2">Descripción o eslogan (máx. 300 caracteres)</label>
                    <textarea name="description" rows="2" maxlength="300" placeholder="Ej: Planes y membresías" class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2">{{ old('description', $panelConfig->description) }}</textarea>
                    <label class="block text-sm text-gray-400 mt-4 mb-2">Horario (texto libre, máx. 500 caracteres)</label>
                    <textarea name="schedule" rows="3" maxlength="500" placeholder="Lun a Sáb · 8:00 am – 7:00 pm" class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2">{{ old('schedule', $panelConfig->schedule) }}</textarea>
                </div>

                {{-- Imágenes --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Imágenes del panel</h3>
                    @php
                        $coverUrl = $panelConfig->cover_image_path ? asset('storage/'.$panelConfig->cover_image_path) : null;
                        $logoUrl = $panelConfig->logo_image_path ? asset('storage/'.$panelConfig->logo_image_path) : null;
                        $bgUrl = $panelConfig->background_image_path ? asset('storage/'.$panelConfig->background_image_path) : null;
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
                            <label class="block text-sm text-gray-400 mb-2">Fondo general</label>
                            @if ($bgUrl)
                                <div class="mb-2"><img src="{{ $bgUrl }}" alt="Fondo" class="max-h-32 rounded-lg border border-white/10"></div>
                                <label class="flex items-center gap-2 text-gray-400 text-sm"><input type="hidden" name="delete_background" value="0"><input type="checkbox" name="delete_background" value="1"> Eliminar y subir otra</label>
                            @endif
                            <input type="file" name="background_image" accept="image/*" class="mt-2 block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-brand file:text-white">
                        </div>
                    </div>
                </div>

                {{-- Colores --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Colores del panel</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                        <div>
                            <label class="block text-gray-400 mb-1">Fondo del contenido</label>
                            <input type="color" name="main_background_color" value="{{ old('main_background_color', $panelConfig->main_background_color ?? '#ffffff') }}" class="w-full h-10 rounded border border-white/10 bg-white/5">
                        </div>
                        <div>
                            <label class="block text-gray-400 mb-1">Color principal (botones)</label>
                            <input type="color" name="primary_color" value="{{ old('primary_color', $panelConfig->primary_color ?? '#10b981') }}" class="w-full h-10 rounded border border-white/10 bg-white/5">
                        </div>
                        <div>
                            <label class="block text-gray-400 mb-1">Color secundario</label>
                            <input type="color" name="secondary_color" value="{{ old('secondary_color', $panelConfig->secondary_color ?? '#047857') }}" class="w-full h-10 rounded border border-white/10 bg-white/5">
                        </div>
                    </div>
                </div>

                @php
                    $countryCodesByLen = ['593', '598', '595', '591', '503', '502', '506', '507', '505', '504', '57', '52', '54', '51', '58', '34', '56', '1'];
                    $wa = old('whatsapp_contacts', $panelConfig->whatsapp_contacts ?? []);
                    $ph = old('phone_contacts', $panelConfig->phone_contacts ?? []);
                    $locs = old('locations', $panelConfig->locations ?? []);
                    $loc0 = $locs[0] ?? [];
                @endphp

                {{-- WhatsApp (máx. 5) --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">WhatsApp (máx. 5)</h3>
                    <p class="text-sm text-gray-400 mb-4">Indicativo del país (ej. 57) y número. El + se añade automáticamente.</p>
                    @for ($i = 0; $i < 5; $i++)
                        @php
                            $v = preg_replace('/\D/', '', $wa[$i]['value'] ?? '');
                            $prefillCode = '';
                            $prefillNum = $v;
                            if ($v !== '') {
                                foreach ($countryCodesByLen as $code) {
                                    if (str_starts_with($v, $code)) {
                                        $prefillCode = $code;
                                        $prefillNum = substr($v, strlen($code)) ?: '';
                                        break;
                                    }
                                }
                            }
                        @endphp
                        <div class="flex flex-wrap gap-4 items-center mb-3">
                            <span class="text-white text-lg font-medium">+</span>
                            <input type="text" name="whatsapp_contacts[{{ $i }}][country_code]" value="{{ old('whatsapp_contacts.'.$i.'.country_code', $prefillCode) }}" placeholder="57" maxlength="4" inputmode="numeric" pattern="[0-9]*" class="rounded-lg border-white/10 bg-white/5 text-white px-4 py-2 w-20 text-center">
                            <input type="text" name="whatsapp_contacts[{{ $i }}][number]" value="{{ old('whatsapp_contacts.'.$i.'.number', $prefillNum) }}" placeholder="300 123 4567" class="flex-1 min-w-[180px] rounded-lg border-white/10 bg-white/5 text-white px-4 py-2">
                        </div>
                    @endfor
                </div>

                {{-- Teléfonos (máx. 5) --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Teléfonos para llamar (máx. 5)</h3>
                    <p class="text-sm text-gray-400 mb-4">Indicativo (ej. 57) y número.</p>
                    @for ($i = 0; $i < 5; $i++)
                        @php
                            $v = preg_replace('/\D/', '', $ph[$i]['value'] ?? '');
                            $prefillCode = '';
                            $prefillNum = $v;
                            if ($v !== '') {
                                foreach ($countryCodesByLen as $code) {
                                    if (str_starts_with($v, $code)) {
                                        $prefillCode = $code;
                                        $prefillNum = substr($v, strlen($code)) ?: '';
                                        break;
                                    }
                                }
                            }
                        @endphp
                        <div class="flex flex-wrap gap-4 items-center mb-3">
                            <span class="text-white text-lg font-medium">+</span>
                            <input type="text" name="phone_contacts[{{ $i }}][country_code]" value="{{ old('phone_contacts.'.$i.'.country_code', $prefillCode) }}" placeholder="57" maxlength="4" inputmode="numeric" pattern="[0-9]*" class="rounded-lg border-white/10 bg-white/5 text-white px-4 py-2 w-20 text-center">
                            <input type="text" name="phone_contacts[{{ $i }}][number]" value="{{ old('phone_contacts.'.$i.'.number', $prefillNum) }}" placeholder="300 123 4567" class="flex-1 min-w-[180px] rounded-lg border-white/10 bg-white/5 text-white px-4 py-2">
                        </div>
                    @endfor
                </div>

                {{-- Ubicación --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Ubicación</h3>
                    <p class="text-sm text-gray-400 mb-4">Pega el código iframe de Google Maps (Compartir → Incorporar un mapa) para mostrar el mapa en el panel.</p>
                    <div class="border border-white/10 rounded-lg p-4">
                        <textarea name="locations[0][map_iframe]" rows="3" placeholder="Pega aquí el iframe completo de Google Maps" class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2 font-mono text-sm">{{ old('locations.0.map_iframe', isset($loc0['map_iframe_src']) && $loc0['map_iframe_src'] ? '<iframe src="'.e($loc0['map_iframe_src']).'" width="600" height="450" style="border:0;"></iframe>' : '') }}</textarea>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2.5 bg-brand text-white font-medium rounded-xl hover:opacity-90 transition">
                        Guardar panel
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
