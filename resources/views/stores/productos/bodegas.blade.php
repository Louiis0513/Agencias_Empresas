<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-white leading-tight">
                Bodegas — {{ $store->name }}
            </h2>
            <a href="{{ route('stores.configuracion', $store) }}?panel=productos" class="text-sm text-gray-400 hover:text-brand transition">
                ← Configuración
            </a>
        </div>
    </x-slot>

    <div class="py-12" x-data="{
        showCreate: {{ $errors->any() && ! old('_edit_id') && $store->maneja_bodegas ? 'true' : 'false' }},
        showEdit: false,
        editId: null,
        editCodigo: '',
        editNombre: '',
        editActivo: true,
        openEdit(b) {
            this.editId = b.id;
            this.editCodigo = String(b.codigo);
            this.editNombre = b.nombre;
            this.editActivo = !!b.activo;
            this.showEdit = true;
        }
    }">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-950/30 px-4 py-3 text-emerald-200">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 rounded-xl border border-red-500/30 bg-red-950/30 px-4 py-3 text-red-200">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 rounded-xl border border-red-500/30 bg-red-950/30 px-4 py-3 text-red-200">
                    <ul class="list-disc list-inside text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mb-4 rounded-xl border border-sky-500/30 bg-sky-950/40 px-4 py-3 text-sm text-sky-100">
                Organiza el inventario por ubicaciones. Activa el <strong>manejo de bodegas</strong> si despachas desde más de un lugar.
                Si una bodega ya tiene movimientos, no podrás desactivar el manejo.
            </div>

            {{-- Switch manejo de bodegas --}}
            <div class="mb-6 bg-dark-card border border-white/5 rounded-xl px-6 py-5">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-white">Manejo de bodegas</h3>
                        <p class="mt-1 text-sm text-gray-400">
                            @if($store->maneja_bodegas)
                                Activo: al facturar o mover inventario deberás indicar la bodega.
                            @else
                                Desactivado: el inventario se maneja sin dimensión de bodega.
                            @endif
                        </p>
                    </div>
                    @storeCan($store, 'products.bodegas.edit')
                    <form method="POST" action="{{ route('stores.products.bodegas.manejo', $store) }}" class="flex items-center gap-3">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="maneja_bodegas" value="{{ $store->maneja_bodegas ? '0' : '1' }}">
                        <button type="submit"
                                class="relative inline-flex h-7 w-12 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 focus:ring-offset-dark
                                {{ $store->maneja_bodegas ? 'bg-brand' : 'bg-gray-600' }}"
                                role="switch"
                                aria-checked="{{ $store->maneja_bodegas ? 'true' : 'false' }}"
                                title="{{ $store->maneja_bodegas ? 'Desactivar manejo de bodegas' : 'Activar manejo de bodegas' }}">
                            <span class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out
                                {{ $store->maneja_bodegas ? 'translate-x-5' : 'translate-x-0' }}"></span>
                        </button>
                        <span class="text-sm font-medium {{ $store->maneja_bodegas ? 'text-emerald-300' : 'text-gray-400' }}">
                            {{ $store->maneja_bodegas ? 'Activado' : 'Desactivado' }}
                        </span>
                    </form>
                    @else
                    <span class="text-sm font-medium {{ $store->maneja_bodegas ? 'text-emerald-300' : 'text-gray-400' }}">
                        {{ $store->maneja_bodegas ? 'Activado' : 'Desactivado' }}
                    </span>
                    @endstoreCan
                </div>
            </div>

            @if(! $store->maneja_bodegas)
                <div class="bg-dark-card border border-white/5 rounded-xl px-6 py-10 text-center text-gray-400 text-sm">
                    <p class="mb-2">El manejo de bodegas está desactivado.</p>
                    <p>Activa el interruptor de arriba para crear y administrar bodegas.</p>
                </div>
            @else
                <div class="bg-dark-card border border-white/5 overflow-hidden sm:rounded-xl">
                    <div class="p-6">
                        <div class="mb-6 flex flex-wrap justify-between items-center gap-4">
                            <form method="GET" action="{{ route('stores.products.bodegas', $store) }}" class="flex flex-wrap gap-2 items-end">
                                <input type="text" name="search" value="{{ request('search') }}"
                                       placeholder="Buscar código o nombre..."
                                       class="rounded-md border-white/10 bg-white/5 text-gray-100">
                                <select name="activo" class="rounded-md border-white/10 bg-white/5 text-gray-100">
                                    <option value="">En uso e inactivas</option>
                                    <option value="1" @selected(request('activo') === '1')>En uso</option>
                                    <option value="0" @selected(request('activo') === '0')>Inactivas</option>
                                </select>
                                <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl">Filtrar</button>
                                @if(request()->anyFilled(['search', 'activo']))
                                    <a href="{{ route('stores.products.bodegas', $store) }}" class="px-4 py-2 bg-gray-700 text-gray-200 rounded-md text-sm">Limpiar</a>
                                @endif
                            </form>

                            @storeCan($store, 'products.bodegas.create')
                            <button type="button" @click="showCreate = true"
                                    class="inline-flex items-center px-4 py-2 bg-brand text-white text-xs font-semibold uppercase tracking-wider rounded-xl">
                                Crear bodega
                            </button>
                            @endstoreCan
                        </div>

                        @if($items && $items->count())
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-white/5">
                                    <thead>
                                        <tr>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Código</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">Nombre</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-400 uppercase">En uso</th>
                                            <th class="px-3 py-3 text-right text-xs font-medium text-gray-400 uppercase">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5">
                                        @foreach($items as $item)
                                            <tr class="hover:bg-white/5 transition text-sm text-gray-300">
                                                <td class="px-3 py-3 font-mono">{{ $item->codigo }}</td>
                                                <td class="px-3 py-3">{{ $item->nombre }}</td>
                                                <td class="px-3 py-3">
                                                    @if($item->activo)
                                                        <span class="text-emerald-400">Sí</span>
                                                    @else
                                                        <span class="text-gray-500">No</span>
                                                    @endif
                                                </td>
                                                <td class="px-3 py-3 text-right space-x-3">
                                                    @storeCan($store, 'products.bodegas.edit')
                                                    <button type="button" class="text-brand hover:underline text-sm"
                                                        @click="openEdit({
                                                            id: {{ $item->id }},
                                                            codigo: @js($item->codigo),
                                                            nombre: @js($item->nombre),
                                                            activo: {{ $item->activo ? 'true' : 'false' }}
                                                        })">
                                                        Editar
                                                    </button>
                                                    @endstoreCan
                                                    @storeCan($store, 'products.bodegas.delete')
                                                        @if(! $item->tiene_movimientos)
                                                            <form method="POST" action="{{ route('stores.products.bodegas.destroy', [$store, $item]) }}"
                                                                  class="inline"
                                                                  onsubmit="return confirm(@js('¿Eliminar la bodega «'.$item->codigo.' — '.$item->nombre.'»? No se puede recuperar.'));">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="text-red-400 hover:underline text-sm">Eliminar</button>
                                                            </form>
                                                        @endif
                                                    @endstoreCan
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-4">{{ $items->links() }}</div>
                        @else
                            <div class="text-center py-10 text-gray-400 text-sm">
                                <p class="mb-2">Aún no hay bodegas.</p>
                                <p>Crea la primera con código y nombre.</p>
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        @if($store->maneja_bodegas)
        {{-- Crear --}}
        <div x-show="showCreate" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60" @keydown.escape.window="showCreate = false">
            <div class="w-full max-w-lg rounded-xl border border-white/10 bg-dark-card p-6 shadow-xl" @click.outside="showCreate = false">
                <h3 class="text-lg font-semibold text-white mb-4">Crear bodega</h3>
                <form method="POST" action="{{ route('stores.products.bodegas.store', $store) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Código de la bodega</label>
                        <input type="text" name="codigo" value="{{ old('codigo') }}" required maxlength="32"
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100"
                               placeholder="Ej: 01">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Nombre de la bodega</label>
                        <input type="text" name="nombre" value="{{ old('nombre') }}" required maxlength="255"
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100"
                               placeholder="Ej: Principal">
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showCreate = false" class="px-4 py-2 bg-gray-700 text-gray-200 rounded-xl text-sm">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl text-sm">Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Editar --}}
        <div x-show="showEdit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60" @keydown.escape.window="showEdit = false">
            <div class="w-full max-w-lg rounded-xl border border-white/10 bg-dark-card p-6 shadow-xl" @click.outside="showEdit = false">
                <h3 class="text-lg font-semibold text-white mb-4">Editar bodega</h3>
                <form method="POST" :action="'{{ url('/stores/'.$store->slug.'/productos/bodegas') }}/' + editId" class="space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_edit_id" :value="editId">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Código de la bodega</label>
                        <input type="text" name="codigo" x-model="editCodigo" required maxlength="32"
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Nombre de la bodega</label>
                        <input type="text" name="nombre" x-model="editNombre" required maxlength="255"
                               class="w-full rounded-md border-white/10 bg-white/5 text-gray-100">
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-300">
                        <input type="hidden" name="activo" value="0">
                        <input type="checkbox" name="activo" value="1" x-model="editActivo" class="rounded border-white/20 bg-white/5">
                        En uso
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="showEdit = false" class="px-4 py-2 bg-gray-700 text-gray-200 rounded-xl text-sm">Cancelar</button>
                        <button type="submit" class="px-4 py-2 bg-brand text-white rounded-xl text-sm">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
        @endif
    </div>
</x-app-layout>
