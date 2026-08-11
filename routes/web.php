<?php

use App\Http\Controllers\StoreAccountPayableController;
use App\Http\Controllers\StoreAccountReceivableController;
use App\Http\Controllers\StoreCajaController;
use App\Http\Controllers\StoreCategoriaContableController;
use App\Http\Controllers\StoreComprobanteContableController;
use App\Http\Controllers\StoreConfigController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoreCuentaContableController;
use App\Http\Controllers\StoreCustomerController;
use App\Http\Controllers\StoreInvoiceAnalysisController;
use App\Http\Controllers\StoreInvoiceController;
use App\Http\Controllers\StoreBodegaController;
use App\Http\Controllers\StoreListaPrecioController;
use App\Http\Controllers\StoreDocumentoInventarioController;
use App\Http\Controllers\StoreProductController;
use App\Http\Controllers\StoreProveedorController;
use App\Http\Controllers\StoreRoleController;
use App\Http\Controllers\StoreTerceroController;
use App\Http\Controllers\StoreTipoComprobanteController;
use App\Http\Controllers\StoreReciboCajaTipoController;
use App\Http\Controllers\StoreReciboCajaController;
use App\Http\Controllers\StoreImpuestoController;
use App\Http\Controllers\StoreFormaPagoController;
use App\Http\Controllers\StoreCentroCostoController;
use App\Http\Controllers\StoreWorkerController;
use App\Http\Controllers\StoreWorkerHourRateTemplateController;
use App\Http\Controllers\StoreWorkerScheduleController;
use App\Models\Store;
use Illuminate\Support\Facades\Route;

