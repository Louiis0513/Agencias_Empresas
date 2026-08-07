@php
    $images = $images ?? collect();
    $productId = (int) ($productId ?? 0);
@endphp

@if($images->isNotEmpty() && $productId > 0)
    <div class="{{ $wrapperClass ?? '' }}">
        @if(! empty($title))
            <p class="text-sm text-gray-400 mb-2">{{ $title }}</p>
        @endif
        <div class="flex flex-wrap gap-2">
            @foreach($images as $img)
                <button type="button"
                        onclick="Livewire.dispatch('open-manage-product-image', { productId: {{ $productId }}, imageId: {{ (int) $img->id }} })"
                        class="group relative h-16 w-16 rounded-lg overflow-hidden border border-white/10 hover:border-brand/50 focus:outline-none focus:ring-2 focus:ring-brand/40"
                        title="Ver imagen">
                    <img src="{{ asset('storage/'.$img->path) }}"
                         alt=""
                         class="h-full w-full object-cover transition group-hover:opacity-90">
                </button>
            @endforeach
        </div>
    </div>
@endif
