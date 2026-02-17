<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\InventarioService;
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

    /** Variantes existentes del producto (desde product_variants) */
    public array $existingVariants = [];

    /** ID de la variante seleccionada (índice en el array existingVariants) */
    public ?string $selectedVariantId = null;

    /** Claves de variantes que ya están en el carrito; esas opciones se muestran deshabilitadas. */
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
                'variants.batchItems',
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

        // Construir variantes desde product_variants
        $variantsList = [];
        foreach ($product->variants as $variant) {
            if (! $variant->is_active) {
                continue;
            }

            $features = $variant->features ?? [];
            if (empty($features)) {
                continue;
            }

            $totalStock = $variant->batchItems->sum('quantity');

            $variantsList[] = [
                'product_variant_id' => $variant->id,
                'variant_key'        => $variant->normalized_key,
                'features'           => $features,
                'display_name'       => $this->formatVariantDisplayName($features),
                'quantity'           => (int) $totalStock,
                'price'              => (float) $variant->selling_price,
            ];
        }

        $this->existingVariants = $variantsList;
    }

    protected function formatVariantDisplayName(array $features): string
    {
        $parts = [];
        foreach ($features as $attrId => $value) {
            $attrIdStr = (string) $attrId;
            $attrName = $this->categoryAttributes[$attrIdStr]['name'] ?? $this->categoryAttributes[$attrId]['name'] ?? "Atributo {$attrId}";
            $parts[] = "{$attrName}: {$value}";
        }
        return implode(', ', $parts);
    }

    public function selectVariant(): void
    {
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

        $this->dispatch('batch-variant-selected',
            rowId: $this->rowId,
            productId: $this->productId,
            productName: $this->productName,
            variantFeatures: $selectedVariant['features'],
            displayName: $selectedVariant['display_name'],
            totalStock: (int) $selectedVariant['quantity'],
            price: (float) ($selectedVariant['price'] ?? 0),
            productVariantId: $selectedVariant['product_variant_id'],
        );

        $this->dispatch('close-modal', 'select-batch-variant');
    }

    public function render()
    {
        return view('livewire.select-batch-variant-modal');
    }
}
