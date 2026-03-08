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
                    @if ($config->schedule)
                        <p class="mt-2 text-sm text-gray-700 whitespace-pre-line"><span class="font-medium">Horario:</span><br>{{ $config->schedule }}</p>
                    @endif
                    <p class="mt-3 text-sm font-medium text-gray-700">Planes y suscripciones</p>
                </section>

                <section class="mt-8 max-w-4xl mx-auto">
                    @if (!empty($vitrinaSlug))
                        <a
                            href="{{ route('vitrina.show', $vitrinaSlug) }}"
                            class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium shadow border transition mb-6"
                            style="background-color: #ffffff; color: {{ $secondaryColor }}; border-color: {{ $secondaryColor }};"
                        >
                            Ver catálogo de productos
                        </a>
                    @endif

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
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-center text-gray-500 py-8">No hay planes disponibles en este momento.</p>
                    @endif
                </section>
            </main>
        </div>
    </div>
</body>
</html>
