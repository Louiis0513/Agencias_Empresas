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

                {{-- Navegación desktop --}}
                <nav class="hidden sm:flex items-center gap-6 text-sm text-slate-300">
                    <a href="#beneficios" class="hover:text-white transition">Beneficios</a>
                    <a href="#funcionalidades" class="hover:text-white transition">Qué incluye</a>
                    <a href="#empresario" class="hover:text-white transition">Para quién es</a>
                </nav>
                <div class="hidden sm:flex items-center gap-3">
                    <a
                        href="https://wa.me/573015031041?text={{ urlencode('Hola, quiero saber más sobre CENTRADIA.') }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="text-sm text-slate-300 hover:text-white transition"
                    >
                        Hablar con un asesor
                    </a>
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

                {{-- Botón hamburguesa móvil --}}
                <button
                    type="button"
                    id="mobile-menu-toggle"
                    class="sm:hidden inline-flex items-center justify-center rounded-md border border-slate-700 bg-slate-900/70 p-2 text-slate-200 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-sky-500"
                    aria-label="Abrir menú de navegación"
                >
                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>

            {{-- Panel móvil desplegable --}}
            <div
                id="mobile-menu"
                class="sm:hidden border-t border-slate-800 bg-slate-950/95 hidden"
            >
                <div class="mx-auto max-w-6xl px-4 py-4 space-y-4 text-sm text-slate-200">
                    <nav class="space-y-2">
                        <a href="#beneficios" class="block rounded-md px-2 py-2 hover:bg-slate-900">Beneficios</a>
                        <a href="#funcionalidades" class="block rounded-md px-2 py-2 hover:bg-slate-900">Qué incluye</a>
                        <a href="#empresario" class="block rounded-md px-2 py-2 hover:bg-slate-900">Para quién es</a>
                    </nav>

                    <div class="pt-2 border-t border-slate-800 space-y-2">
                        <a
                            href="https://wa.me/573015031041?text={{ urlencode('Hola, quiero saber más sobre CENTRADIA.') }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="flex w-full items-center justify-center rounded-full border border-slate-700 bg-slate-900/80 px-3 py-2 text-xs font-medium text-slate-100 hover:border-sky-400 hover:text-sky-200"
                        >
                            Hablar con un asesor
                        </a>
                        @if (Route::has('login'))
                            <a
                                href="{{ route('login') }}"
                                class="flex w-full items-center justify-center rounded-full border border-slate-700 bg-slate-950 px-3 py-2 text-xs font-medium text-slate-100 hover:border-sky-400 hover:text-sky-200"
                            >
                                Iniciar sesión
                            </a>
                        @endif
                        @if (Route::has('register'))
                            <a
                                href="{{ route('register') }}"
                                class="flex w-full items-center justify-center rounded-full bg-sky-500 px-3 py-2 text-xs font-semibold text-slate-950 shadow-md shadow-sky-500/40 hover:bg-sky-400"
                            >
                                Crear cuenta
                            </a>
                        @endif
                    </div>
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

    {{-- Menú móvil: comportamiento con JavaScript simple --}}
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.getElementById('mobile-menu-toggle');
            const menu = document.getElementById('mobile-menu');
            if (!toggle || !menu) return;

            toggle.addEventListener('click', (event) => {
                event.stopPropagation();
                menu.classList.toggle('hidden');
            });

            document.addEventListener('click', (event) => {
                if (!menu.classList.contains('hidden')
                    && !menu.contains(event.target)
                    && !toggle.contains(event.target)
                ) {
                    menu.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>

