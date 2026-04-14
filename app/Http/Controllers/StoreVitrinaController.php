<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use App\Models\StorePlan;
use App\Models\VitrinaConfig;
use App\Services\ConvertidorImgService;
use App\Services\StorePermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StoreVitrinaController extends Controller
{
    public function edit(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'vitrina.view');

        $vitrinaConfig = VitrinaConfig::firstOrCreate(
            ['store_id' => $store->id],
            [
                'show_products' => true,
                'show_plans' => true,
				'default_page_size' => 10,
                'whatsapp_contacts' => [],
                'phone_contacts' => [],
                'locations' => [],
            ]
        );

        $products = $store->products()->orderBy('name')->get();
        $storePlans = $store->storePlans()->orderBy('name')->get();

        return view('stores.vitrina.edit', compact('store', 'vitrinaConfig', 'products', 'storePlans'));
    }

    public function update(Request $request, Store $store, ConvertidorImgService $convertidorImgService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'vitrina.edit');

        $vitrinaConfig = VitrinaConfig::firstOrCreate(
            ['store_id' => $store->id],
            [
                'show_products' => true,
                'show_plans' => true,
				'default_page_size' => 10,
                'whatsapp_contacts' => [],
                'phone_contacts' => [],
                'locations' => [],
            ]
        );

        $validated = $request->validate([
            'slug' => ['nullable', 'string', 'max:255', 'unique:vitrina_configs,slug,' . $vitrinaConfig->id],
            'description' => ['nullable', 'string', 'max:300'],
            'schedule' => ['nullable', 'string', 'max:500'],
            'show_products' => ['boolean'],
            'show_plans' => ['boolean'],
            'default_page_size' => ['nullable', 'integer', 'in:10,20,50'],
            'main_background_color' => ['nullable', 'string', 'max:50'],
            'primary_color' => ['nullable', 'string', 'max:50'],
            'secondary_color' => ['nullable', 'string', 'max:50'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
            'logo_image' => ['nullable', 'image', 'max:5120'],
            'background_image' => ['nullable', 'image', 'max:5120'],
            'delete_cover' => ['nullable', 'boolean'],
            'delete_logo' => ['nullable', 'boolean'],
            'delete_background' => ['nullable', 'boolean'],
            'store_plan_ids' => ['nullable', 'array'],
            'store_plan_ids.*' => ['integer', 'exists:store_plans,id'],
        ]);

        $slug = $request->filled('slug') ? \Illuminate\Support\Str::slug($request->slug) : null;
        if ($slug !== null && $slug === '') {
            $slug = null;
        }

        $vitrinaConfig->slug = $slug;
        $vitrinaConfig->description = $request->filled('description') ? $request->input('description') : null;
        $vitrinaConfig->schedule = $request->filled('schedule') ? $request->input('schedule') : null;
        $vitrinaConfig->show_products = $request->boolean('show_products');
        $vitrinaConfig->show_plans = $request->boolean('show_plans');
        $vitrinaConfig->default_page_size = $request->input('default_page_size') ?: null;
        $vitrinaConfig->main_background_color = $request->input('main_background_color') ?: null;
        $vitrinaConfig->primary_color = $request->input('primary_color') ?: null;
        $vitrinaConfig->secondary_color = $request->input('secondary_color') ?: null;

        $basePath = 'vitrina/' . $store->id;

        if ($request->boolean('delete_cover') && $vitrinaConfig->cover_image_path) {
            Storage::disk('public')->delete($vitrinaConfig->cover_image_path);
            $vitrinaConfig->cover_image_path = null;
        }
        if ($request->hasFile('cover_image')) {
            if ($vitrinaConfig->cover_image_path) {
                Storage::disk('public')->delete($vitrinaConfig->cover_image_path);
            }

            $path = $request->file('cover_image')->store($basePath, 'public');

            try {
                $path = $convertidorImgService->convertPublicImageToWebp($path);
            } catch (\Throwable $e) {
                Log::error('Error al convertir cover_image a WebP', [
                    'store_id' => $store->id,
                    'path' => $path,
                    'exception' => $e->getMessage(),
                ]);

                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Hubo un problema al procesar la imagen de portada. Intenta nuevamente más tarde.');
            }

            $vitrinaConfig->cover_image_path = $path;
        }

        if ($request->boolean('delete_logo') && $vitrinaConfig->logo_image_path) {
            Storage::disk('public')->delete($vitrinaConfig->logo_image_path);
            $vitrinaConfig->logo_image_path = null;
        }
        if ($request->hasFile('logo_image')) {
            if ($vitrinaConfig->logo_image_path) {
                Storage::disk('public')->delete($vitrinaConfig->logo_image_path);
            }

            $path = $request->file('logo_image')->store($basePath, 'public');

            try {
                $path = $convertidorImgService->convertPublicImageToWebp($path);
            } catch (\Throwable $e) {
                Log::error('Error al convertir logo_image a WebP', [
                    'store_id' => $store->id,
                    'path' => $path,
                    'exception' => $e->getMessage(),
                ]);

                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Hubo un problema al procesar el logo. Intenta nuevamente más tarde.');
            }

            $vitrinaConfig->logo_image_path = $path;
        }

        if ($request->boolean('delete_background') && $vitrinaConfig->background_image_path) {
            Storage::disk('public')->delete($vitrinaConfig->background_image_path);
            $vitrinaConfig->background_image_path = null;
        }
        if ($request->hasFile('background_image')) {
            if ($vitrinaConfig->background_image_path) {
                Storage::disk('public')->delete($vitrinaConfig->background_image_path);
            }

            $path = $request->file('background_image')->store($basePath, 'public');

            try {
                $path = $convertidorImgService->convertPublicImageToWebp($path);
            } catch (\Throwable $e) {
                Log::error('Error al convertir background_image a WebP', [
                    'store_id' => $store->id,
                    'path' => $path,
                    'exception' => $e->getMessage(),
                ]);

                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'Hubo un problema al procesar la imagen de fondo. Intenta nuevamente más tarde.');
            }

            $vitrinaConfig->background_image_path = $path;
        }

        $whatsappContacts = $this->parseContacts($request->input('whatsapp_contacts', []));
        $phoneContacts = $this->parseContacts($request->input('phone_contacts', []));
        $locations = $this->parseLocations($request->input('locations', []));

        $vitrinaConfig->whatsapp_contacts = array_slice($whatsappContacts, 0, 5);
        $vitrinaConfig->phone_contacts = array_slice($phoneContacts, 0, 5);
        $vitrinaConfig->locations = array_slice($locations, 0, 1);

        $vitrinaConfig->save();

        $planIds = $request->input('store_plan_ids', []);
        $store->storePlans()->update(['in_showcase' => false]);
        if (! empty($planIds)) {
            $store->storePlans()->whereIn('id', $planIds)->update(['in_showcase' => true]);
        }

        return redirect()->route('stores.vitrina.edit', $store)
            ->with('success', 'Vitrina virtual actualizada correctamente.');
    }

    /**
     * Parse contact rows from request (value only; no location/sede association).
     * For WhatsApp, value can be built from country_code + number if both present.
     */
    private function parseContacts(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $value = null;
            $code = ltrim(preg_replace('/\D/', '', (string) ($row['country_code'] ?? '')), '0');
            if (! empty($row['number']) && $code !== '') {
                $value = '+' . $code . preg_replace('/\D/', '', (string) $row['number']);
            } elseif (! empty($row['value'])) {
                $value = trim($row['value']);
            }
            if ($value === '' || $value === null) {
                continue;
            }
            $out[] = ['value' => $value, 'location_index' => null];
        }
        return $out;
    }

    /**
     * Parse location rows: extract map_iframe_src from iframe HTML. Solo se guarda el mapa.
     */
    private function parseLocations(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $mapIframeSrc = $this->extractIframeSrc($row['map_iframe'] ?? '');
            if ($mapIframeSrc === '') {
                continue;
            }
            $out[] = [
                'map_iframe_src' => $mapIframeSrc,
            ];
        }
        return $out;
    }

    private function extractIframeSrc(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        if (preg_match('/src=["\']([^"\']+)["\']/', $html, $m)) {
            return trim($m[1]);
        }
        if (filter_var(trim($html), FILTER_VALIDATE_URL)) {
            return trim($html);
        }
        return '';
    }
}
