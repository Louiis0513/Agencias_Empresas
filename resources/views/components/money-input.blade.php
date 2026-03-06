@props([
    'currency' => 'COP',
    'value' => null,
    'wireModel' => null,
    'name' => null,
])

@php
    $wireModelAttr = $attributes->get('wire:model');
    $wireModel = $wireModel ?? $wireModelAttr;
    $isLivewire = $wireModel !== null;
    $initialFormatted = $value !== null && $value !== '' ? app(\App\Services\CurrencyFormatService::class)->format((float) $value, $currency) : '';
@endphp

<div
    x-data="moneyInput(
        @js($currency),
        @js($value),
        @js($wireModel)
    )"
    x-init="init()"
    class="block"
>
    <input
        type="text"
        inputmode="decimal"
        autocomplete="off"
        @if($name && !$isLivewire) name="{{ $name }}" @endif
        x-model="displayValue"
        @input="onInput($event)"
        @blur="onBlur()"
        placeholder="{{ in_array(strtoupper($currency ?? 'COP'), ['COP', 'CLP']) ? '16.000' : '1,234.56' }}"
        {{ $attributes->except(['wire:model', 'value'])->merge(['class' => 'border-white/10 bg-white/5 text-gray-100 rounded-lg shadow-sm focus:border-brand focus:ring-brand block mt-1 w-full']) }}
    />
</div>
