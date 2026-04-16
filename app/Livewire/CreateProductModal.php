<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\MovimientoInventario;
use App\Models\Product;
use App\Models\Store;
use App\Support\Quantity;
use App\Services\ConvertidorImgService;
use App\Services\ProductService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class CreateProductModal extends Component
{
    use WithFileUploads;

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
    public string $quantity_mode = Product::QUANTITY_MODE_UNIT;
    public string $quantity_step = '1.00';
    public string $location = '';
    /** Tipo de producto: simple, batch o serialized */
    public string $type = 'simple';
    public bool $is_active = true;
    /** Para productos simples y serializados: indica si tiene stock inicial */
    public bool $has_initial_stock = false;

    /** @var array<int, string> Valores de atributos: [attribute_id => value] */
    public array $attribute_values = [];

    /** Variantes para productos tipo Lote: [['attribute_values' => [...], 'price' => '', 'cost' => '', 'stock_initial' => '', 'batch_number' => '', 'expiration_date' => ''], ...] */
    public array $variants = [];

    /** Unidades serializadas para productos tipo Serializado: [['serial_number' => '', 'attribute_values' => [...], 'price' => '', 'cost' => ''], ...] */
    public array $serializedItems = [];

    /** Imagen del producto simple (opcional). */
    public $image = null;

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
            'quantity_mode' => Quantity::quantityModeRules(),
            'quantity_step' => Quantity::stepRulesForMode($this->quantity_mode),
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
            $rules['stock'] = $this->quantity_mode === Product::QUANTITY_MODE_DECIMAL
                ? ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/']
                : ['nullable', 'integer', 'min:0'];
            $rules['image'] = ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'];
        }

        if ($this->type === MovimientoInventario::PRODUCT_TYPE_BATCH) {
            $rules['variants'] = ['required', 'array', 'min:1'];
            $rules['variants.*.sku'] = ['nullable', 'string', 'max:255'];
            $rules['variants.*.barcode'] = ['nullable', 'string', 'max:255'];
            $rules['variants.*.price'] = ['nullable', 'numeric', 'min:0'];
            $rules['variants.*.cost'] = ['nullable', 'numeric', 'min:0'];
            $rules['variants.*.stock_initial'] = $this->quantity_mode === Product::QUANTITY_MODE_DECIMAL
                ? ['nullable', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/']
                : ['nullable', 'integer', 'min:0'];
            $rules['variants.*.batch_number'] = ['nullable', 'string', 'max:255'];
            $rules['variants.*.expiration_date'] = ['nullable', 'date'];
        }

        if ($this->type === MovimientoInventario::PRODUCT_TYPE_SERIALIZED) {
            // Sin stock inicial: permitir array vacío (required falla con [] en Laravel).
            // Con stock inicial: obligatorio y al menos un ítem.
            $rules['serializedItems'] = $this->has_initial_stock
                ? ['required', 'array', 'min:1']
                : ['array'];
            if ($this->has_initial_stock) {
                $rules['serializedItems.*.serial_number'] = ['required', 'string', 'max:255'];
                $rules['serializedItems.*.price'] = ['nullable', 'numeric', 'min:0'];
                $rules['serializedItems.*.cost'] = ['nullable', 'numeric', 'min:0'];
            }
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
            ->with(['attributes'])
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
            ->with(['attributes' => fn ($q) => $q->with(['groups'])->orderByPivot('position')])
            ->first();
    }

    public function updatedCategoryId(): void
    {
        $this->attribute_values = [];
        $this->variants = [];
        $this->serializedItems = [];
        $cat = $this->getSelectedCategoryProperty();
        if ($cat) {
            foreach ($cat->attributes as $attr) {
                $this->attribute_values[$attr->id] = '';
            }
            
            // Si es tipo Lote, agregar automáticamente la primera variante
            if ($this->type === MovimientoInventario::PRODUCT_TYPE_BATCH) {
                $this->addVariant();
            }
            
            // Serializado: no se agrega unidad automáticamente; el usuario marca "Tiene stock inicial" y añade unidades si quiere
        }
        $this->resetValidation();
    }

    public function updatedType(): void
    {
        $this->variants = [];
        $this->serializedItems = [];
        $this->attribute_values = [];

        // En producto por lote, SKU/Barcode son por variante; no enviar valores a nivel producto
        if ($this->type === MovimientoInventario::PRODUCT_TYPE_BATCH) {
            $this->sku = '';
            $this->barcode = '';
        }

        // Si cambia a tipo Lote y ya hay una categoría seleccionada, agregar automáticamente la primera variante
        if ($this->type === MovimientoInventario::PRODUCT_TYPE_BATCH && $this->category_id) {
            $cat = $this->getSelectedCategoryProperty();
            if ($cat && $cat->attributes->isNotEmpty()) {
                $this->addVariant();
            }
        }
        
        // Serializado: no se agrega unidad automáticamente; el usuario marca "Tiene stock inicial" y añade unidades si quiere
        if ($this->type === MovimientoInventario::PRODUCT_TYPE_SERIALIZED) {
            $this->has_initial_stock = false;
            $this->quantity_mode = Product::QUANTITY_MODE_UNIT;
            $this->quantity_step = '1.00';
        }
        
        $this->resetValidation();
    }

    public function updatedQuantityMode($value): void
    {
        if ($this->type === MovimientoInventario::PRODUCT_TYPE_SERIALIZED) {
            $this->quantity_mode = Product::QUANTITY_MODE_UNIT;
            $this->quantity_step = '1.00';
        } else {
            $this->quantity_step = $value === Product::QUANTITY_MODE_DECIMAL ? '0.01' : '1.00';
        }
    }

    /**
     * Si en serializado se desmarca "Tiene stock inicial", vaciar la lista de unidades para no enviar datos residuales.
     */
    public function updatedHasInitialStock($value): void
    {
        if ($this->type === MovimientoInventario::PRODUCT_TYPE_SERIALIZED && ! $value) {
            $this->serializedItems = [];
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
            'has_stock' => false,
            'sku' => '',
            'barcode' => '',
        ];

        foreach ($category->attributes as $attr) {
            $variant['attribute_values'][$attr->id] = '';
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
            $item['attribute_values'][$attr->id] = '';
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

    public function save(ProductService $service, ConvertidorImgService $convertidorImgService)
    {
        // Serializado sin stock inicial: asegurar que no enviamos datos residuales y que el servicio recibe un array
        if ($this->type === MovimientoInventario::PRODUCT_TYPE_SERIALIZED && ! $this->has_initial_stock) {
            $this->serializedItems = [];
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

        $currency = $store->currency ?? 'COP';
        $price = 0;
        $cost = 0;
        $stock = 0.0;
        $attributeValues = [];

        if ($this->type === 'simple') {
            $price = parse_money($this->price !== '' ? $this->price : 0, $currency);
            $cost = parse_money($this->cost !== '' ? $this->cost : 0, $currency);
            $stock = Quantity::normalize($this->stock !== '' ? $this->stock : 0);
            $attributeValues = $this->attribute_values;
        }

        try {
            $productData = [
                'type' => $this->type,
                'name' => $this->name,
                'barcode' => $this->barcode ?: null,
                'sku' => $this->sku ?: null,
                'category_id' => $this->category_id,
                'price' => $price,
                'cost' => $cost,
                'stock' => $stock,
                'quantity_mode' => $this->quantity_mode,
                'quantity_step' => Quantity::normalize($this->quantity_step),
                'location' => $this->location ?: null,
                'is_active' => $this->is_active,
                'attribute_values' => $attributeValues,
            ];

            // Para productos simples: indicar si tiene stock inicial
            if ($this->type === 'simple') {
                $productData['has_initial_stock'] = $this->has_initial_stock && $stock > 0;
            }

            // Para productos tipo batch: añadir variantes (parsear y redondear precios/costos según moneda)
            if ($this->type === MovimientoInventario::PRODUCT_TYPE_BATCH) {
                $productData['variants'] = array_map(function ($v) use ($currency) {
                    $v['price'] = isset($v['price']) && $v['price'] !== '' ? parse_money($v['price'], $currency) : 0;
                    $v['cost'] = isset($v['cost']) && $v['cost'] !== '' ? parse_money($v['cost'], $currency) : 0;
                    if (isset($v['stock_initial']) && $v['stock_initial'] !== '') {
                        $v['stock_initial'] = Quantity::normalize($v['stock_initial']);
                    }

                    return $v;
                }, $this->variants);
            }

            // Para productos tipo serialized: parsear y redondear precios/costos según moneda
            if ($this->type === MovimientoInventario::PRODUCT_TYPE_SERIALIZED) {
                $productData['serializedItems'] = array_map(function ($item) use ($currency) {
                    $item['price'] = isset($item['price']) && $item['price'] !== '' && $item['price'] !== null
                        ? parse_money($item['price'], $currency)
                        : null;
                    $item['cost'] = isset($item['cost']) && $item['cost'] !== '' ? parse_money($item['cost'], $currency) : 0;

                    return $item;
                }, is_array($this->serializedItems) ? $this->serializedItems : []);
            }

            $userId = Auth::id();
            $product = $service->createProduct($store, $productData, $userId);

            // Imagen solo para producto simple (no batch ni serializado)
            if ($this->type === 'simple' && $this->image) {
                $basePath = 'products/' . $store->id . '/' . $product->id;
                $path = $this->image->store($basePath, 'public');

                try {
                    $path = $convertidorImgService->convertPublicImageToWebp($path);
                    $product->image_path = $path;
                    $product->save();
                } catch (\Throwable $e) {
                    // No cancelar la creación del producto; solo registrar el error
                    \Illuminate\Support\Facades\Log::error('Error al convertir imagen de producto simple a WebP', [
                        'store_id' => $store->id,
                        'product_id' => $product->id,
                        'path' => $path ?? null,
                        'exception' => $e->getMessage(),
                    ]);

                    session()->flash('error', 'El producto se creó, pero hubo un problema al procesar la imagen. Intenta nuevamente más tarde.');
                }
            }
        } catch (\Exception $e) {
            $field = $this->type === MovimientoInventario::PRODUCT_TYPE_BATCH ? 'variants' : 'category_id';
            $this->addError($field, $e->getMessage());

            return;
        }

        $compraRowId = $this->compraRowId;

        $this->reset([
            'name', 'barcode', 'sku', 'category_id', 'location',
            'type', 'is_active', 'attribute_values', 'compraRowId',
            'price', 'cost', 'stock', 'variants', 'serializedItems', 'has_initial_stock',
            'quantity_mode', 'quantity_step',
            'image',
        ]);
        $this->price = '0';
        $this->cost = '0';
        $this->stock = '0';
        $this->variants = [];
        $this->serializedItems = [];
        $this->has_initial_stock = false;
        $this->quantity_mode = Product::QUANTITY_MODE_UNIT;
        $this->quantity_step = '1.00';
        $this->resetValidation();

        if ($this->fromPurchase) {
            $this->dispatch('item-selected', rowId: $compraRowId, id: $product->id, name: $product->name, type: 'INVENTARIO', productType: $product->type);
            $this->dispatch('close-modal', 'create-product-from-compra');
            $this->dispatch('close-modal', 'select-item-compra');

            return;
        }

        session()->flash('success', __('Producto creado correctamente.'));
        return redirect()->route('stores.products', ['store' => $store]);
    }


    public function render()
    {
        // Asegurar que la propiedad se evalúe al renderizar
        $this->categoriesWithAttributes;
        return view('livewire.create-product-modal');
    }
}
