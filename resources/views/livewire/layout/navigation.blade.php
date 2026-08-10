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
                            $canPersonas = app(\App\Services\StorePermissionService::class)->can($store, 'terceros.view') || app(\App\Services\StorePermissionService::class)->can($store, 'workers.schedules.view');
                            $canProductos = app(\App\Services\StorePermissionService::class)->can($store, 'products.view');
                            $canFinanciero = app(\App\Services\StorePermissionService::class)->can($store, 'caja.view') || app(\App\Services\StorePermissionService::class)->can($store, 'accounts-payables.view') || app(\App\Services\StorePermissionService::class)->can($store, 'accounts-receivables.view') || app(\App\Services\StorePermissionService::class)->can($store, 'comprobantes-egreso.view') || app(\App\Services\StorePermissionService::class)->can($store, 'comprobantes-ingreso.view') || app(\App\Services\StorePermissionService::class)->can($store, 'invoices.view') || app(\App\Services\StorePermissionService::class)->can($store, 'contabilidad.tipos.view') || app(\App\Services\StorePermissionService::class)->can($store, 'contabilidad.formas-pago.view') || app(\App\Services\StorePermissionService::class)->can($store, 'contabilidad.comprobantes.view');
                            $canVentas = app(\App\Services\StorePermissionService::class)->can($store, 'ventas.carrito.view');
                        @endphp
                        @if($canPersonas)
                        <x-nav-link :href="route('stores.terceros', $store)" :active="request()->routeIs('stores.terceros*') || request()->routeIs('stores.workers*')" wire:navigate>
                            {{ __('Personas') }}
                        </x-nav-link>
                        @endif
                        @if($canProductos)
                        <x-nav-link :href="route('stores.products', $store)" :active="request()->routeIs('stores.products*') && ! request()->routeIs('stores.products.bodegas*')" wire:navigate>
                            {{ __('Productos y servicios') }}
                        </x-nav-link>
                        @endif
                        @if($canFinanciero)
                        <x-nav-link :href="route('stores.cajas.movimientos', $store)" :active="request()->routeIs('stores.cajas*') || request()->routeIs('stores.accounts-payables*') || request()->routeIs('stores.accounts-receivables*') || request()->routeIs('stores.comprobantes-egreso*') || request()->routeIs('stores.comprobantes-ingreso*') || request()->routeIs('stores.invoices*') || request()->routeIs('stores.contabilidad.formas-pago*') || request()->routeIs('stores.contabilidad.comprobantes*') || request()->routeIs('stores.contabilidad.diario') || request()->routeIs('stores.contabilidad.mayor') || request()->routeIs('stores.contabilidad.recibos-caja*') || request()->routeIs('stores.recibos-caja*')" wire:navigate>
                            {{ __('Financiero') }}
                        </x-nav-link>
                        @endif
                        @if($canVentas)
                        <x-nav-link :href="route('stores.ventas.carrito', $store)" :active="request()->routeIs('stores.ventas*')" wire:navigate>
                            {{ __('Ventas') }}
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
                $inPersonas = request()->routeIs('stores.terceros*') || request()->routeIs('stores.customers*') || request()->routeIs('stores.workers*') || request()->routeIs('stores.workers.schedules*');
                $inProductos = request()->routeIs('stores.products*') && ! request()->routeIs('stores.products.bodegas*');
                $inFinanciero = request()->routeIs('stores.cajas*') || request()->routeIs('stores.accounts-payables*') || request()->routeIs('stores.accounts-receivables*') || request()->routeIs('stores.comprobantes-egreso*') || request()->routeIs('stores.comprobantes-ingreso*') || request()->routeIs('stores.invoices*') || request()->routeIs('stores.contabilidad.formas-pago*') || request()->routeIs('stores.contabilidad.comprobantes*') || request()->routeIs('stores.contabilidad.diario') || request()->routeIs('stores.contabilidad.mayor') || request()->routeIs('stores.contabilidad.recibos-caja*') || request()->routeIs('stores.recibos-caja*');
                $inVentas = request()->routeIs('stores.ventas*');
            @endphp
            @if($inPersonas || $inProductos || $inFinanciero || $inVentas)
                <div class="border-t border-white/5 bg-dark/80">
                    <div class="flex gap-1 py-2 overflow-x-auto">
                        @if($inPersonas)
                            @storeCan($store, 'terceros.view')
                            <a href="{{ route('stores.terceros', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.terceros*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Terceros') }}
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
                            <a href="{{ route('stores.products', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.products*') && ! request()->routeIs('stores.products.bodegas*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Productos y servicios') }}
                            </a>
                            @endstoreCan
                        @endif
                        @if($inFinanciero)
                            @storeCan($store, 'caja.view')
                            <a href="{{ route('stores.cajas.movimientos', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.cajas*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Movimientos') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'contabilidad.tipos.view')
                            <a href="{{ route('stores.contabilidad.recibos-caja', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.contabilidad.recibos-caja*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Recibos de caja') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'comprobantes-ingreso.create')
                            <a href="{{ route('stores.recibos-caja.create', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.recibos-caja.create') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Elaborar RC') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'contabilidad.formas-pago.view')
                            <a href="{{ route('stores.contabilidad.formas-pago', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.contabilidad.formas-pago*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Formas de pago') }}
                            </a>
                            @endstoreCan
                            @storeCan($store, 'contabilidad.comprobantes.view')
                            <a href="{{ route('stores.contabilidad.comprobantes', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.contabilidad.comprobantes*') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Asientos manuales') }}
                            </a>
                            <a href="{{ route('stores.contabilidad.diario', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.contabilidad.diario') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Libro Diario') }}
                            </a>
                            <a href="{{ route('stores.contabilidad.mayor', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-lg text-sm font-medium {{ request()->routeIs('stores.contabilidad.mayor') ? 'bg-brand/20 text-brand border border-brand/30' : 'text-gray-400 hover:bg-white/5 hover:text-gray-100' }}">
                                {{ __('Libro Mayor') }}
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
                    $canP = $perm->can($store, 'terceros.view') || $perm->can($store, 'workers.schedules.view');
                    $canProd = $perm->can($store, 'products.view');
                    $canF = $perm->can($store, 'caja.view') || $perm->can($store, 'accounts-payables.view') || $perm->can($store, 'accounts-receivables.view') || $perm->can($store, 'comprobantes-egreso.view') || $perm->can($store, 'comprobantes-ingreso.view') || $perm->can($store, 'invoices.view') || $perm->can($store, 'contabilidad.tipos.view') || $perm->can($store, 'contabilidad.formas-pago.view') || $perm->can($store, 'contabilidad.comprobantes.view');
                    $canV = $perm->can($store, 'ventas.carrito.view');
                @endphp
                {{-- Móvil: Menú de Tienda (agrupado) --}}
                @storeCan($store, 'dashboard.view')
                <x-responsive-nav-link :href="route('stores.dashboard', $store)" :active="request()->routeIs('stores.dashboard')" wire:navigate>
                    {{ __('Resumen') }}
                </x-responsive-nav-link>
                @endstoreCan
                @if($canP)
                <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">{{ __('Personas') }}</div>
                @storeCan($store, 'terceros.view')
                <x-responsive-nav-link :href="route('stores.terceros', $store)" :active="request()->routeIs('stores.terceros*')" wire:navigate>
                    {{ __('Terceros') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'workers.schedules.view')
                <x-responsive-nav-link :href="route('stores.workers.time-attendance', $store)" :active="request()->routeIs('stores.workers.time-attendance') || request()->routeIs('stores.workers.schedules*')" wire:navigate>
                    {{ __('Registro de horarios') }}
                </x-responsive-nav-link>
                @endstoreCan
                @endif
                @if($canProd)
                <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">{{ __('Productos y servicios') }}</div>
                @storeCan($store, 'products.view')
                <x-responsive-nav-link :href="route('stores.products', $store)" :active="request()->routeIs('stores.products*') && ! request()->routeIs('stores.products.bodegas*')" wire:navigate>
                    {{ __('Productos y servicios') }}
                </x-responsive-nav-link>
                @endstoreCan
                @endif
                @if($canF)
                <div class="px-4 py-2 text-xs font-semibold text-gray-400 uppercase">{{ __('Financiero') }}</div>
                @storeCan($store, 'caja.view')
                <x-responsive-nav-link :href="route('stores.cajas.movimientos', $store)" :active="request()->routeIs('stores.cajas*')" wire:navigate>
                    {{ __('Movimientos') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'contabilidad.tipos.view')
                <x-responsive-nav-link :href="route('stores.contabilidad.recibos-caja', $store)" :active="request()->routeIs('stores.contabilidad.recibos-caja*')" wire:navigate>
                    {{ __('Recibos de caja') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'contabilidad.formas-pago.view')
                <x-responsive-nav-link :href="route('stores.contabilidad.formas-pago', $store)" :active="request()->routeIs('stores.contabilidad.formas-pago*')" wire:navigate>
                    {{ __('Formas de pago') }}
                </x-responsive-nav-link>
                @endstoreCan
                @storeCan($store, 'contabilidad.comprobantes.view')
                <x-responsive-nav-link :href="route('stores.contabilidad.comprobantes', $store)" :active="request()->routeIs('stores.contabilidad.comprobantes*')" wire:navigate>
                    {{ __('Asientos manuales') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('stores.contabilidad.diario', $store)" :active="request()->routeIs('stores.contabilidad.diario')" wire:navigate>
                    {{ __('Libro Diario') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('stores.contabilidad.mayor', $store)" :active="request()->routeIs('stores.contabilidad.mayor')" wire:navigate>
                    {{ __('Libro Mayor') }}
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