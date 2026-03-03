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
            <a href="{{ route('stores.vitrina.edit', $store) }}" wire:navigate class="block bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl hover:border-brand/30 transition">
                <div class="p-6 flex items-center gap-4">
                    <div class="shrink-0 w-12 h-12 rounded-xl bg-brand/20 flex items-center justify-center">
                        <svg class="w-6 h-6 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-white">Vitrina virtual</h3>
                        <p class="text-sm text-gray-400">Configura y comparte el enlace de tu catálogo para que tus clientes vean productos, planes y te contacten por WhatsApp o llamada.</p>
                    </div>
                    <span class="text-brand shrink-0">Ver vitrina virtual →</span>
                </div>
            </a>
            <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                <div class="p-6 text-gray-100">
                    ¡Bienvenido a la administración de <strong class="text-white">{{ $store->name }}</strong>!
                    <br><br>
                    Aquí irán los menús de Inventario, Ventas, Usuarios, etc.
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
