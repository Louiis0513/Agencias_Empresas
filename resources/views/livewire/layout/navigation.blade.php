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
<nav x-data="{ open: false }" class="bg-white border-b border-gray-100 dark:bg-gray-800 dark:border-gray-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate>
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                    </a>
                </div>

                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">

                    @if($store)
                        {{-- === ESTAMOS DENTRO DE UNA TIENDA === --}}
                        {{-- Navbar principal: Resumen, Personas, Productos, Financiero --}}

                        <x-nav-link :href="route('stores.dashboard', $store)" :active="request()->routeIs('stores.dashboard')" wire:navigate>
                            {{ __('Resumen') }}
                        </x-nav-link>

                        <x-nav-link :href="route('stores.customers', $store)" :active="request()->routeIs('stores.customers*') || request()->routeIs('stores.workers*')" wire:navigate>
                            {{ __('Personas') }}
                        </x-nav-link>

                        <x-nav-link :href="route('stores.products', $store)" :active="request()->routeIs('stores.products*') || request()->routeIs('stores.categories*') || request()->routeIs('stores.attribute-groups*') || request()->routeIs('stores.inventario*') || request()->routeIs('stores.proveedores*')" wire:navigate>
                            {{ __('Productos') }}
                        </x-nav-link>

                        <x-nav-link :href="route('stores.cajas', $store)" :active="request()->routeIs('stores.cajas*') || request()->routeIs('stores.activos*') || request()->routeIs('stores.accounts-payables*') || request()->routeIs('stores.comprobantes-egreso*') || request()->routeIs('stores.invoices*') || request()->routeIs('stores.purchases*')" wire:navigate>
                            {{ __('Financiero') }}
                        </x-nav-link>

                        {{-- Botón de Salir (Volver al panel general) --}}
                        <div class="flex items-center ml-4 pl-4 border-l border-gray-300 dark:border-gray-600 h-6 my-auto">
                            <a href="{{ route('dashboard') }}" class="text-sm text-gray-500 hover:text-red-500 transition dark:text-gray-400 dark:hover:text-red-400" wire:navigate>
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
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
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
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
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
                $inPersonas = request()->routeIs('stores.customers*') || request()->routeIs('stores.workers*');
                $inProductos = request()->routeIs('stores.products*') || request()->routeIs('stores.categories*') || request()->routeIs('stores.attribute-groups*') || request()->routeIs('stores.inventario*') || request()->routeIs('stores.proveedores*') || request()->routeIs('stores.product-purchases*');
                $inFinanciero = request()->routeIs('stores.cajas*') || request()->routeIs('stores.activos*') || request()->routeIs('stores.accounts-payables*') || request()->routeIs('stores.comprobantes-egreso*') || request()->routeIs('stores.invoices*') || request()->routeIs('stores.purchases*');
            @endphp
            @if($inPersonas || $inProductos || $inFinanciero)
                <div class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                    <div class="flex gap-1 py-2 overflow-x-auto">
                        @if($inPersonas)
                            <a href="{{ route('stores.customers', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('stores.customers*') ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                {{ __('Clientes') }}
                            </a>
                            <a href="{{ route('stores.workers', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('stores.workers*') ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                {{ __('Trabajadores') }}
                            </a>
                        @endif
                        @if($inProductos)
                            <a href="{{ route('stores.products', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('stores.products*') ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                {{ __('Productos') }}
                            </a>
                            <a href="{{ route('stores.categories', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('stores.categories*') ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                {{ __('Categorías') }}
                            </a>
                            <a href="{{ route('stores.attribute-groups', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('stores.attribute-groups*') ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                {{ __('Atributos') }}
                            </a>
                            <a href="{{ route('stores.inventario', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('stores.inventario*') ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                {{ __('Inventario') }}
                            </a>
                            <a href="{{ route('stores.proveedores', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('stores.proveedores*') ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                {{ __('Proveedores') }}
                            </a>
                            <a href="{{ route('stores.product-purchases', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('stores.product-purchases*') ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                {{ __('Compra de productos') }}
                            </a>
                        @endif
                        @if($inFinanciero)
                            <a href="{{ route('stores.purchases', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('stores.purchases*') ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                {{ __('Compra de activos') }}
                            </a>
                            <a href="{{ route('stores.cajas', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('stores.cajas*') ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                {{ __('Caja') }}
                            </a>
                            <a href="{{ route('stores.activos', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('stores.activos*') ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                {{ __('Activos') }}
                            </a>
                            <a href="{{ route('stores.accounts-payables', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('stores.accounts-payables*') ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                {{ __('Cuentas por pagar') }}
                            </a>
                            <a href="{{ route('stores.comprobantes-egreso.index', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('stores.comprobantes-egreso*') ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                {{ __('Comprobantes de egreso') }}
                            </a>
                            <a href="{{ route('stores.invoices', $store) }}" wire:navigate class="shrink-0 px-4 py-2 rounded-md text-sm font-medium {{ request()->routeIs('stores.invoices*') ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-200' : 'text-gray-600 hover:bg-gray-200 dark:text-gray-400 dark:hover:bg-gray-700' }}">
                                {{ __('Facturas') }}
                            </a>
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
                {{-- Móvil: Menú de Tienda (agrupado) --}}
                <x-responsive-nav-link :href="route('stores.dashboard', $store)" :active="request()->routeIs('stores.dashboard')" wire:navigate>
                    {{ __('Resumen') }}
                </x-responsive-nav-link>
                <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('Personas') }}</div>
                <x-responsive-nav-link :href="route('stores.customers', $store)" :active="request()->routeIs('stores.customers*')" wire:navigate>
                    {{ __('Clientes') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('stores.workers', $store)" :active="request()->routeIs('stores.workers*')" wire:navigate>
                    {{ __('Trabajadores') }}
                </x-responsive-nav-link>
                <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('Productos') }}</div>
                <x-responsive-nav-link :href="route('stores.products', $store)" :active="request()->routeIs('stores.products*')" wire:navigate>
                    {{ __('Productos') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('stores.categories', $store)" :active="request()->routeIs('stores.categories*')" wire:navigate>
                    {{ __('Categorías') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('stores.attribute-groups', $store)" :active="request()->routeIs('stores.attribute-groups*')" wire:navigate>
                    {{ __('Atributos') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('stores.inventario', $store)" :active="request()->routeIs('stores.inventario*')" wire:navigate>
                    {{ __('Inventario') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('stores.proveedores', $store)" :active="request()->routeIs('stores.proveedores*')" wire:navigate>
                    {{ __('Proveedores') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('stores.product-purchases', $store)" :active="request()->routeIs('stores.product-purchases*')" wire:navigate>
                    {{ __('Compra de productos') }}
                </x-responsive-nav-link>
                <div class="px-4 py-2 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase">{{ __('Financiero') }}</div>
                <x-responsive-nav-link :href="route('stores.purchases', $store)" :active="request()->routeIs('stores.purchases*')" wire:navigate>
                    {{ __('Compra de activos') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('stores.cajas', $store)" :active="request()->routeIs('stores.cajas*')" wire:navigate>
                    {{ __('Caja') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('stores.activos', $store)" :active="request()->routeIs('stores.activos*')" wire:navigate>
                    {{ __('Activos') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('stores.accounts-payables', $store)" :active="request()->routeIs('stores.accounts-payables*')" wire:navigate>
                    {{ __('Cuentas por pagar') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('stores.comprobantes-egreso.index', $store)" :active="request()->routeIs('stores.comprobantes-egreso*')" wire:navigate>
                    {{ __('Comprobantes de egreso') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('stores.invoices', $store)" :active="request()->routeIs('stores.invoices*')" wire:navigate>
                    {{ __('Facturas') }}
                </x-responsive-nav-link>
                <div class="border-t border-gray-200 dark:border-gray-600 my-2"></div>
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

        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="font-medium text-sm text-gray-500">{{ auth()->user()->email }}</div>
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