Route::view('/', 'marketing.centradia')->name('centradia.landing');
Route::view('/centradia', 'marketing.centradia');
Route::view('/laravel-welcome', 'welcome')->name('laravel.welcome');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified', 'store.access'])->prefix('stores/{store:slug}')->name('stores.')->group(function () {

    Route::get('/', [StoreController::class, 'show'])->name('dashboard');

    Route::get('/configuracion', [StoreConfigController::class, 'edit'])->name('configuracion');
    Route::put('/configuracion', [StoreConfigController::class, 'update'])->name('configuracion.update');

    Route::get('/terceros', [StoreTerceroController::class, 'index'])->name('terceros');
    Route::post('/terceros', [StoreTerceroController::class, 'store'])->name('terceros.store');
    Route::get('/terceros/crear', [StoreTerceroController::class, 'create'])->name('terceros.create');
    Route::get('/terceros/productos/buscar', [StoreTerceroController::class, 'buscarProductos'])->name('terceros.productos.buscar');
    Route::post('/terceros/{tercero}/contactos', [StoreTerceroController::class, 'storeContacto'])->name('terceros.contactos.store');
    Route::put('/terceros/{tercero}/contactos/{contacto}', [StoreTerceroController::class, 'updateContacto'])->name('terceros.contactos.update');
    Route::delete('/terceros/{tercero}/contactos/{contacto}', [StoreTerceroController::class, 'destroyContacto'])->name('terceros.contactos.destroy');
    Route::post('/terceros/{tercero}/direcciones', [StoreTerceroController::class, 'storeDireccion'])->name('terceros.direcciones.store');
    Route::put('/terceros/{tercero}/direcciones/{direccion}', [StoreTerceroController::class, 'updateDireccion'])->name('terceros.direcciones.update');
    Route::delete('/terceros/{tercero}/direcciones/{direccion}', [StoreTerceroController::class, 'destroyDireccion'])->name('terceros.direcciones.destroy');
    Route::get('/terceros/{tercero}', [StoreTerceroController::class, 'show'])->name('terceros.show');
    Route::put('/terceros/{tercero}', [StoreTerceroController::class, 'update'])->name('terceros.update');
    Route::delete('/terceros/{tercero}', [StoreTerceroController::class, 'destroy'])->name('terceros.destroy');

    Route::get('/trabajadores', [StoreWorkerController::class, 'index'])->name('workers');
    Route::get('/trabajadores/registro-horarios', [StoreWorkerController::class, 'timeAttendance'])->name('workers.time-attendance');
    Route::get('/trabajadores/registro-horarios/clasificacion-excel', [StoreWorkerController::class, 'exportTimeAttendanceClassification'])->name('workers.time-attendance.classification-excel');
    Route::post('/trabajadores/registro-horarios', [StoreWorkerScheduleController::class, 'store'])->name('workers.schedules.store');
    Route::put('/trabajadores/registro-horarios/{workerSchedule}', [StoreWorkerScheduleController::class, 'update'])->name('workers.schedules.update');
    Route::delete('/trabajadores/registro-horarios/{workerSchedule}', [StoreWorkerScheduleController::class, 'destroy'])->name('workers.schedules.destroy');
    Route::post('/trabajadores/registro-horarios/plantillas-valor-hora', [StoreWorkerHourRateTemplateController::class, 'store'])->name('workers.schedules.rate-templates.store');
    Route::put('/trabajadores/registro-horarios/plantillas-valor-hora/{template}', [StoreWorkerHourRateTemplateController::class, 'update'])->name('workers.schedules.rate-templates.update');
    Route::delete('/trabajadores/registro-horarios/plantillas-valor-hora/{template}', [StoreWorkerHourRateTemplateController::class, 'destroy'])->name('workers.schedules.rate-templates.destroy');
    Route::get('/roles', [StoreRoleController::class, 'index'])->name('roles');
    Route::get('/roles/{role}/permisos', [StoreRoleController::class, 'permissions'])->name('roles.permissions');
    Route::post('/roles/{role}/permisos', [StoreRoleController::class, 'updatePermissions'])->name('roles.permissions.update');
    Route::post('/roles', [StoreRoleController::class, 'store'])->name('roles.store');
    Route::put('/roles/{role}', [StoreRoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [StoreRoleController::class, 'destroy'])->name('roles.destroy');

    // Productos y servicios
    Route::get('/productos', [StoreProductController::class, 'index'])->name('products');
    Route::get('/productos/crear', [StoreProductController::class, 'create'])->name('products.create');
    Route::post('/productos', [StoreProductController::class, 'store'])->name('products.store');
    Route::get('/productos/bodegas', [StoreBodegaController::class, 'index'])->name('products.bodegas');
    Route::post('/productos/bodegas', [StoreBodegaController::class, 'store'])->name('products.bodegas.store');
    Route::put('/productos/bodegas/manejo', [StoreBodegaController::class, 'updateManejo'])->name('products.bodegas.manejo');
    Route::put('/productos/bodegas/{bodega}', [StoreBodegaController::class, 'update'])->name('products.bodegas.update');
    Route::delete('/productos/bodegas/{bodega}', [StoreBodegaController::class, 'destroy'])->name('products.bodegas.destroy');
    Route::get('/productos/listas-precios', [StoreListaPrecioController::class, 'index'])->name('products.listas-precios');
    Route::put('/productos/listas-precios/{listaPrecio}', [StoreListaPrecioController::class, 'update'])->name('products.listas-precios.update');
    Route::get('/productos/documentos/saldos-iniciales/crear', [StoreProductController::class, 'createSaldosIniciales'])->name('products.documentos.saldos-iniciales.create');
    Route::post('/productos/documentos/saldos-iniciales', [StoreDocumentoInventarioController::class, 'storeSaldosIniciales'])->name('products.documentos.saldos-iniciales.store');
    Route::get('/productos/documentos/ajuste/crear', [StoreProductController::class, 'createAjuste'])->name('products.documentos.ajuste.create');
    Route::post('/productos/documentos/ajuste', [StoreDocumentoInventarioController::class, 'storeAjuste'])->name('products.documentos.ajuste.store');
    Route::get('/productos/documentos/traslado/crear', [StoreProductController::class, 'createTraslado'])->name('products.documentos.traslado.create');
    Route::post('/productos/documentos/traslado', [StoreDocumentoInventarioController::class, 'storeTraslado'])->name('products.documentos.traslado.store');
    Route::get('/productos/documentos/{documentoInventario}', [StoreDocumentoInventarioController::class, 'show'])->name('products.documentos.show');
    Route::get('/productos/documentos/{documentoInventario}/pdf', [StoreDocumentoInventarioController::class, 'pdf'])->name('products.documentos.pdf');
    Route::get('/productos/documentos/{documentoInventario}/contabilizacion', [StoreDocumentoInventarioController::class, 'contabilizacion'])->name('products.documentos.contabilizacion');
    Route::get('/productos/documentos/{documentoInventario}/contabilizacion/excel', [StoreDocumentoInventarioController::class, 'contabilizacionExcel'])->name('products.documentos.contabilizacion.excel');
    Route::get('/productos/{product}/editar', [StoreProductController::class, 'edit'])->name('products.edit');
    Route::put('/productos/{product}', [StoreProductController::class, 'update'])->name('products.update');
    Route::patch('/productos/{product}/estado', [StoreProductController::class, 'toggle'])->name('products.toggle');
    Route::post('/productos/{product}/duplicar', [StoreProductController::class, 'duplicate'])->name('products.duplicate');
    Route::delete('/productos/{product}', [StoreProductController::class, 'destroy'])->name('products.destroy');
    Route::get('/productos/{product}', [StoreProductController::class, 'show'])->name('products.show');

    Route::get('/informes', [StoreController::class, 'reportsIndex'])->name('reports.index');
    Route::post('/informes/analisis-facturas', [StoreInvoiceAnalysisController::class, 'process'])->name('reports.invoice-analysis.process');

    // Ventas (shell — flujo incompleto)
    Route::get('/ventas/carrito', [StoreController::class, 'carrito'])->name('ventas.carrito');

    // Facturas (listado histórico; creación deshabilitada)
    Route::get('/facturas', [StoreInvoiceController::class, 'index'])->name('invoices');
    Route::get('/facturas/exportar-excel', [StoreInvoiceController::class, 'exportExcel'])->name('invoices.export-excel');
    Route::get('/facturas/{invoice}', [StoreInvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/facturas/{invoice}/imprimir-tira', [StoreInvoiceController::class, 'printReceipt'])->name('invoices.printReceipt');
    Route::post('/facturas/{invoice}/anular', [StoreInvoiceController::class, 'void'])->name('invoices.void');

    // Proveedores (legacy redirect / list si aplica)
    Route::get('/proveedores', [StoreProveedorController::class, 'index'])->name('proveedores');

    // Clientes
    Route::get('/clientes', [StoreCustomerController::class, 'index'])->name('customers');

    Route::get('/caja', [StoreCajaController::class, 'index'])->name('cajas');
    Route::get('/caja/movimientos/exportar-excel', [StoreCajaController::class, 'exportMovimientosExcel'])->name('cajas.movimientos.export-excel');
    Route::get('/caja/movimientos', [StoreCajaController::class, 'movimientos'])->name('cajas.movimientos');
    Route::get('/caja/apertura', [StoreCajaController::class, 'aperturaCaja'])->name('cajas.apertura');
    Route::post('/caja/apertura', [StoreCajaController::class, 'storeAperturaCaja'])->name('cajas.apertura.store');
    Route::get('/caja/cerrar', [StoreCajaController::class, 'cerrarCaja'])->name('cajas.cerrar');
    Route::post('/caja/cerrar', [StoreCajaController::class, 'storeCierreCaja'])->name('cajas.cerrar.store');
    Route::get('/caja/sesiones', [StoreCajaController::class, 'sesiones'])->name('cajas.sesiones');
    Route::get('/caja/sesiones/{sesionCaja}', [StoreCajaController::class, 'showSesion'])->name('cajas.sesiones.show');
    Route::post('/caja/bolsillos', [StoreCajaController::class, 'storeBolsillo'])->name('cajas.bolsillos.store');
    Route::get('/caja/bolsillos/{bolsillo}', [StoreCajaController::class, 'showBolsillo'])->name('cajas.bolsillos.show');
    Route::put('/caja/bolsillos/{bolsillo}', [StoreCajaController::class, 'updateBolsillo'])->name('cajas.bolsillos.update');
    Route::delete('/caja/bolsillos/{bolsillo}', [StoreCajaController::class, 'destroyBolsillo'])->name('cajas.bolsillos.destroy');

    Route::get('/comprobantes-ingreso', function (Store $store) {
        return redirect()->route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'ingresos']);
    })->name('comprobantes-ingreso.index');
    Route::get('/comprobantes-ingreso/crear', [StoreCajaController::class, 'createComprobanteIngreso'])->name('comprobantes-ingreso.create');
    Route::post('/comprobantes-ingreso', [StoreCajaController::class, 'storeComprobanteIngreso'])->name('comprobantes-ingreso.store');
    Route::get('/comprobantes-ingreso/{comprobanteIngreso}/pdf', [StoreCajaController::class, 'pdfComprobanteIngreso'])->name('comprobantes-ingreso.pdf');
    Route::get('/comprobantes-ingreso/{comprobanteIngreso}', [StoreCajaController::class, 'showComprobanteIngreso'])->name('comprobantes-ingreso.show');

    Route::get('/recibos-caja/crear', [StoreReciboCajaController::class, 'create'])->name('recibos-caja.create');
    Route::post('/recibos-caja', [StoreReciboCajaController::class, 'store'])->name('recibos-caja.store');
    Route::get('/recibos-caja/cuentas-pendientes', [StoreReciboCajaController::class, 'cuentasPendientes'])->name('recibos-caja.cuentas-pendientes');

    Route::get('/comprobantes-egreso', function (Store $store) {
        return redirect()->route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'egresos']);
    })->name('comprobantes-egreso.index');
    Route::get('/comprobantes-egreso/crear', [StoreCajaController::class, 'createComprobanteEgreso'])->name('comprobantes-egreso.create');
    Route::get('/comprobantes-egreso/cuentas-proveedor', [StoreCajaController::class, 'cuentasPorPagarProveedor'])->name('comprobantes-egreso.cuentas-proveedor');
    Route::post('/comprobantes-egreso', [StoreCajaController::class, 'storeComprobanteEgreso'])->name('comprobantes-egreso.store');
    Route::get('/comprobantes-egreso/{comprobanteEgreso}/pdf', [StoreCajaController::class, 'pdfComprobanteEgreso'])->name('comprobantes-egreso.pdf');
    Route::get('/comprobantes-egreso/{comprobanteEgreso}', [StoreCajaController::class, 'showComprobanteEgreso'])->name('comprobantes-egreso.show');
    Route::get('/comprobantes-egreso/{comprobanteEgreso}/editar', [StoreCajaController::class, 'editComprobanteEgreso'])->name('comprobantes-egreso.edit');
    Route::put('/comprobantes-egreso/{comprobanteEgreso}', [StoreCajaController::class, 'updateComprobanteEgreso'])->name('comprobantes-egreso.update');
    Route::post('/comprobantes-egreso/{comprobanteEgreso}/reversar', [StoreCajaController::class, 'reversarComprobanteEgreso'])->name('comprobantes-egreso.reversar');
    Route::post('/comprobantes-egreso/{comprobanteEgreso}/anular', [StoreCajaController::class, 'anularComprobanteEgreso'])->name('comprobantes-egreso.anular');

    // Contabilidad
    Route::get('/contabilidad/cuentas', [StoreCuentaContableController::class, 'index'])->name('contabilidad.cuentas');
    Route::get('/contabilidad/cuentas/{cuentaContable}/hijos', [StoreCuentaContableController::class, 'hijosJson'])->name('contabilidad.cuentas.hijos.json');
    Route::post('/contabilidad/cuentas/importar-puc', [StoreCuentaContableController::class, 'importarPuc'])->name('contabilidad.cuentas.importar');
    Route::post('/contabilidad/cuentas/hijos', [StoreCuentaContableController::class, 'storeHijo'])->name('contabilidad.cuentas.hijos');
    Route::put('/contabilidad/cuentas/{cuentaContable}', [StoreCuentaContableController::class, 'update'])->name('contabilidad.cuentas.update');

    Route::get('/contabilidad/categorias', [StoreCategoriaContableController::class, 'index'])->name('contabilidad.categorias');
    Route::post('/contabilidad/categorias', [StoreCategoriaContableController::class, 'store'])->name('contabilidad.categorias.store');
    Route::put('/contabilidad/categorias/{categoriaContable}', [StoreCategoriaContableController::class, 'update'])->name('contabilidad.categorias.update');

    Route::get('/contabilidad/tipos-comprobante', [StoreTipoComprobanteController::class, 'index'])->name('contabilidad.tipos');
    Route::post('/contabilidad/tipos-comprobante', [StoreTipoComprobanteController::class, 'store'])->name('contabilidad.tipos.store');
    Route::put('/contabilidad/tipos-comprobante/{tipoComprobante}', [StoreTipoComprobanteController::class, 'update'])->name('contabilidad.tipos.update');

    Route::get('/contabilidad/recibos-caja', [StoreReciboCajaTipoController::class, 'index'])->name('contabilidad.recibos-caja');
    Route::post('/contabilidad/recibos-caja', [StoreReciboCajaTipoController::class, 'store'])->name('contabilidad.recibos-caja.store');
    Route::put('/contabilidad/recibos-caja/{tipoComprobante}', [StoreReciboCajaTipoController::class, 'update'])->name('contabilidad.recibos-caja.update');

    Route::get('/contabilidad/impuestos', [StoreImpuestoController::class, 'index'])->name('contabilidad.impuestos');
    Route::post('/contabilidad/impuestos', [StoreImpuestoController::class, 'store'])->name('contabilidad.impuestos.store');
    Route::put('/contabilidad/impuestos/{impuesto}', [StoreImpuestoController::class, 'update'])->name('contabilidad.impuestos.update');

    Route::get('/contabilidad/formas-pago', [StoreFormaPagoController::class, 'index'])->name('contabilidad.formas-pago');
    Route::post('/contabilidad/formas-pago', [StoreFormaPagoController::class, 'store'])->name('contabilidad.formas-pago.store');
    Route::put('/contabilidad/formas-pago/{formaPago}', [StoreFormaPagoController::class, 'update'])->name('contabilidad.formas-pago.update');

    Route::get('/contabilidad/centros-costo', [StoreCentroCostoController::class, 'index'])->name('contabilidad.centros-costo');
    Route::post('/contabilidad/centros-costo', [StoreCentroCostoController::class, 'store'])->name('contabilidad.centros-costo.store');
    Route::put('/contabilidad/centros-costo/definir-comprobantes', [StoreCentroCostoController::class, 'updateDefinirComprobantes'])->name('contabilidad.centros-costo.definir');
    Route::put('/contabilidad/centros-costo/{centroCosto}', [StoreCentroCostoController::class, 'update'])->name('contabilidad.centros-costo.update');

    Route::get('/contabilidad/comprobantes', [StoreComprobanteContableController::class, 'index'])->name('contabilidad.comprobantes');
    Route::get('/contabilidad/comprobantes/crear', [StoreComprobanteContableController::class, 'create'])->name('contabilidad.comprobantes.create');
    Route::get('/contabilidad/libro-diario', [StoreComprobanteContableController::class, 'diario'])->name('contabilidad.diario');
    Route::get('/contabilidad/libro-mayor', [StoreComprobanteContableController::class, 'mayor'])->name('contabilidad.mayor');
    Route::post('/contabilidad/comprobantes', [StoreComprobanteContableController::class, 'store'])->name('contabilidad.comprobantes.store');
    Route::get('/contabilidad/comprobantes/{comprobanteContable}', [StoreComprobanteContableController::class, 'show'])->name('contabilidad.comprobantes.show');
    Route::get('/contabilidad/comprobantes/{comprobanteContable}/editar', [StoreComprobanteContableController::class, 'edit'])->name('contabilidad.comprobantes.edit');
    Route::put('/contabilidad/comprobantes/{comprobanteContable}', [StoreComprobanteContableController::class, 'update'])->name('contabilidad.comprobantes.update');
    Route::post('/contabilidad/comprobantes/{comprobanteContable}/contabilizar', [StoreComprobanteContableController::class, 'contabilizar'])->name('contabilidad.comprobantes.post');
    Route::post('/contabilidad/comprobantes/{comprobanteContable}/reversar', [StoreComprobanteContableController::class, 'reversar'])->name('contabilidad.comprobantes.reverse');

    Route::get('/cuentas-por-pagar', function (Store $store) {
        return redirect()->route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'por-pagar']);
    })->name('accounts-payables');
    Route::controller(StoreAccountPayableController::class)->group(function () {
        Route::get('/cuentas-por-pagar/registrar-manual', 'createManual')->name('accounts-payables.create-manual');
        Route::post('/cuentas-por-pagar/registrar-manual', 'storeManual')->name('accounts-payables.store-manual');
        Route::get('/cuentas-por-pagar/{accountPayable}', 'show')->name('accounts-payables.show');
        Route::post('/cuentas-por-pagar/{accountPayable}/pagar', 'pay')->name('accounts-payables.pay');
    });

    Route::get('/cuentas-por-cobrar', function (Store $store) {
        return redirect()->route('stores.cajas.movimientos', ['store' => $store, 'tab' => 'por-cobrar']);
    })->name('accounts-receivables');
    Route::controller(StoreAccountReceivableController::class)->group(function () {
        Route::get('/cuentas-por-cobrar/{accountReceivable}', 'show')->name('accounts-receivables.show');
        Route::post('/cuentas-por-cobrar/{accountReceivable}/cobrar', 'cobrar')->name('accounts-receivables.cobrar');
    });
});

require __DIR__.'/auth.php';
