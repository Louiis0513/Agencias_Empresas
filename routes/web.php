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
    Route::delete('/productos/{product}', [StoreController::class, 'destroyProduct'])->name('products.destroy');

    Route::get('/categorias', [StoreController::class, 'categories'])->name('categories');
    Route::delete('/categorias/{category}', [StoreController::class, 'destroyCategory'])->name('categories.destroy');
    
    // Grupos de atributos (gestión global)
    Route::get('/atributos', [StoreController::class, 'attributeGroups'])->name('attribute-groups');
    Route::delete('/atributos/grupos/{attributeGroup}', [StoreController::class, 'destroyAttributeGroup'])->name('attribute-groups.destroy');

    // Atributos de categorías
    Route::get('/categorias/{category}/atributos', [StoreController::class, 'categoryAttributes'])->name('category.attributes');
    Route::post('/categorias/{category}/atributos', [StoreController::class, 'assignAttributes'])->name('category.attributes.assign');

    // Facturas
    Route::get('/facturas', [StoreController::class, 'invoices'])->name('invoices');
    Route::post('/facturas', [StoreController::class, 'storeInvoice'])->name('invoices.store');
    Route::get('/facturas/{invoice}', [StoreController::class, 'showInvoice'])->name('invoices.show');
    Route::post('/facturas/{invoice}/anular', [StoreController::class, 'voidInvoice'])->name('invoices.void');

    // Clientes
    Route::get('/clientes', [StoreController::class, 'customers'])->name('customers');
    Route::post('/clientes', [StoreController::class, 'storeCustomer'])->name('customers.store');
    Route::put('/clientes/{customer}', [StoreController::class, 'updateCustomer'])->name('customers.update');
    Route::delete('/clientes/{customer}', [StoreController::class, 'destroyCustomer'])->name('customers.destroy');

});

require __DIR__.'/auth.php';