<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'CENTRADIA | Control total de tu empresa')</title>

    <meta name="description" content="CENTRADIA es la central operativa que conecta tus procesos internos con tus ventas para llevar tu negocio al siguiente nivel.">

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
                <div class="flex items-center gap-3">
                    <img
                        src="{{ asset('centradialogo.png') }}"
                        alt="Logo CENTRADIA"
                        class="h-10 w-auto"
                    >
                    <div class="flex flex-col leading-tight">
                        <span class="text-sm font-semibold tracking-[0.2em] text-sky-400 uppercase">Centradia</span>
                        <span class="text-xs text-slate-300">El núcleo de la operación de tu empresa</span>
                    </div>
                </div>
                <nav class="hidden sm:flex items-center gap-6 text-sm text-slate-300">
                    <a href="#beneficios" class="hover:text-white transition">Beneficios</a>
                    <a href="#funcionalidades" class="hover:text-white transition">Qué incluye</a>
                    <a href="#empresario" class="hover:text-white transition">Para quién es</a>
                </nav>
                <div class="hidden sm:flex items-center gap-3">
                    <a href="#cta" class="text-sm text-slate-300 hover:text-white transition">Hablar con un asesor</a>
                    @if (Route::has('login'))
                        <a
                            href="{{ route('login') }}"
                            class="text-sm text-slate-300 hover:text-white transition"
                        >
                            Iniciar sesión
                        </a>
                    @endif
                    @if (Route::has('register'))
                        <a
                            href="{{ route('register') }}"
                            class="inline-flex items-center gap-2 rounded-full bg-sky-500 px-4 py-2 text-sm font-semibold text-slate-950 shadow-lg shadow-sky-500/40 hover:bg-sky-400 transition"
                        >
                            Crear cuenta
                        </a>
                    @endif
                </div>
            </div>
        </header>

        <main class="flex-1">
            @yield('content')
        </main>

        <footer class="border-t border-white/10 bg-slate-950/90">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8 py-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-400">
                <p>© {{ date('Y') }} CENTRADIA. Todos los derechos reservados.</p>
                <p class="text-[11px] text-slate-500">CENTRADIA: El núcleo donde orbitan tus sueños y se ejecutan tus resultados.</p>
            </div>
        </footer>
    </div>
</body>
</html>

