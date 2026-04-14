<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Services\ConvertidorImgService;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StoreConfigController extends Controller
{
    public function edit(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'store-config.view');

        return view('stores.configuracion', compact('store'));
    }

    public function update(Request $request, Store $store, ConvertidorImgService $convertidorImgService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'store-config.edit');

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:50'],
            'rut_nit' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'max:10'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'date_format' => ['nullable', 'string', 'max:20'],
            'time_format' => ['nullable', 'string', 'in:12,24'],
            'country' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9+]+$/', 'max:20'],
            'mobile' => ['nullable', 'string', 'regex:/^[0-9+]+$/', 'max:20'],
            'domain' => ['nullable', 'string', 'max:255'],
            'regimen' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'delete_logo' => ['nullable', 'boolean'],
        ], [
            'phone.regex' => 'El teléfono solo debe contener números.',
            'mobile.regex' => 'El celular solo debe contener números.',
        ]);

        $store->name = $validated['name'];
        $store->rut_nit = $validated['rut_nit'] ?? null;
        $store->currency = $validated['currency'] ?? null;
        $store->timezone = $validated['timezone'] ?? 'America/Bogota';
        $store->date_format = $validated['date_format'] ?? 'd-m-Y';
        $store->time_format = $validated['time_format'] ?? '24';
        $store->country = $validated['country'] ?? null;
        $store->department = $validated['department'] ?? null;
        $store->city = $validated['city'] ?? null;
        $store->address = $validated['address'] ?? null;
        $store->phone = $validated['phone'] ?? null;
        $store->mobile = $validated['mobile'] ?? null;
        $store->domain = $validated['domain'] ?? null;
        $store->regimen = $validated['regimen'] ?? null;

        if ($request->boolean('delete_logo') && $store->logo_path) {
            Storage::disk('public')->delete($store->logo_path);
            $store->logo_path = null;
        }

        if ($request->hasFile('logo')) {
            if ($store->logo_path) {
                Storage::disk('public')->delete($store->logo_path);
            }

            $basePath = 'stores/'.$store->id;
            $path = $request->file('logo')->store($basePath, 'public');

            try {
                $path = $convertidorImgService->convertPublicImageToWebp($path);
                $store->logo_path = $path;
            } catch (\Throwable $e) {
                Log::error('Error al convertir logo de tienda a WebP', [
                    'store_id' => $store->id,
                    'path' => $path,
                    'exception' => $e->getMessage(),
                ]);

                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Hubo un problema al procesar el logo. Intenta nuevamente más tarde.');
            }
        }

        $store->save();

        return redirect()->route('stores.configuracion', $store)
            ->with('success', 'Configuración de la tienda actualizada correctamente.');
    }
}
