<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Store;
use App\Services\ProductService;
use App\Services\StorePermissionService;
use Exception;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class ManageProductImageModal extends Component
{
    use WithFileUploads;

    public int $storeId;

    public ?int $productId = null;

    public ?int $imageId = null;

    public string $productNombre = '';

    public string $imageUrl = '';

    public bool $canEdit = false;

    /** preview | replace */
    public string $mode = 'preview';

    /** @var mixed */
    public $photo = null;

    public function mount(int $storeId): void
    {
        $this->storeId = $storeId;
    }

    #[On('open-manage-product-image')]
    public function open(int $productId, int $imageId): void
    {
        $store = Store::find($this->storeId);
        if (! $store) {
            return;
        }

        app(StorePermissionService::class)->authorize($store, 'products.view');

        $product = Product::query()->deStore($store)->findOrFail($productId);
        $image = ProductImage::query()
            ->where('product_id', $product->id)
            ->whereKey($imageId)
            ->firstOrFail();

        $this->reset(['photo']);
        $this->resetValidation();
        $this->mode = 'preview';
        $this->productId = $product->id;
        $this->imageId = $image->id;
        $this->productNombre = $product->nombre;
        $this->imageUrl = asset('storage/'.$image->path).'?t='.time();
        $this->canEdit = app(StorePermissionService::class)->can($store, 'products.edit');

        $this->js('window.dispatchEvent(new CustomEvent("open-modal", { detail: "manage-product-image" }))');
    }

    public function startReplace(): void
    {
        $store = Store::find($this->storeId);
        if (! $store) {
            return;
        }
        app(StorePermissionService::class)->authorize($store, 'products.edit');

        $this->reset(['photo']);
        $this->resetValidation();
        $this->mode = 'replace';
        $this->js('window.dispatchEvent(new CustomEvent("manage-product-image-replace"))');
    }

    public function cancelReplace(): void
    {
        $this->reset(['photo']);
        $this->resetValidation();
        $this->mode = 'preview';
    }

    public function deleteImage(ProductService $productService, StorePermissionService $permissionService)
    {
        $store = Store::find($this->storeId);
        if (! $store || ! $this->productId || ! $this->imageId) {
            return;
        }

        $permissionService->authorize($store, 'products.edit');

        $product = Product::query()->deStore($store)->findOrFail($this->productId);
        $image = ProductImage::query()
            ->where('product_id', $product->id)
            ->whereKey($this->imageId)
            ->firstOrFail();

        try {
            $productService->eliminarImagen($store, $product, $image);
        } catch (Exception $e) {
            $this->addError('photo', $e->getMessage());

            return;
        }

        $this->closeAndReload('Imagen eliminada.');
    }

    public function saveReplace(ProductService $productService, StorePermissionService $permissionService)
    {
        $store = Store::find($this->storeId);
        if (! $store || ! $this->productId || ! $this->imageId) {
            return;
        }

        $permissionService->authorize($store, 'products.edit');

        $this->validate([
            'photo' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png',
                'max:'.Product::MAX_IMAGEN_KB,
            ],
        ], [
            'photo.required' => 'Selecciona o recorta una foto antes de guardar.',
            'photo.max' => 'Cada imagen debe pesar máximo 1 MB.',
            'photo.mimes' => 'Las imágenes deben ser PNG o JPG.',
        ]);

        $product = Product::query()->deStore($store)->findOrFail($this->productId);
        $image = ProductImage::query()
            ->where('product_id', $product->id)
            ->whereKey($this->imageId)
            ->firstOrFail();

        try {
            $productService->reemplazarImagen($store, $product, $image, $this->photo);
        } catch (Exception $e) {
            $this->addError('photo', $e->getMessage());

            return;
        }

        $this->closeAndReload('Imagen reemplazada.');
    }

    private function closeAndReload(string $message): void
    {
        $this->reset(['photo', 'productId', 'imageId', 'productNombre', 'imageUrl', 'mode']);
        $this->mode = 'preview';
        $this->js('window.dispatchEvent(new CustomEvent("close-modal", { detail: "manage-product-image" }))');
        session()->flash('success', $message);
        $this->js('window.location.reload()');
    }

    public function render()
    {
        return view('livewire.manage-product-image-modal');
    }
}
