<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\SupportDocument;
use App\Services\AttributeService;
use App\Services\CategoryService;
use App\Services\CotizacionService;
use App\Services\ProductReportsService;
use App\Services\ProductPurchasesBandejaService;
use App\Services\PurchaseService;
use App\Services\StorePermissionService;
use App\Services\SupportDocumentExcelExportService;
use App\Services\SupportDocumentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Picqer\Barcode\BarcodeGeneratorPNG;

class StoreController extends Controller
{
    public function show(Store $store)
    {
        // 1. SEGURIDAD: Verificar si el usuario autenticado pertenece a esta tienda
        // Si no está en la lista de trabajadores, lanzamos un error 403 (Prohibido)
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        // 2. (Opcional) Guardamos en sesión en qué tienda estamos trabajando
        // Esto es útil para no tener que preguntar el ID a cada rato
        session(['current_store_id' => $store->id]);

        // 3. Retornamos la vista del panel de la tienda
        return view('stores.dashboard', compact('store'));
    }

    public function categories(Store $store, CategoryService $categoryService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'categories.view');

        session(['current_store_id' => $store->id]);

        // Obtenemos el árbol de categorías (raíces con hijos)
        $categoryTree = $categoryService->getCategoryTree($store);

        // También obtenemos lista plana para dropdowns
        $categoriesFlat = $categoryService->getFlatList($store);

