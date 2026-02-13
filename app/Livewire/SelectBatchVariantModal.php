<?php

namespace App\Livewire;

use App\Models\BatchItem;
use App\Models\Product;
use App\Models\Store;
use App\Services\InventarioService;
use App\Services\VentaService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class SelectBatchVariantModal extends Component
{
    public int $storeId;
    public int $productId;
    public string $rowId = '';
    public string $productName = '';

    /** Atributos de la categoría con sus opciones */
    public array $categoryAttributes = [];

    /** Variantes existentes del producto (desde BatchItems) */
    public array $existingVariants = [];

    /** ID de la variante seleccionada */
    public ?string $selectedVariantId = null;

    /** Claves de variantes que ya están en el carrito (solo contexto venta); esas opciones se muestran deshabilitadas. */
    public array $variantKeysInCart = [];

    public function mount(int $storeId): void
    {
        $this->storeId = $storeId;
    }

    #[On('open-select-batch-variant')]
    public function openForProduct(int $productId, string $rowId, string $productName, array $variantKeysInCart = []): void
    {
        $this->productId = $productId;
        $this->rowId = $rowId;
        $this->productName = $productName;
        $this->variantKeysInCart = $variantKeysInCart ?? [];
        $this->selectedVariantId = null;
        $this->loadProductVariants();
        $this->dispatch('open-modal', 'select-batch-variant');
    }

    protected function loadProductVariants(): void
    {
        $store = Store::find($this->storeId);
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            $this->existingVariants = [];
            $this->categoryAttributes = [];
            return;
        }

        $product = Product::where('id', $this->productId)
            ->where('store_id', $store->id)
            ->with([
                'category.attributes' => fn ($q) => $q->orderByPivot('position'),
                'batches.batchItems',
                'allowedVariantOptions',
            ])
            ->first();

        if (! $product || ! $product->category) {
            $this->existingVariants = [];
            $this->categoryAttributes = [];
            return;
        }

        // Cargar atributos de la categoría para mostrar los nombres
        $categoryAttributes = $product->category->attributes;
        $this->categoryAttributes = $categoryAttributes->map(function ($attr) {
            return [
                'id' => $attr->id,
                'name' => $attr->name,
            ];
        })->keyBy('id')->all();

        // Una fila por variante (mismas features), con stock total de esa variante en todos los lotes. El carrito no necesita ver lotes.
        $variantsMap = [];

        $batches = \App\Models\Batch::where('product_id', $product->id)
            ->where('store_id', $store->id)
            ->with('batchItems')
            ->orderBy('created_at')
            ->get();

        foreach ($batches as $batch) {
            foreach ($batch->batchItems as $batchItem) {
                if (! ($batchItem->is_active ?? true)) {
                    continue;
                }
                // Incluir también variantes con cantidad 0 (sin stock inicial o ya vendidas), para que aparezcan
                // en compra (añadir stock) y en factura/carrito; la vista ya muestra "0 uds".
                $normalizedFeatures = [];
                if ($batchItem->features !== null && is_array($batchItem->features) && ! empty($batchItem->features)) {
                    foreach ($batchItem->features as $key => $value) {
                        if ($value !== '' && $value !== null) {
                            $normalizedFeatures[(string) $key] = (string) $value;
                        }
                    }
                    ksort($normalizedFeatures);
                }
                if (empty($normalizedFeatures)) {
                    continue;
                }
                $key = InventarioService::detectorDeVariantesEnLotes($normalizedFeatures);
                if (! isset($variantsMap[$key])) {
                    $ventaService = app(VentaService::class);
                    $variantsMap[$key] = [
                        'variant_key'   => $key,
                        'features'      => $normalizedFeatures,
                        'display_name'  => $this->formatVariantDisplayName($normalizedFeatures),
                        'quantity'      => 0,
                        'price'         => $ventaService->verPrecio($store, (int) $product->id, 'batch', $normalizedFeatures),
                    ];
                }
                $variantsMap[$key]['quantity'] += (int) $batchItem->quantity;
            }
        }

        $this->existingVariants = array_values($variantsMap);
    }

    protected function formatVariantDisplayName(array $features): string
    {
        $parts = [];
        foreach ($features as $attrId => $value) {
            // Asegurar que attrId sea string para buscar en el array
            $attrIdStr = (string)$attrId;
            $attrName = $this->categoryAttributes[$attrIdStr]['name'] ?? $this->categoryAttributes[$attrId]['name'] ?? "Atributo {$attrId}";
            $parts[] = "{$attrName}: {$value}";
        }
        return implode(', ', $parts);
    }


    public function selectVariant(): void
    {
        // selectedVariantId es el índice (0, 1, 2...); no usar empty() porque empty(0) es true en PHP
        if ($this->selectedVariantId === null || $this->selectedVariantId === '') {
            $this->addError('selectedVariantId', 'Debes seleccionar una variante.');
            return;
        }

        $idx = (int) $this->selectedVariantId;
        $selectedVariant = $this->existingVariants[$idx] ?? null;

        if (! $selectedVariant) {
            $this->addError('selectedVariantId', 'La variante seleccionada no es válida.');
            return;
        }

        $variantKey = $selectedVariant['variant_key'] ?? null;
        if ($variantKey !== null && in_array($variantKey, $this->variantKeysInCart, true)) {
            $this->addError('selectedVariantId', 'Esta variante ya está en el carrito.');
            return;
        }

        // Incluir precio para que el receptor (factura/carrito) lo muestre sin recalcular (evita desajustes por serialización de variantFeatures)
        $this->dispatch('batch-variant-selected',
            rowId: $this->rowId,
            productId: $this->productId,
            productName: $this->productName,
            variantFeatures: $selectedVariant['features'],
            displayName: $selectedVariant['display_name'],
            totalStock: (int) $selectedVariant['quantity'],
            price: (float) ($selectedVariant['price'] ?? 0),
        );

        $this->dispatch('close-modal', 'select-batch-variant');
    }

    public function render()
    {
        return view('livewire.select-batch-variant-modal');
    }
}
