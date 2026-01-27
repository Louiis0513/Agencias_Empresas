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

    Route::get('/trabajadores', [StoreController::class, 'workers'])->name('workers');
    
    Route::get('/roles', [StoreController::class, 'roles'])->name('roles');

    Route::get('/productos', [StoreController::class, 'products'])->name('products');

    Route::get('/categorias', [StoreController::class, 'categories'])->name('categories');
    Route::delete('/categorias/{category}', [StoreController::class, 'destroyCategory'])->name('categories.destroy');
    
    // Grupos de atributos (gestión global)
    Route::get('/atributos', [StoreController::class, 'attributeGroups'])->name('attribute-groups');
    Route::delete('/atributos/grupos/{attributeGroup}', [StoreController::class, 'destroyAttributeGroup'])->name('attribute-groups.destroy');

    // Atributos de categorías
    Route::get('/categorias/{category}/atributos', [StoreController::class, 'categoryAttributes'])->name('category.attributes');
    Route::post('/categorias/{category}/atributos', [StoreController::class, 'assignAttributes'])->name('category.attributes.assign');

    Route::get('/facturas', function () { 
        return "Aquí irá el módulo de Facturas"; 
    })->name('invoices');

});

require __DIR__.'/auth.php';