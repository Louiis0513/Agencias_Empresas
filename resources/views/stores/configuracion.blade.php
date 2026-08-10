@php
    $panelQuery = request('panel');
    $allowedPanels = ['menu', 'basica', 'caja', 'productos', 'contabilidad'];
    if (in_array($panelQuery, $allowedPanels, true)) {
        $initialPanel = $panelQuery;
    } elseif ($errors->any()) {
        $initialPanel = 'basica';
    } else {
        $initialPanel = 'menu';
    }
    $perm = app(\App\Services\StorePermissionService::class);
    $canHubProductos = $perm->can($store, 'contabilidad.categorias.view') || $perm->can($store, 'products.bodegas.view');
    $canHubContabilidad = $perm->can($store, 'contabilidad.impuestos.view')
        || $perm->can($store, 'contabilidad.centros-costo.view')
        || $perm->can($store, 'contabilidad.tipos.view')
        || $perm->can($store, 'contabilidad.cuentas.view');
@endphp
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

            @if(! empty($canViewCajaBolsillos))
                <livewire:create-bolsillo-modal :store-id="$store->id" />
                <livewire:edit-bolsillo-modal :store-id="$store->id" />
            @endif

            <div x-data="{ panel: @js($initialPanel) }" class="space-y-8">
                {{-- Índice: accesos a cada tipo de configuración --}}
                <div x-show="panel === 'menu'" x-cloak class="space-y-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Configuraciones</h3>
                        <p class="text-sm text-gray-400 mt-1">Elige qué deseas configurar.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @storeCan($store, 'caja.view')
                        <button type="button" @click="panel = 'caja'"
                                class="flex w-full items-start gap-4 rounded-xl border border-white/10 bg-dark-card p-5 text-left text-white transition hover:border-brand/30 hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-brand/50">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand/20 text-brand">
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m0 0H21" /></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-white">{{ __('Caja') }}</span>
                                <span class="mt-1 block text-sm text-gray-400">{{ __('Medios de pago y bolsillos: efectivo, cuentas bancarias y saldos.') }}</span>
                            </span>
                        </button>
                        @endstoreCan
                        @storeCan($store, 'store-config.view')
                        <button type="button" @click="panel = 'basica'"
                                class="flex w-full items-start gap-4 rounded-xl border border-white/10 bg-dark-card p-5 text-left text-white transition hover:border-brand/30 hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-brand/50">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand/20 text-brand">
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-white">Configuración de la tienda</span>
                                <span class="mt-1 block text-sm text-gray-400">Edita RUT/NIT, moneda, zona horaria, ubicación, logo y más.</span>
                            </span>
                        </button>
                        @endstoreCan
                        @if($canHubProductos)
                        <button type="button" @click="panel = 'productos'"
                                class="flex w-full items-start gap-4 rounded-xl border border-white/10 bg-dark-card p-5 text-left text-white transition hover:border-brand/30 hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-brand/50">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand/20 text-brand">
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-white">Productos y servicios</span>
                                <span class="mt-1 block text-sm text-gray-400">Categorías y configuración de bodegas.</span>
                            </span>
                        </button>
                        @endif
                        @if($canHubContabilidad)
                        <button type="button" @click="panel = 'contabilidad'"
                                class="flex w-full items-start gap-4 rounded-xl border border-white/10 bg-dark-card p-5 text-left text-white transition hover:border-brand/30 hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-brand/50">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand/20 text-brand">
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" /></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-white">Contabilidad</span>
                                <span class="mt-1 block text-sm text-gray-400">Cuentas, impuestos, centros de costo y comprobantes.</span>
                            </span>
                        </button>
                        @endif
                    </div>
                </div>

                {{-- Formulario: configuración básica de la empresa --}}
                <div x-show="panel === 'basica'" x-cloak class="space-y-8">
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" @click="panel = 'menu'"
                                class="inline-flex items-center gap-2 rounded-lg border border-white/10 px-3 py-2 text-sm text-gray-300 transition hover:bg-white/5 hover:text-white focus:outline-none focus:ring-2 focus:ring-brand/50">
                            <span aria-hidden="true">←</span> Volver a configuraciones
                        </button>
                    </div>

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

                {{-- Panel: Caja / bolsillos --}}
                @storeCan($store, 'caja.view')
                <div x-show="panel === 'caja'" x-cloak class="space-y-8">
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" @click="panel = 'menu'"
                                class="inline-flex items-center gap-2 rounded-lg border border-white/10 px-3 py-2 text-sm text-gray-300 transition hover:bg-white/5 hover:text-white focus:outline-none focus:ring-2 focus:ring-brand/50">
                            <span aria-hidden="true">←</span> {{ __('Volver a configuraciones') }}
                        </button>
                    </div>
                    @if(isset($bolsillosConfig))
                        @include('stores.configuracion.partials.panel-caja-bolsillos', ['store' => $store, 'bolsillosConfig' => $bolsillosConfig])
                    @endif
                </div>
                @endstoreCan

                {{-- Hub: Productos y servicios --}}
                @if($canHubProductos)
                <div x-show="panel === 'productos'" x-cloak class="space-y-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" @click="panel = 'menu'"
                                class="inline-flex items-center gap-2 rounded-lg border border-white/10 px-3 py-2 text-sm text-gray-300 transition hover:bg-white/5 hover:text-white focus:outline-none focus:ring-2 focus:ring-brand/50">
                            <span aria-hidden="true">←</span> Volver a configuraciones
                        </button>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-white">Productos y servicios</h3>
                        <p class="text-sm text-gray-400 mt-1">Elige qué deseas configurar.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @storeCan($store, 'contabilidad.categorias.view')
                        <a href="{{ route('stores.contabilidad.categorias', $store) }}" wire:navigate
                           class="flex w-full items-start gap-4 rounded-xl border border-white/10 bg-dark-card p-5 text-left text-white transition hover:border-brand/30 hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-brand/50">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand/20 text-brand">
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z" /><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z" /></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-white">Categoría de productos y servicios</span>
                                <span class="mt-1 block text-sm text-gray-400">Clasificación contable de productos y servicios.</span>
                            </span>
                        </a>
                        @endstoreCan
                        @storeCan($store, 'products.bodegas.view')
                        <a href="{{ route('stores.products.bodegas', $store) }}" wire:navigate
                           class="flex w-full items-start gap-4 rounded-xl border border-white/10 bg-dark-card p-5 text-left text-white transition hover:border-brand/30 hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-brand/50">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand/20 text-brand">
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" /></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-white">Configuración de bodegas</span>
                                <span class="mt-1 block text-sm text-gray-400">Manejo de bodegas, códigos y ubicaciones.</span>
                            </span>
                        </a>
                        @endstoreCan
                    </div>
                </div>
                @endif

                {{-- Hub: Contabilidad --}}
                @if($canHubContabilidad)
                <div x-show="panel === 'contabilidad'" x-cloak class="space-y-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" @click="panel = 'menu'"
                                class="inline-flex items-center gap-2 rounded-lg border border-white/10 px-3 py-2 text-sm text-gray-300 transition hover:bg-white/5 hover:text-white focus:outline-none focus:ring-2 focus:ring-brand/50">
                            <span aria-hidden="true">←</span> Volver a configuraciones
                        </button>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-white">Contabilidad</h3>
                        <p class="text-sm text-gray-400 mt-1">Elige qué deseas configurar.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @storeCan($store, 'contabilidad.impuestos.view')
                        <a href="{{ route('stores.contabilidad.impuestos', $store) }}" wire:navigate
                           class="flex w-full items-start gap-4 rounded-xl border border-white/10 bg-dark-card p-5 text-left text-white transition hover:border-brand/30 hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-brand/50">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand/20 text-brand">
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.036-1.166a2.25 2.25 0 00-3.182.034l-1.5 1.5a2.25 2.25 0 00.034 3.182l3 3a2.25 2.25 0 003.182-.034l1.5-1.5a2.25 2.25 0 00-.034-3.182l-3-3z" /><path stroke-linecap="round" stroke-linejoin="round" d="M12.75 15.75l-1.5 1.5a2.25 2.25 0 01-3.182-.034l-3-3a2.25 2.25 0 01.034-3.182l1.5-1.5" /></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-white">Impuestos</span>
                                <span class="mt-1 block text-sm text-gray-400">IVA, retenciones y demás impuestos.</span>
                            </span>
                        </a>
                        @endstoreCan
                        @storeCan($store, 'contabilidad.centros-costo.view')
                        <a href="{{ route('stores.contabilidad.centros-costo', $store) }}" wire:navigate
                           class="flex w-full items-start gap-4 rounded-xl border border-white/10 bg-dark-card p-5 text-left text-white transition hover:border-brand/30 hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-brand/50">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand/20 text-brand">
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-white">Centros de costo</span>
                                <span class="mt-1 block text-sm text-gray-400">Centros, subcentros y definición en comprobantes.</span>
                            </span>
                        </a>
                        @endstoreCan
                        @storeCan($store, 'contabilidad.tipos.view')
                        <a href="{{ route('stores.contabilidad.tipos', $store) }}" wire:navigate
                           class="flex w-full items-start gap-4 rounded-xl border border-white/10 bg-dark-card p-5 text-left text-white transition hover:border-brand/30 hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-brand/50">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand/20 text-brand">
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-white">Comprobantes contables</span>
                                <span class="mt-1 block text-sm text-gray-400">Tipos de comprobante y consecutivos.</span>
                            </span>
                        </a>
                        @endstoreCan
                        @storeCan($store, 'contabilidad.cuentas.view')
                        <a href="{{ route('stores.contabilidad.cuentas', $store) }}" wire:navigate
                           class="flex w-full items-start gap-4 rounded-xl border border-white/10 bg-dark-card p-5 text-left text-white transition hover:border-brand/30 hover:bg-white/5 focus:outline-none focus:ring-2 focus:ring-brand/50">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand/20 text-brand">
                                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" /></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium text-white">Cuentas contables</span>
                                <span class="mt-1 block text-sm text-gray-400">Cuentas contables / PUC de la tienda.</span>
                            </span>
                        </a>
                        @endstoreCan
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
