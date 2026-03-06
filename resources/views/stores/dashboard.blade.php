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
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
            <a href="{{ route('stores.configuracion', $store) }}" wire:navigate class="block bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl hover:border-brand/30 transition">
                <div class="p-6 flex items-center gap-4">
                    <div class="shrink-0 w-12 h-12 rounded-xl bg-brand/20 flex items-center justify-center">
                        <svg class="w-6 h-6 text-brand" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-white">Configuración de la tienda</h3>
                        <p class="text-sm text-gray-400">Edita RUT/NIT, moneda, zona horaria, ubicación, logo y más.</p>
                    </div>
                    <span class="text-brand shrink-0">Configurar →</span>
                </div>
            </a>
            </div>
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
