<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Gestionando: <span class="text-brand">{{ $store->name }}</span>
            </h2>
            <span class="px-3 py-1 text-sm bg-brand/20 text-brand border border-brand/30 rounded-full font-medium">
                Panel Principal
            </span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- Acciones rápidas (solo presentación; enlaces en una siguiente iteración) --}}
            <div class="bg-dark-card border border-white/5 rounded-xl p-6">
                <div class="mb-4">
                    <h3 class="text-lg font-semibold text-white">Acciones rápidas</h3>
                    <p class="text-sm text-gray-400 mt-1">Atajos para tareas frecuentes.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3">
                    <button type="button" class="group flex w-full items-center gap-3 rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-left text-white transition hover:bg-white/10 hover:border-white/15 focus:outline-none focus:ring-2 focus:ring-brand/50">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand/20 text-brand">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" /></svg>
                        </span>
                        <span class="font-medium">Crear cliente</span>
                    </button>
                    <button type="button" class="group flex w-full items-center gap-3 rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-left text-white transition hover:bg-white/10 hover:border-white/15 focus:outline-none focus:ring-2 focus:ring-brand/50">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand/20 text-brand">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.25 2.25 0 00-3.984 0 17.902 17.902 0 00-3.213 9.193c-.038.62.469 1.124 1.09 1.124h1.125m-9 0H9.375a1.125 1.125 0 01-1.125-1.125V14.25M9.75 21h7.5v-2.25a3 3 0 00-3-3h-1.5a3 3 0 00-3 3V21z" /></svg>
                        </span>
                        <span class="font-medium">Crear Proveedor</span>
                    </button>
                    <button type="button" class="group flex w-full items-center gap-3 rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-left text-white transition hover:bg-white/10 hover:border-white/15 focus:outline-none focus:ring-2 focus:ring-brand/50">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand/20 text-brand">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>
                        </span>
                        <span class="font-medium">Vender</span>
                    </button>
                    <button type="button" class="group flex w-full items-center gap-3 rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-left text-white transition hover:bg-white/10 hover:border-white/15 focus:outline-none focus:ring-2 focus:ring-brand/50">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand/20 text-brand">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                        </span>
                        <span class="font-medium">Crear Producto</span>
                    </button>
                    <button type="button" class="group flex w-full items-center gap-3 rounded-lg border border-white/10 bg-white/5 px-4 py-3 text-left text-white transition hover:bg-white/10 hover:border-white/15 focus:outline-none focus:ring-2 focus:ring-brand/50">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand/20 text-brand">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>
                        </span>
                        <span class="font-medium">Crear trabajador</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
