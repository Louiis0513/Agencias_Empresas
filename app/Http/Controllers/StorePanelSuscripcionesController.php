<?php

namespace App\Http\Controllers;

use App\Models\PanelSuscripcionesConfig;
use App\Models\Store;
use App\Services\ConvertidorImgService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class StorePanelSuscripcionesController extends Controller
{
    public function edit(Store $store)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        $panelConfig = PanelSuscripcionesConfig::firstOrCreate(
            ['store_id' => $store->id],
            [
                'whatsapp_contacts' => [],
                'phone_contacts' => [],
                'locations' => [],
            ]
        );

        return view('stores.panel_suscripciones.edit', compact('store', 'panelConfig'));
    }

    public function update(Request $request, Store $store, ConvertidorImgService $convertidorImgService)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        $panelConfig = PanelSuscripcionesConfig::firstOrCreate(
            ['store_id' => $store->id],
            [
                'whatsapp_contacts' => [],
                'phone_contacts' => [],
                'locations' => [],
            ]
        );

        $request->validate([
            'slug' => ['nullable', 'string', 'max:255', 'unique:panel_suscripciones_configs,slug,' . $panelConfig->id],
            'description' => ['nullable', 'string', 'max:300'],
            'schedule' => ['nullable', 'string', 'max:500'],
            'main_background_color' => ['nullable', 'string', 'max:50'],
            'primary_color' => ['nullable', 'string', 'max:50'],
            'secondary_color' => ['nullable', 'string', 'max:50'],
            'cover_image' => ['nullable', 'image', 'max:5120'],
            'logo_image' => ['nullable', 'image', 'max:5120'],
            'background_image' => ['nullable', 'image', 'max:5120'],
            'delete_cover' => ['nullable', 'boolean'],
            'delete_logo' => ['nullable', 'boolean'],
            'delete_background' => ['nullable', 'boolean'],
        ]);

        $slug = $request->filled('slug') ? \Illuminate\Support\Str::slug($request->slug) : null;
        if ($slug !== null && $slug === '') {
            $slug = null;
        }

        $panelConfig->slug = $slug;
        $panelConfig->description = $request->filled('description') ? $request->input('description') : null;
        $panelConfig->schedule = $request->filled('schedule') ? $request->input('schedule') : null;
        $panelConfig->main_background_color = $request->input('main_background_color') ?: null;
        $panelConfig->primary_color = $request->input('primary_color') ?: null;
        $panelConfig->secondary_color = $request->input('secondary_color') ?: null;

        $basePath = 'panel_suscripciones/' . $store->id;

        if ($request->boolean('delete_cover') && $panelConfig->cover_image_path) {
            Storage::disk('public')->delete($panelConfig->cover_image_path);
            $panelConfig->cover_image_path = null;
        }
        if ($request->hasFile('cover_image')) {
            if ($panelConfig->cover_image_path) {
                Storage::disk('public')->delete($panelConfig->cover_image_path);
            }
            $path = $request->file('cover_image')->store($basePath, 'public');
            try {
                $path = $convertidorImgService->convertPublicImageToWebp($path);
            } catch (\Throwable $e) {
                Log::error('Error al convertir cover_image del panel a WebP', ['store_id' => $store->id, 'exception' => $e->getMessage()]);
                return redirect()->back()->withInput()->with('error', 'Hubo un problema al procesar la imagen de portada.');
            }
            $panelConfig->cover_image_path = $path;
        }

        if ($request->boolean('delete_logo') && $panelConfig->logo_image_path) {
            Storage::disk('public')->delete($panelConfig->logo_image_path);
            $panelConfig->logo_image_path = null;
        }
        if ($request->hasFile('logo_image')) {
            if ($panelConfig->logo_image_path) {
                Storage::disk('public')->delete($panelConfig->logo_image_path);
            }
            $path = $request->file('logo_image')->store($basePath, 'public');
            try {
                $path = $convertidorImgService->convertPublicImageToWebp($path);
            } catch (\Throwable $e) {
                Log::error('Error al convertir logo_image del panel a WebP', ['store_id' => $store->id, 'exception' => $e->getMessage()]);
                return redirect()->back()->withInput()->with('error', 'Hubo un problema al procesar el logo.');
            }
            $panelConfig->logo_image_path = $path;
        }

        if ($request->boolean('delete_background') && $panelConfig->background_image_path) {
            Storage::disk('public')->delete($panelConfig->background_image_path);
            $panelConfig->background_image_path = null;
        }
        if ($request->hasFile('background_image')) {
            if ($panelConfig->background_image_path) {
                Storage::disk('public')->delete($panelConfig->background_image_path);
            }
            $path = $request->file('background_image')->store($basePath, 'public');
            try {
                $path = $convertidorImgService->convertPublicImageToWebp($path);
            } catch (\Throwable $e) {
                Log::error('Error al convertir background_image del panel a WebP', ['store_id' => $store->id, 'exception' => $e->getMessage()]);
                return redirect()->back()->withInput()->with('error', 'Hubo un problema al procesar la imagen de fondo.');
            }
            $panelConfig->background_image_path = $path;
        }

        $whatsappContacts = $this->parseContacts($request->input('whatsapp_contacts', []));
        $phoneContacts = $this->parseContacts($request->input('phone_contacts', []));
        $locations = $this->parseLocations($request->input('locations', []));

        $panelConfig->whatsapp_contacts = array_slice($whatsappContacts, 0, 5);
        $panelConfig->phone_contacts = array_slice($phoneContacts, 0, 5);
        $panelConfig->locations = array_slice($locations, 0, 1);

        $panelConfig->save();

        return redirect()->route('stores.panel-suscripciones.edit', $store)
            ->with('success', 'Panel de suscripciones actualizado correctamente.');
    }

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

    private function parseLocations(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $mapIframeSrc = $this->extractIframeSrc($row['map_iframe'] ?? '');
            if ($mapIframeSrc === '') {
                continue;
            }
            $out[] = ['map_iframe_src' => $mapIframeSrc];
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
