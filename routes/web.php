<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoreWorkerController;
use App\Http\Controllers\StoreCategoryController;
use App\Http\Controllers\StoreProductController;
use App\Http\Controllers\StoreCustomerController;
use App\Http\Controllers\StoreProveedorController;
use App\Http\Controllers\StoreInvoiceController;
use App\Http\Controllers\StoreRoleController;
use App\Http\Controllers\StoreCajaController;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');


Route::middleware(['auth', 'verified', 'store.access'])->prefix('stores/{store:slug}')->name('stores.')->group(function () {
    
  
    Route::get('/', [StoreController::class, 'show'])->name('dashboard');

    Route::get('/trabajadores', [StoreWorkerController::class, 'index'])->name('workers');
    Route::get('/trabajadores/crear', [StoreWorkerController::class, 'create'])->name('workers.create');
    Route::post('/trabajadores', [StoreWorkerController::class, 'store'])->name('workers.store');
    Route::get('/trabajadores/{worker}/editar', [StoreWorkerController::class, 'edit'])->name('workers.edit');
    Route::put('/trabajadores/{worker}', [StoreWorkerController::class, 'update'])->name('workers.update');
    Route::delete('/trabajadores/{worker}', [StoreWorkerController::class, 'destroy'])->name('workers.destroy');

    Route::get('/roles', [StoreRoleController::class, 'index'])->name('roles');
    Route::get('/roles/{role}/permisos', [StoreRoleController::class, 'permissions'])->name('roles.permissions');
    Route::post('/roles/{role}/permisos', [StoreRoleController::class, 'updatePermissions'])->name('roles.permissions.update');
    Route::post('/roles', [StoreRoleController::class, 'store'])->name('roles.store');
    Route::put('/roles/{role}', [StoreRoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [StoreRoleController::class, 'destroy'])->name('roles.destroy');

    Route::get('/productos', [StoreProductController::class, 'index'])->name('products');
    Route::get('/productos/compras', [StoreController::class, 'productPurchases'])->name('product-purchases');
    Route::get('/productos/compras/crear', [StoreController::class, 'createProductPurchase'])->name('product-purchases.create');
    Route::post('/productos/compras', [StoreController::class, 'storeProductPurchase'])->name('product-purchases.store');
    Route::get('/productos/{product}', [StoreProductController::class, 'show'])->name('products.show');
    Route::put('/productos/{product}/variantes-permitidas', [StoreProductController::class, 'updateProductVariantOptions'])->name('productos.variant-options.update');
    Route::put('/productos/{product}/variante', [StoreProductController::class, 'updateVariant'])->name('productos.variant.update');
    Route::post('/productos/{product}/variantes', [StoreProductController::class, 'storeVariants'])->name('productos.variants.store');
    Route::delete('/productos/{product}', [StoreProductController::class, 'destroy'])->name('products.destroy');

    Route::get('/categorias', [StoreCategoryController::class, 'index'])->name('categories');
    Route::get('/categorias/{category}', [StoreCategoryController::class, 'show'])->name('category.show');
    Route::delete('/categorias/{category}', [StoreCategoryController::class, 'destroy'])->name('categories.destroy');
    
    // Grupos de atributos (gestión global)
    Route::get('/atributos', [StoreController::class, 'attributeGroups'])->name('attribute-groups');
    Route::delete('/atributos/grupos/{attributeGroup}', [StoreController::class, 'destroyAttributeGroup'])->name('attribute-groups.destroy');

    // Atributos de categorías
    Route::get('/categorias/{category}/atributos', [StoreCategoryController::class, 'attributes'])->name('category.attributes');
    Route::post('/categorias/{category}/atributos', [StoreCategoryController::class, 'assignAttributes'])->name('category.attributes.assign');

    // Ventas (carrito, cotizaciones)
    Route::get('/ventas/carrito', [StoreController::class, 'carrito'])->name('ventas.carrito');
    Route::get('/ventas/cotizaciones', [StoreController::class, 'cotizaciones'])->name('ventas.cotizaciones');
    Route::get('/ventas/cotizaciones/{cotizacion}', [StoreController::class, 'showCotizacion'])->name('ventas.cotizaciones.show');
    Route::delete('/ventas/cotizaciones/{cotizacion}', [StoreController::class, 'destroyCotizacion'])->name('ventas.cotizaciones.destroy');

    // Facturas
    Route::get('/facturas', [StoreInvoiceController::class, 'index'])->name('invoices');
    Route::post('/facturas', [StoreInvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/facturas/{invoice}', [StoreInvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/facturas/{invoice}/anular', [StoreInvoiceController::class, 'void'])->name('invoices.void');

    // Proveedores
    Route::get('/proveedores', [StoreProveedorController::class, 'index'])->name('proveedores');
    Route::post('/proveedores', [StoreProveedorController::class, 'store'])->name('proveedores.store');
    Route::put('/proveedores/{proveedor}', [StoreProveedorController::class, 'update'])->name('proveedores.update');
    Route::delete('/proveedores/{proveedor}', [StoreProveedorController::class, 'destroy'])->name('proveedores.destroy');

    // Clientes
    Route::get('/clientes', [StoreCustomerController::class, 'index'])->name('customers');
    Route::post('/clientes', [StoreCustomerController::class, 'store'])->name('customers.store');
    Route::put('/clientes/{customer}', [StoreCustomerController::class, 'update'])->name('customers.update');
    Route::delete('/clientes/{customer}', [StoreCustomerController::class, 'destroy'])->name('customers.destroy');

    Route::get('/caja', [StoreCajaController::class, 'index'])->name('cajas');
    Route::post('/caja/bolsillos', [StoreCajaController::class, 'storeBolsillo'])->name('cajas.bolsillos.store');
    Route::get('/caja/bolsillos/{bolsillo}', [StoreCajaController::class, 'showBolsillo'])->name('cajas.bolsillos.show');
    Route::put('/caja/bolsillos/{bolsillo}', [StoreCajaController::class, 'updateBolsillo'])->name('cajas.bolsillos.update');
    Route::delete('/caja/bolsillos/{bolsillo}', [StoreCajaController::class, 'destroyBolsillo'])->name('cajas.bolsillos.destroy');
    // Comprobantes de ingreso
    Route::get('/comprobantes-ingreso', [StoreCajaController::class, 'comprobantesIngreso'])->name('comprobantes-ingreso.index');
    Route::get('/comprobantes-ingreso/crear', [StoreCajaController::class, 'createComprobanteIngreso'])->name('comprobantes-ingreso.create');
    Route::post('/comprobantes-ingreso', [StoreCajaController::class, 'storeComprobanteIngreso'])->name('comprobantes-ingreso.store');
    Route::get('/comprobantes-ingreso/{comprobanteIngreso}', [StoreCajaController::class, 'showComprobanteIngreso'])->name('comprobantes-ingreso.show');
    // Comprobantes de Egreso
    Route::get('/comprobantes-egreso', [StoreCajaController::class, 'comprobantesEgreso'])->name('comprobantes-egreso.index');
    Route::get('/comprobantes-egreso/crear', [StoreCajaController::class, 'createComprobanteEgreso'])->name('comprobantes-egreso.create');
    Route::get('/comprobantes-egreso/cuentas-proveedor', [StoreCajaController::class, 'cuentasPorPagarProveedor'])->name('comprobantes-egreso.cuentas-proveedor');
    Route::post('/comprobantes-egreso', [StoreCajaController::class, 'storeComprobanteEgreso'])->name('comprobantes-egreso.store');
    Route::get('/comprobantes-egreso/{comprobanteEgreso}', [StoreCajaController::class, 'showComprobanteEgreso'])->name('comprobantes-egreso.show');
    Route::get('/comprobantes-egreso/{comprobanteEgreso}/editar', [StoreCajaController::class, 'editComprobanteEgreso'])->name('comprobantes-egreso.edit');
    Route::put('/comprobantes-egreso/{comprobanteEgreso}', [StoreCajaController::class, 'updateComprobanteEgreso'])->name('comprobantes-egreso.update');
    Route::post('/comprobantes-egreso/{comprobanteEgreso}/reversar', [StoreCajaController::class, 'reversarComprobanteEgreso'])->name('comprobantes-egreso.reversar');
    Route::post('/comprobantes-egreso/{comprobanteEgreso}/anular', [StoreCajaController::class, 'anularComprobanteEgreso'])->name('comprobantes-egreso.anular');
});

require __DIR__.'/auth.php';