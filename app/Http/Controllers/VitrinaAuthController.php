<?php

namespace App\Http\Controllers;

use App\Livewire\Actions\Logout;
use App\Models\Customer;
use App\Models\User;
use App\Models\Plan;
use App\Models\VitrinaConfig;
use App\Services\CustomerService;
use App\Services\WorkerService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class VitrinaAuthController extends Controller
{
    public function __construct(
        private CustomerService $customerService
    ) {}

    /**
     * Login desde la vitrina. Tras autenticar, asegura que exista un Customer para esta tienda.
     */
    public function login(Request $request, string $slug): RedirectResponse
    {
        $config = VitrinaConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $request->merge(['email' => Str::lower($request->input('email', ''))]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        if (! Auth::attempt(
            ['email' => $validated['email'], 'password' => $validated['password']],
            (bool) ($validated['remember'] ?? false)
        )) {
            $view = $request->input('view', 'catalog');
            $params = ['slug' => $slug];
            if ($view === 'cart') {
                $params['view'] = 'cart';
            }
            return redirect()->route('vitrina.show', $params)
                ->with('auth_form', 'login')
                ->with('error', __('auth.failed'))
                ->withInput($request->only('email'));
        }

        $user = Auth::user();
        $request->session()->regenerate();

        // Guardar el slug de la tienda en sesión para el flujo de completar Customer
        $request->session()->put('complete_customer_slug', $slug);

        $hasCustomer = Customer::where('store_id', $store->id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('email', $user->email);
            })
            ->exists();

        if (! $hasCustomer) {
            return redirect()->route('vitrina.show', ['slug' => $slug])
                ->with('show_complete_customer_form', true)
                ->with('complete_customer_slug', $slug);
        }

        $this->ensureCustomerForStore($store, $user);

        return redirect()->route('vitrina.show', ['slug' => $slug])
            ->with('success', 'Sesión iniciada correctamente.');
    }

    /**
     * Registro desde la vitrina. Crea User si el email no existe; luego crea Customer para esta tienda.
     */
    public function register(Request $request, string $slug): RedirectResponse
    {
        $config = VitrinaConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $request->merge(['email' => Str::lower($request->input('email', ''))]);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'phone' => ['nullable', 'digits_between:7,12'],
            'address' => ['nullable', 'string', 'max:500'],
        ];

        $validated = $request->validate($rules, [
            'phone.digits_between' => 'El teléfono debe tener entre 7 y 12 dígitos.',
        ]);

        $existingUser = User::where('email', $validated['email'])->first();

        if (! $existingUser) {
            $user = $this->createUser($validated);
            try {
                $this->customerService->createCustomer($store, [
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                ]);
            } catch (\Exception $e) {
                $user->delete();
                $view = $request->input('view', 'catalog');
                $params = ['slug' => $slug];
                if ($view === 'cart') {
                    $params['view'] = 'cart';
                }
                return redirect()->route('vitrina.show', $params)
                    ->with('auth_form', 'register')
                    ->with('error', $e->getMessage())
                    ->withInput($request->only('name', 'email', 'phone', 'address'));
            }
            $this->customerService->vincularCustomersExistentes($user);
            app(WorkerService::class)->vincularWorkersExistentes($user);
            Auth::login($user);
        } else {
            try {
                $this->customerService->createCustomer($store, [
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'phone' => $validated['phone'] ?? null,
                    'address' => $validated['address'] ?? null,
                ]);
            } catch (\Exception $e) {
                $view = $request->input('view', 'catalog');
                $params = ['slug' => $slug];
                if ($view === 'cart') {
                    $params['view'] = 'cart';
                }
                return redirect()->route('vitrina.show', $params)
                    ->with('auth_form', 'register')
                    ->with('error', $e->getMessage())
                    ->withInput($request->only('name', 'email', 'phone', 'address'));
            }
            return redirect()->route('vitrina.show', ['slug' => $slug])
                ->with('auth_form', 'login')
                ->with('success', 'Tu ficha de cliente se ha creado. Inicia sesión con tu contraseña habitual.')
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        return redirect()->route('vitrina.show', ['slug' => $slug])
            ->with('success', 'Cuenta creada correctamente.');
    }

    /**
     * Completar ficha de Customer tras login (pantalla de humo). Requiere auth.
     */
    public function completeCustomerProfile(Request $request, string $slug): RedirectResponse
    {
        $config = VitrinaConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        if (auth()->guest()) {
            return redirect()->route('vitrina.show', ['slug' => $slug])
                ->with('error', 'Sesión inválida. Intenta iniciar sesión de nuevo.');
        }

        $user = Auth::user();
        $hasCustomer = Customer::where('store_id', $store->id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('email', $user->email);
            })
            ->exists();

        if ($hasCustomer) {
            $request->session()->forget(['show_complete_customer_form', 'complete_customer_slug']);
            return redirect()->route('vitrina.show', ['slug' => $slug])
                ->with('success', 'Ya eres cliente de este negocio.');
        }

        $validator = \Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'digits_between:7,12'],
            'address' => ['nullable', 'string', 'max:500'],
        ], [
            'phone.digits_between' => 'El teléfono debe tener entre 7 y 12 dígitos.',
        ]);

        if ($validator->fails()) {
            $view = $request->input('view', 'catalog');
            $params = ['slug' => $slug];
            if ($view === 'cart') {
                $params['view'] = 'cart';
            }

            return redirect()->route('vitrina.show', $params)
                ->with('show_complete_customer_form', true)
                ->with('complete_customer_slug', $slug)
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $customerData = [
            'name' => $validated['name'],
            'email' => $user->email,
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
        ];

        try {
            $this->customerService->createCustomer($store, $customerData);
        } catch (\Exception $e) {
            $view = $request->input('view', 'catalog');
            $params = ['slug' => $slug];
            if ($view === 'cart') {
                $params['view'] = 'cart';
            }

            return redirect()->route('vitrina.show', $params)
                ->with('show_complete_customer_form', true)
                ->with('complete_customer_slug', $slug)
                ->with('error', $e->getMessage())
                ->withInput($validated);
        }

        $request->session()->forget(['show_complete_customer_form', 'complete_customer_slug']);

        return redirect()->route('vitrina.show', ['slug' => $slug])
            ->with('success', 'Datos guardados. Ya eres cliente de este negocio.');
    }

    /**
     * Cerrar sesión y volver a la vitrina.
     */
    public function logout(Request $request, string $slug, Logout $logout): RedirectResponse
    {
        $logout();

        return redirect()->route('vitrina.show', ['slug' => $slug])
            ->with('success', 'Sesión cerrada.');
    }

    private function createUser(array $data): User
    {
        $data['password'] = Hash::make($data['password']);
        $freePlan = Plan::where('slug', 'free')->first();
        if ($freePlan) {
            $data['plan_id'] = $freePlan->id;
        }
        event(new Registered($user = User::create($data)));
        return $user;
    }

    /**
     * Si el usuario no tiene un Customer en esta tienda, lo crea (vinculado por email).
     */
    private function ensureCustomerForStore($store, User $user): void
    {
        $exists = Customer::where('store_id', $store->id)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhere('email', $user->email);
            })
            ->exists();

        if (! $exists && $user->email) {
            $this->customerService->createCustomer($store, [
                'name' => $user->name,
                'email' => $user->email,
            ]);
        }
    }
}
