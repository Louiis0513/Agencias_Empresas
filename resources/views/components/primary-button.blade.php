<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-brand border border-transparent rounded-xl font-semibold text-xs text-white uppercase tracking-widest shadow-[0_0_15px_rgba(34,114,255,0.3)] hover:shadow-[0_0_20px_rgba(34,114,255,0.4)] focus:outline-none focus:ring-2 focus:ring-brand transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
