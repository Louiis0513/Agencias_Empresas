<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductItem;
use App\Models\Store;
use App\Services\ConvertidorImgService;
use App\Services\ProductService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditProductItemModal extends Component
{
    use WithFileUploads;

    public int $storeId;
    public int $productId;

    public ?int $productItemId = null;
    public string $serial_number = '';
    public string $price = '';
    public string $margin = '';
    public string $status = 'AVAILABLE';
    public $image = null;
    public bool $remove_image = false;
    public ?string $current_image_path = null;
    public bool $in_showcase = false;
    /** Array asociativo: [attribute_id => value] */
    public array $attributeValues = [];

    protected function rules(): array
    {
        $store = $this->getStoreProperty();
        $productItem = $this->productItemId ? ProductItem::find($this->productItemId) : null;

        return [
            'serial_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('product_items', 'serial_number')
                    ->where('store_id', $store?->id)
                    ->where('product_id', $this->productId)
                    ->ignore($this->productItemId),
            ],
            'price' => ['nullable', 'numeric', 'min:0'],
            'margin' => ['nullable', 'numeric'],
            'status' => ['required', 'string', 'in:AVAILABLE,SOLD,RESERVED,DEFECTIVE'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'remove_image' => ['boolean'],
            'attributeValues.*' => ['nullable', 'string'],
            'in_showcase' => ['boolean'],
        ];
    }

    public function getStoreProperty(): ?Store
    {
        return Store::find($this->storeId);
    }

    public function getProductProperty(): ?Product
    {
        return Product::where('id', $this->productId)
            ->where('store_id', $this->storeId)
            ->with(['category.attributes'])
            ->first();
    }

    public function loadProductItem($id = null): void
    {
        if ($id === null) {
            return;
        }
        if (is_array($id) && isset($id['id'])) {
            $id = $id['id'];
        } elseif (is_object($id) && isset($id->id)) {
            $id = $id->id;
        }

        $this->productItemId = (int) $id;
        $store = $this->getStoreProperty();
        if (! $store) {
            return;
        }

        $item = ProductItem::where('id', $this->productItemId)
            ->where('store_id', $store->id)
            ->where('product_id', $this->productId)
            ->first();

        if (! $item) {
            return;
        }

        $this->serial_number = $item->serial_number;
        $this->price = $item->price !== null ? (string) $item->price : '';
        $this->margin = '';
        $this->status = $item->status ?? ProductItem::STATUS_AVAILABLE;
        $this->current_image_path = $item->image_path;
        $this->image = null;
        $this->remove_image = false;
        $this->in_showcase = (bool) $item->in_showcase;
        
        // Cargar valores de atributos desde features del item
        $this->attributeValues = [];
        $product = $this->getProductProperty();
        if ($product && $product->category && $product->category->attributes) {
            foreach ($product->category->attributes as $attribute) {
                $attrId = (string) $attribute->id;
                // Buscar el valor en features del item (puede estar como string o int)
                $value = null;
                if (!empty($item->features) && is_array($item->features)) {
                    $value = $item->features[$attrId] ?? $item->features[$attribute->id] ?? null;
                }
                $this->attributeValues[$attrId] = $value !== null ? (string) $value : '';
            }
        }

        $this->resetValidation();
        $this->dispatch('open-modal', 'edit-product-item');
    }

    public function update(ProductService $productService): void
    {
        $this->validate();
        if ($this->price !== '' && $this->margin !== '') {
            $this->addError('margin', 'Ingresa precio o margen, no ambos.');
            return;
        }
        if ($this->price === '' && $this->margin === '') {
            $this->addError('price', 'Ingresa precio o margen.');
            return;
        }

        $store = $this->getStoreProperty();
        if (! $store || $this->productItemId === null) {
            return;
        }

        $item = ProductItem::where('id', $this->productItemId)
            ->where('store_id', $store->id)
            ->where('product_id', $this->productId)
            ->firstOrFail();

        // Convertir attributeValues a formato features (usando ID numérico como clave)
        $features = [];
        foreach ($this->attributeValues as $attrId => $value) {
            $value = trim($value);
            if ($value !== '') {
                $features[(int) $attrId] = $value;
            }
        }

        $currency = $store->currency ?? 'COP';
        $priceValue = $this->price !== '' ? parse_money($this->price, $currency) : null;
        $marginValue = $this->margin !== '' ? (float) $this->margin : null;
        try {
            $resolvedPricing = $productService->resolvePriceAndMargin(
                (float) $item->cost,
                $priceValue,
                $marginValue,
                $currency
            );
        } catch (\Exception $e) {
            $this->addError('margin', $e->getMessage());
            return;
        }

        $data = [
            'serial_number' => trim($this->serial_number),
            'price' => $resolvedPricing['price'],
            'margin' => $resolvedPricing['margin'],
            'status' => $this->status,
            'features' => ! empty($features) ? $features : null,
        ];
        $data['in_showcase'] = $this->in_showcase;

        if ($this->remove_image) {
            if ($item->image_path) {
                Storage::disk('public')->delete($item->image_path);
            }
            $data['image_path'] = null;
        }

        if ($this->image) {
            if ($item->image_path && ! $this->remove_image) {
                Storage::disk('public')->delete($item->image_path);
            }

            $relativeDir = "products/{$store->id}/{$this->productId}/items/{$item->id}";
            $uploadedFile = $this->image;
            $originalRelativePath = $uploadedFile->store($relativeDir, 'public');

            try {
                /** @var ConvertidorImgService $convertidorImg */
                $convertidorImg = app(ConvertidorImgService::class);
                $webpPath = $convertidorImg->convertPublicImageToWebp($originalRelativePath);
                $data['image_path'] = $webpPath;
            } catch (\Throwable $e) {
                Log::error('Error al convertir imagen de unidad serializada a WebP', [
                    'store_id' => $store->id,
                    'product_id' => $this->productId,
                    'product_item_id' => $item->id,
                    'exception' => $e,
                ]);

                Storage::disk('public')->delete($originalRelativePath);

                session()->flash('error', 'La imagen se subió pero ocurrió un error al convertirla a WebP. Inténtalo de nuevo más tarde.');
            }
        }

        $item->update($data);

        $this->dispatch('close-modal', 'edit-product-item');
        $this->redirect(request()->header('Referer') ?: route('stores.products.show', [$store, $this->productId]), navigate: true);
    }

    public function render()
    {
        $product = $this->getProductProperty();
        $attributes = $product && $product->category ? $product->category->attributes : collect();
        
        return view('livewire.edit-product-item-modal', [
            'attributes' => $attributes,
        ]);
    }
}
