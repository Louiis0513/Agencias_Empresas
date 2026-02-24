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
    <div class="min-h-screen bg-dark">

        <livewire:layout.navigation />

        @if (isset($header))
            <header class="bg-dark/50 backdrop-blur-md border-b border-dark-border sticky top-0 z-10">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 flex items-start gap-4">
                    <div class="h-6 w-1 bg-brand shadow-[0_0_10px_#2272FF] mt-1"></div>

                    <div class="flex-1">
                        {{ $header }}
                    </div>
                </div>
            </header>
        @endif

        <main class="py-10">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                {{ $slot }}
            </div>
        </main>
    </div>
</body>

</html>