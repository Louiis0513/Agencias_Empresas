<?php

use App\Models\User;
use App\Models\Plan;
use Illuminate\Support\Str;
use App\Services\CustomerService;
use App\Services\WorkerService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest-centradia')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $this->email = Str::lower($this->email);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        // Asignar automáticamente el plan gratuito al usuario
        $freePlan = Plan::where('slug', 'free')->first();
        if ($freePlan) {
            $validated['plan_id'] = $freePlan->id;
        }

        event(new Registered($user = User::create($validated)));

        // Vincular automáticamente customers existentes con el mismo email
        $customerService = app(CustomerService::class);
        $customerService->vincularCustomersExistentes($user);

        // Vincular automáticamente workers (trabajadores) existentes con el mismo email
        $workerService = app(WorkerService::class);
        $workerService->vincularWorkersExistentes($user);

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="space-y-6">
    <header>
        <h1 class="text-xl sm:text-2xl font-semibold tracking-tight text-slate-50">
            Crea tu cuenta para empezar
        </h1>
    </header>

    <form wire:submit="register" class="space-y-4 mt-2">
        <!-- Name -->
        <div>
            <x-input-label for="name" value="Nombre completo" class="text-xs font-medium text-slate-200" />
            <x-text-input
                wire:model="name"
                id="name"
                class="mt-1 block w-full rounded-xl border border-slate-700 bg-slate-900/70 text-slate-50 placeholder:text-slate-500 focus:border-sky-400 focus:ring-sky-500"
                type="text"
                name="name"
                required
                autofocus
                autocomplete="name"
                placeholder="Cómo te llamas"
            />
            <x-input-error :messages="$errors->get('name')" class="mt-2 text-xs" />
        </div>

        <!-- Email Address -->
        <div>
            <x-input-label for="email" value="Correo electrónico" class="text-xs font-medium text-slate-200" />
            <x-text-input
                wire:model="email"
                id="email"
                class="mt-1 block w-full rounded-xl border border-slate-700 bg-slate-900/70 text-slate-50 placeholder:text-slate-500 focus:border-sky-400 focus:ring-sky-500"
                type="email"
                name="email"
                required
                autocomplete="username"
                placeholder="tu@empresa.com"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-xs" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" value="Contraseña" class="text-xs font-medium text-slate-200" />
            <p class="mt-1 text-[11px] text-slate-400">
                Debe tener al menos 8 caracteres, una mayúscula y un símbolo.
            </p>

            <x-text-input
                wire:model="password"
                id="password"
                class="mt-1 block w-full rounded-xl border border-slate-700 bg-slate-900/70 text-slate-50 placeholder:text-slate-500 focus:border-sky-400 focus:ring-sky-500"
                type="password"
                name="password"
                required
                autocomplete="new-password"
                placeholder="Crea una contraseña segura"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-xs" />
        </div>

        <!-- Confirm Password -->
        <div>
            <x-input-label for="password_confirmation" value="Confirmar contraseña" class="text-xs font-medium text-slate-200" />

            <x-text-input
                wire:model="password_confirmation"
                id="password_confirmation"
                class="mt-1 block w-full rounded-xl border border-slate-700 bg-slate-900/70 text-slate-50 placeholder:text-slate-500 focus:border-sky-400 focus:ring-sky-500"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Repite tu contraseña"
            />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2 text-xs" />
        </div>

        <div class="pt-2 flex flex-col gap-3">
            <x-primary-button class="w-full justify-center rounded-full bg-sky-500 px-4 py-2.5 text-sm font-semibold text-slate-950 shadow-md shadow-sky-500/40 hover:bg-sky-400 focus:ring-sky-500">
                Crear cuenta
            </x-primary-button>

            <p class="text-[11px] text-slate-500 text-center">
                ¿Ya tienes cuenta?
                <a href="{{ route('login') }}" wire:navigate class="font-medium text-sky-300 hover:text-sky-200">
                    Inicia sesión
                </a>
            </p>
        </div>
    </form>
</div>
