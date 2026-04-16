<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Cotizacion;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductItem;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\PanelSuscripcionesConfig;
use App\Models\VitrinaConfig;
use App\Support\Quantity;
use App\Services\CotizacionService;
use App\Services\InventarioService;
use App\Services\VitrinaCartService;
use App\Services\VentaService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class VitrinaController extends Controller
{
    public function __construct(
        private VitrinaCartService $cartService
    ) {}

    /**
     * Vitrina pública por slug (sin autenticación).
     */
	public function show(Request $request, string $slug, InventarioService $inventarioService)
    {
        $config = VitrinaConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

		$catalogItems = collect();
		$catalogPaginator = null;

		$rootCategories = $store->categories()
			->whereNull('parent_id')
			->orderBy('name')
			->get();

		$rootCategoryId = $request->filled('root_category_id') ? (int) $request->input('root_category_id') : null;
		$selectedCategoryId = $request->filled('category_id') ? (int) $request->input('category_id') : null;
		$currentCategoryId = $selectedCategoryId ?: $rootCategoryId;
		$currentCategory = null;
		$childCategories = collect();
		$breadcrumb = collect();

		$allowedPageSizes = [10, 20, 50];
		$defaultPageSize = $config->default_page_size && in_array($config->default_page_size, $allowedPageSizes, true)
			? (int) $config->default_page_size
			: 10;

		$pageSize = (int) $request->input('page_size', $defaultPageSize);
		if (! in_array($pageSize, $allowedPageSizes, true)) {
			$pageSize = $defaultPageSize;
		}

		$order = $request->input('order', 'price_asc');
		$search = $request->filled('search') ? trim($request->input('search')) : null;

		if ($currentCategoryId) {
			$currentCategory = Category::where('store_id', $store->id)
				->where('id', $currentCategoryId)
				->first();

			if ($currentCategory) {
				// Construir breadcrumb desde la raíz hasta la categoría actual
				$stack = [];
				$node = $currentCategory;
				while ($node) {
					$stack[] = $node;
					$node = $node->parent;
				}
				$breadcrumb = collect(array_reverse($stack));

				$childCategories = $currentCategory->children()
					->orderBy('name')
					->get();
			}
		}

        if ($config->show_products) {
			$productsQuery = $store->products()
				->where('is_active', true)
				->with([
					'attributeValues.attribute',
					'category.attributes',
					'variants' => function ($q) {
						$q->where('in_showcase', true)->where('is_active', true);
					},
					'productItems' => function ($q) {
						$q->where('in_showcase', true)->where('status', ProductItem::STATUS_AVAILABLE);
					},
				]);

			if ($currentCategory) {
				$categoryIdsForFilter = $this->collectCategoryAndDescendants($currentCategory);
				$productsQuery->whereIn('category_id', $categoryIdsForFilter);
			}

			$products = $productsQuery
				->orderBy('name')
				->get();

            foreach ($products as $product) {
                // Producto simple (sin variantes): usa flag in_showcase del propio producto
                if (! $product->isBatch() && ! $product->isSerialized() && $product->in_showcase) {
                    $displayName = $product->name;
                    if ($product->attributeValues && $product->attributeValues->isNotEmpty()) {
                        $attrs = $product->attributeValues
                            ->filter(fn ($av) => $av->attribute && $av->value !== null && $av->value !== '')
                            ->map(fn ($av) => $av->attribute->name . ': ' . $av->value)
                            ->values()
                            ->all();
                        if (! empty($attrs)) {
                            $displayName .= ' (' . implode(', ', $attrs) . ')';
                        }
                    }

                    $stockResult = $inventarioService->stockDisponible($store, $product->id);
                    $inCart = $this->cartService->getQuantityInCart($store, $product->id, null, null);
                    $stockVisible = max(0, (float) $stockResult['cantidad'] - $inCart);
                    $catalogItems->push((object) [
                        'display_name' => $displayName,
                        'price' => (float) ($product->price ?? 0),
                        'image_path' => $product->image_path,
						'category_id' => $product->category_id,
						'category_name' => $product->category ? $product->category->name : null,
                        'product_id' => $product->id,
                        'variant_id' => null,
                        'product_item_id' => null,
                        'stock' => $stockVisible,
                        'quantity_mode' => $product->quantity_mode ?? Product::QUANTITY_MODE_UNIT,
                        'quantity_step' => (float) ($product->quantity_step ?? 1),
                    ]);
                }

                // Producto por lotes: cada variante en vitrina se muestra como ítem
                if ($product->isBatch() && $product->relationLoaded('variants')) {
                    foreach ($product->variants as $variant) {
                        if (! $variant->in_showcase) {
                            continue;
                        }

                        $displayName = $product->name . ' (' . $variant->display_name . ')';
                        $stockResult = $inventarioService->stockDisponible($store, $product->id, null, null, $variant->id);
                        $inCart = $this->cartService->getQuantityInCart($store, $product->id, $variant->id, null);
                        $stockVisible = max(0, (float) $stockResult['cantidad'] - $inCart);

                        $catalogItems->push((object) [
                            'display_name' => $displayName,
                            'price' => $variant->selling_price,
                            'image_path' => $variant->image_path ?: $product->image_path,
							'category_id' => $product->category_id,
							'category_name' => $product->category ? $product->category->name : null,
                            'product_id' => $product->id,
                            'variant_id' => $variant->id,
                            'product_item_id' => null,
                            'stock' => $stockVisible,
                            'quantity_mode' => $product->quantity_mode ?? Product::QUANTITY_MODE_UNIT,
                            'quantity_step' => (float) ($product->quantity_step ?? 1),
                        ]);
                    }
                }

                // Producto serializado: cada unidad marcada para vitrina se muestra como ítem
                if ($product->isSerialized() && $product->relationLoaded('productItems')) {
                    $attrNames = $product->category && $product->category->attributes
                        ? $product->category->attributes->pluck('name', 'id')->all()
                        : [];

                    foreach ($product->productItems as $item) {
                        if (! $item->in_showcase) {
                            continue;
                        }

                        $displayName = $product->name;
                        $featuresStr = ! empty($item->features) && is_array($item->features)
                            ? ProductVariant::formatFeaturesWithAttributeNames($item->features, $attrNames)
                            : '';

                        if ($featuresStr !== '') {
                            $displayName .= ' (' . $featuresStr . ')';
                        }

                        $inCart = $this->cartService->getQuantityInCart($store, $product->id, null, $item->id);
                        $stockVisible = $item->status === ProductItem::STATUS_AVAILABLE ? (1 - min(1, $inCart)) : 0;

                        $catalogItems->push((object) [
                            'display_name' => $displayName,
                            'price' => (float) ($item->price ?? $product->price ?? 0),
                            'image_path' => $item->image_path ?: $product->image_path,
							'category_id' => $product->category_id,
							'category_name' => $product->category ? $product->category->name : null,
                            'product_id' => $product->id,
                            'variant_id' => null,
                            'product_item_id' => $item->id,
                            'stock' => $stockVisible,
                            'quantity_mode' => Product::QUANTITY_MODE_UNIT,
                            'quantity_step' => 1.0,
                        ]);
                    }
                }
            }

			if ($search !== null && $search !== '') {
				$catalogItems = $catalogItems->filter(fn ($item) => $this->displayNameMatchesSearch($item->display_name, $search))->values();
			}

			if ($order === 'price_desc') {
				$catalogItems = $catalogItems->sortByDesc('price')->values();
			} else {
				$catalogItems = $catalogItems->sortBy('price')->values();
			}

			$total = $catalogItems->count();
			$currentPage = LengthAwarePaginator::resolveCurrentPage();

			$itemsForCurrentPage = $catalogItems->forPage($currentPage, $pageSize)->values();

			$catalogPaginator = new LengthAwarePaginator(
				$itemsForCurrentPage,
				$total,
				$pageSize,
				$currentPage,
				[
					'path' => url()->current(),
					'query' => $request->query(),
				]
			);
        }

        $plans = $config->show_plans
            ? $store->storePlans()->where('in_showcase', true)->orderBy('name')->get()
            : collect();

        $cartItems = $this->cartService->getCartForStore($store);
        $totals = $this->cartService->getTotals($store);
        $currentView = $request->get('view', 'catalog') === 'cart' ? 'cart' : 'catalog';

        return view('vitrina.show', [
            'config' => $config,
            'store' => $store,
            'catalogItems' => $catalogItems,
			'catalogPaginator' => $catalogPaginator,
			'rootCategories' => $rootCategories,
			'rootCategoryId' => $rootCategoryId,
			'currentCategoryId' => $currentCategory ? $currentCategory->id : null,
			'childCategories' => $childCategories,
			'breadcrumb' => $breadcrumb,
			'order' => $order,
			'pageSize' => $pageSize,
			'pageSizeOptions' => $allowedPageSizes,
			'search' => $search,
            'plans' => $plans,
            'cartItems' => $cartItems,
            'cartSubtotal' => $totals['subtotal'],
            'cartTotal' => $totals['total'],
            'cartCount' => $totals['count'],
            'currentView' => $currentView,
        ]);
    }

    /**
     * Panel de suscripciones público: configuración e imágenes propias (panel_suscripciones_configs).
     * URL: /{slug}/PanelSuscripciones
     */
    public function panelSuscripciones(string $slug)
    {
        $config = PanelSuscripcionesConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;
        $plans = $store->storePlans()->where('in_showcase', true)->orderBy('name')->get();
        $vitrinaSlug = $store->vitrinaConfig?->slug;

        return view('panel_suscripciones.show', [
            'config' => $config,
            'store' => $store,
            'plans' => $plans,
            'vitrinaSlug' => $vitrinaSlug,
        ]);
    }

    public function addToCart(Request $request, string $slug, VentaService $ventaService): JsonResponse|RedirectResponse
    {
        $config = VitrinaConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $productId = (int) $request->input('product_id');
        $variantId = $request->filled('variant_id') ? (int) $request->input('variant_id') : null;
        $productItemId = $request->filled('product_item_id') ? (int) $request->input('product_item_id') : null;
        $product = Product::where('store_id', $store->id)->where('id', $productId)->first();
        if (! $product) {
            $message = 'Producto no encontrado o no disponible.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }
            return redirect()->route('vitrina.show', ['slug' => $slug])->with('error', $message);
        }
        $quantity = $this->normalizeRequestedQuantity($product, $request->input('quantity', 1));

        $itemToValidate = $this->buildItemForStockValidation($store, $productId, $variantId, $productItemId, $quantity);
        if ($itemToValidate === null) {
            $message = 'Producto no encontrado o no disponible.';
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }
            return redirect()->route('vitrina.show', ['slug' => $slug])->with('error', $message);
        }

        try {
            $ventaService->validarGuardadoItemCarrito($store, [$itemToValidate]);
        } catch (Exception $e) {
            $message = $e->getMessage();
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }
            return redirect()->route('vitrina.show', ['slug' => $slug])->with('error', $message);
        }

        $this->cartService->addItem($store, $productId, $variantId, $productItemId, $quantity);
        $totals = $this->cartService->getTotals($store);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Producto añadido al carrito.',
                'cart_count' => $totals['count'],
            ]);
        }

        return redirect()->route('vitrina.show', ['slug' => $slug])
            ->with('success', 'Producto añadido al carrito.');
    }

    public function updateCart(Request $request, string $slug, InventarioService $inventarioService): RedirectResponse
    {
        $config = VitrinaConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $lineKey = (string) $request->input('line_key');
        $delta = (float) $request->input('delta', 0);

        if ($lineKey !== '' && $delta !== 0) {
            $cart = $this->cartService->getCartForStore($store);
            $line = null;
            foreach ($cart as $row) {
                if (($row['line_key'] ?? '') === $lineKey) {
                    $line = $row;
                    break;
                }
            }
            if ($line !== null && $delta > 0) {
                $productId = (int) ($line['product_id'] ?? 0);
                $product = Product::where('store_id', $store->id)->where('id', $productId)->first();
                $step = $product && $product->usesDecimalQuantity() ? (float) ($product->quantity_step ?? 0.01) : 1.0;
                $delta = ($delta > 0 ? 1 : -1) * $step;
                $currentQty = (float) ($line['quantity'] ?? 0);
                $newQty = Quantity::normalize($currentQty + $delta);
                $variantId = isset($line['variant_id']) ? (int) $line['variant_id'] : null;
                $productItemId = isset($line['product_item_id']) ? (int) $line['product_item_id'] : null;

                if ($productItemId !== null && $productItemId > 0) {
                    if ($newQty > 1) {
                        return redirect()->route('vitrina.show', ['slug' => $slug, 'view' => 'cart'])
                            ->with('error', 'Cada unidad serializada solo puede tener cantidad 1.');
                    }
                } else {
                    $stockResult = $variantId !== null && $variantId > 0
                        ? $inventarioService->stockDisponible($store, $productId, null, null, $variantId)
                        : $inventarioService->stockDisponible($store, $productId);
                    $maxStock = (float) $stockResult['cantidad'];
                    if ($newQty > $maxStock) {
                        return redirect()->route('vitrina.show', ['slug' => $slug, 'view' => 'cart'])
                            ->with('error', "Stock insuficiente. Disponible: {$maxStock}, solicitado: {$newQty}.");
                    }
                }
            }
            $this->cartService->updateItemQuantity($store, $lineKey, $delta);
        }

        return redirect()->route('vitrina.show', ['slug' => $slug, 'view' => 'cart'])
            ->with('success', 'Carrito actualizado.');
    }

    public function clearCart(Request $request, string $slug): RedirectResponse
    {
        $config = VitrinaConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $this->cartService->clearCart($store);

        return redirect()->route('vitrina.show', ['slug' => $slug, 'view' => 'cart'])
            ->with('success', 'Carrito limpio.');
    }

    public function checkoutCart(Request $request, string $slug, CotizacionService $cotizacionService): RedirectResponse
    {
        $config = VitrinaConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $cartItems = $this->cartService->getCartForStore($store);
        if (empty($cartItems)) {
            return redirect()->route('vitrina.show', ['slug' => $slug, 'view' => 'cart'])
                ->with('error', 'Agrega productos al carrito antes de solicitar.');
        }

        if (! $request->has('nota')) {
            return redirect()->route('vitrina.show', ['slug' => $slug, 'view' => 'cart'])
                ->with('show_checkout_modal', true);
        }

        $userId = null;
        $customerId = null;
        if (! auth()->guest()) {
            $userId = auth()->id();
            $customer = Customer::where('store_id', $store->id)->where('user_id', $userId)->first();
            $customerId = $customer ? $customer->id : null;
        }

        $nota = 'DESDE VITRINA ' . trim((string) $request->input('nota', ''));
        $carritoConvertido = $cotizacionService->carritoVitrinaToCarritoCotizacion($store, $cartItems);

        try {
            $cotizacion = $cotizacionService->crearDesdeCarrito(
                $store,
                $userId,
                $customerId,
                $nota,
                $carritoConvertido,
                now()->addDay()
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('vitrina.show', ['slug' => $slug, 'view' => 'cart'])
                ->with('error', $e->getMessage());
        }

        $whatsappQuoteUrl = null;
        $whatsappContacts = $config->whatsapp_contacts ?? [];
        foreach ($whatsappContacts as $c) {
            $value = trim((string) ($c['value'] ?? ''));
            if ($value !== '') {
                $message = $this->buildWhatsAppQuoteMessage($cotizacion);
                $numero = preg_replace('/[^0-9]/', '', $value);
                $whatsappQuoteUrl = 'https://wa.me/' . $numero . '?text=' . rawurlencode($message);
                break;
            }
        }

        $this->cartService->clearCart($store);

        $redirect = redirect()->route('vitrina.show', ['slug' => $slug])
            ->with('success', 'Solicitud enviada. Te contactaremos a la brevedad.');
        if ($whatsappQuoteUrl !== null) {
            $redirect->with('whatsapp_quote_url', $whatsappQuoteUrl);
        }
        return $redirect;
    }

    /**
     * Construye el texto del mensaje de cotización para WhatsApp (número, detalle, subtotal, total).
     */
    private function buildWhatsAppQuoteMessage(Cotizacion $cotizacion): string
    {
        $sep = ' - - - - - - - - - - ';
        $currency = $cotizacion->store->currency ?? 'COP';
        $lines = [
            'Hola ! quiero realizar la siguiente compra cotizada #' . $cotizacion->id,
            $sep,
            'Detalle :',
            $sep,
        ];
        $subtotal = 0.0;
        foreach ($cotizacion->items as $item) {
            $name = (string) ($item->name ?? '');
            $variantDisplay = $item->variant_display_name ? trim((string) $item->variant_display_name) : null;
            $quantity = (int) $item->quantity;
            $unitPrice = (float) $item->unit_price;
            $line = $name;
            if ($variantDisplay !== null && $variantDisplay !== '') {
                $line .= ' (' . $variantDisplay . ')';
            }
            $line .= '   x ' . $quantity . ' x ' . money($unitPrice, $currency, false);
            $lines[] = $line;
            $lines[] = $sep;
            $subtotal += $unitPrice * $quantity;
        }
        $lines[] = 'subtotal: ' . money($subtotal, $currency, false);
        $lines[] = 'Total: ' . money($subtotal, $currency, false);

        return implode("\n", $lines);
    }

	/**
	 * Construye el ítem en formato esperado por VentaService::validarGuardadoItemCarrito.
	 *
	 * @return array{product_id: int, quantity?: int, product_variant_id?: int, serial_numbers?: array}|null
	 */
	private function buildItemForStockValidation(Store $store, int $productId, ?int $variantId, ?int $productItemId, float $quantity): ?array
	{
		if ($productItemId !== null && $productItemId > 0) {
			$item = ProductItem::where('store_id', $store->id)
				->where('product_id', $productId)
				->where('id', $productItemId)
				->first();
			if (! $item || $item->serial_number === null || $item->serial_number === '') {
				return null;
			}
			return ['product_id' => $productId, 'serial_numbers' => [$item->serial_number]];
		}
		if ($variantId !== null && $variantId > 0) {
			return ['product_id' => $productId, 'product_variant_id' => $variantId, 'quantity' => $quantity];
		}
		return ['product_id' => $productId, 'quantity' => $quantity];
	}

    private function normalizeRequestedQuantity(Product $product, mixed $rawQuantity): float
    {
        if ($product->isSerialized()) {
            return 1.0;
        }

        if ($product->usesDecimalQuantity()) {
            return max(0.01, Quantity::normalize($rawQuantity));
        }

        return (float) max(1, (int) $rawQuantity);
    }

	/**
	 * Búsqueda por "contiene": el texto del producto coincide si la búsqueda está contenida
	 * o si alguna palabra de la búsqueda coincide con alguna palabra del nombre/atributos
	 * (p. ej. "camisetas" encuentra "Camiseta" y "camiseta" encuentra "Camisetas").
	 */
	private function displayNameMatchesSearch(string $displayName, string $search): bool
	{
		$displayName = (string) $displayName;
		$search = trim((string) $search);
		if ($search === '') {
			return true;
		}
		// Coincidencia directa: la búsqueda está contenida en el nombre
		if (stripos($displayName, $search) !== false) {
			return true;
		}
		// Por palabras: cada palabra de la búsqueda debe coincidir con algún token del nombre
		$searchWords = array_filter(preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY));
		$tokens = array_filter(preg_split('/[\s,()]+/u', $displayName, -1, PREG_SPLIT_NO_EMPTY));
		$displayLower = mb_strtolower($displayName);
		foreach ($searchWords as $searchWord) {
			$sw = mb_strtolower($searchWord);
			$found = false;
			foreach ($tokens as $token) {
				$tk = mb_strtolower(trim($token, ':'));
				if ($tk === '' || $sw === '') {
					continue;
				}
				// Contiene: la palabra del usuario está en el token
				if (mb_strpos($tk, $sw) !== false) {
					$found = true;
					break;
				}
				// O el token está en la palabra del usuario, solo si el token tiene al menos 2 caracteres
				// (evita que "S", "M", "L" de tallas coincidan con "camiseta", "mesa", etc.)
				if (mb_strlen($tk) >= 2 && mb_strpos($sw, $tk) !== false) {
					$found = true;
					break;
				}
			}
			if (! $found && mb_strpos($displayLower, $sw) !== false) {
				$found = true;
			}
			if (! $found) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Obtener el id de una categoría y todos sus descendientes (cualquier profundidad).
	 */
	private function collectCategoryAndDescendants(Category $category): array
	{
		$ids = [$category->id];

		$children = $category->children()->get();
		foreach ($children as $child) {
			$ids = array_merge($ids, $this->collectCategoryAndDescendants($child));
		}

		return $ids;
	}
}
