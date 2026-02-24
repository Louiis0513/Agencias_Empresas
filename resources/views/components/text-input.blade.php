@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-white/10 bg-white/5 text-gray-100 rounded-lg shadow-sm focus:border-brand focus:ring-brand']) }}>
