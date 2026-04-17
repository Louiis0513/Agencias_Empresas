@php
    $store = request()->route('store');
    $perm = app(\App\Services\StorePermissionService::class);
    $canPersonas = $store && ($perm->can($store, 'customers.view') || $perm->can($store, 'workers.view'));
    $canProductos = $store && ($perm->can($store, 'products.view') || $perm->can($store, 'categories.view') || $perm->can($store, 'attribute-groups.view') || $perm->can($store, 'inventario.view') || $perm->can($store, 'proveedores.view') || $perm->can($store, 'product-purchases.view') || $perm->can($store, 'support-documents.view'));
    $canFinanciero = $store && ($perm->can($store, 'caja.view') || $perm->can($store, 'activos.view') || $perm->can($store, 'accounts-payables.view') || $perm->can($store, 'accounts-receivables.view') || $perm->can($store, 'comprobantes-egreso.view') || $perm->can($store, 'comprobantes-ingreso.view') || $perm->can($store, 'invoices.view') || $perm->can($store, 'purchases.view'));
    $canVentas = $store && ($perm->can($store, 'ventas.carrito.view') || $perm->can($store, 'cotizaciones.view'));
    $canSuscripciones = $store && ($perm->can($store, 'subscriptions.view') || $perm->can($store, 'asistencias.view'));
    $canInformes = $store && ($perm->can($store, 'reports.products.view') || $perm->can($store, 'reports.billing.view'));
    $isProductPurchase = false;
    if ($store && request()->routeIs('stores.purchases.show')) {
        $p = request()->route('purchase');
        if ($p && method_exists($p, 'isProducto')) {
            $isProductPurchase = $p->isProducto();
        } elseif (request()->header('referer') && str_contains(request()->header('referer'), '/productos/compras')) {
            $isProductPurchase = true;
        }
    }
    $inProductos = $store && (request()->routeIs('stores.products*') || request()->routeIs('stores.categories*') || request()->routeIs('stores.attribute-groups*') || request()->routeIs('stores.inventario*') || request()->routeIs('stores.proveedores*') || request()->routeIs('stores.product-purchases*') || (request()->routeIs('stores.purchases.show') && $isProductPurchase));
    $inPersonas = $store && (request()->routeIs('stores.customers*') || request()->routeIs('stores.workers*'));
    $inFinanciero = $store && ((request()->routeIs('stores.cajas*') || request()->routeIs('stores.activos*') || request()->routeIs('stores.accounts-payables*') || request()->routeIs('stores.accounts-receivables*') || request()->routeIs('stores.comprobantes-egreso*') || request()->routeIs('stores.comprobantes-ingreso*') || request()->routeIs('stores.invoices*') || (request()->routeIs('stores.purchases*') && !$isProductPurchase)) && !request()->routeIs('stores.product-purchases*'));
    $inVentas = $store && request()->routeIs('stores.ventas*');
    $inSuscripciones = $store && (request()->routeIs('stores.subscriptions*') || request()->routeIs('stores.asistencias*'));
    $inInformes = $store && request()->routeIs('stores.reports*');
@endphp
{{-- Sidebar: siempre expandido (icono + texto). Móvil: se despliega con hamburger. --}}
<aside
    class="fixed left-0 top-0 z-40 h-screen w-64 flex flex-col bg-dark-card border-r border-white/5 transition-transform duration-300 ease-in-out -translate-x-full lg:translate-x-0"
    :class="{ '!translate-x-0': sidebarMobileOpen }"
