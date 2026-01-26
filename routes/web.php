<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StoreController;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');


Route::middleware(['auth', 'verified'])->prefix('tienda/{store:slug}')->name('stores.')->group(function () {
    
  
    Route::get('/', [StoreController::class, 'show'])->name('dashboard');

    Route::get('/trabajadores', function () { 
        return "Aquí irá el módulo de Trabajadores"; 
    })->name('workers');

    Route::get('/productos', function () { 
        return "Aquí irá el módulo de Productos"; 
    })->name('products');

    Route::get('/facturas', function () { 
        return "Aquí irá el módulo de Facturas"; 
    })->name('invoices');

});

require __DIR__.'/auth.php';