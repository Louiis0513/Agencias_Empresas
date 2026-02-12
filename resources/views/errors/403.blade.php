<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Sin permiso
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <p class="text-gray-700 dark:text-gray-300">
                    {{ $exception->getMessage() ?: 'No tienes permiso para realizar esta acción en esta tienda.' }}
                </p>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    Serás redirigido hacia atrás en <span id="countdown">5</span> segundos…
                </p>
            </div>
        </div>
    </div>

    <script>
        (function () {
            var s = 5;
            var el = document.getElementById('countdown');
            var t = setInterval(function () {
                s--;
                if (el) el.textContent = s;
                if (s <= 0) {
                    clearInterval(t);
                    if (window.history.length > 1) {
                        window.history.back();
                    } else {
                        window.location.href = '{{ route("dashboard") }}';
                    }
                }
            }, 1000);
        })();
    </script>
</x-app-layout>
