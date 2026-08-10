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
    @livewireStyles
</head>

<body class="font-sans antialiased bg-dark text-white">
    <div class="flex min-h-screen flex-col bg-dark">
        @isset($header)
            <header class="sticky top-0 z-40 shrink-0 border-b border-white/10 bg-dark-card/95 backdrop-blur-md">
                <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <main class="min-h-0 flex-1">
            {{ $slot }}
        </main>
    </div>
    @stack('scripts')
    @livewireScripts
</body>

</html>
