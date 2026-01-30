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

    // Activos (espejo de products: computadores, muebles, etc. - van al escritorio, no a la estantería)
    Route::get('/activos', [StoreController::class, 'activos'])->name('activos');
    Route::get('/activos/movimientos', [StoreController::class, 'activosMovimientos'])->name('activos.movimientos');
    Route::get('/activos/crear', [StoreController::class, 'createActivo'])->name('activos.create');
    Route::post('/activos', [StoreController::class, 'storeActivo'])->name('activos.store');
    Route::get('/activos/{activo}/editar', [StoreController::class, 'editActivo'])->name('activos.edit');
    Route::put('/activos/{activo}', [StoreController::class, 'updateActivo'])->name('activos.update');
    Route::delete('/activos/{activo}', [StoreController::class, 'destroyActivo'])->name('activos.destroy');
    Route::get('/api/activos/buscar', [StoreController::class, 'buscarActivos'])->name('activos.buscar');
    Route::get('/api/productos-inventario/buscar', [StoreController::class, 'buscarProductosInventario'])->name('productos-inventario.buscar');

    // Compras
    Route::get('/compras', [StoreController::class, 'purchases'])->name('purchases');
    Route::get('/compras/crear', [StoreController::class, 'createPurchase'])->name('purchases.create');
    Route::post('/compras', [StoreController::class, 'storePurchase'])->name('purchases.store');
    Route::get('/compras/{purchase}', [StoreController::class, 'showPurchase'])->name('purchases.show');
    Route::get('/compras/{purchase}/editar', [StoreController::class, 'editPurchase'])->name('purchases.edit');
    Route::put('/compras/{purchase}', [StoreController::class, 'updatePurchase'])->name('purchases.update');
    Route::post('/compras/{purchase}/aprobar', [StoreController::class, 'approvePurchase'])->name('purchases.approve');
    Route::post('/compras/{purchase}/anular', [StoreController::class, 'voidPurchase'])->name('purchases.void');

    // Cuentas por pagar
    Route::get('/cuentas-por-pagar', [StoreController::class, 'accountsPayables'])->name('accounts-payables');
    Route::get('/cuentas-por-pagar/{accountPayable}', [StoreController::class, 'showAccountPayable'])->name('accounts-payables.show');
    Route::post('/cuentas-por-pagar/{accountPayable}/pagar', [StoreController::class, 'payAccountPayable'])->name('accounts-payables.pay');
    Route::post('/cuentas-por-pagar/{accountPayable}/reversar-pago/{payment}', [StoreController::class, 'reversarPagoAccountPayable'])->name('accounts-payables.reversar-pago');
});

require __DIR__.'/auth.php';