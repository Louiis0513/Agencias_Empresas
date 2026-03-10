<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest-centradia')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="space-y-6">
    <!-- Session Status -->
    <x-auth-session-status class="mb-2" :status="session('status')" />

    <header>
        <h1 class="text-xl sm:text-2xl font-semibold tracking-tight text-slate-50">
            Inicia sesión en CENTRADIA
        </h1>
    </header>

    <form wire:submit="login" class="space-y-4 mt-2">
        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Correo electrónico" class="text-xs font-medium text-slate-200" />
            <x-text-input
                wire:model="form.email"
                id="email"
                class="mt-1 block w-full rounded-xl border border-slate-700 bg-slate-900/70 text-slate-50 placeholder:text-slate-500 focus:border-sky-400 focus:ring-sky-500"
                type="email"
                name="email"
                required
                autofocus
                autocomplete="username"
                placeholder="tu@empresa.com"
            />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2 text-xs" />
        </div>

        <!-- Password -->
        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" value="Contraseña" class="text-xs font-medium text-slate-200" />
                @if (Route::has('password.request'))
                    <a
                        href="{{ route('password.request') }}"
                        wire:navigate
                        class="text-[11px] font-medium text-sky-300 hover:text-sky-200"
                    >
                        Recuperar acceso
                    </a>
                @endif
            </div>

            <x-text-input
                wire:model="form.password"
                id="password"
                class="mt-1 block w-full rounded-xl border border-slate-700 bg-slate-900/70 text-slate-50 placeholder:text-slate-500 focus:border-sky-400 focus:ring-sky-500"
                type="password"
                name="password"
                required
                autocomplete="current-password"
                placeholder="••••••••"
            />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2 text-xs" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center justify-between pt-1">
            <label for="remember" class="inline-flex items-center gap-2">
                <input
                    wire:model="form.remember"
                    id="remember"
                    type="checkbox"
                    class="rounded border-slate-600 bg-slate-900/80 text-sky-400 shadow-sm focus:ring-sky-500"
                    name="remember"
                >
                <span class="text-xs text-slate-300">Mantener sesión iniciada</span>
            </label>
        </div>

        <div class="pt-2 flex flex-col gap-3">
            <x-primary-button class="w-full justify-center rounded-full bg-sky-500 px-4 py-2.5 text-sm font-semibold text-slate-950 shadow-md shadow-sky-500/40 hover:bg-sky-400 focus:ring-sky-500">
                Iniciar sesión
            </x-primary-button>

            @if (Route::has('register'))
                <p class="text-[11px] text-slate-500 text-center">
                    ¿Aún no tienes cuenta?
                    <a href="{{ route('register') }}" wire:navigate class="font-medium text-sky-300 hover:text-sky-200">
                        Crear cuenta gratuita
                    </a>
                </p>
            @endif
        </div>
    </form>
</div>
