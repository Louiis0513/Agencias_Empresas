<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Configuración - {{ $store->name }}
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

            <form action="{{ route('stores.configuracion.update', $store) }}" method="POST" enctype="multipart/form-data" class="space-y-8">
                @csrf
                @method('PUT')

                {{-- Datos básicos --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Datos básicos</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm text-gray-400 mb-2">Nombre de la Tienda</label>
                            <input type="text" name="name" value="{{ old('name', $store->name) }}" required
                                   class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2 focus:ring-brand focus:border-brand"
                                   placeholder="Ej: Restaurante La Plaza">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">RUT/NIT</label>
                            <input type="text" name="rut_nit" value="{{ old('rut_nit', $store->rut_nit) }}"
                                   class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2"
                                   placeholder="Número de identificación tributaria">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Moneda</label>
                            <select name="currency" class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2">
                                <option value="COP" {{ old('currency', $store->currency) === 'COP' ? 'selected' : '' }}>COP - Peso colombiano</option>
                                <option value="USD" {{ old('currency', $store->currency) === 'USD' ? 'selected' : '' }}>USD - Dólar</option>
                                <option value="MXN" {{ old('currency', $store->currency) === 'MXN' ? 'selected' : '' }}>MXN - Peso mexicano</option>
                                <option value="ARS" {{ old('currency', $store->currency) === 'ARS' ? 'selected' : '' }}>ARS - Peso argentino</option>
                                <option value="CLP" {{ old('currency', $store->currency) === 'CLP' ? 'selected' : '' }}>CLP - Peso chileno</option>
                                <option value="PEN" {{ old('currency', $store->currency) === 'PEN' ? 'selected' : '' }}>PEN - Sol peruano</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Régimen</label>
                            <input type="text" name="regimen" value="{{ old('regimen', $store->regimen) }}"
                                   class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2"
                                   placeholder="Ej: Régimen simplificado">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Dominio</label>
                            <input type="text" name="domain" value="{{ old('domain', $store->domain) }}"
                                   class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2"
                                   placeholder="mitienda.com">
                        </div>
                    </div>
                </div>

                {{-- Ubicación y zona horaria --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Ubicación y zona horaria</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Zona horaria</label>
                            <select name="timezone" class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2">
                                <option value="America/Bogota" {{ old('timezone', $store->timezone ?? 'America/Bogota') === 'America/Bogota' ? 'selected' : '' }}>COT (Colombia) - UTC-05:00</option>
                                <option value="America/Mexico_City" {{ old('timezone', $store->timezone) === 'America/Mexico_City' ? 'selected' : '' }}>CST (México) - UTC-06:00</option>
                                <option value="America/Argentina/Buenos_Aires" {{ old('timezone', $store->timezone) === 'America/Argentina/Buenos_Aires' ? 'selected' : '' }}>ART (Argentina) - UTC-03:00</option>
                                <option value="America/Lima" {{ old('timezone', $store->timezone) === 'America/Lima' ? 'selected' : '' }}>PET (Perú) - UTC-05:00</option>
                                <option value="America/Santiago" {{ old('timezone', $store->timezone) === 'America/Santiago' ? 'selected' : '' }}>CLT (Chile) - UTC-04:00</option>
                                <option value="America/Caracas" {{ old('timezone', $store->timezone) === 'America/Caracas' ? 'selected' : '' }}>VET (Venezuela) - UTC-04:00</option>
                                <option value="America/Guayaquil" {{ old('timezone', $store->timezone) === 'America/Guayaquil' ? 'selected' : '' }}>ECT (Ecuador) - UTC-05:00</option>
                                <option value="Europe/Madrid" {{ old('timezone', $store->timezone) === 'Europe/Madrid' ? 'selected' : '' }}>CET (España) - UTC+01:00</option>
                                <option value="America/New_York" {{ old('timezone', $store->timezone) === 'America/New_York' ? 'selected' : '' }}>EST (USA Este) - UTC-05:00</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Formato de fecha</label>
                            <select name="date_format" class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2">
                                <option value="d-m-Y" {{ old('date_format', $store->date_format ?? 'd-m-Y') === 'd-m-Y' ? 'selected' : '' }}>d-MM-YYYY (31-12-2025)</option>
                                <option value="Y-m-d" {{ old('date_format', $store->date_format) === 'Y-m-d' ? 'selected' : '' }}>YYYY-MM-dd (2025-12-31)</option>
                                <option value="m/d/Y" {{ old('date_format', $store->date_format) === 'm/d/Y' ? 'selected' : '' }}>MM/dd/YYYY (12/31/2025)</option>
                                <option value="d/m/Y" {{ old('date_format', $store->date_format) === 'd/m/Y' ? 'selected' : '' }}>dd/MM/YYYY (31/12/2025)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Formato de hora</label>
                            <select name="time_format" class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2">
                                <option value="24" {{ old('time_format', $store->time_format ?? '24') === '24' ? 'selected' : '' }}>24 horas</option>
                                <option value="12" {{ old('time_format', $store->time_format) === '12' ? 'selected' : '' }}>12 horas (AM/PM)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">País</label>
                            <input type="text" name="country" value="{{ old('country', $store->country) }}"
                                   class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2"
                                   placeholder="Colombia">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Departamento/Provincia</label>
                            <input type="text" name="department" value="{{ old('department', $store->department) }}"
                                   class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2"
                                   placeholder="Antioquia">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Ciudad</label>
                            <input type="text" name="city" value="{{ old('city', $store->city) }}"
                                   class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2"
                                   placeholder="Medellín">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm text-gray-400 mb-2">Dirección</label>
                            <input type="text" name="address" value="{{ old('address', $store->address) }}"
                                   class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2"
                                   placeholder="Calle 123 #45-67">
                        </div>
                    </div>
                </div>

                {{-- Contacto --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Contacto</h3>
                    <p class="text-sm text-gray-400 mb-4">Solo caracteres numéricos (incluyendo indicativo de país sin +).</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Teléfono</label>
                            <input type="text" name="phone" value="{{ old('phone', $store->phone) }}"
                                   class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2"
                                   placeholder="573001234567" inputmode="numeric">
                        </div>
                        <div>
                            <label class="block text-sm text-gray-400 mb-2">Celular</label>
                            <input type="text" name="mobile" value="{{ old('mobile', $store->mobile) }}"
                                   class="w-full rounded-lg border-white/10 bg-white/5 text-white px-4 py-2"
                                   placeholder="573001234567" inputmode="numeric">
                        </div>
                    </div>
                </div>

                {{-- Logo --}}
                <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                    <h3 class="font-medium text-white mb-4">Logo</h3>
                    @if ($store->logo_path)
                        <div class="mb-4 flex items-center gap-4">
                            <img src="{{ asset('storage/'.$store->logo_path) }}" alt="Logo" class="h-16 w-auto object-contain rounded-lg border border-white/10">
                            <label class="flex items-center gap-2 text-gray-400">
                                <input type="hidden" name="delete_logo" value="0">
                                <input type="checkbox" name="delete_logo" value="1" class="rounded border-white/10">
                                <span class="text-sm">Eliminar logo actual</span>
                            </label>
                        </div>
                    @endif
                    <p class="text-sm text-gray-400 mb-4">Sube una nueva imagen para reemplazar el logo. Se convertirá automáticamente a WebP.</p>
                    <input type="file" name="logo" accept="image/*" class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-brand file:text-white file:font-medium hover:file:opacity-90">
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="px-6 py-2.5 bg-brand text-white font-medium rounded-xl hover:opacity-90 transition">
                        Guardar configuración
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
