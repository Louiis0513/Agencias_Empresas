<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased bg-dark text-white">
    <div
        x-data="{ sidebarMobileOpen: false }"
        class="flex min-h-screen bg-dark"
    >
        {{-- Overlay móvil: al tocar fuera se cierra el sidebar --}}
        <div
            x-show="sidebarMobileOpen"
            x-transition:enter="transition-opacity ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            @click="sidebarMobileOpen = false"
            class="fixed inset-0 z-30 bg-black/50 lg:hidden"
            aria-hidden="true"
        ></div>

        @include('livewire.layout.partials.sidebar')

        {{-- Espaciador: mismo ancho que el sidebar para no tapar contenido --}}
        {{-- Contenedor principal: en desktop se desplaza con el ancho del sidebar (estilo TailAdmin) --}}
        <div
            class="flex min-w-0 flex-1 flex-col lg:ml-64"
        >
            @include('livewire.layout.partials.navbar')

            @if (isset($header))
                <header class="border-b border-white/5 bg-dark/50 px-4 py-6 backdrop-blur-md sm:px-6 lg:px-8">
                    <div class="flex max-w-7xl items-start gap-4 mx-auto">
                        <div class="mt-1 h-6 w-1 shrink-0 bg-brand shadow-[0_0_10px_#2272FF]"></div>
                        <div class="min-w-0 flex-1">
                            {{ $header }}
                        </div>
                    </div>
                </header>
            @endif

            <main class="flex-1 py-10">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </div>
    @stack('scripts')
</body>

</html>
