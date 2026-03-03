@php
    $coverUrl = $config->cover_image_path ? asset('storage/'.$config->cover_image_path) : asset('vitrina-demo/fondo-portada.jpg');
    $logoUrl = $config->logo_image_path ? asset('storage/'.$config->logo_image_path) : asset('vitrina-demo/logo-negocio.png');
    $bgUrl = $config->background_image_path ? asset('storage/'.$config->background_image_path) : asset('vitrina-demo/fondo-pagina.jpg');
    $whatsappContacts = $config->whatsapp_contacts ?? [];
    $phoneContacts = $config->phone_contacts ?? [];
    $locations = $config->locations ?? [];
    $generalWhatsapp = array_filter($whatsappContacts, fn($c) => ($c['location_index'] ?? null) === null);
    $generalPhone = array_filter($phoneContacts, fn($c) => ($c['location_index'] ?? null) === null);
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
        <div class="flex-1 bg-white/80">
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
                            <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $wa['value']) }}?text={{ urlencode('Hola, quiero hacer un pedido') }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg bg-green-500 text-white text-sm font-medium shadow hover:bg-green-600 transition">
                                WhatsApp {{ $wa['value'] }}
                            </a>
                        @endforeach
                        @foreach ($generalPhone as $ph)
                            <a href="tel:{{ $ph['value'] }}" class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg bg-white/90 text-emerald-700 text-sm font-medium shadow border border-emerald-100 hover:bg-emerald-50 transition">
                                Llamar {{ $ph['value'] }}
                            </a>
                        @endforeach
                        @if (count($generalWhatsapp) + count($generalPhone) === 0 && (count($whatsappContacts) + count($phoneContacts)) > 0)
                            <p class="text-sm text-gray-500 col-span-2">Contactos por sede más abajo.</p>
                        @endif
                        <a href="#catalogo" class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-medium shadow hover:bg-emerald-700 transition">
                            Ver catálogo
                        </a>
                        @if (count($locations) > 0)
                            <a href="#ubicaciones" class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg bg-white/90 text-emerald-700 text-sm font-medium shadow border border-emerald-100 hover:bg-emerald-50 transition">
                                Ver ubicaciones
                            </a>
                        @endif
                    </div>
                </section>

                <section id="catalogo" class="mt-12 max-w-5xl mx-auto">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Catálogo</h2>
                    @if ($config->show_products)
                        <form method="GET" action="{{ url()->current() }}" class="mb-6 bg-white/90 rounded-xl shadow p-4 grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-700 mb-1">Categoría principal</label>
                                <select name="root_category_id" class="w-full rounded-lg border-gray-200 text-gray-900 px-3 py-2">
                                    <option value="">Todas las categorías</option>
                                    @foreach ($rootCategories as $category)
                                        <option value="{{ $category->id }}" @selected((int) request('root_category_id', $rootCategoryId) === $category->id)>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @if(isset($breadcrumb) && $breadcrumb->isNotEmpty())
                                <div class="md:col-span-2 flex items-end">
                                    <p class="text-xs text-gray-600">
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
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        Subcategorías de {{ optional($breadcrumb->last())->name ?? 'categoría seleccionada' }}
                                    </label>
                                    <select name="category_id" class="w-full rounded-lg border-gray-200 text-gray-900 px-3 py-2">
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
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Ordenar por precio</label>
                                @php
                                    $currentOrder = request('order', $order ?? 'price_asc');
                                @endphp
                                <select name="order" class="w-full rounded-lg border-gray-200 text-gray-900 px-3 py-2">
                                    <option value="price_asc" @selected($currentOrder === 'price_asc')>Menor a mayor</option>
                                    <option value="price_desc" @selected($currentOrder === 'price_desc')>Mayor a menor</option>
                                </select>
                            </div>
                            <div class="md:col-span-4 flex flex-wrap items-end justify-between gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Productos por página</label>
                                    <select name="page_size" class="rounded-lg border-gray-200 text-gray-900 px-3 py-2">
                                        @foreach ($pageSizeOptions as $size)
                                            <option value="{{ $size }}" @selected((int) request('page_size', $pageSize) === $size)>{{ $size }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex items-center gap-2">
                                    <a href="{{ url()->current() }}" class="text-xs text-gray-500 hover:text-gray-700 underline">
                                        Limpiar filtros
                                    </a>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-emerald-600 text-white text-xs font-semibold shadow hover:bg-emerald-700 transition">
                                        Aplicar filtros
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
                                        @if (!empty($item->image_path))
                                            <div class="mb-3">
                                                <img src="{{ asset('storage/'.$item->image_path) }}"
                                                     alt="{{ $item->display_name }}"
                                                     class="w-full h-40 object-cover rounded-lg border border-gray-100">
                                            </div>
                                        @endif
                                        <p class="font-medium text-gray-900">{{ $item->display_name }}</p>
                                        <p class="text-sm text-gray-600 mt-1">${{ number_format($item->price, 0) }}</p>
                                    </div>
                                @endforeach
                            </div>
                            <div class="mt-6">
                                {{ $catalogPaginator->appends(request()->except('page'))->links() }}
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
                                            <a href="{{ $loc['map_iframe_src'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 mt-3 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 transition">
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
