@php
    $coverUrl = $config->cover_image_path ? asset('storage/'.$config->cover_image_path) : asset('vitrina-demo/fondo-portada.jpg');
    $logoUrl = $config->logo_image_path ? asset('storage/'.$config->logo_image_path) : asset('vitrina-demo/logo-negocio.png');
    $bgUrl = $config->background_image_path ? asset('storage/'.$config->background_image_path) : asset('vitrina-demo/fondo-pagina.jpg');
    $whatsappContacts = $config->whatsapp_contacts ?? [];
    $phoneContacts = $config->phone_contacts ?? [];
    $locations = $config->locations ?? [];
    $generalWhatsapp = array_filter($whatsappContacts, fn($c) => ($c['location_index'] ?? null) === null);
    $generalPhone = array_filter($phoneContacts, fn($c) => ($c['location_index'] ?? null) === null);

    // Colores personalizables con valores por defecto
    // mainBg controla el fondo general del área de contenido (no las tarjetas internas)
    $rawMainBg = $config->main_background_color ?: '#ffffff';

    // Forzar siempre opacidad 0.8
    if (preg_match('/^#([0-9a-fA-F]{6})$/', $rawMainBg, $m)) {
        $r = hexdec(substr($m[1], 0, 2));
        $g = hexdec(substr($m[1], 2, 2));
        $b = hexdec(substr($m[1], 4, 2));
        $mainBg = "rgba({$r}, {$g}, {$b}, 0.8)";
    } else {
        // fallback por si viene algo raro
        $mainBg = 'rgba(255, 255, 255, 0.8)';
    }
    $primaryColor = $config->primary_color ?: '#10b981'; // emerald-500/600
    $secondaryColor = $config->secondary_color ?: '#047857'; // emerald-700 aproximado
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $store->name }} - Vitrina</title>
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

                @if (($currentView ?? 'catalog') !== 'cart')
                <section class="max-w-xl mx-auto bg-white/90 backdrop-blur rounded-xl shadow-lg p-6 text-center">
                    <h1 class="text-2xl font-semibold text-gray-900">{{ $store->name }}</h1>
                    @if ($config->description)
                        <p class="mt-2 text-sm text-gray-600">{{ $config->description }}</p>
                    @endif
                    @if (count($locations) > 0)
                        <p class="mt-3 text-sm text-gray-700">
                            <span class="font-medium">Ubicación:</span>
                            {{ implode(' · ', array_filter(array_column($locations, 'name'))) }}
                        </p>
                    @endif
                    @if ($config->schedule)
                        <p class="mt-2 text-sm text-gray-700 whitespace-pre-line"><span class="font-medium">Horario:</span><br>{{ $config->schedule }}</p>
                    @endif
                    @if (!$config->description && count($locations) === 0 && !$config->schedule)
                        <p class="mt-2 text-sm text-gray-600">Revisa nuestro catálogo y contáctanos por WhatsApp o llamada.</p>
                    @endif
                </section>

                {{-- Barra Login / Registro / Cerrar sesión (estilo vitrina) --}}
                <section class="mt-6 max-w-xl mx-auto">
                    <div class="flex flex-wrap items-center justify-center gap-3">
                        @guest
                            <button type="button" id="vitrina-auth-show-login" class="px-4 py-2 rounded-lg text-sm font-medium border transition" style="background-color: #ffffff; color: {{ $secondaryColor }}; border-color: {{ $secondaryColor }};">Login</button>
                            <button type="button" id="vitrina-auth-show-register" class="px-4 py-2 rounded-lg text-sm font-medium shadow transition" style="background-color: {{ $primaryColor }}; color: #ffffff;">Registro</button>
                        @else
                            <span class="text-sm text-gray-600">{{ auth()->user()->name }}</span>
                            <form method="POST" action="{{ route('vitrina.logout', $config->slug) }}" class="inline">
                                @csrf
                                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Cerrar sesión</button>
                            </form>
                        @endguest
                    </div>
                </section>

                {{-- Contenedor formularios Login y Registro (solo invitados) --}}
                @guest
                <section id="vitrina-auth-container" class="mt-6 max-w-md mx-auto hidden">
                    <div class="bg-white/90 backdrop-blur rounded-xl shadow-lg p-6">
                        <div id="vitrina-auth-form-login" class="vitrina-auth-form hidden">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Iniciar sesión</h2>
                            <form method="POST" action="{{ route('vitrina.login', $config->slug) }}">
                                @csrf
                                <div class="space-y-4">
                                    <div>
                                        <label for="vitrina-login-email" class="block text-sm font-medium text-gray-700">Correo</label>
                                        <input type="email" name="email" id="vitrina-login-email" value="{{ old('email') }}" required autocomplete="email" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="vitrina-login-password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                                        <input type="password" name="password" id="vitrina-login-password" required autocomplete="current-password" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="remember" value="1" class="rounded border-gray-300 text-gray-600 shadow-sm focus:ring-gray-500">
                                            <span class="ml-2 text-sm text-gray-600">Recordarme</span>
                                        </label>
                                    </div>
                                    <button type="submit" class="w-full inline-flex justify-center px-4 py-2.5 rounded-lg text-sm font-medium text-white shadow" style="background-color: {{ $primaryColor }};">Iniciar sesión</button>
                                </div>
                            </form>
                        </div>
                        <div id="vitrina-auth-form-register" class="vitrina-auth-form hidden">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Registrarse</h2>
                            <form method="POST" action="{{ route('vitrina.register', $config->slug) }}">
                                @csrf
                                <div class="space-y-4">
                                    <div>
                                        <label for="vitrina-register-name" class="block text-sm font-medium text-gray-700">Nombre</label>
                                        <input type="text" name="name" id="vitrina-register-name" value="{{ old('name') }}" required autocomplete="name" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="vitrina-register-email" class="block text-sm font-medium text-gray-700">Correo</label>
                                        <input type="email" name="email" id="vitrina-register-email" value="{{ old('email') }}" required autocomplete="email" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="vitrina-register-password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                                        <input type="password" name="password" id="vitrina-register-password" required autocomplete="new-password" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="vitrina-register-password-confirm" class="block text-sm font-medium text-gray-700">Confirmar contraseña</label>
                                        <input type="password" name="password_confirmation" id="vitrina-register-password-confirm" required autocomplete="new-password" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                    </div>
                                    <div>
                                        <label for="vitrina-register-phone" class="block text-sm font-medium text-gray-700">Teléfono</label>
                                        <input type="text" name="phone" id="vitrina-register-phone" value="{{ old('phone') }}" autocomplete="tel" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <div>
                                        <label for="vitrina-register-address" class="block text-sm font-medium text-gray-700">Dirección (opcional)</label>
                                        <input type="text" name="address" id="vitrina-register-address" value="{{ old('address') }}" autocomplete="street-address" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                        @error('address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                                    </div>
                                    <button type="submit" class="w-full inline-flex justify-center px-4 py-2.5 rounded-lg text-sm font-medium text-white shadow" style="background-color: {{ $primaryColor }};">Registrarse</button>
                                </div>
                            </form>
                        </div>
                        <div class="mt-3 text-center">
                            <button type="button" id="vitrina-auth-close" class="text-sm text-gray-500 hover:text-gray-700">Cerrar</button>
                        </div>
                    </div>
                </section>
                @endguest

                <section class="mt-8 max-w-3xl mx-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($generalWhatsapp as $wa)
                            <a
                                href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $wa['value']) }}?text={{ urlencode('Hola, quiero hacer un pedido') }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium shadow transition"
                                style="background-color: {{ $primaryColor }}; color: #ffffff;"
                            >
                                WhatsApp {{ $wa['value'] }}
                            </a>
                        @endforeach
                        @foreach ($generalPhone as $ph)
                            <a
                                href="tel:{{ $ph['value'] }}"
                                class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium shadow border transition"
                                style="background-color: #ffffff; color: {{ $secondaryColor }}; border-color: {{ $secondaryColor }};"
                            >
                                Llamar {{ $ph['value'] }}
                            </a>
                        @endforeach
                        @if (count($generalWhatsapp) + count($generalPhone) === 0 && (count($whatsappContacts) + count($phoneContacts)) > 0)
                            <p class="text-sm text-gray-500 col-span-2">Contactos por sede más abajo.</p>
                        @endif
                        <a
                            href="#catalogo"
                            class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium shadow transition"
                            style="background-color: {{ $primaryColor }}; color: #ffffff;"
                        >
                            Ver catálogo
                        </a>
                        @if (count($locations) > 0)
                            <a
                                href="#ubicaciones"
                                class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium shadow border transition"
                                style="background-color: #ffffff; color: {{ $secondaryColor }}; border-color: {{ $secondaryColor }};"
                            >
                                Ver ubicaciones
                            </a>
                        @endif
                    </div>
                </section>

                <section id="catalogo" class="mt-12 max-w-5xl mx-auto">
                    <div class="mb-4">
                        <h2 class="text-xl font-semibold text-gray-900">Catálogo</h2>
                        <p class="mt-1 text-sm text-gray-500">Explora nuestros productos y ajusta los filtros para encontrar lo que necesitas.</p>
                    </div>
                    @if ($config->show_products)
                        <form
                            method="GET"
                            action="{{ url()->current() }}#catalogo"
                            class="mb-6 bg-white/90 backdrop-blur-md rounded-2xl shadow-lg border border-gray-100 p-5 md:p-6 text-sm flex flex-col md:flex-row md:items-stretch md:gap-6"
                        >
                            {{-- Columna lateral con título de filtros (se ve como "sidebar" en desktop) --}}
                            <div class="mb-4 md:mb-0 md:w-56 md:pr-6 md:border-r md:border-gray-100 flex flex-row md:flex-col md:items-start md:justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Filtros</p>
                                    <p class="text-xs text-gray-500">Ajusta el catálogo según tus preferencias.</p>
                                </div>
                                <svg class="hidden md:block w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M4 5h16M6 12h12M10 19h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>

                            {{-- Contenido de campos de filtro --}}
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-12 gap-4 md:gap-5">
                                <div class="md:col-span-5">
                                    <label class="block text-xs md:text-sm font-medium text-gray-700 mb-1">Categoría principal</label>
                                    <select
                                        name="root_category_id"
                                        class="w-full rounded-lg border-gray-200 bg-white text-gray-900 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    >
                                        <option value="">Todas las categorías</option>
                                        @foreach ($rootCategories as $category)
                                            <option value="{{ $category->id }}" @selected((int) request('root_category_id', $rootCategoryId) === $category->id)>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @if(isset($breadcrumb) && $breadcrumb->isNotEmpty())
                                    <div class="md:col-span-7 flex items-center md:justify-end">
                                        <p class="text-xs md:text-sm text-gray-600">
                                            Ruta:
                                            @foreach ($breadcrumb as $crumb)
                                                @if (!$loop->first)
                                                    /
                                                @endif
                                                <span class="font-medium">{{ $crumb->name }}</span>
                                            @endforeach
                                        </p>
                                    </div>
                                @endif
                                @if(isset($childCategories) && $childCategories->isNotEmpty())
                                    <div class="md:col-span-5">
                                        <label class="block text-xs md:text-sm font-medium text-gray-700 mb-1">
                                            Subcategorías de {{ optional($breadcrumb->last())->name ?? 'categoría seleccionada' }}
                                        </label>
                                        <select
                                            name="category_id"
                                            class="w-full rounded-lg border-gray-200 bg-white text-gray-900 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                        >
                                            <option value="">
                                                Todas dentro de {{ optional($breadcrumb->last())->name ?? 'esta categoría' }}
                                            </option>
                                            @foreach ($childCategories as $child)
                                                <option value="{{ $child->id }}" @selected((int) request('category_id', $currentCategoryId) === $child->id)>
                                                    {{ $child->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="md:col-span-3">
                                    <label class="block text-xs md:text-sm font-medium text-gray-700 mb-1">Ordenar por precio</label>
                                    @php
                                        $currentOrder = request('order', $order ?? 'price_asc');
                                    @endphp
                                    <select
                                        name="order"
                                        class="w-full rounded-lg border-gray-200 bg-white text-gray-900 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    >
                                        <option value="price_asc" @selected($currentOrder === 'price_asc')>Menor a mayor</option>
                                        <option value="price_desc" @selected($currentOrder === 'price_desc')>Mayor a menor</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2 pt-3 border-t border-gray-100 md:border-0 md:pt-0">
                                    <label class="block text-xs md:text-sm font-medium text-gray-700 mb-1">Productos por página</label>
                                    <select
                                        name="page_size"
                                        class="w-full rounded-lg border-gray-200 bg-white text-gray-900 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    >
                                        @foreach ($pageSizeOptions as $size)
                                            <option value="{{ $size }}" @selected((int) request('page_size', $pageSize) === $size)>{{ $size }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="md:col-span-2 pt-3 border-t border-gray-100 md:border-0 md:pt-0 flex flex-col md:flex-row md:items-end md:justify-end gap-2 md:gap-3">
                                    <a
                                        href="{{ url()->current() }}#catalogo"
                                        class="inline-flex items-center justify-center px-3 py-2 rounded-lg text-xs md:text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-50 border border-transparent transition"
                                    >
                                        Limpiar filtros
                                    </a>
                                    <button
                                        type="submit"
                                        class="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold shadow-md hover:shadow-lg transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1"
                                        style="background-color: {{ $primaryColor }}; color: #ffffff;"
                                    >
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M5 13l4 4L19 7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <span>Aplicar filtros</span>
                                    </button>
                                </div>
                            </div>
                        </form>
                    @endif
                    @if ($config->show_products && isset($catalogPaginator) && $catalogPaginator && $catalogPaginator->count())
                        <div class="mb-8">
                            <h3 class="text-lg font-medium text-gray-800 mb-3">Productos</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach ($catalogPaginator as $item)
                                    <div class="js-product-card bg-white/90 rounded-xl shadow p-4 flex flex-col"
                                        data-product-id="{{ $item->product_id ?? 0 }}"
                                        data-variant-id="{{ $item->variant_id ?? 0 }}"
                                        data-product-item-id="{{ $item->product_item_id ?? 0 }}"
                                        data-stock="{{ (int) ($item->stock ?? 0) }}">
                                    <div class="mb-3">
    {{-- CONTENEDOR EXTERNO: Cuadrado perfecto --}}
    <div style="position: relative; width: 100%; aspect-ratio: 1 / 1; background-color: #ffffff; border-radius: 0.5rem; border: 1px solid #f3f4f6; overflow: hidden;">
        
        {{-- CONTENEDOR INTERNO: Centrado absoluto --}}
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; padding: 10px;">
            @if (!empty($item->image_path))
                <img
                    src="{{ asset('storage/'.$item->image_path) }}"
                    alt="{{ $item->display_name }}"
                    {{-- CSS EN LÍNEA: Forzamos a que NO se estire --}}
                    style="max-width: 100%; max-height: 100%; width: auto !important; height: auto !important; object-fit: contain !important; display: block;"
                >
            @else
                <span style="font-size: 0.75rem; color: #9ca3af; text-align: center; padding: 0.5rem;">
                    Sin foto de referencia
                </span>
            @endif
        </div>
    </div>
</div>
                                        <p class="font-medium text-gray-900">{{ $item->display_name }}</p>
                                        <p class="text-sm text-gray-600 mt-1">${{ number_format($item->price, 0) }}</p>
                                        @if (isset($item->stock))
                                            <p class="js-stock-text text-xs mt-0.5 {{ ($item->stock ?? 0) > 0 ? 'text-gray-500' : 'text-red-600' }}">
                                                {{ ($item->stock ?? 0) > 0 ? ($item->stock . ' disponible' . (($item->stock ?? 0) !== 1 ? 's' : '')) : 'No disponible' }}
                                            </p>
                                        @endif
                                        @if (isset($item->product_id))
                                            @php
                                                $stock = (int) ($item->stock ?? 0);
                                                $canAdd = $stock > 0;
                                                $isSerialized = !empty($item->product_item_id);
                                                $maxQty = $isSerialized ? 1 : max(1, $stock);
                                            @endphp
                                            <form method="POST" action="{{ route('vitrina.cart.add', $config->slug) }}" class="mt-3 js-add-to-cart-form">
                                                @csrf
                                                <input type="hidden" name="product_id" value="{{ $item->product_id }}">
                                                @if (!empty($item->variant_id))
                                                    <input type="hidden" name="variant_id" value="{{ $item->variant_id }}">
                                                @endif
                                                @if (!empty($item->product_item_id))
                                                    <input type="hidden" name="product_item_id" value="{{ $item->product_item_id }}">
                                                @endif
                                                @if (!$isSerialized && $maxQty > 1)
                                                    <label for="qty-{{ $item->product_id }}-{{ $item->variant_id ?? 0 }}-{{ $item->product_item_id ?? 0 }}" class="sr-only">Cantidad</label>
                                                    <input type="number" name="quantity" id="qty-{{ $item->product_id }}-{{ $item->variant_id ?? 0 }}-{{ $item->product_item_id ?? 0 }}" value="1" min="1" max="{{ $maxQty }}" class="mb-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                                                @else
                                                    <input type="hidden" name="quantity" value="1">
                                                @endif
                                                <button
                                                    type="submit"
                                                    @if (!$canAdd) disabled @endif
                                                    class="w-full inline-flex items-center justify-center px-3 py-2 rounded-lg text-sm font-medium shadow transition focus:outline-none focus:ring-2 focus:ring-offset-1 {{ $canAdd ? 'hover:brightness-110' : 'opacity-50 cursor-not-allowed' }}"
                                                    style="{{ $canAdd ? 'background-color: ' . $primaryColor . '; color: #ffffff;' : 'background-color: #9ca3af; color: #ffffff;' }}"
                                                >
                                                    {{ $canAdd ? 'Añadir al carrito' : 'No disponible' }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-6">
                                {{ $catalogPaginator->appends(request()->except('page'))->fragment('catalogo')->links() }}
                            </div>
                        </div>
                    @endif
                    @if ($config->show_plans && $plans->isNotEmpty())
                        <div>
                            <h3 class="text-lg font-medium text-gray-800 mb-3">Planes / Suscripciones</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach ($plans as $plan)
                                    <div class="bg-white/90 rounded-xl shadow p-4">
                                        <p class="font-medium text-gray-900">{{ $plan->name }}</p>
                                        <p class="text-sm text-gray-600 mt-1">${{ number_format($plan->price, 0) }}</p>
                                        @if (!empty($plan->description))
                                            <p class="text-xs text-gray-500 mt-2">{{ Str::limit($plan->description, 80) }}</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @if (
                        ($config->show_products && (!isset($catalogPaginator) || !$catalogPaginator || $catalogPaginator->isEmpty())) &&
                        ($config->show_plans && $plans->isEmpty())
                    )
                        <p class="text-gray-500 text-center py-8">Próximamente productos y planes aquí.</p>
                    @endif
                </section>

                @if (count($locations) > 0)
                    <section id="ubicaciones" class="mt-12 max-w-4xl mx-auto space-y-6">
                        <h2 class="text-xl font-semibold text-gray-900">Ubicaciones</h2>
                        @foreach ($locations as $loc)
                            @if (!empty($loc['name']) || !empty($loc['address']) || !empty($loc['map_iframe_src']))
                                <div class="bg-white/90 rounded-xl shadow-lg overflow-hidden">
                                    @if (!empty($loc['map_iframe_src']))
                                        <div class="aspect-video w-full">
                                            <iframe src="{{ $loc['map_iframe_src'] }}" class="w-full h-full" style="border:0;" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="{{ $loc['name'] ?? 'Mapa' }}"></iframe>
                                        </div>
                                    @endif
                                    <div class="p-4">
                                        @if (!empty($loc['name']))
                                            <h3 class="font-semibold text-gray-900">{{ $loc['name'] }}</h3>
                                        @endif
                                        @if (!empty($loc['address']))
                                            <p class="text-sm text-gray-600 mt-1">{{ $loc['address'] }}</p>
                                        @endif
                                        @if (!empty($loc['map_iframe_src']))
                                            <a
                                                href="{{ $loc['map_iframe_src'] }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="inline-flex items-center gap-2 mt-3 px-4 py-2 rounded-lg text-sm font-medium transition"
                                                style="background-color: {{ $primaryColor }}; color: #ffffff;"
                                            >
                                                Cómo llegar
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                                            </a>
                                        @endif
                                        @php
                                            $idx = $loop->index;
                                            $waForLoc = array_filter($whatsappContacts, fn($c) => ($c['location_index'] ?? null) === $idx);
                                            $phForLoc = array_filter($phoneContacts, fn($c) => ($c['location_index'] ?? null) === $idx);
                                        @endphp
                                        @if (count($waForLoc) + count($phForLoc) > 0)
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                @foreach ($waForLoc as $wa)
                                                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $wa['value']) }}?text={{ urlencode('Hola, quiero hacer un pedido') }}" target="_blank" rel="noopener noreferrer" class="text-sm text-green-600 hover:underline">WhatsApp {{ $wa['value'] }}</a>
                                                @endforeach
                                                @foreach ($phForLoc as $ph)
                                                    <a href="tel:{{ $ph['value'] }}" class="text-sm text-gray-700 hover:underline">Llamar {{ $ph['value'] }}</a>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </section>
                @endif

                @else
                    {{-- Vista de carrito: oculta catálogo y ubicaciones --}}
                    <section id="carrito" class="w-full max-w-6xl mx-auto px-2 sm:px-4">
                        <div class="bg-white/90 backdrop-blur rounded-2xl shadow-lg overflow-hidden">
                            {{-- Cabecera del carrito --}}
                            <div class="p-4 sm:p-6 border-b border-gray-100">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                    <div>
                                        <h2 class="text-xl sm:text-2xl font-semibold text-gray-900">Mi Carrito</h2>
                                        <p class="mt-1 text-sm text-gray-600">Revisa y gestiona tus productos seleccionados.</p>
                                    </div>
                                    <a
                                        href="{{ route('vitrina.show', ['slug' => $config->slug]) }}#catalogo"
                                        class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium shadow border transition flex-shrink-0"
                                        style="background-color: #ffffff; color: {{ $secondaryColor }}; border-color: {{ $secondaryColor }};"
                                    >
                                        Continuar comprando
                                    </a>
                                </div>
                            </div>

                            @if (!empty($cartItems) && count($cartItems) > 0)
                                <div class="lg:flex lg:gap-8">
                                    {{-- Listado de productos: ancho flexible en desktop --}}
                                    <div class="flex-1 min-w-0 p-4 sm:p-6">
                                        <div class="space-y-4 sm:space-y-0 divide-y divide-gray-100">
                                            @foreach ($cartItems as $row)
                                                <div class="py-4 sm:py-5 first:pt-0 sm:first:pt-0">
                                                    <div class="flex gap-3 sm:gap-4">
                                                        {{-- Imagen del producto (mismo estilo que vitrina: cuadrado, object-contain) --}}
                                                        <div class="flex-shrink-0 w-20 h-20 sm:w-24 sm:h-24 rounded-lg overflow-hidden bg-white border border-gray-100 flex items-center justify-center">
                                                            @if (!empty($row['image_path']))
                                                                <img
                                                                    src="{{ asset('storage/'.$row['image_path']) }}"
                                                                    alt="{{ $row['name'] }}"
                                                                    class="w-full h-full object-contain"
                                                                >
                                                            @else
                                                                <span class="text-xs text-gray-400 text-center px-1">Sin foto</span>
                                                            @endif
                                                        </div>
                                                        {{-- Nombre, precio unitario, cantidad y total por línea --}}
                                                        <div class="flex-1 min-w-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-4">
                                                            <div class="min-w-0">
                                                                <p class="font-medium text-gray-900 text-sm sm:text-base line-clamp-3">{{ $row['name'] }}</p>
                                                                <p class="text-sm text-gray-600 mt-0.5">${{ number_format($row['price'], 0) }} c/u</p>
                                                            </div>
                                                            <div class="flex items-center justify-between sm:justify-end gap-3 flex-wrap">
                                                                <div class="flex items-center gap-1 sm:gap-2">
                                                                    <form method="POST" action="{{ route('vitrina.cart.update', $config->slug) }}" class="inline">
                                                                        @csrf
                                                                        <input type="hidden" name="line_key" value="{{ $row['line_key'] }}">
                                                                        <input type="hidden" name="delta" value="-1">
                                                                        <button type="submit" class="w-9 h-9 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500" aria-label="Disminuir cantidad">−</button>
                                                                    </form>
                                                                    <span class="w-8 text-center font-medium text-gray-900 text-sm sm:text-base">{{ $row['quantity'] }}</span>
                                                                    <form method="POST" action="{{ route('vitrina.cart.update', $config->slug) }}" class="inline">
                                                                        @csrf
                                                                        <input type="hidden" name="line_key" value="{{ $row['line_key'] }}">
                                                                        <input type="hidden" name="delta" value="1">
                                                                        <button type="submit" class="w-9 h-9 rounded-lg border border-gray-300 bg-white text-gray-700 font-medium hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500" aria-label="Aumentar cantidad">+</button>
                                                                    </form>
                                                                </div>
                                                                <p class="font-semibold text-gray-900 text-sm sm:text-base whitespace-nowrap">${{ number_format($row['price'] * $row['quantity'], 0) }}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    {{-- Resumen y acciones: sidebar en desktop, bloque abajo en móvil --}}
                                    <div class="lg:w-80 flex-shrink-0 border-t lg:border-t-0 lg:border-l border-gray-100 p-4 sm:p-6 bg-gray-50/50">
                                        <div class="space-y-4">
                                            <div class="space-y-2 text-right sm:text-right">
                                                <p class="text-gray-600 text-sm">Subtotal: <span class="font-semibold text-gray-900">${{ number_format($cartSubtotal ?? 0, 0) }}</span></p>
                                                <p class="text-lg sm:text-xl font-semibold text-gray-900">Total: ${{ number_format($cartTotal ?? 0, 0) }}</p>
                                            </div>
                                            <div class="flex flex-col-reverse sm:flex-row sm:flex-wrap gap-3 pt-2">
                                                <div class="flex-1 min-w-0 sm:min-w-[140px]">
                                                    <button
                                                        type="button"
                                                        id="vitrina-checkout-open-modal"
                                                        class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium shadow transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-offset-1"
                                                        style="background-color: {{ $primaryColor }}; color: #ffffff;"
                                                    >
                                                        Solicitar Pedido
                                                    </button>
                                                </div>
                                                <form method="POST" action="{{ route('vitrina.cart.clear', $config->slug) }}" class="flex-1 min-w-0 sm:min-w-[140px]" onsubmit="return confirm('¿Vaciar todo el carrito?');">
                                                    @csrf
                                                    <button
                                                        type="submit"
                                                        class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                                    >
                                                        Limpiar carrito
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="p-6 sm:p-8 text-center">
                                    <p class="text-gray-500">No hay productos en el carrito.</p>
                                    <a
                                        href="{{ route('vitrina.show', ['slug' => $config->slug]) }}#catalogo"
                                        class="mt-4 inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium shadow transition"
                                        style="background-color: {{ $primaryColor }}; color: #ffffff;"
                                    >
                                        Ver catálogo
                                    </a>
                                </div>
                            @endif
                        </div>
                    </section>
                @endif
            </main>
        </div>
    </div>

    {{-- Modal Solicitar Pedido: nota opcional y envío a checkout (invitados y logueados) --}}
    <div id="vitrina-checkout-modal" class="hidden fixed inset-0 z-[150] overflow-y-auto" aria-modal="true" role="dialog" aria-labelledby="vitrina-checkout-modal-title">
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="fixed inset-0 bg-black/50" id="vitrina-checkout-modal-backdrop" aria-hidden="true"></div>
            <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                <h2 id="vitrina-checkout-modal-title" class="text-lg font-semibold text-gray-900 mb-4">Solicitar pedido</h2>
                <form method="POST" action="{{ route('vitrina.cart.checkout', $config->slug) }}">
                    @csrf
                    <label for="vitrina-checkout-nota" class="block text-sm font-medium text-gray-700 mb-2">Nota (opcional)</label>
                    <textarea name="nota" id="vitrina-checkout-nota" rows="3" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Comentarios o instrucciones para tu pedido"></textarea>
                    <p class="text-xs text-gray-500 mt-1">Tu solicitud se guardará como cotización y te contactaremos a la brevedad.</p>
                    <div class="mt-4 flex gap-3 justify-end">
                        <button type="button" id="vitrina-checkout-close-modal" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
                            Cerrar
                        </button>
                        <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium text-white shadow" style="background-color: {{ $primaryColor }};">
                            Enviar solicitud
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Botón flotante del carrito: hijo directo de body para position:fixed respecto al viewport.
         Se oculta cuando la vista actual es el carrito. --}}
    @php $cartCount = $cartCount ?? 0; @endphp
    @if (($currentView ?? 'catalog') !== 'cart')
        <a
            href="{{ route('vitrina.show', ['slug' => $config->slug, 'view' => 'cart']) }}"
            class="fixed bottom-4 right-4 sm:bottom-6 sm:right-6 z-[100] flex items-center gap-2 px-4 py-3 sm:px-5 sm:py-3 rounded-full shadow-lg transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white"
            style="background-color: {{ $primaryColor }}; color: #ffffff;"
            aria-label="Ver carrito ({{ $cartCount }} productos)"
        >
            <span class="text-xl sm:text-2xl" aria-hidden="true">🛒</span>
            <span class="font-medium text-sm sm:text-base whitespace-nowrap">Ver Carrito</span>
            @if ($cartCount > 0)
                <span id="vitrina-cart-count" class="flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-red-500 px-1.5 text-xs font-bold text-white">
                    {{ $cartCount > 99 ? '99+' : $cartCount }}
                </span>
            @else
                <span id="vitrina-cart-count" class="hidden flex h-6 min-w-[1.5rem] items-center justify-center rounded-full bg-red-500 px-1.5 text-xs font-bold text-white"></span>
            @endif
        </a>
    @endif

    {{-- Toast de éxito (notificación flotante) --}}
    <div id="vitrina-toast" class="hidden fixed top-4 right-4 z-[200] max-w-sm" role="alert" aria-live="polite">
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-4 flex items-start gap-3">
            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-emerald-500 flex items-center justify-center">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0 pt-0.5">
                <p id="vitrina-toast-message" class="font-semibold text-gray-900 text-sm"></p>
            </div>
            <button type="button" id="vitrina-toast-close" class="flex-shrink-0 text-gray-400 hover:text-gray-600 p-1" aria-label="Cerrar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <script>
    (function() {
        var toastEl = document.getElementById('vitrina-toast');
        var toastMessage = document.getElementById('vitrina-toast-message');
        var toastClose = document.getElementById('vitrina-toast-close');
        var cartCountEl = document.getElementById('vitrina-cart-count');
        var toastTimer = null;

        function showToast(message) {
            if (toastTimer) clearTimeout(toastTimer);
            if (toastMessage) toastMessage.textContent = message || 'Producto añadido al carrito.';
            if (toastEl) {
                toastEl.classList.remove('hidden');
                toastTimer = setTimeout(function() {
                    toastEl.classList.add('hidden');
                    toastTimer = null;
                }, 3000);
            }
        }

        function hideToast() {
            if (toastTimer) clearTimeout(toastTimer);
            toastTimer = null;
            if (toastEl) toastEl.classList.add('hidden');
        }

        if (toastClose) toastClose.addEventListener('click', hideToast);

        function updateCartCount(count) {
            if (!cartCountEl) return;
            count = parseInt(count, 10) || 0;
            cartCountEl.textContent = count > 99 ? '99+' : count;
            cartCountEl.classList.remove('hidden');
            if (count === 0) cartCountEl.classList.add('hidden');
        }

        var checkoutModal = document.getElementById('vitrina-checkout-modal');
        var checkoutOpenBtn = document.getElementById('vitrina-checkout-open-modal');
        var checkoutCloseBtn = document.getElementById('vitrina-checkout-close-modal');
        var checkoutBackdrop = document.getElementById('vitrina-checkout-modal-backdrop');
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

        var authContainer = document.getElementById('vitrina-auth-container');
        var authFormLogin = document.getElementById('vitrina-auth-form-login');
        var authFormRegister = document.getElementById('vitrina-auth-form-register');
        var authShowLogin = document.getElementById('vitrina-auth-show-login');
        var authShowRegister = document.getElementById('vitrina-auth-show-register');
        var authClose = document.getElementById('vitrina-auth-close');
        function showAuthForm(formId) {
            if (!authContainer) return;
            authContainer.classList.remove('hidden');
            if (authFormLogin) authFormLogin.classList.toggle('hidden', formId !== 'login');
            if (authFormRegister) authFormRegister.classList.toggle('hidden', formId !== 'register');
        }
        function hideAuthContainer() {
            if (authContainer) authContainer.classList.add('hidden');
        }
        if (authShowLogin) authShowLogin.addEventListener('click', function() { showAuthForm('login'); });
        if (authShowRegister) authShowRegister.addEventListener('click', function() { showAuthForm('register'); });
        if (authClose) authClose.addEventListener('click', hideAuthContainer);
        (function() {
            var auth = document.body.getAttribute('data-auth-form');
            if (auth) { showAuthForm(auth); return; }
            var params = new URLSearchParams(window.location.search);
            auth = params.get('auth');
            if (auth === 'login' || auth === 'register') showAuthForm(auth);
        })();

        var primaryColor = '{{ $primaryColor ?? "#10b981" }}';
        document.querySelectorAll('.js-add-to-cart-form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                var action = form.getAttribute('action');
                var formData = new FormData(form);
                var token = form.querySelector('input[name="_token"]');
                if (token) formData.append('_token', token.value);

                fetch(action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }); })
                .then(function(result) {
                    if (result.ok && result.data && result.data.success) {
                        showToast(result.data.message || 'Producto añadido al carrito.');
                        if (typeof result.data.cart_count !== 'undefined') updateCartCount(result.data.cart_count);
                        var qtyInput = form.querySelector('input[name="quantity"]');
                        var qty = qtyInput ? (parseInt(qtyInput.value, 10) || 1) : 1;
                        var card = form.closest('.js-product-card');
                        if (card) {
                            var current = parseInt(card.getAttribute('data-stock'), 10) || 0;
                            var newStock = Math.max(0, current - qty);
                            card.setAttribute('data-stock', newStock);
                            var stockText = card.querySelector('.js-stock-text');
                            if (stockText) {
                                stockText.textContent = newStock > 0 ? (newStock === 1 ? '1 disponible' : newStock + ' disponibles') : 'No disponible';
                                stockText.classList.remove('text-gray-500', 'text-red-600');
                                stockText.classList.add(newStock > 0 ? 'text-gray-500' : 'text-red-600');
                            }
                            if (qtyInput && qtyInput.type === 'number') {
                                qtyInput.setAttribute('max', newStock);
                                var val = parseInt(qtyInput.value, 10) || 1;
                                if (val > newStock) qtyInput.value = newStock;
                            }
                            var btn = form.querySelector('button[type="submit"]');
                            if (btn) {
                                if (newStock === 0) {
                                    btn.disabled = true;
                                    btn.textContent = 'No disponible';
                                    btn.classList.add('opacity-50', 'cursor-not-allowed');
                                    btn.classList.remove('hover:brightness-110');
                                    btn.style.backgroundColor = '#9ca3af';
                                    btn.style.color = '#ffffff';
                                } else {
                                    btn.disabled = false;
                                    btn.textContent = 'Añadir al carrito';
                                    btn.classList.remove('opacity-50', 'cursor-not-allowed');
                                    btn.classList.add('hover:brightness-110');
                                    btn.style.backgroundColor = primaryColor || '#10b981';
                                    btn.style.color = '#ffffff';
                                }
                            }
                        }
                    } else {
                        showToast((result.data && result.data.message) ? result.data.message : 'No se pudo añadir al carrito.');
                    }
                })
                .catch(function() {
                    showToast('Error al añadir al carrito.');
                });
            });
        });
    })();
    </script>
</body>
</html>