>
    {{-- Logo --}}
    <div class="flex h-16 shrink-0 items-center border-b border-white/5 px-3">
        <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2 min-w-0">
            <x-application-logo class="h-9 w-9 shrink-0 fill-current text-brand" />
            <span class="whitespace-nowrap font-semibold text-white truncate">{{ config('app.name') }}</span>
        </a>
    </div>
    <nav class="flex-1 overflow-y-auto overflow-x-hidden py-4" @click="if ($event.target.closest('a')) sidebarMobileOpen = false">
        <div class="px-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Menú</div>
        <ul class="mt-2 space-y-0.5 px-2">
            @if($store)
                {{-- Resumen --}}
                @storeCan($store, 'dashboard.view')
                <li>
                    <a href="{{ route('stores.dashboard', $store) }}" wire:navigate class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-gray-300 transition {{ request()->routeIs('stores.dashboard') ? 'bg-brand/20 text-brand' : 'hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                        <span class="whitespace-nowrap">Resumen</span>
                    </a>
                </li>
                @endstoreCan
                {{-- Vitrina virtual --}}
                @storeCan($store, 'vitrina.view')
                <li>
                    <a href="{{ route('stores.vitrina.edit', $store) }}" wire:navigate class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-gray-300 transition {{ request()->routeIs('stores.vitrina.*') ? 'bg-brand/20 text-brand' : 'hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" /></svg>
                        <span class="whitespace-nowrap">Vitrina virtual</span>
                    </a>
                </li>
                @endstoreCan
                {{-- Panel Suscripciones --}}
                @storeCan($store, 'panel-suscripciones-config.view')
                <li>
                    <a href="{{ route('stores.panel-suscripciones.edit', $store) }}" wire:navigate class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-gray-300 transition {{ request()->routeIs('stores.panel-suscripciones.*') ? 'bg-brand/20 text-brand' : 'hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.856-.117-1.653-.124-2.653-.04-1.326.087-2.653.124-3.918.124-1.265 0-2.592-.037-3.918-.124-1-.084-1.797-.023-2.653.04a6 6 0 01-7.03-5.92 3 3 0 013-3m14.25 0a3 3 0 013 3m-3 0v1.875c0-1.036-.84-1.875-1.875-1.875H3.375C2.34 7.5 1.5 8.34 1.5 9.375V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0020.25 18V9.375c0-1.036-.84-1.875-1.875-1.875H18.75a3 3 0 01-3-3V5.25z" /></svg>
                        <span class="whitespace-nowrap">Panel Suscripciones</span>
                    </a>
                </li>
                @endstoreCan
                {{-- Configuraciones de la tienda --}}
                @storeCan($store, 'store-config.view')
                <li>
                    <a href="{{ route('stores.configuracion', $store) }}" wire:navigate class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-gray-300 transition {{ request()->routeIs('stores.configuracion*') ? 'bg-brand/20 text-brand' : 'hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.37.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.542-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        <span class="whitespace-nowrap">Configuraciones</span>
                    </a>
                </li>
                @endstoreCan
                {{-- Personas (dropdown) --}}
                @if($canPersonas)
                <li x-data="{ open: {{ $inPersonas ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-gray-300 transition {{ $inPersonas ? 'bg-brand/20 text-brand' : 'hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" /></svg>
                        <span class="flex-1 whitespace-nowrap">Personas</span>
                        <svg :class="open && 'rotate-180'" class="h-4 w-4 shrink-0 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" x-transition class="ml-4 mt-0.5 space-y-0.5 border-l border-white/5 pl-2">
                        @storeCan($store, 'customers.view')
                        <a href="{{ route('stores.customers', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.customers*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Clientes</a>
                        @endstoreCan
                        @storeCan($store, 'workers.view')
                        <a href="{{ route('stores.workers', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.workers*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Trabajadores</a>
                        @endstoreCan
                    </div>
                </li>
                @endif
                {{-- Productos (dropdown) --}}
                @if($canProductos)
                <li x-data="{ open: {{ $inProductos ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-gray-300 transition {{ $inProductos ? 'bg-brand/20 text-brand' : 'hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" /></svg>
                        <span class="flex-1 whitespace-nowrap">Productos</span>
                        <svg :class="open && 'rotate-180'" class="h-4 w-4 shrink-0 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" x-transition class="ml-4 mt-0.5 space-y-0.5 border-l border-white/5 pl-2">
                        @storeCan($store, 'products.view')
                        <a href="{{ route('stores.products', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.products*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Productos</a>
                        @endstoreCan
                        @storeCan($store, 'categories.view')
                        <a href="{{ route('stores.categories', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.categories*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Categorías</a>
                        @endstoreCan
                        @storeCan($store, 'attribute-groups.view')
                        <a href="{{ route('stores.attribute-groups', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.attribute-groups*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Atributos</a>
                        @endstoreCan
                        @storeCan($store, 'inventario.view')
                        <a href="{{ route('stores.inventario', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.inventario*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Inventario</a>
                        @endstoreCan
                        @storeCan($store, 'proveedores.view')
                        <a href="{{ route('stores.proveedores', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.proveedores*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Proveedores</a>
                        @endstoreCan
                        @storeCan($store, 'product-purchases.view')
                        <a href="{{ route('stores.product-purchases', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.product-purchases*') || (request()->routeIs('stores.purchases.show') && $isProductPurchase) ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Compra de productos</a>
                        @endstoreCan
                    </div>
                </li>
                @endif
                {{-- Financiero (dropdown) --}}
                @if($canFinanciero)
                <li x-data="{ open: {{ $inFinanciero ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-gray-300 transition {{ $inFinanciero ? 'bg-brand/20 text-brand' : 'hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75a.75.75 0 01-.75.75h-.75m0 0v-.375c0-.621-.504-1.125-1.125-1.125H3.75M17.25 6v9m0-10.5v.75a.75.75 0 01.75-.75h.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M3.75 6v9m0-10.5v.75a.75.75 0 01.75-.75h.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25" /></svg>
                        <span class="flex-1 whitespace-nowrap">Financiero</span>
                        <svg :class="open && 'rotate-180'" class="h-4 w-4 shrink-0 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" x-transition class="ml-4 mt-0.5 space-y-0.5 border-l border-white/5 pl-2">
                        @storeCan($store, 'purchases.view')
                        <a href="{{ route('stores.purchases', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ (request()->routeIs('stores.purchases*') && !$isProductPurchase) ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Compra de activos</a>
                        @endstoreCan
                        @storeCan($store, 'caja.view')
                        <a href="{{ route('stores.cajas', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.cajas*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Caja</a>
                        @endstoreCan
                        @storeCan($store, 'activos.view')
                        <a href="{{ route('stores.activos', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.activos*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Activos</a>
                        @endstoreCan
                        @storeCan($store, 'accounts-payables.view')
                        <a href="{{ route('stores.accounts-payables', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.accounts-payables*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Cuentas por pagar</a>
                        @endstoreCan
                        @storeCan($store, 'comprobantes-egreso.view')
                        <a href="{{ route('stores.comprobantes-egreso.index', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.comprobantes-egreso*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Comprobantes de egreso</a>
                        @endstoreCan
                        @storeCan($store, 'accounts-receivables.view')
                        <a href="{{ route('stores.accounts-receivables', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.accounts-receivables*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Cuentas por cobrar</a>
                        @endstoreCan
                        @storeCan($store, 'comprobantes-ingreso.view')
                        <a href="{{ route('stores.comprobantes-ingreso.index', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.comprobantes-ingreso*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Comprobantes de ingreso</a>
                        @endstoreCan
                        @storeCan($store, 'invoices.view')
                        <a href="{{ route('stores.invoices', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.invoices*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Facturas</a>
                        @endstoreCan
                    </div>
                </li>
                @endif
                {{-- Ventas (dropdown) --}}
                @if($canVentas)
                <li x-data="{ open: {{ $inVentas ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-gray-300 transition {{ $inVentas ? 'bg-brand/20 text-brand' : 'hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" /></svg>
                        <span class="flex-1 whitespace-nowrap">Ventas</span>
                        <svg :class="open && 'rotate-180'" class="h-4 w-4 shrink-0 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" x-transition class="ml-4 mt-0.5 space-y-0.5 border-l border-white/5 pl-2">
                        @storeCan($store, 'ventas.carrito.view')
                        <a href="{{ route('stores.ventas.carrito', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.ventas.carrito*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Carrito</a>
                        @endstoreCan
                        @storeCan($store, 'cotizaciones.view')
                        <a href="{{ route('stores.ventas.cotizaciones', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.ventas.cotizaciones*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Cotizaciones</a>
                        @endstoreCan
                    </div>
                </li>
                @endif
                {{-- Informes (dropdown) --}}
                @if($canInformes)
                <li x-data="{ open: {{ $inInformes ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-gray-300 transition {{ $inInformes ? 'bg-brand/20 text-brand' : 'hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M7.5 15.75V12m4.5 3.75V8.25m4.5 7.5V10.5" /></svg>
                        <span class="flex-1 whitespace-nowrap">Informes</span>
                        <svg :class="open && 'rotate-180'" class="h-4 w-4 shrink-0 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" x-transition class="ml-4 mt-0.5 space-y-0.5 border-l border-white/5 pl-2">
                        @storeCan($store, 'reports.products.view')
                        <a href="{{ route('stores.reports.index', [$store, 'tab' => 'productos', 'ventas' => request()->query('ventas', \App\Services\ProductReportsService::VENTAS_7D)]) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.reports*') && request()->query('tab', 'productos') === 'productos' ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Productos</a>
                        @endstoreCan
                        @storeCan($store, 'reports.billing.view')
                        <a href="{{ route('stores.reports.index', [$store, 'tab' => 'facturacion']) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.reports*') && request()->query('tab') === 'facturacion' ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Facturación</a>
                        @endstoreCan
                    </div>
                </li>
                @endif
                {{-- Suscripciones (dropdown) --}}
                @if($canSuscripciones)
                <li x-data="{ open: {{ $inSuscripciones ? 'true' : 'false' }} }">
                    <button type="button" @click="open = !open" class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-gray-300 transition {{ $inSuscripciones ? 'bg-brand/20 text-brand' : 'hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.856-.117-1.653-.124-2.653-.04-1.326.087-2.653.124-3.918.124-1.265 0-2.592-.037-3.918-.124-1-.084-1.797-.023-2.653.04a6 6 0 01-7.03-5.92 3 3 0 013-3m14.25 0a3 3 0 013 3m-3 0v1.875c0-1.036-.84-1.875-1.875-1.875H3.375C2.34 7.5 1.5 8.34 1.5 9.375V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0020.25 18V9.375c0-1.036-.84-1.875-1.875-1.875H18.75a3 3 0 01-3-3V5.25z" /></svg>
                        <span class="flex-1 whitespace-nowrap">Suscripciones</span>
                        <svg :class="open && 'rotate-180'" class="h-4 w-4 shrink-0 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" /></svg>
                    </button>
                    <div x-show="open" x-transition class="ml-4 mt-0.5 space-y-0.5 border-l border-white/5 pl-2">
                        @storeCan($store, 'subscriptions.view')
                        <a href="{{ route('stores.subscriptions.memberships', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.subscriptions.memberships*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Membresías</a>
                        <a href="{{ route('stores.subscriptions.plans', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.subscriptions.plans*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Planes</a>
                        @endstoreCan
                        @storeCan($store, 'asistencias.view')
                        <a href="{{ route('stores.asistencias', $store) }}" wire:navigate class="block rounded-lg py-2 pl-2 text-sm {{ request()->routeIs('stores.asistencias*') ? 'text-brand' : 'text-gray-400 hover:text-white' }}">Asistencias</a>
                        @endstoreCan
                    </div>
                </li>
                @endif
                {{-- Salir de la tienda --}}
                <li class="border-t border-white/5 pt-2 mt-2">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-gray-400 hover:bg-red-500/10 hover:text-red-400 transition">
                        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v3.75M15.75 9L12 12.75m0 0l-3.75 3.75M12 12.75V2.25" /></svg>
                        <span class="whitespace-nowrap">Salir de la tienda</span>
                    </a>
                </li>
            @else
                {{-- Panel general: solo Mis Tiendas --}}
                <li>
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-gray-300 transition {{ request()->routeIs('dashboard') ? 'bg-brand/20 text-brand' : 'hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.75-5.925 3.004 3.004 0 013.75-2.27M20.25 21V9.349m0 0a3.001 3.001 0 00-3.75-.615A2.993 2.993 0 0114.25 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 012.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 00-3.75-.614m0 0a3.004 3.004 0 01.75-5.925 3.004 3.004 0 00-3.75-2.27M3.75 21V9.349m0 0a3.001 3.001 0 013.75-.615 2.993 2.993 0 012.25 1.016 2.993 2.993 0 005.25 2.27M3.75 21h3.75a.75.75 0 00.75-.75V9.349a.75.75 0 00-.75-.75H3.75a.75.75 0 00-.75.75V20.25c0 .414.336.75.75.75z" /></svg>
                        <span class="whitespace-nowrap">Mis Tiendas</span>
                    </a>
                </li>
            @endif
        </ul>
    </nav>
</aside>
