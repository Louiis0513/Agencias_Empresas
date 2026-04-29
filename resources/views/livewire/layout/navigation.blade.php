<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    $store = request()->route('store');
@endphp
<nav x-data="{ open: false }" class="bg-dark-card/80 backdrop-blur-md border-b border-white/5">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="block h-9 w-auto fill-current text-white" />
                    </a>
                </div>

                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">

                    @if($store)
                        {{-- === ESTAMOS DENTRO DE UNA TIENDA === --}}
                        {{-- Navbar principal: Resumen, Personas, Productos, Financiero --}}

                        @storeCan($store, 'dashboard.view')
                        <x-nav-link :href="route('stores.dashboard', $store)" :active="request()->routeIs('stores.dashboard')" wire:navigate>
                            {{ __('Resumen') }}
                        </x-nav-link>
                        @endstoreCan

                        @php
                            $canPersonas = app(\App\Services\StorePermissionService::class)->can($store, 'customers.view') || app(\App\Services\StorePermissionService::class)->can($store, 'workers.view') || app(\App\Services\StorePermissionService::class)->can($store, 'workers.schedules.view');
                            $canProductos = app(\App\Services\StorePermissionService::class)->can($store, 'products.view') || app(\App\Services\StorePermissionService::class)->can($store, 'categories.view') || app(\App\Services\StorePermissionService::class)->can($store, 'attribute-groups.view') || app(\App\Services\StorePermissionService::class)->can($store, 'inventario.view') || app(\App\Services\StorePermissionService::class)->can($store, 'proveedores.view') || app(\App\Services\StorePermissionService::class)->can($store, 'product-purchases.view') || app(\App\Services\StorePermissionService::class)->can($store, 'support-documents.view');
                            $canFinanciero = app(\App\Services\StorePermissionService::class)->can($store, 'caja.view') || app(\App\Services\StorePermissionService::class)->can($store, 'activos.view') || app(\App\Services\StorePermissionService::class)->can($store, 'accounts-payables.view') || app(\App\Services\StorePermissionService::class)->can($store, 'accounts-receivables.view') || app(\App\Services\StorePermissionService::class)->can($store, 'comprobantes-egreso.view') || app(\App\Services\StorePermissionService::class)->can($store, 'comprobantes-ingreso.view') || app(\App\Services\StorePermissionService::class)->can($store, 'invoices.view') || app(\App\Services\StorePermissionService::class)->can($store, 'purchases.view');
                            $canVentas = app(\App\Services\StorePermissionService::class)->can($store, 'ventas.carrito.view') || app(\App\Services\StorePermissionService::class)->can($store, 'cotizaciones.view');
                            $canSuscripciones = app(\App\Services\StorePermissionService::class)->can($store, 'subscriptions.view') || app(\App\Services\StorePermissionService::class)->can($store, 'asistencias.view');
                        @endphp
                        @if($canPersonas)
                        <x-nav-link :href="route('stores.customers', $store)" :active="request()->routeIs('stores.customers*') || request()->routeIs('stores.workers*')" wire:navigate>
                            {{ __('Personas') }}
                        </x-nav-link>
                        @endif
                        @if($canProductos)
                        @php
                            $isProductPurchaseMain = false;
                            if (request()->routeIs('stores.purchases.show')) {
                                $purchaseMain = request()->route('purchase');
                                if ($purchaseMain && method_exists($purchaseMain, 'isProducto')) {
                                    $isProductPurchaseMain = $purchaseMain->isProducto();
                                } elseif (request()->header('referer') && str_contains(request()->header('referer'), '/productos/compras')) {
                                    $isProductPurchaseMain = true;
                                }
                            }
                        @endphp
                        <x-nav-link :href="route('stores.products', $store)" :active="request()->routeIs('stores.products*') || request()->routeIs('stores.categories*') || request()->routeIs('stores.attribute-groups*') || request()->routeIs('stores.proveedores*') || request()->routeIs('stores.product-purchases*') || (request()->routeIs('stores.purchases.show') && $isProductPurchaseMain)" wire:navigate>
                            {{ __('Productos') }}
                        </x-nav-link>
                        @endif
                        @if($canFinanciero)
                        @php
                            $isProductPurchaseNav = false;
                            if (request()->routeIs('stores.purchases.show')) {
                                $purchaseNav = request()->route('purchase');
                                if ($purchaseNav && method_exists($purchaseNav, 'isProducto')) {
                                    $isProductPurchaseNav = $purchaseNav->isProducto();
                                } elseif (request()->header('referer') && str_contains(request()->header('referer'), '/productos/compras')) {
                                    $isProductPurchaseNav = true;
                                }
                            }
                        @endphp
                        <x-nav-link :href="route('stores.cajas', $store)" :active="(request()->routeIs('stores.cajas*') || request()->routeIs('stores.activos*') || request()->routeIs('stores.accounts-payables*') || request()->routeIs('stores.accounts-receivables*') || request()->routeIs('stores.comprobantes-egreso*') || request()->routeIs('stores.comprobantes-ingreso*') || request()->routeIs('stores.invoices*') || (request()->routeIs('stores.purchases*') && !$isProductPurchaseNav)) && !request()->routeIs('stores.product-purchases*')" wire:navigate>
                            {{ __('Financiero') }}
                        </x-nav-link>
                        @endif
                        @if($canVentas)
                        <x-nav-link :href="route('stores.ventas.carrito', $store)" :active="request()->routeIs('stores.ventas*')" wire:navigate>
                            {{ __('Ventas') }}
                        </x-nav-link>
                        @endif
                        @if($canSuscripciones)
                        <x-nav-link :href="route('stores.subscriptions.memberships', $store)" :active="request()->routeIs('stores.subscriptions*') || request()->routeIs('stores.asistencias*')" wire:navigate>
                            {{ __('Suscripciones') }}
                        </x-nav-link>
                        @endif

                        {{-- Botón de Salir (Volver al panel general) --}}
                        <div class="flex items-center ml-4 pl-4 border-l border-white/10 h-6 my-auto">
                            <a href="{{ route('dashboard') }}" class="text-sm text-gray-400 hover:text-red-400 transition" wire:navigate>
                                &larr; Salir
                            </a>
                        </div>

                    @else
                        {{-- === ESTAMOS EN EL PANEL GENERAL === --}}
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Mis Tiendas') }}
                        </x-nav-link>
                    @endif

                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-white/10 text-sm leading-4 font-medium rounded-lg text-gray-300 bg-white/5 hover:text-white hover:border-brand/50 focus:outline-none transition ease-in-out duration-150">
                            <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-lg text-gray-400 hover:text-white hover:bg-white/10 focus:outline-none focus:bg-white/10 focus:text-white transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- Sub-navbar (Personas, Productos, Financiero) --}}
        @if($store ?? null)
            @php
                // Verificar si estamos en purchases.show y si es una compra de productos
                $isProductPurchase = false;
                if (request()->routeIs('stores.purchases.show')) {
                    $purchase = request()->route('purchase');
                    if ($purchase && method_exists($purchase, 'isProducto')) {
                        $isProductPurchase = $purchase->isProducto();
                    } elseif (request()->header('referer') && str_contains(request()->header('referer'), '/productos/compras')) {
                        // Fallback: verificar referer si viene de compras de productos
                        $isProductPurchase = true;
                    }
                }
                
                $inPersonas = request()->routeIs('stores.customers*') || request()->routeIs('stores.workers*') || request()->routeIs('stores.workers.schedules*');
                $inProductos = request()->routeIs('stores.products*') || request()->routeIs('stores.categories*') || request()->routeIs('stores.attribute-groups*') || request()->routeIs('stores.proveedores*') || request()->routeIs('stores.product-purchases*') || (request()->routeIs('stores.purchases.show') && $isProductPurchase);
                $inFinanciero = (request()->routeIs('stores.cajas*') || request()->routeIs('stores.activos*') || request()->routeIs('stores.accounts-payables*') || request()->routeIs('stores.accounts-receivables*') || request()->routeIs('stores.comprobantes-egreso*') || request()->routeIs('stores.comprobantes-ingreso*') || request()->routeIs('stores.invoices*') || (request()->routeIs('stores.purchases*') && !$isProductPurchase)) && !request()->routeIs('stores.product-purchases*');
                $inVentas = request()->routeIs('stores.ventas*');
                $inSuscripciones = request()->routeIs('stores.subscriptions*');
                $inAsistencias = request()->routeIs('stores.asistencias*');
            @endphp
            @if($inPersonas || $inProductos || $inFinanciero || $inVentas || $inSuscripciones || $inAsistencias)
                <div class="border-t border-white/5 bg-dark/80">
                    <div class="flex gap-1 py-2 overflow-x-auto">
                        @if($inPersonas)
                            @storeCan($store, 'customers.view')
                            <a href="{{ route('stores.customers', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.customers*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Clientes') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'workers.view')
                            <a href="{{ route('stores.workers', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.workers*') && !request()->routeIs('stores.workers.time-attendance') && !request()->routeIs('stores.workers.schedules*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Trabajadores') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'workers.schedules.view')
                            <a href="{{ route('stores.workers.time-attendance', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.workers.time-attendance') || request()->routeIs('stores.workers.schedules*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Registro de horarios') }}
                            </a>
                            @endstoreCan
                        @endif
                        @if($inProductos)
                            @storeCan($store, 'products.view')
                            <a href="{{ route('stores.products', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.products*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Productos') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'categories.view')
                            <a href="{{ route('stores.categories', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.categories*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Categorías') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'attribute-groups.view')
                            <a href="{{ route('stores.attribute-groups', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.attribute-groups*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Atributos') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'proveedores.view')
                            <a href="{{ route('stores.proveedores', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.proveedores*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Proveedores') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'product-purchases.view')
                            <a href="{{ route('stores.product-purchases', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.product-purchases*') || (request()->routeIs('stores.purchases.show') && $isProductPurchase) ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Compra de productos') }}
                            </a>
                            @endstoreCan
                        @endif
                        @if($inFinanciero)
                            @storeCan($store, 'purchases.view')
                            <a href="{{ route('stores.purchases', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ (request()->routeIs('stores.purchases*') && !$isProductPurchase) ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Compra de activos') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'caja.view')
                            <a href="{{ route('stores.cajas', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.cajas*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Caja') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'activos.view')
                            <a href="{{ route('stores.activos', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.activos*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Activos') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'accounts-payables.view')
                            <a href="{{ route('stores.accounts-payables', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.accounts-payables*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Cuentas por pagar') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'comprobantes-egreso.view')
                            <a href="{{ route('stores.comprobantes-egreso.index', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.comprobantes-egreso*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Comprobantes de egreso') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'accounts-receivables.view')
                            <a href="{{ route('stores.accounts-receivables', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.accounts-receivables*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Cuentas por cobrar') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'comprobantes-ingreso.view')
                            <a href="{{ route('stores.comprobantes-ingreso.index', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.comprobantes-ingreso*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Comprobantes de ingreso') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'invoices.view')
                            <a href="{{ route('stores.invoices', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.invoices*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Facturas') }}
                            </a>
                            @endstoreCan
                        @endif
                        @if($inVentas)
                            @storeCan($store, 'ventas.carrito.view')
                            <a href="{{ route('stores.ventas.carrito', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.ventas.carrito*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Carrito') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'cotizaciones.view')
                            <a href="{{ route('stores.ventas.cotizaciones', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.ventas.cotizaciones*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Cotizaciones') }}
                            </a>
                            @endstoreCan
                        @endif
                        @if($inSuscripciones || $inAsistencias)
                            @storeCan($store, 'subscriptions.view')
                            <a href="{{ route('stores.subscriptions.memberships', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.subscriptions.memberships*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Membresías') }}
                            </a>
                            <a href="{{ route('stores.subscriptions.plans', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.subscriptions.plans*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Planes') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'asistencias.view')
                            <a href="{{ route('stores.asistencias', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.asistencias*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Asistencias') }}
                            </a>
                            @endstoreCan
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            
            @php
                $store = request()->route('store');
            @endphp

            @if($store)
                @php
                    $perm = app(\App\Services\StorePermissionService::class);
                    $canP = $perm->can($store, 'customers.view') || $perm->can($store, 'workers.view') || $perm->can($store, 'workers.schedules.view');
                    $canProd = $perm->can($store, 'products.view') || $perm->can($store, 'categories.view') || $perm->can($store, 'attribute-groups.view') || $perm->can($store, 'inventario.view') || $perm->can($store, 'proveedores.view') || $perm->can($store, 'product-purchases.view') || $perm->can($store, 'support-documents.view');
                    $canF = $perm->can($store, 'caja.view') || $perm->can($store, 'activos.view') || $perm->can($store, 'accounts-payables.view') || $perm->can($store, 'accounts-receivables.view') || $perm->can($store, 'comprobantes-egreso.view') || $perm->can($store, 'comprobantes-ingreso.view') || $perm->can($store, 'invoices.view') || $perm->can($store, 'purchases.view');
                    $canV = $perm->can($store, 'ventas.carrito.view') || $perm->can($store, 'cotizaciones.view');
                    $canSuscripciones = $perm->can($store, 'subscriptions.view') || $perm->can($store, 'asistencias.view');
                @endphp
                {{-- Móvil: Menú de Tienda (agrupado) --}}
                @storeCan($store, 'dashboard.view')
                <x-responsive-nav-link :href="route('stores.dashboard', $store)" :active="request()->routeIs('stores.dashboard')" wire:navigate>
                    {{ __('Resumen') }}
                </x-responsive-nav-link>
                @endstoreCan
                @if($canP)
                <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">{{ __('Personas') }}</div>
                @storeCan($store, 'customers.view')
                <x-responsive-nav-link :href="route('stores.customers', $store)" :active="request()->routeIs('stores.customers*')" wire:navigate>
                    {{ __('Clientes') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'workers.view')
                <x-responsive-nav-link :href="route('stores.workers', $store)" :active="request()->routeIs('stores.workers*') && !request()->routeIs('stores.workers.time-attendance') && !request()->routeIs('stores.workers.schedules*')" wire:navigate>
                    {{ __('Trabajadores') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'workers.schedules.view')
                <x-responsive-nav-link :href="route('stores.workers.time-attendance', $store)" :active="request()->routeIs('stores.workers.time-attendance') || request()->routeIs('stores.workers.schedules*')" wire:navigate>
                    {{ __('Registro de horarios') }}
                </x-responsive-nav-link>
                @endstoreCan
                @endif
                @if($canProd)
                <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">{{ __('Productos') }}</div>
                @storeCan($store, 'products.view')
                <x-responsive-nav-link :href="route('stores.products', $store)" :active="request()->routeIs('stores.products*')" wire:navigate>
                    {{ __('Productos') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'categories.view')
                <x-responsive-nav-link :href="route('stores.categories', $store)" :active="request()->routeIs('stores.categories*')" wire:navigate>
                    {{ __('Categorías') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'attribute-groups.view')
                <x-responsive-nav-link :href="route('stores.attribute-groups', $store)" :active="request()->routeIs('stores.attribute-groups*')" wire:navigate>
                    {{ __('Atributos') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'proveedores.view')
                <x-responsive-nav-link :href="route('stores.proveedores', $store)" :active="request()->routeIs('stores.proveedores*')" wire:navigate>
                    {{ __('Proveedores') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'product-purchases.view')
                <x-responsive-nav-link :href="route('stores.product-purchases', $store)" :active="request()->routeIs('stores.product-purchases*')" wire:navigate>
                    {{ __('Compra de productos') }}
                </x-responsive-nav-link>
                @endstoreCan
                @endif
                @if($canF)
                <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">{{ __('Financiero') }}</div>
                @storeCan($store, 'purchases.view')
                <x-responsive-nav-link :href="route('stores.purchases', $store)" :active="request()->routeIs('stores.purchases*')" wire:navigate>
                    {{ __('Compra de activos') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'caja.view')
                <x-responsive-nav-link :href="route('stores.cajas', $store)" :active="request()->routeIs('stores.cajas*')" wire:navigate>
                    {{ __('Caja') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'activos.view')
                <x-responsive-nav-link :href="route('stores.activos', $store)" :active="request()->routeIs('stores.activos*')" wire:navigate>
                    {{ __('Activos') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'accounts-payables.view')
                <x-responsive-nav-link :href="route('stores.accounts-payables', $store)" :active="request()->routeIs('stores.accounts-payables*')" wire:navigate>
                    {{ __('Cuentas por pagar') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'comprobantes-egreso.view')
                <x-responsive-nav-link :href="route('stores.comprobantes-egreso.index', $store)" :active="request()->routeIs('stores.comprobantes-egreso*')" wire:navigate>
                    {{ __('Comprobantes de egreso') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'accounts-receivables.view')
                <x-responsive-nav-link :href="route('stores.accounts-receivables', $store)" :active="request()->routeIs('stores.accounts-receivables*')" wire:navigate>
                    {{ __('Cuentas por cobrar') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'comprobantes-ingreso.view')
                <x-responsive-nav-link :href="route('stores.comprobantes-ingreso.index', $store)" :active="request()->routeIs('stores.comprobantes-ingreso*')" wire:navigate>
                    {{ __('Comprobantes de ingreso') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'invoices.view')
                <x-responsive-nav-link :href="route('stores.invoices', $store)" :active="request()->routeIs('stores.invoices*')" wire:navigate>
                    {{ __('Facturas') }}
                </x-responsive-nav-link>
                @endstoreCan
                @endif
                @if($canV)
                <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">{{ __('Ventas') }}</div>
                @storeCan($store, 'ventas.carrito.view')
                <x-responsive-nav-link :href="route('stores.ventas.carrito', $store)" :active="request()->routeIs('stores.ventas.carrito*')" wire:navigate>
                    {{ __('Carrito') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'cotizaciones.view')
                <x-responsive-nav-link :href="route('stores.ventas.cotizaciones', $store)" :active="request()->routeIs('stores.ventas.cotizaciones*')" wire:navigate>
                    {{ __('Cotizaciones') }}
                </x-responsive-nav-link>
                @endstoreCan
                @endif
                @if($canSuscripciones)
                <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">{{ __('Suscripciones') }}</div>
                @storeCan($store, 'subscriptions.view')
                <x-responsive-nav-link :href="route('stores.subscriptions.memberships', $store)" :active="request()->routeIs('stores.subscriptions.memberships*')" wire:navigate>
                    {{ __('Membresías') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('stores.subscriptions.plans', $store)" :active="request()->routeIs('stores.subscriptions.plans*')" wire:navigate>
                    {{ __('Planes') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'asistencias.view')
                <x-responsive-nav-link :href="route('stores.asistencias', $store)" :active="request()->routeIs('stores.asistencias*')" wire:navigate>
                    {{ __('Asistencias') }}
                </x-responsive-nav-link>
                @endstoreCan
                @endif
                <div class="border-t border-white/10 my-2"></div>
                <x-responsive-nav-link :href="route('dashboard')" wire:navigate class="text-red-500">
                    {{ __('← Salir de la Tienda') }}
                </x-responsive-nav-link>
            @else
                {{-- Móvil: Menú General --}}
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Mis Tiendas') }}
                </x-responsive-nav-link>
            @endif

        </div>

        <div class="pt-4 pb-1 border-t border-white/10">
            <div class="px-4">
                <div class="font-medium text-base text-white" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="font-medium text-sm text-gray-400">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>