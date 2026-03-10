<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'CENTRADIA') }}</title>

    <meta name="description" content="CENTRADIA es la central operativa que conecta tus procesos internos con tus ventas para llevar tu negocio al siguiente nivel. Inicia sesión o crea tu cuenta.">

    <link rel="icon" href="{{ asset('centradialogo.png') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-950 text-slate-50">
    <div class="min-h-screen flex flex-col">
        <header class="relative z-20 border-b border-white/10 bg-slate-950/80 backdrop-blur">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-4 flex items-center justify-between gap-4">
                <a href="{{ url('/') }}" class="flex items-center gap-3">
                    <img
                        src="{{ asset('centradialogo.png') }}"
                        alt="Logo CENTRADIA"
                        class="h-9 w-auto"
                    >
                    <div class="flex flex-col leading-tight">
                        <span class="text-xs font-semibold tracking-[0.22em] text-sky-400 uppercase">Centradia</span>
                        <span class="text-[11px] text-slate-300">Tu central operativa empresarial</span>
                    </div>
                </a>
                <div class="hidden sm:flex items-center gap-3 text-xs text-slate-300">
                    <span class="hidden md:inline text-slate-400">¿Necesitas ayuda?</span>
                    <a
                        href="https://wa.me/573015031041?text={{ urlencode('Hola, necesito ayuda con CENTRADIA.') }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-full border border-slate-600/80 bg-slate-900/60 px-3 py-1.5 text-[11px] font-medium hover:border-sky-400 hover:text-sky-200 transition"
                    >
                        Hablar con un asesor
                    </a>
                </div>
            </div>
        </header>

        <main class="flex-1">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 py-10 sm:py-16">
                <div class="flex justify-center">
                    <div class="relative w-full max-w-3xl">
                        {{-- Tarjeta de logo / bienvenida (clara, por detrás) --}}
                        <div class="hidden sm:block absolute -left-6 top-6 w-1/2 rounded-2xl bg-white px-6 py-7 shadow-lg shadow-black/10">
                            <div class="flex flex-col items-start gap-4">
                                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-900/5">
                                    <img
                                        src="{{ asset('centradialogo.png') }}"
                                        alt="CENTRADIA"
                                        class="h-10 w-auto"
                                    >
                                </div>
                                <div>
                                    <h2 class="text-sm font-semibold text-slate-900">
                                        Bienvenido a CENTRADIA
                                    </h2>
                                    <p class="mt-2 text-xs text-slate-500">
                                        Inicia sesión o crea tu cuenta para centralizar la operación de tu empresa.
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Tarjeta del formulario (oscura, al frente) --}}
                        <section class="relative z-10 mx-auto w-full rounded-2xl bg-slate-950 border border-slate-800 px-6 py-8 sm:px-8 sm:py-9 shadow-2xl shadow-black/50 sm:w-3/5">
                            {{ $slot }}
                        </section>
                    </div>
                </div>
            </div>
        </main>

        <footer class="border-t border-white/10 bg-slate-950/90">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-5 flex flex-col sm:flex-row items-center justify-between gap-3 text-[11px] text-slate-400">
                <p>© {{ date('Y') }} CENTRADIA. Todos los derechos reservados.</p>
                <p class="text-[10px] text-slate-500">CENTRADIA: El núcleo donde orbitan tus sueños y se ejecutan tus resultados.</p>
            </div>
        </footer>
    </div>
</body>
</html>

