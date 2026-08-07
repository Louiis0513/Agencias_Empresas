@props([
    'type' => 'success',
    'timeout' => 5000,
])

@php
    $typeClasses = match ($type) {
        'error' => 'border-red-500/30 bg-red-950/30 text-red-200',
        default => 'border-emerald-500/30 bg-emerald-950/30 text-emerald-200',
    };
@endphp

<div
    x-data="{ show: true }"
    x-init="setTimeout(() => show = false, {{ (int) $timeout }})"
    x-show="show"
    x-transition.opacity.duration.300ms
    {{ $attributes->merge(['class' => 'rounded-xl border px-4 py-3 '.$typeClasses, 'role' => 'alert']) }}
>
    {{ $slot }}
</div>
