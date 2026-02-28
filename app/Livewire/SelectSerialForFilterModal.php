<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductItem;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class SelectSerialForFilterModal extends Component
{
    public int $storeId;

    public ?int $productId = null;

    public string $productName = '';

    public string $search = '';

    public int $page = 1;

    public int $perPage = 15;

    public array $units = [];

    public int $totalUnits = 0;

    public ?int $selectedProductItemId = null;

    public function mount(int $storeId): void
    {
        $this->storeId = $storeId;
    }

    #[On('open-select-serial-for-filter')]
    public function openForProduct($productIdOrPayload = null, string $productName = ''): void
    {
        if (is_array($productIdOrPayload)) {
            $productId = (int) ($productIdOrPayload['productId'] ?? $productIdOrPayload['product_id'] ?? 0);
            $productName = (string) ($productIdOrPayload['productName'] ?? $productIdOrPayload['productName'] ?? 'Producto');
        } else {
            $productId = (int) $productIdOrPayload;
            $productName = $productName ?: 'Producto';
        }
        $this->productId = $productId;
        $this->productName = $productName;
        $this->search = '';
        $this->page = 1;
        $this->selectedProductItemId = null;
        $this->loadUnits();
        $this->dispatch('open-modal', 'select-serial-for-filter');
    }

    protected function loadUnits(): void
    {
        $store = Store::find($this->storeId);
        if (! $store || ! $this->productId || ! Auth::user()->stores->contains($store->id)) {
            $this->units = [];
            $this->totalUnits = 0;
            return;
        }

        $product = Product::where('id', $this->productId)
            ->where('store_id', $store->id)
            ->first();

        if (! $product || ! $product->isSerialized()) {
            $this->units = [];
            $this->totalUnits = 0;
            return;
        }

        $query = ProductItem::where('store_id', $store->id)
            ->where('product_id', $this->productId);

        $search = trim($this->search);
        if ($search !== '') {
            $query->where('serial_number', 'like', '%' . $search . '%');
        }

        $this->totalUnits = $query->count();
        $this->units = $query->orderBy('serial_number')
            ->offset(($this->page - 1) * $this->perPage)
            ->limit($this->perPage)
            ->get()
            ->map(fn (ProductItem $item) => [
                'id' => $item->id,
                'serial_number' => $item->serial_number,
                'features' => $item->features,
            ])
            ->values()
            ->toArray();
    }

    public function goToPage(int $page): void
    {
        $maxPage = (int) max(1, ceil($this->totalUnits / $this->perPage));
        $this->page = max(1, min($page, $maxPage));
        $this->loadUnits();
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->loadUnits();
    }

    public function selectUnit(int $productItemId, string $serialNumber): void
    {
        $this->selectedProductItemId = $productItemId;
        $this->dispatch('filter-serial-selected', [
            'productId' => $this->productId,
            'productItemId' => $productItemId,
            'serialNumber' => $serialNumber,
            'productName' => $this->productName,
        ]);
        $this->dispatch('close-modal', 'select-serial-for-filter');
    }

    public function close(): void
    {
        $this->productId = null;
        $this->productName = '';
        $this->dispatch('close-modal', 'select-serial-for-filter');
    }

    public function render()
    {
        return view('livewire.select-serial-for-filter-modal');
    }
}
