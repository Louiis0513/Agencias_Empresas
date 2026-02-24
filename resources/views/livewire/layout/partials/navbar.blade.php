<header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-4 border-b border-white/5 bg-dark-card/80 px-4 backdrop-blur-md sm:px-6">
    {{-- Hamburger: solo visible en móvil/tablet, abre el sidebar --}}
    <button type="button" @click="sidebarMobileOpen = !sidebarMobileOpen" class="rounded-lg p-2 text-gray-400 hover:bg-white/5 hover:text-white lg:hidden" aria-label="Abrir menú">
        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
        </svg>
    </button>
    {{-- Búsqueda (placeholder) --}}
    <div class="hidden flex-1 max-w-md sm:block">
        <div class="flex items-center gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-gray-400">
            <svg class="h-4 w-4 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" /></svg>
            <span class="text-sm">Buscar o escribir comando...</span>
        </div>
    </div>
    <div class="flex flex-1 items-center justify-end gap-2 sm:flex-none sm:gap-4">
        {{-- Notificaciones (placeholder) --}}
        <button type="button" class="relative rounded-lg p-2 text-gray-400 hover:bg-white/5 hover:text-white" aria-label="Notificaciones">
            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" /></svg>
            <span class="absolute right-1 top-1 h-2 w-2 rounded-full bg-orange-500"></span>
        </button>
        {{-- Usuario (Livewire: logout y nombre) --}}
        <livewire:layout.user-menu />
    </div>
</header>
