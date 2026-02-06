<?php

namespace App\Livewire;

use App\Models\BatchItem;
use App\Models\Product;
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

    /** Variantes existentes del producto (desde BatchItems) */
    public array $existingVariants = [];

    /** ID de la variante seleccionada */
    public ?string $selectedVariantId = null;

    public function mount(int $storeId): void
    {
        $this->storeId = $storeId;
    }

    #[On('open-select-batch-variant')]
    public function openForProduct(int $productId, string $rowId, string $productName): void
    {
        $this->productId = $productId;
        $this->rowId = $rowId;
        $this->productName = $productName;
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

        // Obtener SOLO las variantes existentes de BatchItems (las que realmente se crearon)
        $variantsMap = [];
        
        // Cargar batches con batchItems explícitamente
        $batches = \App\Models\Batch::where('product_id', $product->id)
            ->where('store_id', $store->id)
            ->with('batchItems')
            ->get();
            
        foreach ($batches as $batch) {
            foreach ($batch->batchItems as $batchItem) {
                // Normalizar features
                $normalizedFeatures = [];
                
                if ($batchItem->features !== null && is_array($batchItem->features) && !empty($batchItem->features)) {
                    // Asegurar que las claves sean strings para la comparación
                    foreach ($batchItem->features as $key => $value) {
                        // Filtrar valores vacíos, null, o '0' (excepto si es un valor válido como "0")
                        if ($value !== '' && $value !== null) {
                            $normalizedFeatures[(string)$key] = (string)$value;
                        }
                    }
                    ksort($normalizedFeatures);
                }
                
                // Solo agregar si tiene features (no agregar variantes sin especificar)
                if (!empty($normalizedFeatures)) {
                    $normalizedKey = InventarioService::normalizeFeaturesForComparison($normalizedFeatures);
                    
                    // Agrupar por variante única (si ya existe, no duplicar)
                    if (! isset($variantsMap[$normalizedKey])) {
                        $variantsMap[$normalizedKey] = [
                            'features' => $normalizedFeatures,
                            'display_name' => $this->formatVariantDisplayName($normalizedFeatures),
                        ];
                    }
                }
            }
        }

        // NO generar combinaciones automáticas - solo mostrar las variantes que realmente existen
        // Si no hay variantes, el array estará vacío y se mostrará el mensaje correspondiente

        // Convertir a array indexado para mostrar en el modal
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
        if (empty($this->selectedVariantId)) {
            $this->addError('selectedVariantId', 'Debes seleccionar una variante.');
            return;
        }

        // Buscar la variante seleccionada
        $selectedVariant = null;
        foreach ($this->existingVariants as $variant) {
            $normalizedKey = InventarioService::normalizeFeaturesForComparison($variant['features']);
            if ($normalizedKey === $this->selectedVariantId) {
                $selectedVariant = $variant;
                break;
            }
        }

        if (! $selectedVariant) {
            $this->addError('selectedVariantId', 'La variante seleccionada no es válida.');
            return;
        }

        // Despachar evento con la variante seleccionada
        $this->dispatch('batch-variant-selected', 
            rowId: $this->rowId,
            productId: $this->productId,
            productName: $this->productName,
            variantFeatures: $selectedVariant['features']
        );

        $this->dispatch('close-modal', 'select-batch-variant');
    }

    public function render()
    {
        return view('livewire.select-batch-variant-modal');
    }
}
