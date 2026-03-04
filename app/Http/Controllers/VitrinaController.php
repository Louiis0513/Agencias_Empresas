<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ProductItem;
use App\Models\ProductVariant;
use App\Models\VitrinaConfig;
use App\Services\VitrinaCartService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class VitrinaController extends Controller
{
    public function __construct(
        private VitrinaCartService $cartService
    ) {}

    /**
     * Vitrina pública por slug (sin autenticación).
     */
	public function show(Request $request, string $slug)
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

                    $catalogItems->push((object) [
                        'display_name' => $displayName,
                        'price' => (float) ($product->price ?? 0),
                        'image_path' => $product->image_path,
						'category_id' => $product->category_id,
						'category_name' => $product->category ? $product->category->name : null,
                        'product_id' => $product->id,
                        'variant_id' => null,
                        'product_item_id' => null,
                    ]);
                }

                // Producto por lotes: cada variante en vitrina se muestra como ítem
                if ($product->isBatch() && $product->relationLoaded('variants')) {
                    foreach ($product->variants as $variant) {
                        if (! $variant->in_showcase) {
                            continue;
                        }

                        $displayName = $product->name . ' (' . $variant->display_name . ')';

                        $catalogItems->push((object) [
                            'display_name' => $displayName,
                            'price' => $variant->selling_price,
                            'image_path' => $variant->image_path ?: $product->image_path,
							'category_id' => $product->category_id,
							'category_name' => $product->category ? $product->category->name : null,
                            'product_id' => $product->id,
                            'variant_id' => $variant->id,
                            'product_item_id' => null,
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

                        $catalogItems->push((object) [
                            'display_name' => $displayName,
                            'price' => (float) ($item->price ?? $product->price ?? 0),
                            'image_path' => $item->image_path ?: $product->image_path,
							'category_id' => $product->category_id,
							'category_name' => $product->category ? $product->category->name : null,
                            'product_id' => $product->id,
                            'variant_id' => null,
                            'product_item_id' => $item->id,
                        ]);
                    }
                }
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
            'plans' => $plans,
            'cartItems' => $cartItems,
            'cartSubtotal' => $totals['subtotal'],
            'cartTotal' => $totals['total'],
            'cartCount' => $totals['count'],
            'currentView' => $currentView,
        ]);
    }

    public function addToCart(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        $config = VitrinaConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $productId = (int) $request->input('product_id');
        $variantId = $request->filled('variant_id') ? (int) $request->input('variant_id') : null;
        $productItemId = $request->filled('product_item_id') ? (int) $request->input('product_item_id') : null;
        $quantity = max(1, (int) $request->input('quantity', 1));

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

    public function updateCart(Request $request, string $slug): RedirectResponse
    {
        $config = VitrinaConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $lineKey = (string) $request->input('line_key');
        $delta = (int) $request->input('delta', 0);

        if ($lineKey !== '' && $delta !== 0) {
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

    public function checkoutCart(Request $request, string $slug): RedirectResponse
    {
        $config = VitrinaConfig::where('slug', $slug)->with('store')->firstOrFail();
        $store = $config->store;

        $this->cartService->clearCart($store);

        return redirect()->route('vitrina.show', ['slug' => $slug])
            ->with('success', 'Solicitud de pedido enviada.');
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