        return view('stores.productos.categorias', compact('store', 'categoryTree', 'categoriesFlat'));
    }

    public function destroyCategory(Store $store, \App\Models\Category $category, CategoryService $categoryService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'categories.destroy');

        try {
            $categoryService->deleteCategory($store, $category->id);

            return redirect()->route('stores.categories', $store)
                ->with('success', 'Categoría eliminada correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.categories', $store)
                ->with('error', $e->getMessage());
        }
    }

    public function showCategory(Store $store, Category $category, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'categories.view');

        if ($category->store_id !== $store->id) {
            abort(404);
        }

        session(['current_store_id' => $store->id]);

        $category->load(['attributes']);
        $products = $category->products()->orderBy('name')->get();

        return view('stores.productos.category-show', compact('store', 'category', 'products'));
    }

    public function attributeGroups(Request $request, Store $store, AttributeService $attributeService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'attribute-groups.view');

        session(['current_store_id' => $store->id]);

        $groups = $attributeService->getStoreAttributeGroupsPaginated($store, $request->input('search'), 10);

        return view('stores.productos.attribute-groups', compact('store', 'groups'));
    }

    public function categoryAttributes(Store $store, Category $category, AttributeService $attributeService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'category-attributes.assign');

        if ($category->store_id !== $store->id) {
            abort(404);
        }

        session(['current_store_id' => $store->id]);

        $storeAttributeGroups = $attributeService->getStoreAttributeGroups($store);
        $categoryAttributes = $category->attributes()->with(['groups'])->get();

        return view('stores.productos.category-attributes', compact('store', 'category', 'storeAttributeGroups', 'categoryAttributes'));
    }

    public function assignAttributes(Store $store, Category $category, Request $request, AttributeService $attributeService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'category-attributes.assign');

        if ($category->store_id !== $store->id) {
            abort(404);
        }

        $request->validate([
            'attribute_group_ids' => 'nullable|array',
            'attribute_group_ids.*' => 'exists:attribute_groups,id',
        ]);

        try {
            $attributeGroupIds = $request->input('attribute_group_ids', []) ?: [];
            // Solo grupos de esta tienda
            $attributeGroupIds = array_values(array_filter(array_map('intval', $attributeGroupIds)));
            $validGroupIds = \App\Models\AttributeGroup::where('store_id', $store->id)
                ->whereIn('id', $attributeGroupIds)
                ->pluck('id')
                ->all();
            $attributeGroupIds = array_values(array_intersect($attributeGroupIds, $validGroupIds));

            $attributeService->assignGroupsToCategory($category, $attributeGroupIds);

            return redirect()->route('stores.category.attributes', [$store, $category])
                ->with('success', 'Grupos de atributos asignados correctamente.');
        } catch (\Exception $e) {
            return redirect()->route('stores.category.attributes', [$store, $category])
                ->with('error', $e->getMessage());
        }
    }

    public function destroyAttributeGroup(Store $store, \App\Models\AttributeGroup $attributeGroup, AttributeService $attributeService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'attribute-groups.destroy');
        if ($attributeGroup->store_id !== $store->id) {
            abort(404);
        }

        try {
            $attributeService->deleteAttributeGroup($store, $attributeGroup->id);

            return redirect()->route('stores.attribute-groups', $store)->with('success', 'Grupo eliminado.');
        } catch (\Exception $e) {
            return redirect()->route('stores.attribute-groups', $store)->with('error', $e->getMessage());
        }
    }

    public function destroyAttribute(Store $store, \App\Models\Attribute $attribute, AttributeService $attributeService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'attribute-groups.edit');
        if ($attribute->store_id !== $store->id) {
            abort(404);
        }

        try {
            $attributeService->deleteAttribute($store, $attribute->id);

            return redirect()->route('stores.attribute-groups', $store)->with('success', 'Atributo eliminado.');
        } catch (\Exception $e) {
            return redirect()->route('stores.attribute-groups', $store)->with('error', $e->getMessage());
        }
    }

    // ==================== VENTAS ====================

    /**
     * Informes: pestañas por tipo (productos, facturación, …). Solo UI; el contenido no activo no se renderiza.
     */
    public function reportsIndex(Request $request, Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }

        $tab = $request->query('tab', 'productos');
        if (! in_array($tab, ['productos', 'facturacion'], true)) {
            $tab = 'productos';
        }

        $canProductsReport = $permission->can($store, 'products.view');
        $canBillingReport = $permission->can($store, 'invoices.view');

        if (! $request->has('tab') && ! $canProductsReport && $canBillingReport) {
            return redirect()->route('stores.reports.index', ['store' => $store, 'tab' => 'facturacion']);
        }

        if ($tab === 'productos' && ! $canProductsReport && $canBillingReport) {
            return redirect()->route('stores.reports.index', ['store' => $store, 'tab' => 'facturacion']);
        }
        if ($tab === 'facturacion' && ! $canBillingReport && $canProductsReport) {
            return redirect()->route('stores.reports.index', ['store' => $store, 'tab' => 'productos']);
        }

        if ($tab === 'productos') {
            $permission->authorize($store, 'products.view');
        } else {
            $permission->authorize($store, 'invoices.view');
        }

        session(['current_store_id' => $store->id]);

        $ventasRange = $request->query('ventas', ProductReportsService::VENTAS_7D);
        if (! in_array($ventasRange, [
            ProductReportsService::VENTAS_7D,
            ProductReportsService::VENTAS_1M,
            ProductReportsService::VENTAS_3M,
            ProductReportsService::VENTAS_SIEMPRE,
        ], true)) {
            $ventasRange = ProductReportsService::VENTAS_7D;
        }

        $topMasVendidos = collect();
        $topMayorMargen = collect();
        if ($tab === 'productos') {
            $reports = app(ProductReportsService::class);
            $topMasVendidos = $reports->topMasVendidos($store, $ventasRange);
            $topMayorMargen = $reports->topMayorMargen($store);
        }

        return view('stores.informes.index', compact('store', 'tab', 'topMasVendidos', 'topMayorMargen', 'ventasRange'));
    }

    public function carrito(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'ventas.carrito.view');

        session(['current_store_id' => $store->id]);

        return view('stores.ventas.carrito', compact('store'));
    }

    public function cotizaciones(Store $store, CotizacionService $cotizacionService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'cotizaciones.view');

        session(['current_store_id' => $store->id]);

        $cotizaciones = \App\Models\Cotizacion::deTienda($store->id)
            ->with(['user', 'customer', 'items'])
            ->orderByDesc('created_at')
            ->paginate(15);

        $totalesPorCotizacion = [];
        foreach ($cotizaciones as $cot) {
            $totalesPorCotizacion[$cot->id] = $cotizacionService->obtenerTotalesCotizacionYActual($store, $cot);
        }

        return view('stores.ventas.cotizaciones', compact('store', 'cotizaciones', 'totalesPorCotizacion'));
    }

    public function showCotizacion(Store $store, \App\Models\Cotizacion $cotizacion, CotizacionService $cotizacionService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'cotizaciones.view');

        if ($cotizacion->store_id !== $store->id) {
            abort(404);
        }

        $cotizacion->load(['user', 'customer', 'items.product', 'invoice']);
        $itemsConPrecios = $cotizacionService->obtenerItemsConPrecios($store, $cotizacion);
        $preConversion = $cotizacionService->validarPreConversion($cotizacion);

        return view('stores.ventas.cotizacion-detalle', compact('store', 'cotizacion', 'itemsConPrecios', 'preConversion'));
    }

    public function destroyCotizacion(Store $store, \App\Models\Cotizacion $cotizacion, CotizacionService $cotizacionService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'cotizaciones.view');

        if ($cotizacion->store_id !== $store->id) {
            abort(404);
        }

        $cotizacionService->eliminarCotizacion($cotizacion);

        return redirect()->route('stores.ventas.cotizaciones', $store)
            ->with('success', 'Cotización eliminada correctamente.');
    }

    // ==================== COMPRAS DE PRODUCTOS ====================

    public function productPurchases(Store $store, Request $request, ProductPurchasesBandejaService $bandeja, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.view');

        session(['current_store_id' => $store->id]);

        $docType = $request->get('doc_type');
        if (! in_array($docType, [
            ProductPurchasesBandejaService::DOC_TYPE_ALL,
            ProductPurchasesBandejaService::DOC_TYPE_PURCHASES,
            ProductPurchasesBandejaService::DOC_TYPE_SUPPORT_DOCUMENTS,
        ], true)) {
            $docType = ProductPurchasesBandejaService::DOC_TYPE_ALL;
        }

        $query = [
            'doc_type' => $docType,
            'status' => $request->get('status'),
            'payment_status' => $request->get('payment_status'),
            'proveedor_nombre' => $request->get('proveedor_nombre'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
            'per_page' => $request->get('per_page', 15),
        ];

        $bandejaRows = $bandeja->listar($store, $query);

        return view('stores.compras.compras-productos', compact('store', 'bandejaRows', 'docType'));
    }

    public function createProductPurchase(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.create');

        session(['current_store_id' => $store->id]);

        $proveedores = $store->proveedores()->orderBy('nombre')->get();

        return view('stores.compras.compra-productos-crear', compact('store', 'proveedores'));
    }

    /**
     * Documento soporte: pantalla de creación.
     */
    public function createDocumentoSoportePurchase(Store $store, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.view');

        session(['current_store_id' => $store->id]);

        $proveedores = $store->proveedores()->orderBy('nombre')->get();
        $bolsillos = $store->bolsillos()->activos()->orderBy('name')->get();

        return view('stores.compras.compra-documento-soporte-crear', compact('store', 'proveedores', 'bolsillos'));
    }

    /**
     * Documento soporte: edición de borrador.
     */
    public function editDocumentoSoportePurchase(Store $store, SupportDocument $supportDocument, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.view');

        if ($supportDocument->store_id !== $store->id) {
            abort(404);
        }

        session(['current_store_id' => $store->id]);

        $proveedores = $store->proveedores()->orderBy('nombre')->get();
        $bolsillos = $store->bolsillos()->activos()->orderBy('name')->get();

        return view('stores.compras.compra-documento-soporte-editar', compact('store', 'proveedores', 'supportDocument', 'bolsillos'));
    }

    /**
     * PDF en formato tira térmica (misma idea que recibo de factura).
     */
    public function printDocumentoSoportePurchase(Store $store, SupportDocument $supportDocument, SupportDocumentService $supportDocumentService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.view');

        if ($supportDocument->store_id !== $store->id) {
            abort(404);
        }

        $document = $supportDocumentService->obtenerDocumento($store, $supportDocument->id);
        $document->loadMissing(['comprobanteEgreso.origenes.bolsillo']);

        $barcodeBase64 = null;
        try {
            $generator = new BarcodeGeneratorPNG();
            $barcodePng = $generator->getBarcode((string) $document->id, $generator::TYPE_CODE_128, 2, 40);
            $barcodeBase64 = 'data:image/png;base64,'.base64_encode($barcodePng);
        } catch (\Throwable $e) {
            // continuar sin código de barras
        }

        $pdf = Pdf::loadView('stores.compras.documento-soporte-receipt-tira', [
            'document' => $document,
            'store' => $store,
            'barcodeBase64' => $barcodeBase64,
        ]);

        $baseHeightMm = 92;
        $itemsHeightMm = 0;
        $charsPerLine = 18;
        foreach ($document->inventoryItems as $line) {
            $desc = (string) ($line->description ?: ($line->product?->name ?? 'Ítem'));
            $lines = max(1, (int) ceil(mb_strlen($desc) / $charsPerLine));
            $itemsHeightMm += max(3, $lines * 3);
        }
        foreach ($document->serviceItems as $line) {
            $desc = trim($line->service_name.($line->description ? ' '.$line->description : ''));
            $lines = max(1, (int) ceil(mb_strlen($desc) / $charsPerLine));
            $itemsHeightMm += max(3, $lines * 3);
        }
        if ($document->inventoryItems->isEmpty() && $document->serviceItems->isEmpty()) {
            $itemsHeightMm = 6;
        }
        $notesExtraMm = 0;
        if ($document->notes) {
            $notesExtraMm = min(24, (int) ceil(mb_strlen((string) $document->notes) / 22) * 3);
        }
        $paymentExtraMm = 0;
        if ($document->comprobanteEgreso && $document->comprobanteEgreso->origenes->isNotEmpty()) {
            $paymentExtraMm = $document->comprobanteEgreso->origenes->count() * 4;
        }
        $totalHeightMm = $baseHeightMm + $itemsHeightMm + $notesExtraMm + $paymentExtraMm;
        $heightPt = round($totalHeightMm * 2.83465, 1);
        $pdf->setPaper([0, 0, 164.4, $heightPt], 'portrait');

        $filename = 'documento-soporte-'.$document->doc_prefix.'-'.$document->doc_number.'.pdf';

        return $pdf->stream($filename);
    }

    /**
     * Excel con todos los documentos soporte de la tienda (respeta filtros GET del listado).
     */
    public function exportSupportDocumentsListExcel(Store $store, Request $request, SupportDocumentService $supportDocumentService, SupportDocumentExcelExportService $excelExport, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.view');

        $filtros = [
            'status' => $request->get('status'),
            'payment_status' => $request->get('payment_status'),
            'proveedor_nombre' => $request->get('proveedor_nombre'),
            'fecha_desde' => $request->get('fecha_desde'),
            'fecha_hasta' => $request->get('fecha_hasta'),
        ];

        $documents = $supportDocumentService->listarDocumentosParaExportacion($store, $filtros);

        return $excelExport->downloadList($store, $documents);
    }

    public function storeDocumentoSoportePurchase(Store $store, Request $request, SupportDocumentService $supportDocumentService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.create');

        try {
            $supportDocumentService->crearBorrador($store, (int) Auth::id(), $request->all());

            return redirect()->route('stores.product-purchases', $store)
                ->with('success', 'Documento soporte guardado en borrador.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function updateDocumentoSoportePurchase(Store $store, SupportDocument $supportDocument, Request $request, SupportDocumentService $supportDocumentService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.create');

        if ($supportDocument->store_id !== $store->id) {
            abort(404);
        }

        try {
            $supportDocumentService->actualizarBorrador($store, $supportDocument->id, $request->all());

            return redirect()->route('stores.product-purchases', $store)
                ->with('success', 'Documento soporte actualizado correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function anularDocumentoSoportePurchase(Store $store, SupportDocument $supportDocument, SupportDocumentService $supportDocumentService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.create');

        if ($supportDocument->store_id !== $store->id) {
            abort(404);
        }

        try {
            $supportDocumentService->anularBorrador($store, $supportDocument->id);

            return redirect()->route('stores.product-purchases', $store)
                ->with('success', 'Documento soporte anulado correctamente.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function aprobarDocumentoSoportePurchase(Store $store, SupportDocument $supportDocument, Request $request, SupportDocumentService $supportDocumentService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.create');

        if ($supportDocument->store_id !== $store->id) {
            abort(404);
        }

        try {
            $supportDocumentService->aprobarDocumento(
                $store,
                $supportDocument->id,
                (int) Auth::id(),
                $request->input('payment_parts', [])
            );

            return redirect()->route('stores.product-purchases', $store)
                ->with('success', 'Documento soporte aprobado correctamente e inventario actualizado.');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function storeProductPurchase(Store $store, Request $request, PurchaseService $purchaseService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.create');

        $this->parseMoneyInputsInProductPurchaseDetails($request, $store->currency ?? 'COP');

        $data = $request->validate([
            'proveedor_id' => ['required', 'exists:proveedores,id'],
            'payment_status' => ['required', 'in:PAGADO,PENDIENTE'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.item_type' => ['nullable', 'string'],
            'details.*.product_id' => ['nullable'],
            'details.*.activo_id' => ['nullable'],
            'details.*.description' => ['nullable', 'string'],
            'details.*.quantity' => ['nullable', 'integer', 'min:0'],
            'details.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'details.*.batch_items' => ['nullable', 'array'],
            'details.*.batch_items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'details.*.batch_items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'details.*.batch_items.*.product_variant_id' => ['required', 'integer', 'min:1'],
            'details.*.batch_items.*.expiration_date' => ['nullable', 'date'],
            'details.*.serial_items' => ['nullable', 'array'],
            'details.*.serial_items.*.serial_number' => ['nullable', 'string'],
            'details.*.serial_items.*.cost' => ['nullable', 'numeric', 'min:0'],
            'details.*.serial_items.*.features' => ['nullable', 'array'],
            'details.*.serial_items.*.features.*' => ['nullable'],
        ], [
            'proveedor_id.required' => 'Las compras de productos deben tener un proveedor seleccionado.',
            'proveedor_id.exists' => 'El proveedor seleccionado no es válido.',
        ]);

        $data['purchase_type'] = Purchase::TYPE_PRODUCTO;

        try {
            $purchaseService->crearCompra($store, Auth::id(), $data);

            return redirect()->route('stores.product-purchases', $store)
                ->with('success', 'Compra de productos creada correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function editProductPurchase(Store $store, Purchase $purchase, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.create');

        if ($purchase->store_id !== $store->id || ! $purchase->isBorrador() || $purchase->purchase_type !== Purchase::TYPE_PRODUCTO) {
            abort(404);
        }

        $purchase->load([
            'details.product',
            'details.product.category.attributes',
            'details.product.variants',
            'proveedor',
        ]);

        $detailsForEdit = $purchase->details->map(function ($d) {
            $product = $d->product;
            $productType = $product ? ($product->type ?? 'simple') : 'simple';
            $description = $d->description ?? '';

            if ($product && $product->isSerialized() && ! empty($d->serial_items) && is_array($d->serial_items)) {
                $attrNames = [];
                if ($product->category) {
                    $category = $product->category->relationLoaded('attributes') ? $product->category : $product->category->load('attributes');
                    $attrNames = $category->attributes->pluck('name', 'id')->all();
                }
                $parts = [];
                foreach ($d->serial_items as $idx => $row) {
                    $row = is_array($row) ? $row : (array) $row;
                    $sn = trim($row['serial_number'] ?? '');
                    $feats = $row['features'] ?? [];
                    $featParts = [];
                    if (is_array($feats)) {
                        foreach ($feats as $attrId => $val) {
                            if ((string) $val !== '') {
                                $name = $attrNames[(int) $attrId] ?? $attrNames[(string) $attrId] ?? "Atributo {$attrId}";
                                $featParts[] = "{$name}: {$val}";
                            }
                        }
                    }
                    $unitLabel = $sn !== '' ? "Serial: {$sn}" : 'Unidad '.($idx + 1);
                    if (! empty($featParts)) {
                        $unitLabel .= ' ('.implode(', ', $featParts).')';
                    }
                    $parts[] = $unitLabel;
                }
                $description = $product->name.(empty($parts) ? '' : ' — '.implode('; ', $parts));
            } elseif ($product && $product->isBatch() && ! empty($d->batch_items) && is_array($d->batch_items)) {
                $bi = $d->batch_items[0] ?? null;
                $variantId = $bi && isset($bi['product_variant_id']) ? (int) $bi['product_variant_id'] : 0;
                if ($variantId > 0) {
                    $variant = \App\Models\ProductVariant::where('id', $variantId)->where('product_id', $product->id)->first();
                    if ($variant) {
                        $description = $product->name.' — '.$variant->display_name;
                    }
                }
            }

            return [
                'item_type' => $d->item_type,
                'product_id' => $d->product_id,
                'activo_id' => $d->activo_id,
                'description' => $description,
                'quantity' => $d->quantity,
                'unit_cost' => (float) $d->unit_cost,
                'subtotal' => (float) $d->subtotal,
                'serial_items' => $d->serial_items ?? [],
                'batch_items' => $d->batch_items ?? [],
                'product_type' => $productType,
            ];
        })->values()->all();

        session(['current_store_id' => $store->id]);
        $proveedores = $store->proveedores()->orderBy('nombre')->get();

        return view('stores.compras.compra-productos-crear', compact('store', 'purchase', 'proveedores', 'detailsForEdit'));
    }

    public function updateProductPurchase(Store $store, Purchase $purchase, Request $request, PurchaseService $purchaseService, StorePermissionService $permission)
    {
        if (! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para acceder a esta tienda.');
        }
        $permission->authorize($store, 'product-purchases.create');

        if ($purchase->store_id !== $store->id || ! $purchase->isBorrador() || $purchase->purchase_type !== Purchase::TYPE_PRODUCTO) {
            abort(404);
        }

        $this->parseMoneyInputsInProductPurchaseDetails($request, $store->currency ?? 'COP');

        $data = $request->validate([
            'proveedor_id' => ['required', 'exists:proveedores,id'],
            'payment_status' => ['required', 'in:PAGADO,PENDIENTE'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.item_type' => ['nullable', 'string'],
            'details.*.product_id' => ['nullable'],
            'details.*.activo_id' => ['nullable'],
            'details.*.description' => ['nullable', 'string'],
            'details.*.quantity' => ['nullable', 'integer', 'min:0'],
            'details.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'details.*.batch_items' => ['nullable', 'array'],
            'details.*.batch_items.*.quantity' => ['nullable', 'integer', 'min:0'],
            'details.*.batch_items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'details.*.batch_items.*.product_variant_id' => ['required', 'integer', 'min:1'],
            'details.*.batch_items.*.expiration_date' => ['nullable', 'date'],
            'details.*.serial_items' => ['nullable', 'array'],
            'details.*.serial_items.*.serial_number' => ['nullable', 'string'],
            'details.*.serial_items.*.cost' => ['nullable', 'numeric', 'min:0'],
            'details.*.serial_items.*.features' => ['nullable', 'array'],
            'details.*.serial_items.*.features.*' => ['nullable'],
        ], [
            'proveedor_id.required' => 'Las compras de productos deben tener un proveedor seleccionado.',
            'proveedor_id.exists' => 'El proveedor seleccionado no es válido.',
        ]);

        $data['purchase_type'] = Purchase::TYPE_PRODUCTO;

        try {
            $purchaseService->actualizarCompra($store, $purchase->id, $data);

            return redirect()->route('stores.purchases.show', [$store, $purchase])
                ->with('success', 'Compra actualizada correctamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withInput()->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    // ==================== PROVEEDORES ====================

    // ==================== CAJA (suma de bolsillos) Y BOLSILLOS ====================

    // ==================== COMPROBANTES DE INGRESO ====================

    // ==================== COMPROBANTES DE EGRESO ====================

    /**
     * Parsea valores de dinero formateados en details de compra de productos.
     */
    protected function parseMoneyInputsInProductPurchaseDetails(Request $request, string $currency): void
    {
        $service = app(\App\Services\CurrencyFormatService::class);
        $details = $request->input('details', []);
        foreach ($details as $i => $d) {
            $d = is_array($d) ? $d : [];
            if (isset($d['unit_cost'])) {
                $parsed = $service->parseFromFormatted((string) $d['unit_cost'], $currency);
                $details[$i]['unit_cost'] = $service->roundForCurrency($parsed, $currency);
            }
            if (! empty($d['batch_items'])) {
                foreach ($d['batch_items'] as $j => $bi) {
                    if (isset($bi['unit_cost'])) {
                        $parsed = $service->parseFromFormatted((string) $bi['unit_cost'], $currency);
                        $details[$i]['batch_items'][$j]['unit_cost'] = $service->roundForCurrency($parsed, $currency);
                    }
                }
            }
            if (! empty($d['serial_items'])) {
                foreach ($d['serial_items'] as $j => $si) {
                    if (isset($si['cost'])) {
                        $parsed = $service->parseFromFormatted((string) $si['cost'], $currency);
                        $details[$i]['serial_items'][$j]['cost'] = $service->roundForCurrency($parsed, $currency);
                    }
                }
            }
        }
        $request->merge(['details' => $details]);
    }
}
