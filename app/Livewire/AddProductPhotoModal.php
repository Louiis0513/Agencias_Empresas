<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\Store;
use App\Services\ProductService;
use App\Services\StorePermissionService;
use Exception;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class AddProductPhotoModal extends Component
{
    use WithFileUploads;

    public int $storeId;

    public ?int $productId = null;

    public string $productNombre = '';

    public int $imagenesCount = 0;

    /** @var mixed */
    public $photo = null;

    public function mount(int $storeId): void
    {
        $this->storeId = $storeId;
    }

    #[On('open-add-product-photo')]
    public function open(int $productId): void
    {
        $store = Store::find($this->storeId);
        if (! $store) {
            return;
        }

        app(StorePermissionService::class)->authorize($store, 'products.edit');

        $product = Product::query()
            ->deStore($store)
            ->withCount('images')
            ->findOrFail($productId);

        if ($product->images_count >= Product::MAX_IMAGENES) {
            $this->js('alert('.json_encode('Ya alcanzaste el máximo de '.Product::MAX_IMAGENES.' imágenes.').')');

            return;
        }

        $this->reset(['photo']);
        $this->resetValidation();
        $this->productId = $product->id;
        $this->productNombre = $product->nombre;
        $this->imagenesCount = (int) $product->images_count;

        // Abrir con detail en string (compatible con Alpine) y también por nombre.
        $this->dispatch('open-modal', name: 'add-product-photo');
        $this->js('window.dispatchEvent(new CustomEvent("open-modal", { detail: "add-product-photo" }))');
    }

    public function save(ProductService $productService, StorePermissionService $permissionService)
    {
        $store = Store::find($this->storeId);
        if (! $store || ! $this->productId) {
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
            'photo.image' => 'El archivo debe ser una imagen.',
            'photo.mimes' => 'Las imágenes deben ser PNG o JPG.',
            'photo.max' => 'Cada imagen debe pesar máximo 1 MB.',
        ]);

        $product = Product::query()->deStore($store)->findOrFail($this->productId);

        try {
            $productService->agregarImagen($store, $product, $this->photo);
        } catch (Exception $e) {
            $this->addError('photo', $e->getMessage());

            return;
        }

        $this->reset(['photo', 'productId', 'productNombre', 'imagenesCount']);
        $this->dispatch('close-modal', name: 'add-product-photo');
        $this->js('window.dispatchEvent(new CustomEvent("close-modal", { detail: "add-product-photo" }))');
        session()->flash('success', 'Foto agregada correctamente.');

        $this->js('window.location.reload()');
    }

    public function render()
    {
        return view('livewire.add-product-photo-modal');
    }
}
