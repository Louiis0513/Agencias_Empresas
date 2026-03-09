@php
    $coverUrl = $config->cover_image_path ? asset('storage/'.$config->cover_image_path) : asset('vitrina-demo/fondo-portada.jpg');
    $logoUrl = $config->logo_image_path ? asset('storage/'.$config->logo_image_path) : asset('vitrina-demo/logo-negocio.png');
    $bgUrl = $config->background_image_path ? asset('storage/'.$config->background_image_path) : asset('vitrina-demo/fondo-pagina.jpg');

    $rawMainBg = $config->main_background_color ?: '#ffffff';
    if (preg_match('/^#([0-9a-fA-F]{6})$/', $rawMainBg, $m)) {
        $r = hexdec(substr($m[1], 0, 2));
        $g = hexdec(substr($m[1], 2, 2));
        $b = hexdec(substr($m[1], 4, 2));
        $mainBg = "rgba({$r}, {$g}, {$b}, 0.4)";
    } else {
        $mainBg = 'rgba(255, 255, 255, 0.8)';
    }
    $primaryColor = $config->primary_color ?: '#10b981';
    $secondaryColor = $config->secondary_color ?: '#047857';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $store->name }} - Panel de suscripciones</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-gray-100" @if(session('show_checkout_modal')) data-show-checkout-modal="1" @endif @if(session('auth_form')) data-auth-form="{{ session('auth_form') }}" @endif>
    <div
        class="min-h-screen flex flex-col"
        style="background-image: url('{{ $bgUrl }}'); background-size: cover; background-position: center;"
    >
        <div
            class="flex-1"
            style="background-color: {{ $mainBg }};"
        >
            <div
                class="relative h-64 w-full"
                style="background-image: url('{{ $coverUrl }}'); background-size: cover; background-position: center;"
            >
                <div class="absolute inset-0 bg-black/30"></div>
                <div class="absolute top-4 right-4 z-10 flex flex-wrap items-center gap-2 sm:gap-3 text-white drop-shadow-md">
                    @guest
                        <button type="button" id="panel-auth-show-login" class="bg-transparent border-0 shadow-none cursor-pointer text-sm font-medium hover:underline focus:outline-none focus:ring-0">Login</button>
                        <button type="button" id="panel-auth-show-register" class="bg-transparent border-0 shadow-none cursor-pointer text-sm font-medium hover:underline focus:outline-none focus:ring-0">Registro</button>
                    @else
                        <a href="{{ route('panel_suscripciones.show', ['slug' => $config->slug]) }}" class="text-sm font-medium hover:underline {{ ($currentView ?? '') === 'plans' ? 'underline' : '' }}">Planes</a>
                        <a href="{{ route('panel_suscripciones.show', ['slug' => $config->slug, 'view' => 'panel']) }}" class="text-sm font-medium hover:underline {{ ($currentView ?? '') === 'panel' ? 'underline' : '' }}">Panel</a>
                        @if (($cartCount ?? 0) > 0)
                            <a href="{{ route('panel_suscripciones.show', ['slug' => $config->slug, 'view' => 'cart']) }}" class="text-sm font-medium hover:underline {{ ($currentView ?? '') === 'cart' ? 'underline' : '' }}">Carrito ({{ $cartCount > 99 ? '99+' : $cartCount }})</a>
                        @endif
                        <span class="text-sm opacity-90">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('panel_suscripciones.logout', $config->slug) }}" class="inline">
                            @csrf
                            <button type="submit" class="bg-transparent border-0 shadow-none cursor-pointer text-sm font-medium hover:underline text-white focus:outline-none focus:ring-0">Cerrar sesión</button>
                        </form>
                    @endguest
                </div>
                <div class="absolute inset-x-0 -bottom-16 flex justify-center">
                    <div class="w-32 h-32 rounded-full border-4 border-white shadow-xl overflow-hidden bg-white">
                        <img src="{{ $logoUrl }}" alt="{{ $store->name }}" class="w-full h-full object-cover">
                    </div>
                </div>
            </div>

            <main class="pt-24 pb-16 px-4">
                @if (session('success'))
                    <div class="max-w-3xl mx-auto mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
                        {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="max-w-3xl mx-auto mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
                        {{ session('error') }}
                    </div>
                @endif

                @auth
                @if (session('show_complete_customer_form') && session('complete_customer_slug') === $config->slug)
                <section class="mt-8 max-w-2xl mx-auto mb-8">
                    <div class="bg-white/90 backdrop-blur rounded-xl shadow-lg p-6">
                        <h2 class="text-lg font-semibold text-gray-900 mb-2">Aún no estás registrado como cliente en este negocio</h2>
                        <p class="text-sm text-gray-600 mb-4">Completa los datos para continuar.</p>
                        <form method="POST" action="{{ route('panel_suscripciones.complete_customer_profile', $config->slug) }}">
                            @csrf
                            <input type="hidden" name="view" value="{{ request('view', 'plans') }}">
                            <div class="space-y-4">
                                <div>
                                    <label for="panel-complete-name" class="block text-sm font-medium text-gray-700">Nombre</label>
                                    <input type="text" name="name" id="panel-complete-name" value="{{ old('name', auth()->user()->name) }}" required class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Correo</label>
                                    <p class="mt-1 text-sm text-gray-600">{{ auth()->user()->email }}</p>
                                </div>
                                <div>
                                    <label for="panel-complete-phone" class="block text-sm font-medium text-gray-700">Teléfono</label>
                                    <input type="text" name="phone" id="panel-complete-phone" value="{{ old('phone') }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Solo números">
                                    @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="panel-complete-address" class="block text-sm font-medium text-gray-700">Dirección (opcional)</label>
                                    <input type="text" name="address" id="panel-complete-address" value="{{ old('address') }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    @error('address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                </div>
                                <hr class="border-gray-200">
                                <p class="text-sm font-medium text-gray-700">Datos para el gimnasio</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="panel-complete-gender" class="block text-sm font-medium text-gray-700">Género</label>
                                        <select name="gender" id="panel-complete-gender" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                            <option value="">—</option>
                                            <option value="M" {{ old('gender') === 'M' ? 'selected' : '' }}>M</option>
                                            <option value="F" {{ old('gender') === 'F' ? 'selected' : '' }}>F</option>
                                            <option value="NN" {{ old('gender') === 'NN' ? 'selected' : '' }}>NN</option>
                                        </select>
                                        @error('gender')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="panel-complete-blood_type" class="block text-sm font-medium text-gray-700">Tipo de sangre</label>
                                        <input type="text" name="blood_type" id="panel-complete-blood_type" value="{{ old('blood_type') }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Ej. O+">
                                        @error('blood_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="panel-complete-eps" class="block text-sm font-medium text-gray-700">EPS</label>
                                        <input type="text" name="eps" id="panel-complete-eps" value="{{ old('eps') }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @error('eps')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="panel-complete-birth_date" class="block text-sm font-medium text-gray-700">Fecha de nacimiento</label>
                                        <input type="date" name="birth_date" id="panel-complete-birth_date" value="{{ old('birth_date') }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @error('birth_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label for="panel-complete-emergency_contact_name" class="block text-sm font-medium text-gray-700">Nombre contacto emergencia</label>
                                        <input type="text" name="emergency_contact_name" id="panel-complete-emergency_contact_name" value="{{ old('emergency_contact_name') }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @error('emergency_contact_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="panel-complete-emergency_contact_phone" class="block text-sm font-medium text-gray-700">Número contacto emergencia</label>
                                        <input type="text" name="emergency_contact_phone" id="panel-complete-emergency_contact_phone" value="{{ old('emergency_contact_phone') }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" inputmode="numeric" placeholder="3001234567">
                                        @error('emergency_contact_phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>
                                <button type="submit" class="w-full inline-flex justify-center px-4 py-2.5 rounded-lg text-sm font-medium text-white shadow" style="background-color: {{ $primaryColor }};">Guardar y continuar</button>
                            </div>
                        </form>
                    </div>
                </section>
                @endif
                @endauth

                <div id="panel-main-content">
                @if (($currentView ?? 'plans') === 'panel')
                <section class="mt-8 max-w-2xl mx-auto">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Mi panel</h2>
                    @if (!isset($customer) || !$customer)
                        <div class="bg-white/90 backdrop-blur rounded-xl shadow-lg p-8 text-center">
                            <p class="text-gray-600 font-medium">No se encontró tu perfil de cliente.</p>
                            <p class="text-gray-500 text-sm mt-2">Contacta al negocio para completar tu registro.</p>
                        </div>
                    @elseif (!isset($activeSubscription) || !$activeSubscription)
                        <div class="bg-white/90 backdrop-blur rounded-xl shadow-lg p-8 text-center">
                            <p class="text-gray-600 text-lg font-medium">No tienes suscripción activa</p>
                            <p class="text-gray-500 text-sm mt-2">Contrata un plan para acceder al gimnasio.</p>
                            <a href="{{ route('panel_suscripciones.show', ['slug' => $config->slug]) }}" class="inline-flex justify-center px-4 py-2.5 rounded-lg text-sm font-medium text-white shadow mt-6" style="background-color: {{ $primaryColor }};">Ver planes</a>
                        </div>
                    @else
                        <div class="bg-white/90 backdrop-blur rounded-xl shadow-lg p-6">
                            @if (!empty($panelPlanName))
                                <p class="text-sm font-medium text-gray-600 mb-4">{{ $panelPlanName }}</p>
                            @endif
                            <div class="flex flex-wrap items-center gap-2 mb-4">
                                <span class="text-sm font-medium text-gray-700">Estado actual:</span>
                                @if (($panelStatusLabel ?? '') === 'Activo')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">Activo</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-200 text-gray-800">Inactivo</span>
                                @endif
                            </div>
                            <dl class="space-y-3 text-sm">
                                @if (isset($panelSubscriptionEndDate))
                                    <div>
                                        <dt class="text-gray-500">Fecha de fin de suscripción</dt>
                                        <dd class="font-medium text-gray-900 mt-0.5">
                                            @if ($panelSubscriptionEndDate->isPast())
                                                Venció el {{ $panelSubscriptionEndDate->format('d/m/Y') }}
                                            @else
                                                Vence el {{ $panelSubscriptionEndDate->format('d/m/Y') }}
                                            @endif
                                        </dd>
                                    </div>
                                @endif
                                <div>
                                    <dt class="text-gray-500">Asistencias</dt>
                                    <dd class="font-medium text-gray-900 mt-0.5">
                                        Has ingresado {{ $panelAttendancesCount ?? 0 }} {{ ($panelAttendancesCount ?? 0) === 1 ? 'vez' : 'veces' }}
                                        @if (isset($panelTotalEntriesLimit) && $panelTotalEntriesLimit !== null)
                                            ({{ $panelAttendancesCount ?? 0 }} de {{ $panelTotalEntriesLimit }} entradas)
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500">Días restantes</dt>
                                    <dd class="font-medium text-gray-900 mt-0.5">
                                        @if (isset($panelDaysLeft) && $panelDaysLeft > 0)
                                            Quedan {{ $panelDaysLeft }} {{ $panelDaysLeft === 1 ? 'día' : 'días' }}
                                        @else
                                            Suscripción vencida
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                            <a href="{{ route('panel_suscripciones.show', ['slug' => $config->slug]) }}" class="inline-block mt-4 text-sm font-medium hover:underline" style="color: {{ $primaryColor }};">Ver planes</a>
                        </div>
                    @endif
                </section>
                @elseif (($currentView ?? 'plans') === 'cart')
                <section class="mt-8 max-w-4xl mx-auto">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Carrito de planes</h2>
                    @if (!empty($cartPlans) && count($cartPlans) > 0)
                        <div class="bg-white/90 rounded-xl shadow overflow-hidden">
                            <ul class="divide-y divide-gray-200">
                                @foreach ($cartPlans as $item)
                                    <li class="p-4 flex flex-wrap items-center gap-4">
                                        @if (!empty($item['image_path']))
                                            <div class="w-16 h-16 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                                                <img src="{{ asset('storage/'.$item['image_path']) }}" alt="" class="w-full h-full object-cover">
                                            </div>
                                        @endif
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium text-gray-900">{{ $item['name'] }}</p>
                                            <p class="text-sm text-gray-600">{{ money($item['price'], $store->currency ?? 'COP') }} × {{ $item['quantity'] }}</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <form method="POST" action="{{ route('panel_suscripciones.cart.update', $config->slug) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="line_key" value="{{ $item['line_key'] }}">
                                                <input type="hidden" name="delta" value="-1">
                                                <button type="submit" class="px-2 py-1 rounded border border-gray-300 text-sm">−</button>
                                            </form>
                                            <span class="text-sm font-medium w-8 text-center">{{ $item['quantity'] }}</span>
                                            <form method="POST" action="{{ route('panel_suscripciones.cart.update', $config->slug) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="line_key" value="{{ $item['line_key'] }}">
                                                <input type="hidden" name="delta" value="1">
                                                <button type="submit" class="px-2 py-1 rounded border border-gray-300 text-sm">+</button>
                                            </form>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                            <div class="p-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-4">
                                <p class="text-lg font-semibold text-gray-900">Total: {{ money($cartTotal ?? 0, $store->currency ?? 'COP') }}</p>
                                <div class="flex gap-3">
                                    <button type="button" id="panel-checkout-open-modal" class="inline-flex justify-center px-4 py-2.5 rounded-lg text-sm font-medium text-white shadow" style="background-color: {{ $primaryColor }};">Solicitar pedido</button>
                                    <form method="POST" action="{{ route('panel_suscripciones.cart.clear', $config->slug) }}" onsubmit="return confirm('¿Vaciar el carrito?');">
                                        @csrf
                                        <button type="submit" class="inline-flex justify-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Limpiar carrito</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <a href="{{ route('panel_suscripciones.show', ['slug' => $config->slug]) }}" class="inline-block mt-4 text-sm text-gray-600 hover:text-gray-900">← Volver a planes</a>
                    @else
                        <div class="bg-white/90 backdrop-blur rounded-xl shadow-lg p-8 text-center">
                            <p class="text-gray-600 text-lg font-medium">Sin planes seleccionados en el carrito</p>
                            <p class="text-gray-500 text-sm mt-2">Añade planes desde la lista para continuar.</p>
                            <a href="{{ route('panel_suscripciones.show', ['slug' => $config->slug]) }}" class="inline-flex justify-center px-4 py-2.5 rounded-lg text-sm font-medium text-white shadow mt-6" style="background-color: {{ $primaryColor }};">Ver planes</a>
                        </div>
                    @endif
                </section>
                @else
                <section class="max-w-xl mx-auto bg-white/90 backdrop-blur rounded-xl shadow-lg p-6 text-center">
                    <h1 class="text-2xl font-semibold text-gray-900">{{ $store->name }}</h1>
                    @if ($config->description)
                        <p class="mt-2 text-sm text-gray-600">{{ $config->description }}</p>
                    @endif
                    @if ($config->schedule)
                        <p class="mt-2 text-sm text-gray-700 whitespace-pre-line"><span class="font-medium">Horario:</span><br>{{ $config->schedule }}</p>
                    @endif
                    <p class="mt-3 text-sm font-medium text-gray-700">Planes y suscripciones</p>
                </section>

                <section class="mt-8 max-w-4xl mx-auto">
                    <form method="GET" action="{{ route('panel_suscripciones.show', $config->slug) }}" class="bg-white/90 backdrop-blur rounded-xl shadow p-4 mb-6">
                        <input type="hidden" name="view" value="plans">
                        <div class="flex flex-wrap items-end gap-3">
                            <div class="flex-1 min-w-[140px]">
                                <label for="filter-name" class="block text-xs font-medium text-gray-600 mb-1">Nombre</label>
                                <input type="text" name="name" id="filter-name" value="{{ $filterName ?? '' }}" placeholder="Buscar por nombre" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                            </div>
                            <div class="w-full sm:w-auto min-w-[140px]">
                                <label for="filter-limit" class="block text-xs font-medium text-gray-600 mb-1">Límite total</label>
                                <select name="limit_type" id="filter-limit" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    <option value="">Todos</option>
                                    <option value="unlimited" {{ ($filterLimitType ?? '') === 'unlimited' ? 'selected' : '' }}>Ilimitado</option>
                                    <option value="limited" {{ ($filterLimitType ?? '') === 'limited' ? 'selected' : '' }}>Con límite</option>
                                </select>
                            </div>
                            <div class="w-full sm:w-auto min-w-[140px]">
                                <label for="filter-duration" class="block text-xs font-medium text-gray-600 mb-1">Duración</label>
                                <select name="duration" id="filter-duration" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    <option value="">Todas</option>
                                    @foreach ($durations ?? [] as $days)
                                        <option value="{{ $days }}" {{ (string)($filterDuration ?? '') === (string)$days ? 'selected' : '' }}>{{ $days }} {{ $days === 1 ? 'día' : 'días' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="inline-flex justify-center px-4 py-2 rounded-lg text-sm font-medium text-white shadow" style="background-color: {{ $primaryColor }};">Filtrar</button>
                                @if (($filterName ?? '') !== '' || ($filterLimitType ?? '') !== '' || ($filterDuration ?? '') !== '')
                                    <a href="{{ route('panel_suscripciones.show', $config->slug) }}" class="inline-flex justify-center px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Limpiar</a>
                                @endif
                            </div>
                        </div>
                    </form>

                    @if ($plans->isNotEmpty())
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach ($plans as $plan)
                                <div class="bg-white/90 rounded-xl shadow p-4 flex flex-col">
                                    @if (!empty($plan->image_path))
                                        <div class="mb-3" style="position: relative; width: 100%; aspect-ratio: 1 / 1; background-color: #ffffff; border-radius: 0.5rem; border: 1px solid #f3f4f6; overflow: hidden;">
                                            <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; padding: 10px;">
                                                <img
                                                    src="{{ asset('storage/'.$plan->image_path) }}"
                                                    alt="{{ $plan->name }}"
                                                    style="max-width: 100%; max-height: 100%; width: auto !important; height: auto !important; object-fit: contain !important; display: block;"
                                                >
                                            </div>
                                        </div>
                                    @endif
                                    <p class="font-medium text-gray-900">{{ $plan->name }}</p>
                                    <p class="text-sm text-gray-600 mt-1">{{ money($plan->price, $store->currency ?? 'COP') }}</p>
                                    @if (!empty($plan->description))
                                        <p class="text-xs text-gray-500 mt-2 flex-1">{{ Str::limit($plan->description, 80) }}</p>
                                    @endif
                                    <form method="POST" action="{{ route('panel_suscripciones.cart.add', $config->slug) }}" class="mt-3">
                                        @csrf
                                        <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="w-full inline-flex justify-center px-4 py-2 rounded-lg text-sm font-medium text-white shadow" style="background-color: {{ $primaryColor }};">Añadir al carrito</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="bg-white/90 backdrop-blur rounded-xl shadow-lg p-8 text-center">
                            @if (($filterName ?? '') !== '' || ($filterLimitType ?? '') !== '' || ($filterDuration ?? '') !== '')
                                <p class="text-gray-600 font-medium">Ningún plan coincide con los filtros.</p>
                                <a href="{{ route('panel_suscripciones.show', $config->slug) }}" class="inline-flex justify-center px-4 py-2.5 rounded-lg text-sm font-medium text-white shadow mt-4" style="background-color: {{ $primaryColor }};">Quitar filtros</a>
                            @else
                                <p class="text-gray-500">No hay planes disponibles en este momento.</p>
                            @endif
                        </div>
                    @endif
                </section>
                @endif
                </div>

                @guest
                <section id="panel-auth-container" class="mt-6 max-w-md mx-auto hidden">
                    <div class="bg-white/90 backdrop-blur rounded-xl shadow-lg p-6">
                        <div id="panel-auth-form-login" class="panel-auth-form hidden">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Iniciar sesión</h2>
                            <form method="POST" action="{{ route('panel_suscripciones.login', $config->slug) }}">
                                @csrf
                                <input type="hidden" name="view" value="{{ request('view', 'plans') }}">
                                <div class="space-y-4">
                                    <div>
                                        <label for="panel-login-email" class="block text-sm font-medium text-gray-700">Correo</label>
                                        <input type="email" name="email" id="panel-login-email" value="{{ old('email') }}" required autocomplete="email" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="panel-login-password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                                        <input type="password" name="password" id="panel-login-password" required autocomplete="current-password" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-gray-600 shadow-sm focus:ring-gray-500">
                                            <span class="ml-2 text-sm text-gray-600">Recordarme</span>
                                        </label>
                                    </div>
                                    <button type="submit" class="w-full inline-flex justify-center px-4 py-2.5 rounded-lg text-sm font-medium text-white shadow" style="background-color: {{ $primaryColor }};">Iniciar sesión</button>
                                    @if (Route::has('password.request'))
                                        <p class="text-center mt-2">
                                            <a href="{{ route('password.request') }}" class="text-sm hover:underline" style="color: {{ $primaryColor }};">¿Olvidaste tu contraseña?</a>
                                        </p>
                                    @endif
                                </div>
                            </form>
                        </div>
                        <div id="panel-auth-form-register" class="panel-auth-form hidden">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Registrarse</h2>
                            <form method="POST" action="{{ route('panel_suscripciones.register', $config->slug) }}">
                                @csrf
                                <input type="hidden" name="view" value="{{ request('view', 'plans') }}">
                                <div class="space-y-4">
                                    <div>
                                        <label for="panel-register-name" class="block text-sm font-medium text-gray-700">Nombre</label>
                                        <input type="text" name="name" id="panel-register-name" value="{{ old('name') }}" required autocomplete="name" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="panel-register-email" class="block text-sm font-medium text-gray-700">Correo</label>
                                        <input type="email" name="email" id="panel-register-email" value="{{ old('email') }}" required autocomplete="email" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="panel-register-password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                                        <p class="text-xs text-gray-500 mt-1">Debe contener al menos 8 caracteres, 1 mayúscula y 1 símbolo.</p>
                                        <input type="password" name="password" id="panel-register-password" required autocomplete="new-password" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="panel-register-password-confirm" class="block text-sm font-medium text-gray-700">Confirmar contraseña</label>
                                        <input type="password" name="password_confirmation" id="panel-register-password-confirm" required autocomplete="new-password" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    </div>
                                    <div>
                                        <label for="panel-register-phone" class="block text-sm font-medium text-gray-700">Teléfono</label>
                                        <input type="text" name="phone" id="panel-register-phone" value="{{ old('phone') }}" autocomplete="tel" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Solo números">
                                        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="panel-register-address" class="block text-sm font-medium text-gray-700">Dirección (opcional)</label>
                                        <input type="text" name="address" id="panel-register-address" value="{{ old('address') }}" autocomplete="street-address" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @error('address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <hr class="border-gray-200">
                                    <p class="text-sm font-medium text-gray-700">Datos para el gimnasio</p>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="panel-register-gender" class="block text-sm font-medium text-gray-700">Género</label>
                                            <select name="gender" id="panel-register-gender" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                <option value="">—</option>
                                                <option value="M" {{ old('gender') === 'M' ? 'selected' : '' }}>M</option>
                                                <option value="F" {{ old('gender') === 'F' ? 'selected' : '' }}>F</option>
                                                <option value="NN" {{ old('gender') === 'NN' ? 'selected' : '' }}>NN</option>
                                            </select>
                                            @error('gender')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                        <div>
                                            <label for="panel-register-blood_type" class="block text-sm font-medium text-gray-700">Tipo de sangre</label>
                                            <input type="text" name="blood_type" id="panel-register-blood_type" value="{{ old('blood_type') }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Ej. O+">
                                            @error('blood_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="panel-register-eps" class="block text-sm font-medium text-gray-700">EPS</label>
                                            <input type="text" name="eps" id="panel-register-eps" value="{{ old('eps') }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                            @error('eps')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                        <div>
                                            <label for="panel-register-birth_date" class="block text-sm font-medium text-gray-700">Fecha de nacimiento</label>
                                            <input type="date" name="birth_date" id="panel-register-birth_date" value="{{ old('birth_date') }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                            @error('birth_date')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <label for="panel-register-emergency_contact_name" class="block text-sm font-medium text-gray-700">Nombre contacto emergencia</label>
                                            <input type="text" name="emergency_contact_name" id="panel-register-emergency_contact_name" value="{{ old('emergency_contact_name') }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                            @error('emergency_contact_name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                        <div>
                                            <label for="panel-register-emergency_contact_phone" class="block text-sm font-medium text-gray-700">Número contacto emergencia</label>
                                            <p class="text-xs text-gray-500 mt-1">Solo números.</p>
                                            <input type="text" name="emergency_contact_phone" id="panel-register-emergency_contact_phone" value="{{ old('emergency_contact_phone') }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" inputmode="numeric" placeholder="3001234567">
                                            @error('emergency_contact_phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                    </div>
                                    <button type="submit" class="w-full inline-flex justify-center px-4 py-2.5 rounded-lg text-sm font-medium text-white shadow" style="background-color: {{ $primaryColor }};">Registrarse</button>
                                </div>
                            </form>
                        </div>
                        <div class="mt-3 text-center">
                            <button type="button" id="panel-auth-close" class="text-sm text-gray-500 hover:text-gray-700">Cerrar</button>
                        </div>
                    </div>
                </section>
                @endguest
            </main>
        </div>
    </div>

    @php $cartCount = $cartCount ?? 0; @endphp
    @if (($currentView ?? 'plans') === 'plans' && $cartCount > 0)
    <a
        id="panel-cart-float-btn"
        href="{{ route('panel_suscripciones.show', ['slug' => $config->slug, 'view' => 'cart']) }}"
        class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-[100] flex items-center gap-2 px-4 py-3 sm:px-5 sm:py-3 rounded-full shadow-lg transition hover:brightness-110 text-white"
        style="background-color: {{ $primaryColor }};"
        aria-label="Ver carrito ({{ $cartCount }} planes)"
    >
        <span class="text-xl sm:text-2xl" aria-hidden="true">🛒</span>
        <span class="font-medium text-sm sm:text-base whitespace-nowrap">Ver Carrito</span>
        <span class="flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-red-500 px-1.5 text-xs font-bold text-white">{{ $cartCount > 99 ? '99+' : $cartCount }}</span>
    </a>
    @endif

    <div id="panel-checkout-modal" class="hidden fixed inset-0 z-[150] overflow-y-auto" aria-modal="true" role="dialog">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" id="panel-checkout-modal-backdrop" aria-hidden="true"></div>
            <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Solicitar planes</h2>
                <form method="POST" action="{{ route('panel_suscripciones.cart.checkout', $config->slug) }}">
                    @csrf
                    <label for="panel-checkout-nota" class="block text-sm font-medium text-gray-700 mb-2">Nota (opcional)</label>
                    <textarea name="nota" id="panel-checkout-nota" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Comentarios"></textarea>
                    <div class="mt-4 flex gap-3 justify-end">
                        <button type="button" id="panel-checkout-close-modal" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">Cerrar</button>
                        <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium text-white shadow" style="background-color: {{ $primaryColor }};">Enviar solicitud</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var authContainer = document.getElementById('panel-auth-container');
        var authFormLogin = document.getElementById('panel-auth-form-login');
        var authFormRegister = document.getElementById('panel-auth-form-register');
        var authShowLogin = document.getElementById('panel-auth-show-login');
        var authShowRegister = document.getElementById('panel-auth-show-register');
        var authClose = document.getElementById('panel-auth-close');
        var mainContent = document.getElementById('panel-main-content');
        var cartFloatBtn = document.getElementById('panel-cart-float-btn');
        function showAuthForm(formId) {
            if (!authContainer) return;
            authContainer.classList.remove('hidden');
            if (authFormLogin) authFormLogin.classList.toggle('hidden', formId !== 'login');
            if (authFormRegister) authFormRegister.classList.toggle('hidden', formId !== 'register');
            if (mainContent) mainContent.classList.add('hidden');
            if (cartFloatBtn) cartFloatBtn.classList.add('hidden');
        }
        function hideAuthContainer() {
            if (authContainer) authContainer.classList.add('hidden');
            if (mainContent) mainContent.classList.remove('hidden');
            if (cartFloatBtn) cartFloatBtn.classList.remove('hidden');
        }
        if (authShowLogin) authShowLogin.addEventListener('click', function() { showAuthForm('login'); });
        if (authShowRegister) authShowRegister.addEventListener('click', function() { showAuthForm('register'); });
        if (authClose) authClose.addEventListener('click', hideAuthContainer);

        var checkoutModal = document.getElementById('panel-checkout-modal');
        var checkoutOpenBtn = document.getElementById('panel-checkout-open-modal');
        var checkoutCloseBtn = document.getElementById('panel-checkout-close-modal');
        var checkoutBackdrop = document.getElementById('panel-checkout-modal-backdrop');
        function showCheckoutModal() {
            if (checkoutModal) checkoutModal.classList.remove('hidden');
        }
        function hideCheckoutModal() {
            if (checkoutModal) checkoutModal.classList.add('hidden');
        }
        if (checkoutOpenBtn) checkoutOpenBtn.addEventListener('click', showCheckoutModal);
        if (checkoutCloseBtn) checkoutCloseBtn.addEventListener('click', hideCheckoutModal);
        if (checkoutBackdrop) checkoutBackdrop.addEventListener('click', hideCheckoutModal);
        if (document.body.getAttribute('data-show-checkout-modal') === '1') {
            showCheckoutModal();
        }

        (function() {
            var auth = document.body.getAttribute('data-auth-form');
            if (auth === 'login' || auth === 'register') { showAuthForm(auth); return; }
            var params = new URLSearchParams(window.location.search);
            auth = params.get('auth');
            if (auth === 'login' || auth === 'register') showAuthForm(auth);
        })();
    })();
    </script>
</body>
</html>
