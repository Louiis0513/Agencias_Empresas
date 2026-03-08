@php
    $coverUrl = $config->cover_image_path ? asset('storage/'.$config->cover_image_path) : asset('vitrina-demo/fondo-portada.jpg');
    $logoUrl = $config->logo_image_path ? asset('storage/'.$config->logo_image_path) : asset('vitrina-demo/logo-negocio.png');
    $bgUrl = $config->background_image_path ? asset('storage/'.$config->background_image_path) : asset('vitrina-demo/fondo-pagina.jpg');
    $whatsappContacts = $config->whatsapp_contacts ?? [];
    $phoneContacts = $config->phone_contacts ?? [];
    $locations = $config->locations ?? [];
    $location = $locations[0] ?? null;
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
        $mainBg = "rgba({$r}, {$g}, {$b}, 0.4)";
    } else {
        // fallback por si viene algo raro
        $mainBg = 'rgba(255, 255, 255, 0.8)';
    }
    $primaryColor = $config->primary_color ?: '#10b981'; // emerald-500/600
    $secondaryColor = $config->secondary_color ?: '#047857'; // emerald-700 aproximado

    $countryCodesLongestFirst = ['593', '598', '595', '591', '503', '502', '506', '507', '505', '504', '57', '52', '54', '51', '58', '34', '56', '1'];
    $localNumber = function ($value) use ($countryCodesLongestFirst) {
        $digits = preg_replace('/\D/', '', $value);
        if ($digits === '') return '';
        foreach ($countryCodesLongestFirst as $code) {
            if (str_starts_with($digits, $code)) {
                $local = substr($digits, strlen($code));
                return $local !== '' ? $local : $digits;
            }
        }
        return $digits;
    };
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
                <div class="absolute top-4 right-4 z-10 flex items-center gap-3 text-white drop-shadow-md">
                    @guest
                        <button type="button" id="vitrina-auth-show-login" class="bg-transparent border-0 shadow-none cursor-pointer text-sm font-medium hover:underline focus:outline-none focus:ring-0">Login</button>
                        <button type="button" id="vitrina-auth-show-register" class="bg-transparent border-0 shadow-none cursor-pointer text-sm font-medium hover:underline focus:outline-none focus:ring-0">Registro</button>
                    @else
                        <span class="text-sm">{{ auth()->user()->name }}</span>
                        <form method="POST" action="{{ route('vitrina.logout', $config->slug) }}" class="inline">
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
                <div id="vitrina-main-content">
                @if (session('success'))
                    <div class="max-w-3xl mx-auto mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm">
                        <p>{{ session('success') }}</p>
                        @if (session('whatsapp_quote_url'))
                            <a
                                href="{{ session('whatsapp_quote_url') }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-center gap-2 mt-3 px-4 py-2.5 rounded-lg text-sm font-medium text-white shadow transition"
                                style="background-color: {{ $primaryColor }};"
                            >
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 360 362" aria-hidden="true"><path fill="#25D366" fill-rule="evenodd" d="M307.546 52.566C273.709 18.684 228.706.017 180.756 0 81.951 0 1.538 80.404 1.504 179.235c-.017 31.594 8.242 62.432 23.928 89.609L0 361.736l95.024-24.925c26.179 14.285 55.659 21.805 85.655 21.814h.077c98.788 0 179.21-80.413 179.244-179.244.017-47.898-18.608-92.926-52.454-126.807v-.008Zm-126.79 275.788h-.06c-26.73-.008-52.952-7.194-75.831-20.765l-5.44-3.231-56.391 14.791 15.05-54.981-3.542-5.638c-14.912-23.721-22.793-51.139-22.776-79.286.035-82.14 66.867-148.973 149.051-148.973 39.793.017 77.198 15.53 105.328 43.695 28.131 28.157 43.61 65.596 43.593 105.398-.035 82.149-66.867 148.982-148.982 148.982v.008Zm81.719-111.577c-4.478-2.243-26.497-13.073-30.606-14.568-4.108-1.496-7.09-2.243-10.073 2.243-2.982 4.487-11.568 14.577-14.181 17.559-2.613 2.991-5.226 3.361-9.704 1.117-4.477-2.243-18.908-6.97-36.02-22.226-13.313-11.878-22.304-26.54-24.916-31.027-2.613-4.486-.275-6.91 1.959-9.136 2.011-2.011 4.478-5.234 6.721-7.847 2.244-2.613 2.983-4.486 4.478-7.469 1.496-2.991.748-5.603-.369-7.847-1.118-2.243-10.073-24.289-13.812-33.253-3.636-8.732-7.331-7.546-10.073-7.692-2.613-.13-5.595-.155-8.586-.155-2.991 0-7.839 1.118-11.947 5.604-4.108 4.486-15.677 15.324-15.677 37.361s16.047 43.344 18.29 46.335c2.243 2.991 31.585 48.225 76.51 67.632 10.684 4.615 19.029 7.374 25.535 9.437 10.727 3.412 20.49 2.931 28.208 1.779 8.604-1.289 26.498-10.838 30.228-21.298 3.73-10.46 3.73-19.433 2.613-21.298-1.117-1.865-4.108-2.991-8.586-5.234l.008-.017Z" clip-rule="evenodd"/></svg>
                                Enviar resumen por WhatsApp
                            </a>
                        @endif
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
                    @if ($config->schedule)
                        <p class="mt-2 text-sm text-gray-700 whitespace-pre-line"><span class="font-medium">Horario:</span><br>{{ $config->schedule }}</p>
                    @endif
                    @if (!$config->description && !$location && !$config->schedule)
                        <p class="mt-2 text-sm text-gray-600">Revisa nuestro catálogo y contáctanos por WhatsApp o llamada.</p>
                    @endif
                </section>

                <section class="mt-8 max-w-3xl mx-auto">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @foreach ($generalWhatsapp as $wa)
                            @php $waDisplay = $localNumber($wa['value']); $waFull = '+' . preg_replace('/\D/', '', $wa['value']); @endphp
                            <a
                                href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $wa['value']) }}?text={{ urlencode('Hola, quiero hacer un pedido') }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                title="WhatsApp {{ $waFull }}"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium shadow transition"
                                style="background-color: {{ $primaryColor }}; color: #ffffff;"
                            >
                                <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 360 362" aria-hidden="true"><path fill="#25D366" fill-rule="evenodd" d="M307.546 52.566C273.709 18.684 228.706.017 180.756 0 81.951 0 1.538 80.404 1.504 179.235c-.017 31.594 8.242 62.432 23.928 89.609L0 361.736l95.024-24.925c26.179 14.285 55.659 21.805 85.655 21.814h.077c98.788 0 179.21-80.413 179.244-179.244.017-47.898-18.608-92.926-52.454-126.807v-.008Zm-126.79 275.788h-.06c-26.73-.008-52.952-7.194-75.831-20.765l-5.44-3.231-56.391 14.791 15.05-54.981-3.542-5.638c-14.912-23.721-22.793-51.139-22.776-79.286.035-82.14 66.867-148.973 149.051-148.973 39.793.017 77.198 15.53 105.328 43.695 28.131 28.157 43.61 65.596 43.593 105.398-.035 82.149-66.867 148.982-148.982 148.982v.008Zm81.719-111.577c-4.478-2.243-26.497-13.073-30.606-14.568-4.108-1.496-7.09-2.243-10.073 2.243-2.982 4.487-11.568 14.577-14.181 17.559-2.613 2.991-5.226 3.361-9.704 1.117-4.477-2.243-18.908-6.97-36.02-22.226-13.313-11.878-22.304-26.54-24.916-31.027-2.613-4.486-.275-6.91 1.959-9.136 2.011-2.011 4.478-5.234 6.721-7.847 2.244-2.613 2.983-4.486 4.478-7.469 1.496-2.991.748-5.603-.369-7.847-1.118-2.243-10.073-24.289-13.812-33.253-3.636-8.732-7.331-7.546-10.073-7.692-2.613-.13-5.595-.155-8.586-.155-2.991 0-7.839 1.118-11.947 5.604-4.108 4.486-15.677 15.324-15.677 37.361s16.047 43.344 18.29 46.335c2.243 2.991 31.585 48.225 76.51 67.632 10.684 4.615 19.029 7.374 25.535 9.437 10.727 3.412 20.49 2.931 28.208 1.779 8.604-1.289 26.498-10.838 30.228-21.298 3.73-10.46 3.73-19.433 2.613-21.298-1.117-1.865-4.108-2.991-8.586-5.234l.008-.017Z" clip-rule="evenodd"/></svg>
                                <span>{{ $waDisplay }}</span>
                            </a>
                        @endforeach
                        @foreach ($generalPhone as $ph)
                            @php $phDisplay = $localNumber($ph['value']); $phFull = '+' . preg_replace('/\D/', '', $ph['value']); @endphp
                            <a
                                href="tel:{{ $ph['value'] }}"
                                title="Llamar {{ $phFull }}"
                                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium shadow border transition"
                                style="background-color: #ffffff; color: {{ $secondaryColor }}; border-color: {{ $secondaryColor }};"
                            >
                                <svg class="w-5 h-5 flex-shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M14.3308 15.9402L15.6608 14.6101C15.8655 14.403 16.1092 14.2384 16.3778 14.1262C16.6465 14.014 16.9347 13.9563 17.2258 13.9563C17.517 13.9563 17.8052 14.014 18.0739 14.1262C18.3425 14.2384 18.5862 14.403 18.7908 14.6101L20.3508 16.1702C20.5579 16.3748 20.7224 16.6183 20.8346 16.887C20.9468 17.1556 21.0046 17.444 21.0046 17.7351C21.0046 18.0263 20.9468 18.3146 20.8346 18.5833C20.7224 18.8519 20.5579 19.0954 20.3508 19.3L19.6408 20.02C19.1516 20.514 18.5189 20.841 17.8329 20.9541C17.1469 21.0672 16.4427 20.9609 15.8208 20.6501C10.4691 17.8952 6.11008 13.5396 3.35083 8.19019C3.03976 7.56761 2.93414 6.86242 3.04914 6.17603C3.16414 5.48963 3.49384 4.85731 3.99085 4.37012L4.70081 3.65015C5.11674 3.23673 5.67937 3.00464 6.26581 3.00464C6.85225 3.00464 7.41488 3.23673 7.83081 3.65015L9.40082 5.22021C9.81424 5.63615 10.0463 6.19871 10.0463 6.78516C10.0463 7.3716 9.81424 7.93416 9.40082 8.3501L8.0708 9.68018C8.95021 10.8697 9.91617 11.9926 10.9608 13.04C11.9994 14.0804 13.116 15.04 14.3008 15.9102L14.3308 15.9402Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                <span>{{ $phDisplay }}</span>
                            </a>
                        @endforeach
                        <a
                            href="#catalogo"
                            class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium shadow transition"
                            style="background-color: {{ $primaryColor }}; color: #ffffff;"
                        >
                            Ver catálogo
                        </a>
                        @if ($location)
                            <a
                                href="#ubicacion"
                                class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium shadow border transition"
                                style="background-color: #ffffff; color: {{ $secondaryColor }}; border-color: {{ $secondaryColor }};"
                            >
                                Ver ubicación
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
                            class="mb-6 bg-white/90 backdrop-blur-md rounded-2xl shadow-lg border border-gray-100 p-5 md:p-6 text-sm flex flex-col lg:flex-row lg:items-stretch lg:gap-6"
                        >
                            {{-- Columna lateral con título de filtros (se ve como "sidebar" en desktop) --}}
                            <div class="mb-4 lg:mb-0 lg:w-56 lg:pr-6 lg:border-r lg:border-gray-100 flex flex-row lg:flex-col lg:items-start lg:justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Filtros</p>
                                    <p class="text-xs text-gray-500">Ajusta el catálogo según tus preferencias.</p>
                                </div>
                                <svg class="hidden lg:block w-5 h-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M4 5h16M6 12h12M10 19h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>

                            {{-- Contenido de campos de filtro --}}
                            <div class="flex-1 grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-5 min-w-0">
                                <div class="lg:col-span-5">
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
                                    <div class="lg:col-span-7 flex items-center lg:justify-end">
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
                                    <div class="lg:col-span-5">
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
                                <div class="lg:col-span-3 min-w-0">
                                    <label class="block text-xs md:text-sm font-medium text-gray-700 mb-1">Ordenar por precio</label>
                                    @php
                                        $currentOrder = request('order', $order ?? 'price_asc');
                                    @endphp
                                    <select
                                        name="order"
                                        class="w-full min-w-[10rem] rounded-lg border-gray-200 bg-white text-gray-900 px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500"
                                    >
                                        <option value="price_asc" @selected($currentOrder === 'price_asc')>Menor a mayor</option>
                                        <option value="price_desc" @selected($currentOrder === 'price_desc')>Mayor a menor</option>
                                    </select>
                                </div>
                                {{-- Fila: Productos por página → Limpiar filtros → Aplicar filtros (orden fijo) --}}
                                <div class="lg:col-span-12 pt-3 border-t border-gray-100 flex flex-col sm:flex-row sm:items-end sm:flex-wrap gap-3">
                                    <div class="order-1 sm:w-auto min-w-[140px]">
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
                                    <div class="order-2 flex items-end gap-2 sm:gap-3">
                                        <a
                                            href="{{ url()->current() }}#catalogo"
                                            class="inline-flex items-center justify-center px-3 py-2 rounded-lg text-xs md:text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-50 border border-gray-200 transition"
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
                                        <p class="text-sm text-gray-600 mt-1">{{ money($item->price, $store->currency ?? 'COP') }}</p>
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

                @if ($location && !empty($location['map_iframe_src']))
                    <section id="ubicacion" class="mt-12 max-w-4xl mx-auto">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">Ubicación</h2>
                        <div class="bg-white/90 rounded-xl shadow-lg overflow-hidden">
                            <div class="aspect-video w-full">
                                <iframe src="{{ $location['map_iframe_src'] }}" class="w-full h-full" style="border:0;" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Mapa"></iframe>
                            </div>
                        </div>
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
                                                                <p class="text-sm text-gray-600 mt-0.5">{{ money($row['price'], $store->currency ?? 'COP', false) }} c/u</p>
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
                                                                <p class="font-semibold text-gray-900 text-sm sm:text-base whitespace-nowrap">{{ money($row['price'] * $row['quantity'], $store->currency ?? 'COP', false) }}</p>
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
                                                <p class="text-gray-600 text-sm">Subtotal: <span class="font-semibold text-gray-900">{{ money($cartSubtotal ?? 0, $store->currency ?? 'COP', false) }}</span></p>
                                                <p class="text-lg sm:text-xl font-semibold text-gray-900">Total: {{ money($cartTotal ?? 0, $store->currency ?? 'COP') }}</p>
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
                </div>{{-- /vitrina-main-content --}}
                @if (($currentView ?? 'catalog') !== 'cart')
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
                                        <p class="text-xs text-gray-500 mt-1">Debe contener al menos 8 caracteres, 1 mayúscula y 1 símbolo.</p>
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
            id="vitrina-cart-float-btn"
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
        var mainContent = document.getElementById('vitrina-main-content');
        var cartFloatBtn = document.getElementById('vitrina-cart-float-btn');
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
