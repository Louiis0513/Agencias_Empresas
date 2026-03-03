<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Store;
use App\Models\StorePlan;
use App\Models\VitrinaConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class StoreVitrinaController extends Controller
{
    public function edit(Store $store)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        $vitrinaConfig = VitrinaConfig::firstOrCreate(
            ['store_id' => $store->id],
            [
                'show_products' => true,
                'show_plans' => true,
                'whatsapp_contacts' => [],
                'phone_contacts' => [],
                'locations' => [],
            ]
        );

        $products = $store->products()->orderBy('name')->get();
        $storePlans = $store->storePlans()->orderBy('name')->get();

        return view('stores.vitrina.edit', compact('store', 'vitrinaConfig', 'products', 'storePlans'));
    }

    public function update(Request $request, Store $store)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        $vitrinaConfig = VitrinaConfig::firstOrCreate(
            ['store_id' => $store->id],
            [
                'show_products' => true,
                'show_plans' => true,
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
            'cover_image' => ['nullable', 'image', 'max:5120'],
            'logo_image' => ['nullable', 'image', 'max:5120'],
            'background_image' => ['nullable', 'image', 'max:5120'],
            'delete_cover' => ['nullable', 'boolean'],
            'delete_logo' => ['nullable', 'boolean'],
            'delete_background' => ['nullable', 'boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'exists:products,id'],
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
            $vitrinaConfig->background_image_path = $path;
        }

        $whatsappContacts = $this->parseContacts($request->input('whatsapp_contacts', []), $request->input('locations', []));
        $phoneContacts = $this->parseContacts($request->input('phone_contacts', []), $request->input('locations', []));
        $locations = $this->parseLocations($request->input('locations', []));

        $vitrinaConfig->whatsapp_contacts = array_slice($whatsappContacts, 0, 5);
        $vitrinaConfig->phone_contacts = array_slice($phoneContacts, 0, 5);
        $vitrinaConfig->locations = array_slice($locations, 0, 5);

        $vitrinaConfig->save();

        $productIds = $request->input('product_ids', []);
        $store->products()->update(['in_showcase' => false]);
        if (! empty($productIds)) {
            $store->products()->whereIn('id', $productIds)->update(['in_showcase' => true]);
        }

        $planIds = $request->input('store_plan_ids', []);
        $store->storePlans()->update(['in_showcase' => false]);
        if (! empty($planIds)) {
            $store->storePlans()->whereIn('id', $planIds)->update(['in_showcase' => true]);
        }

        return redirect()->route('stores.vitrina.edit', $store)
            ->with('success', 'Vitrina virtual actualizada correctamente.');
    }

    /**
     * Parse contact rows from request (value + location_index from location name).
     */
    private function parseContacts(array $rows, array $locations): array
    {
        $names = array_column($locations, 'name');
        $out = [];
        foreach ($rows as $row) {
            if (empty($row['value'] ?? '')) {
                continue;
            }
            $value = trim($row['value']);
            $locationIndex = null;
            if (! empty($row['location_name'])) {
                $idx = array_search($row['location_name'], $names, true);
                if ($idx !== false) {
                    $locationIndex = $idx;
                }
            }
            $out[] = ['value' => $value, 'location_index' => $locationIndex];
        }
        return $out;
    }

    /**
     * Parse location rows: name, address, and extract map_iframe_src from iframe HTML.
     */
    private function parseLocations(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $name = trim($row['name'] ?? '');
            $address = trim($row['address'] ?? '');
            $mapIframeSrc = $this->extractIframeSrc($row['map_iframe'] ?? '');
            if ($name === '' && $address === '' && $mapIframeSrc === '') {
                continue;
            }
            $out[] = [
                'name' => $name,
                'address' => $address,
                'map_iframe_src' => $mapIframeSrc ?: null,
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
