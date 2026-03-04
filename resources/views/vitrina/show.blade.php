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
<body class="min-h-screen bg-gray-100">
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
                                    <div class="bg-white/90 rounded-xl shadow p-4 flex flex-col">
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
            </main>
        </div>
    </div>
</body>
</html>
