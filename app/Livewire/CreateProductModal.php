<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\MovimientoInventario;
use App\Models\Store;
use App\Services\ProductService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateProductModal extends Component
{
    public int $storeId;

    public bool $fromPurchase = false;

    /** RowId de la fila en compra cuando se abre desde el modal de selección */
    public string $compraRowId = '';

    public string $name = '';
    public string $barcode = '';
    public string $sku = '';
    public ?string $category_id = null;
    public string $price = '0';
    public string $cost = '0';
    public string $stock = '0';
    public string $location = '';
    /** Tipo de producto: simple, batch o serialized */
    public string $type = 'simple';
    public bool $is_active = true;

    /** @var array<int, string> Valores de atributos: [attribute_id => value] */
    public array $attribute_values = [];

    /** IDs de opciones de atributos permitidas para este producto (variantes: Talla S, M, L, etc.). */
    public array $attribute_option_ids = [];

    /** Variantes para productos tipo Lote: [['attribute_values' => [...], 'price' => '', 'cost' => '', 'stock_initial' => '', 'batch_number' => '', 'expiration_date' => ''], ...] */
    public array $variants = [];

    /** Unidades serializadas para productos tipo Serializado: [['serial_number' => '', 'attribute_values' => [...], 'price' => '', 'cost' => ''], ...] */
    public array $serializedItems = [];

    #[On('open-create-product-from-compra')]
    public function setCompraRowId(string $rowId = ''): void
    {
        if ($this->fromPurchase) {
            $this->compraRowId = $rowId;
        }
    }

    public function mount(int $storeId, bool $fromPurchase = false): void
    {
        $this->storeId = $storeId;
        $this->fromPurchase = $fromPurchase;
        if (empty($this->type)) {
            $this->type = 'simple';
        }
    }

    /** Opciones de tipo de producto para el select (valor => etiqueta). */
    public static function typeOptions(): array
    {
        return [
            'simple' => 'Simple',
            MovimientoInventario::PRODUCT_TYPE_BATCH => 'Lote (Necesita la identificación para rastreo)',
            MovimientoInventario::PRODUCT_TYPE_SERIALIZED => 'Serializado (Productos únicos)',
        ];
    }

    public function getTypeOptionsProperty(): array
    {
        return self::typeOptions();
    }

    protected function rules(): array
    {
        $store = $this->getStoreProperty();
        $categoryIds = $store ? $this->getCategoriesWithAttributesIds() : [];

        $rules = [
            'type' => ['required', 'string', Rule::in(['simple', MovimientoInventario::PRODUCT_TYPE_BATCH, MovimientoInventario::PRODUCT_TYPE_SERIALIZED])],
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'category_id' => [
                'required',
                Rule::in($categoryIds),
            ],
            'location' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ];

        if ($this->type === 'simple') {
            $rules['price'] = ['nullable', 'numeric', 'min:0'];
            $rules['cost'] = ['nullable', 'numeric', 'min:0'];
            $rules['stock'] = ['nullable', 'integer', 'min:0'];
        }

        if ($this->type === MovimientoInventario::PRODUCT_TYPE_BATCH) {
            $rules['variants'] = ['required', 'array', 'min:1'];
            $rules['variants.*.price'] = ['nullable', 'numeric', 'min:0'];
            $rules['variants.*.cost'] = ['nullable', 'numeric', 'min:0'];
            $rules['variants.*.stock_initial'] = ['nullable', 'integer', 'min:0'];
            $rules['variants.*.batch_number'] = ['nullable', 'string', 'max:255'];
            $rules['variants.*.expiration_date'] = ['nullable', 'date'];
        }

        if ($this->type === MovimientoInventario::PRODUCT_TYPE_SERIALIZED) {
            $rules['serializedItems'] = ['required', 'array', 'min:1'];
            $rules['serializedItems.*.serial_number'] = ['required', 'string', 'max:255'];
            $rules['serializedItems.*.price'] = ['nullable', 'numeric', 'min:0'];
            $rules['serializedItems.*.cost'] = ['nullable', 'numeric', 'min:0'];
        }

        return $rules;
    }

    protected function messages(): array
    {
        return [
            'category_id.required' => 'Debes seleccionar una categoría. Crea categorías y asígnales atributos antes de crear productos.',
            'variants.required' => 'Debes agregar al menos una variante para productos tipo Lote.',
            'variants.min' => 'Debes agregar al menos una variante para productos tipo Lote.',
        ];
    }

    public function getStoreProperty(): ?Store
    {
        $store = Store::find($this->storeId);

        return $store ? $store->load('categories') : null;
    }

    /** Categorías que tienen al menos un atributo asignado. */
    public function getCategoriesWithAttributesProperty()
    {
        $store = $this->getStoreProperty();
        if (! $store) {
            return collect();
        }

        return Category::where('store_id', $store->id)
            ->whereHas('attributes')
            ->with(['attributes' => fn ($q) => $q->with('options')])
            ->orderBy('name')
            ->get();
    }

    public function getCategoriesWithAttributesIds(): array
    {
        return $this->getCategoriesWithAttributesProperty()->pluck('id')->toArray();
    }

    /** Categoría seleccionada con sus atributos (para campos dinámicos). */
    public function getSelectedCategoryProperty(): ?Category
    {
        if (! $this->category_id) {
            return null;
        }

        return Category::where('id', $this->category_id)
            ->where('store_id', $this->getStoreProperty()?->id)
            ->with(['attributes' => fn ($q) => $q->with(['options', 'groups'])->orderByPivot('position')])
            ->first();
    }

    public function updatedCategoryId(): void
    {
        $this->attribute_values = [];
        $this->attribute_option_ids = [];
        $this->variants = [];
        $this->serializedItems = [];
        $cat = $this->getSelectedCategoryProperty();
        if ($cat) {
            foreach ($cat->attributes as $attr) {
                $this->attribute_values[$attr->id] = $attr->type === 'boolean' ? '0' : '';
            }
            
            // Si es tipo Lote, agregar automáticamente la primera variante
            if ($this->type === MovimientoInventario::PRODUCT_TYPE_BATCH) {
                $this->addVariant();
            }
            
            // Si es tipo Serializado, agregar automáticamente la primera unidad
            if ($this->type === MovimientoInventario::PRODUCT_TYPE_SERIALIZED) {
                $this->addSerializedItem();
            }
        }
        $this->resetValidation();
    }

    public function updatedType(): void
    {
        $this->variants = [];
        $this->serializedItems = [];
        $this->attribute_values = [];
        $this->attribute_option_ids = [];
        
        // Si cambia a tipo Lote y ya hay una categoría seleccionada, agregar automáticamente la primera variante
        if ($this->type === MovimientoInventario::PRODUCT_TYPE_BATCH && $this->category_id) {
            $cat = $this->getSelectedCategoryProperty();
            if ($cat && $cat->attributes->isNotEmpty()) {
                $this->addVariant();
            }
        }
        
        // Si cambia a tipo Serializado y ya hay una categoría seleccionada, agregar automáticamente la primera unidad
        if ($this->type === MovimientoInventario::PRODUCT_TYPE_SERIALIZED && $this->category_id) {
            $cat = $this->getSelectedCategoryProperty();
            if ($cat && $cat->attributes->isNotEmpty()) {
                $this->addSerializedItem();
            }
        }
        
        $this->resetValidation();
    }

    /**
     * Agregar una nueva variante para productos tipo Lote.
     */
    public function addVariant(): void
    {
        $category = $this->getSelectedCategoryProperty();
        if (! $category) {
            return;
        }

        $variant = [
            'attribute_values' => [],
            'price' => '',
            'cost' => '',
            'stock_initial' => '',
            'batch_number' => '',
            'expiration_date' => '',
        ];

        foreach ($category->attributes as $attr) {
            $variant['attribute_values'][$attr->id] = $attr->type === 'boolean' ? '0' : '';
        }

        $this->variants[] = $variant;
    }

    /**
     * Eliminar una variante por índice.
     */
    public function removeVariant(int $index): void
    {
        if (isset($this->variants[$index])) {
            unset($this->variants[$index]);
            $this->variants = array_values($this->variants);
        }
    }

    /**
     * Agregar una nueva unidad serializada.
     */
    public function addSerializedItem(): void
    {
        $category = $this->getSelectedCategoryProperty();
        if (! $category) {
            return;
        }

        $item = [
            'serial_number' => '',
            'attribute_values' => [],
            'price' => '',
            'cost' => '',
        ];

        foreach ($category->attributes as $attr) {
            $item['attribute_values'][$attr->id] = $attr->type === 'boolean' ? '0' : '';
        }

        $this->serializedItems[] = $item;
    }

    /**
     * Eliminar una unidad serializada por índice.
     */
    public function removeSerializedItem(int $index): void
    {
        if (isset($this->serializedItems[$index])) {
            unset($this->serializedItems[$index]);
            $this->serializedItems = array_values($this->serializedItems);
        }
    }

    /**
     * Manejar cambios en valores de atributos booleanos para asegurar que siempre sean strings.
     */
    public function updatedAttributeValues($value, $key): void
    {
        // Extraer el ID del atributo de la clave (formato: "attribute_values.7")
        $parts = explode('.', $key);
        if (count($parts) !== 2 || $parts[0] !== 'attribute_values') {
            return;
        }

        $attrId = (int) $parts[1];
        $this->normalizeBooleanAttribute($attrId);
    }

    /**
     * Normalizar todos los atributos booleanos antes de validar.
     * Asegura que cada atributo booleano de la categoría tenga '0' o '1'.
     */
    protected function normalizeBooleanAttributes(): void
    {
        $category = $this->getSelectedCategoryProperty();
        if (! $category) {
            return;
        }

        foreach ($category->attributes as $attr) {
            if ($attr->type === 'boolean') {
                $this->normalizeBooleanAttribute((int) $attr->id);
            }
        }
    }

    /**
     * Normalizar un atributo booleano específico.
     */
    protected function normalizeBooleanAttribute(int $attrId): void
    {
        $val = $this->attribute_values[$attrId] ?? null;
        
        // Convertir cualquier valor booleano a string '0' o '1'
        if ($val === true || $val === '1' || $val === 1 || $val === 'true') {
            $this->attribute_values[$attrId] = '1';
        } else {
            $this->attribute_values[$attrId] = '0';
        }
    }

    public function save(ProductService $service)
    {
        if ($this->type === 'simple') {
            $this->normalizeBooleanAttributes();
        }
        
        // Validar números de serie únicos para productos serializados
        if ($this->type === MovimientoInventario::PRODUCT_TYPE_SERIALIZED && ! empty($this->serializedItems)) {
            $serials = array_filter(array_map(fn($item) => trim($item['serial_number'] ?? ''), $this->serializedItems));
            $duplicates = array_diff_assoc($serials, array_unique($serials));
            if (! empty($duplicates)) {
                foreach ($duplicates as $index => $serial) {
                    $this->addError("serializedItems.{$index}.serial_number", "El número de serie «{$serial}» está duplicado.");
                }
            }
        }
        
        $this->validate();

        $store = $this->getStoreProperty();
        if (! $store || ! Auth::user()->stores->contains($store->id)) {
            abort(403, 'No tienes permiso para crear productos en esta tienda.');
        }

        $price = 0;
        $cost = 0;
        $stock = 0;
        $attributeValues = [];

        if ($this->type === 'simple') {
            $price = (float) ($this->price !== '' ? $this->price : 0);
            $cost = (float) ($this->cost !== '' ? $this->cost : 0);
            $stock = (int) ($this->stock !== '' ? $this->stock : 0);
            $attributeValues = $this->attribute_values;
        }

        try {
            $product = $service->createProduct($store, [
                'type' => $this->type,
                'name' => $this->name,
                'barcode' => $this->barcode ?: null,
                'sku' => $this->sku ?: null,
                'category_id' => $this->category_id,
                'price' => $price,
                'cost' => $cost,
                'stock' => $stock,
                'location' => $this->location ?: null,
                'is_active' => $this->is_active,
                'attribute_values' => $attributeValues,
            ]);
        } catch (\Exception $e) {
            $this->addError('category_id', $e->getMessage());

            return;
        }

        if ($product->isBatch()) {
            // Guardar opciones permitidas de variantes (si se seleccionaron)
            if (! empty($this->attribute_option_ids)) {
                $categoryAttributeIds = $product->category->attributes->pluck('id')->toArray();
                $validIds = \App\Models\AttributeOption::whereIn('id', $this->attribute_option_ids)
                    ->whereIn('attribute_id', $categoryAttributeIds)
                    ->pluck('id')
                    ->toArray();
                $product->allowedVariantOptions()->sync($validIds);
            }

            // Si hay variantes con stock inicial, crear el lote y batch_items
            if (! empty($this->variants)) {
                $this->createBatchFromVariants($product, $store);
            }
        }

        if ($product->isSerialized() && ! empty($this->serializedItems)) {
            $this->createSerializedItems($product, $store);
        }

        $compraRowId = $this->compraRowId;

        $this->reset([
            'name', 'barcode', 'sku', 'category_id', 'location',
            'type', 'is_active', 'attribute_values', 'attribute_option_ids', 'compraRowId',
            'price', 'cost', 'stock', 'variants', 'serializedItems',
        ]);
        $this->price = '0';
        $this->cost = '0';
        $this->stock = '0';
        $this->variants = [];
        $this->serializedItems = [];
        $this->resetValidation();

        if ($this->fromPurchase) {
            $this->dispatch('item-selected', rowId: $compraRowId, id: $product->id, name: $product->name, type: 'INVENTARIO', productType: $product->type);
            $this->dispatch('close-modal', 'create-product-from-compra');
            $this->dispatch('close-modal', 'select-item-compra');

            return;
        }

        return redirect()->route('stores.products', $store);
    }

    /**
     * Crear ProductItems desde las unidades serializadas.
     */
    protected function createSerializedItems(\App\Models\Product $product, Store $store): void
    {
        $reference = 'INI-' . date('Y');
        $totalStock = 0;
        $totalCost = 0;

        foreach ($this->serializedItems as $item) {
            $serial = trim($item['serial_number'] ?? '');
            if (empty($serial)) {
                continue;
            }

            $features = [];
            foreach ($item['attribute_values'] ?? [] as $attrId => $value) {
                if ($value !== '' && $value !== null && $value !== '0') {
                    $features[$attrId] = $value;
                }
            }

            $productItemData = [
                'store_id' => $store->id,
                'product_id' => $product->id,
                'serial_number' => $serial,
                'cost' => (float) ($item['cost'] ?? 0),
                'status' => \App\Models\ProductItem::STATUS_AVAILABLE,
                'batch' => $reference,
                'features' => ! empty($features) ? $features : null,
            ];
            
            if (! empty($item['price'])) {
                $productItemData['price'] = (float) $item['price'];
            }
            
            \App\Models\ProductItem::create($productItemData);

            $totalStock++;
            $totalCost += (float) ($item['cost'] ?? 0);
        }

        // Actualizar stock del producto
        if ($totalStock > 0) {
            $product->increment('stock', $totalStock);
            
            // Actualizar costo ponderado
            $items = \App\Models\ProductItem::where('product_id', $product->id)
                ->where('store_id', $product->store_id)
                ->where('status', \App\Models\ProductItem::STATUS_AVAILABLE)
                ->get();
            $totalCostAll = $items->sum('cost');
            $qty = $items->count();
            $product->cost = $qty > 0 ? (float) round($totalCostAll / $qty, 2) : 0.0;
            $product->save();
        }
    }

    /**
     * Crear batch y batch_items desde las variantes con stock inicial.
     */
    protected function createBatchFromVariants(\App\Models\Product $product, Store $store): void
    {
        $variantsWithStock = array_filter($this->variants, fn ($v) => ! empty($v['stock_initial']) && (int) $v['stock_initial'] > 0);
        
        if (empty($variantsWithStock)) {
            return;
        }

        // Agrupar variantes por número de lote (si tienen el mismo batch_number, van al mismo batch)
        $batchesByNumber = [];
        foreach ($variantsWithStock as $variant) {
            $batchNumber = $variant['batch_number'] ?? 'L-' . date('Ymd');
            if (! isset($batchesByNumber[$batchNumber])) {
                $batchesByNumber[$batchNumber] = [
                    'batch_number' => $batchNumber,
                    'expiration_date' => null,
                    'items' => [],
                ];
            }
            // Tomar la fecha de vencimiento de la primera variante con fecha
            if (empty($batchesByNumber[$batchNumber]['expiration_date']) && ! empty($variant['expiration_date'])) {
                $batchesByNumber[$batchNumber]['expiration_date'] = $variant['expiration_date'];
            }
            $batchesByNumber[$batchNumber]['items'][] = $variant;
        }

        foreach ($batchesByNumber as $batchData) {
            $batch = \App\Models\Batch::create([
                'store_id' => $store->id,
                'product_id' => $product->id,
                'reference' => $batchData['batch_number'],
                'expiration_date' => $batchData['expiration_date'] ?: null,
            ]);

            foreach ($batchData['items'] as $variant) {
                $features = [];
                foreach ($variant['attribute_values'] as $attrId => $value) {
                    if ($value !== '' && $value !== null && $value !== '0') {
                        $features[$attrId] = $value;
                    }
                }

                \App\Models\BatchItem::create([
                    'batch_id' => $batch->id,
                    'quantity' => (int) ($variant['stock_initial'] ?? 0),
                    'unit_cost' => (float) ($variant['cost'] ?? 0),
                    'price' => ! empty($variant['price']) ? (float) $variant['price'] : null,
                    'features' => ! empty($features) ? $features : null,
                ]);
            }

            // Actualizar stock del producto
            $totalStock = $batch->batchItems()->sum('quantity');
            $product->increment('stock', $totalStock);
        }
    }

    public function render()
    {
        // Asegurar que la propiedad se evalúe al renderizar
        $this->categoriesWithAttributes;
        return view('livewire.create-product-modal');
    }
}
