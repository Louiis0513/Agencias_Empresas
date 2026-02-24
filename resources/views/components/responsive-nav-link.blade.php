@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-brand text-start text-base font-medium text-brand bg-brand/10 focus:outline-none focus:text-brand focus:bg-brand/20 focus:border-brand transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-gray-400 hover:text-gray-100 hover:bg-white/5 hover:border-white/20 focus:outline-none focus:text-gray-100 focus:bg-white/5 focus:border-white/20 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
