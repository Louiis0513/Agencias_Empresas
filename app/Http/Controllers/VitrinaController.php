<?php

namespace App\Http\Controllers;

use App\Models\VitrinaConfig;
use Illuminate\Http\Request;

class VitrinaController extends Controller
{
    /**
     * Vitrina pública por slug (sin autenticación).
     */
    public function show(string $slug)
    {
        $config = VitrinaConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $products = $config->show_products
            ? $store->products()->where('in_showcase', true)->where('is_active', true)->orderBy('name')->get()
            : collect();

        $plans = $config->show_plans
            ? $store->storePlans()->where('in_showcase', true)->orderBy('name')->get()
            : collect();

        return view('vitrina.show', [
            'config' => $config,
            'store' => $store,
            'products' => $products,
            'plans' => $plans,
        ]);
    }
}
