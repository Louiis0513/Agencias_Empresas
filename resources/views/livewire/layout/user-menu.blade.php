<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();
        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="relative" x-data="{ open: false }" @click.outside="open = false">
    <button @click="open = !open" type="button" class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-gray-300 hover:bg-white/5 transition">
        <div class="w-8 h-8 rounded-full bg-brand/30 flex items-center justify-center text-brand shrink-0">
            <span class="text-sm font-semibold">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</span>
        </div>
        <span class="hidden sm:block text-sm font-medium max-w-[120px] truncate" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
        <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform" :class="open && 'rotate-180'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
        </svg>
    </button>
    <div x-show="open"
         x-transition:enter="transition ease-out duration-100"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-75"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="absolute right-0 mt-2 w-56 rounded-xl bg-dark-card border border-white/10 shadow-xl py-2 z-50"
         style="display: none;">
        <div class="px-4 py-3 border-b border-white/5">
            <p class="font-medium text-white truncate">{{ auth()->user()->name }}</p>
            <p class="text-xs text-gray-400 truncate">{{ auth()->user()->email }}</p>
        </div>
        <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-300 hover:bg-white/5 transition">
            <svg class="w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" /></svg>
            {{ __('Perfil') }}
        </a>
        <button wire:click="logout" type="button" class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-gray-300 hover:bg-white/5 transition text-left">
            <svg class="w-4 h-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v3.75M15.75 9L12 12.75m0 0l-3.75 3.75M12 12.75V2.25" /></svg>
            {{ __('Cerrar sesión') }}
        </button>
    </div>
</div>
