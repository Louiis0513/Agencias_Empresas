<?php

namespace App\Http\Controllers;

use App\Livewire\Actions\Logout;
use App\Models\Customer;
use App\Models\PanelSuscripcionesConfig;
use App\Models\User;
use App\Models\Plan;
use App\Services\CustomerService;
use App\Services\WorkerService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

class PanelSuscripcionesAuthController extends Controller
{
    public function __construct(
        private CustomerService $customerService
    ) {}

    /**
     * Login desde el Panel de Suscripciones.
     */
    public function login(Request $request, string $slug): RedirectResponse
    {
        $config = PanelSuscripcionesConfig::where('slug', $slug)->with('store')->firstOrFail();
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
            $view = $request->input('view', 'plans');
            $params = ['slug' => $slug];
            if ($view === 'cart') {
                $params['view'] = 'cart';
            }
            return redirect()->route('panel_suscripciones.show', $params)
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
            $view = $request->input('view', 'plans');
            $params = ['slug' => $slug];
            if ($view === 'cart') {
                $params['view'] = 'cart';
            }
            return redirect()->route('panel_suscripciones.show', $params)
                ->with('show_complete_customer_form', true)
                ->with('complete_customer_slug', $slug);
        }

        $this->ensureCustomerForStore($store, $user);

        $view = $request->input('view', 'plans');
        $params = ['slug' => $slug];
        if ($view === 'cart') {
            $params['view'] = 'cart';
        }
        return redirect()->route('panel_suscripciones.show', $params)
            ->with('success', 'Sesión iniciada correctamente.');
    }

    /**
     * Registro desde el Panel de Suscripciones (con campos gym).
     */
    public function register(Request $request, string $slug): RedirectResponse
    {
        $config = PanelSuscripcionesConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $request->merge(['email' => Str::lower($request->input('email', ''))]);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
            'phone' => ['nullable', 'digits_between:7,12'],
            'address' => ['nullable', 'string', 'max:500'],
            'gender' => ['nullable', 'string', 'in:M,F,NN', 'max:5'],
            'blood_type' => ['nullable', 'string', 'max:20'],
            'eps' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'digits_between:7,12'],
        ];

        $validated = $request->validate($rules, [
            'phone.digits_between' => 'El teléfono debe tener entre 7 y 12 dígitos.',
            'emergency_contact_phone.digits_between' => 'El número de contacto de emergencia debe tener entre 7 y 12 dígitos.',
        ]);

        $existingUser = User::where('email', $validated['email'])->first();

        $customerData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'address' => $validated['address'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'blood_type' => $validated['blood_type'] ?? null,
            'eps' => $validated['eps'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
        ];

        if (! $existingUser) {
            $user = $this->createUser($validated);
            try {
                $this->customerService->createCustomer($store, $customerData);
            } catch (\Exception $e) {
                $user->delete();
                return $this->redirectPanelRegisterError($slug, $request, $e->getMessage());
            }
            $this->customerService->vincularCustomersExistentes($user);
            app(WorkerService::class)->vincularWorkersExistentes($user);
            Auth::login($user);
        } else {
            try {
                $this->customerService->createCustomer($store, $customerData);
            } catch (\Exception $e) {
                return $this->redirectPanelRegisterError($slug, $request, $e->getMessage());
            }
            $view = $request->input('view', 'plans');
            $params = ['slug' => $slug];
            if ($view === 'cart') {
                $params['view'] = 'cart';
            }
            return redirect()->route('panel_suscripciones.show', $params)
                ->with('auth_form', 'login')
                ->with('success', 'Tu ficha de cliente se ha creado. Inicia sesión con tu contraseña habitual.')
                ->withInput($request->only('email'));
        }

        $request->session()->regenerate();

        $view = $request->input('view', 'plans');
        $params = ['slug' => $slug];
        if ($view === 'cart') {
            $params['view'] = 'cart';
        }
        return redirect()->route('panel_suscripciones.show', $params)
            ->with('success', 'Cuenta creada correctamente.');
    }

    /**
     * Completar ficha de Customer tras login (pantalla de humo). Requiere auth.
     */
    public function completeCustomerProfile(Request $request, string $slug): RedirectResponse
    {
        $config = PanelSuscripcionesConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        if (auth()->guest()) {
            return redirect()->route('panel_suscripciones.show', ['slug' => $slug])
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
            return redirect()->route('panel_suscripciones.show', ['slug' => $slug])
                ->with('success', 'Ya eres cliente de este negocio.');
        }

        $validator = \Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'digits_between:7,12'],
            'address' => ['nullable', 'string', 'max:500'],
            'gender' => ['nullable', 'string', 'in:M,F,NN', 'max:5'],
            'blood_type' => ['nullable', 'string', 'max:20'],
            'eps' => ['nullable', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'digits_between:7,12'],
        ], [
            'phone.digits_between' => 'El teléfono debe tener entre 7 y 12 dígitos.',
            'emergency_contact_phone.digits_between' => 'El número de contacto de emergencia debe tener entre 7 y 12 dígitos.',
        ]);

        if ($validator->fails()) {
            $view = $request->input('view', 'plans');
            $params = ['slug' => $slug];
            if ($view === 'cart') {
                $params['view'] = 'cart';
            }

            return redirect()->route('panel_suscripciones.show', $params)
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
            'gender' => $validated['gender'] ?? null,
            'blood_type' => $validated['blood_type'] ?? null,
            'eps' => $validated['eps'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
        ];

        try {
            $this->customerService->createCustomer($store, $customerData);
        } catch (\Exception $e) {
            $view = $request->input('view', 'plans');
            $params = ['slug' => $slug];
            if ($view === 'cart') {
                $params['view'] = 'cart';
            }

            return redirect()->route('panel_suscripciones.show', $params)
                ->with('show_complete_customer_form', true)
                ->with('complete_customer_slug', $slug)
                ->with('error', $e->getMessage())
                ->withInput($validated);
        }

        $request->session()->forget(['show_complete_customer_form', 'complete_customer_slug']);

        $view = $request->input('view', 'plans');
        $params = ['slug' => $slug];
        if ($view === 'cart') {
            $params['view'] = 'cart';
        }
        return redirect()->route('panel_suscripciones.show', $params)
            ->with('success', 'Datos guardados. Ya eres cliente de este negocio.');
    }

    public function logout(Request $request, string $slug, Logout $logout): RedirectResponse
    {
        $logout();

        return redirect()->route('panel_suscripciones.show', ['slug' => $slug])
            ->with('success', 'Sesión cerrada.');
    }

    private function redirectPanelRegisterError(string $slug, Request $request, string $message): RedirectResponse
    {
        $view = $request->input('view', 'plans');
        $params = ['slug' => $slug];
        if ($view === 'cart') {
            $params['view'] = 'cart';
        }
        return redirect()->route('panel_suscripciones.show', $params)
            ->with('auth_form', 'register')
            ->with('error', $message)
            ->withInput($request->only(
                'name', 'email', 'phone', 'address',
                'gender', 'blood_type', 'eps', 'birth_date',
                'emergency_contact_name', 'emergency_contact_phone'
            ));
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
