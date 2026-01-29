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

    // Proveedores
    Route::get('/proveedores', [StoreController::class, 'proveedores'])->name('proveedores');
    Route::post('/proveedores', [StoreController::class, 'storeProveedor'])->name('proveedores.store');
    Route::put('/proveedores/{proveedor}', [StoreController::class, 'updateProveedor'])->name('proveedores.update');
    Route::delete('/proveedores/{proveedor}', [StoreController::class, 'destroyProveedor'])->name('proveedores.destroy');

    // Clientes
    Route::get('/clientes', [StoreController::class, 'customers'])->name('customers');
    Route::post('/clientes', [StoreController::class, 'storeCustomer'])->name('customers.store');
    Route::put('/clientes/{customer}', [StoreController::class, 'updateCustomer'])->name('customers.update');
    Route::delete('/clientes/{customer}', [StoreController::class, 'destroyCustomer'])->name('customers.destroy');

    // Caja = suma de bolsillos. Bolsillos (Caja 1, Caja 2, Cuenta banco…) y movimientos.
    Route::get('/caja', [StoreController::class, 'caja'])->name('cajas');
    Route::post('/caja/bolsillos', [StoreController::class, 'storeBolsillo'])->name('cajas.bolsillos.store');
    Route::get('/caja/bolsillos/{bolsillo}', [StoreController::class, 'showBolsillo'])->name('cajas.bolsillos.show');
    Route::put('/caja/bolsillos/{bolsillo}', [StoreController::class, 'updateBolsillo'])->name('cajas.bolsillos.update');
    Route::delete('/caja/bolsillos/{bolsillo}', [StoreController::class, 'destroyBolsillo'])->name('cajas.bolsillos.destroy');
    Route::post('/caja/movimientos', [StoreController::class, 'storeMovimiento'])->name('cajas.movimientos.store');
    Route::delete('/caja/movimientos/{movimiento}', [StoreController::class, 'destroyMovimiento'])->name('cajas.movimientos.destroy');

    // Inventario: movimientos entrada/salida (solo productos type = producto)
    Route::get('/inventario', [StoreController::class, 'inventario'])->name('inventario');
    Route::post('/inventario/movimientos', [StoreController::class, 'storeMovimientoInventario'])->name('inventario.movimientos.store');
});

require __DIR__.'/auth.php';