@php
    /** @var \App\Models\Product|null $product */
    $product = $product ?? null;
    $isEdit = $isEdit ?? false;
    $preciosPorLista = $product?->precios?->keyBy('lista_precio_id') ?? collect();
    $imagenesExistentes = $product?->images ?? collect();
    $cupoImagenes = max(0, \App\Models\Product::MAX_IMAGENES - $imagenesExistentes->count());

    $v = function (string $field, mixed $default = null) use ($product) {
        return old($field, $product?->{$field} ?? $default);
    };

    $precioLista = function ($listaId) use ($preciosPorLista) {
        $pp = $preciosPorLista->get($listaId);

        return old('precios.'.$listaId, $pp?->precio !== null ? number_format((float) $pp->precio, 2, '.', '') : '0.00');
    };
@endphp

<form method="POST"
      action="{{ $isEdit ? route('stores.products.update', [$store, $product]) : route('stores.products.store', $store) }}"
      enctype="multipart/form-data"
      class="space-y-6">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    {{-- Tipo --}}
    <div class="bg-dark-card border border-white/5 rounded-xl p-5 sm:p-6">
        <div class="flex flex-wrap gap-6">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="radio" name="tipo" value="producto" x-model="tipo" @change="onTipoChange()"
                       class="text-brand border-white/20 bg-white/5 focus:ring-brand">
                <span class="text-gray-100 font-medium">Producto</span>
            </label>
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="radio" name="tipo" value="servicio" x-model="tipo" @change="onTipoChange()"
                       class="text-brand border-white/20 bg-white/5 focus:ring-brand">
                <span class="text-gray-100 font-medium">Servicio</span>
            </label>
        </div>
    </div>

    {{-- Datos generales --}}
    <div class="bg-dark-card border border-white/5 rounded-xl p-5 sm:p-6 space-y-4">
        <h3 class="text-lg font-semibold text-white">Datos generales</h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-sm text-gray-400 mb-1">
                    <span x-text="tipo === 'servicio' ? 'Categoría del servicio' : 'Categoría del producto'"></span>
                    <span class="text-red-400">*</span>
                </label>
                <div class="flex gap-2">
                    <select name="categoria_contable_id" x-model="categoriaId" required
                            class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100">
                        <option value="">Elige una opción</option>
                        <template x-for="c in categoriasFiltradas" :key="c.id">
                            <option :value="c.id" x-text="c.nombre" :selected="String(c.id) === String(categoriaId)"></option>
                        </template>
                    </select>
                    @storeCan($store, 'contabilidad.categorias.view')
                    <a href="{{ route('stores.contabilidad.categorias', $store) }}" target="_blank"
                       class="inline-flex items-center justify-center shrink-0 h-10 w-10 rounded-lg border border-white/10 text-gray-300 hover:text-brand hover:border-brand/40"
                       title="Categorías contables">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                    @endstoreCan
                </div>
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">
                    <span x-text="tipo === 'servicio' ? 'Código de servicio' : 'Código de producto (SKU)'"></span>
                    <span class="text-red-400">*</span>
                </label>
                <input type="text" name="codigo" value="{{ $v('codigo') }}" required maxlength="30"
                       class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100">
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">
                    <span x-text="tipo === 'servicio' ? 'Nombre del servicio' : 'Nombre del producto'"></span>
                    <span class="text-red-400">*</span>
                </label>
                <input type="text" name="nombre" value="{{ $v('nombre') }}" required maxlength="255"
                       class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100">
            </div>

            <div x-show="tipo === 'producto'" x-cloak>
                <label class="block text-sm text-gray-400 mb-1">Código de barras</label>
                <input type="text" name="codigo_barras" value="{{ $v('codigo_barras') }}" maxlength="64"
                       class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100">
            </div>

            <div>
                <label class="block text-sm text-gray-400 mb-1">Unidad de medida DIAN <span class="text-red-400">*</span></label>
                <div class="relative"
                     x-data="{
                        open: false,
                        query: '',
                        selected: @js($unidadDianDefault),
                        unidades: @js($unidadesDianJson),
                        get selectedLabel() {
                            const u = this.unidades.find(x => String(x.codigo) === String(this.selected));
                            return u ? u.label : (this.selected || '');
                        },
                        get filtered() {
                            const q = this.query.trim().toLowerCase();
                            if (q.length < 1) return [];
                            const hits = this.unidades.filter(u =>
                                u.label.toLowerCase().includes(q)
                                || String(u.codigo).toLowerCase().includes(q)
                                || String(u.nombre).toLowerCase().includes(q)
                            );
                            return hits.slice(0, 40);
                        },
                        select(u) {
                            this.selected = u.codigo;
                            this.query = '';
                            this.open = false;
                        },
                        openList() {
                            this.open = true;
                            this.query = '';
                            this.$nextTick(() => this.$refs.umSearch?.focus());
                        }
                     }"
                     @click.outside="open = false; query = ''"
                     @keydown.escape.window="open = false; query = ''">
                    <input type="hidden" name="unidad_medida_dian" :value="selected" required>

                    <button type="button"
                            @click="openList()"
                            class="w-full flex items-center justify-between gap-2 rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-left text-gray-100 hover:border-white/20">
                        <span class="truncate text-sm" x-text="selectedLabel || 'Elige una opción'"></span>
                        <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>

                    <div x-show="open" x-cloak x-transition
                         class="absolute z-30 mt-1 w-full rounded-lg border border-white/10 bg-dark-card shadow-xl overflow-hidden">
                        <div class="p-2 border-b border-white/10">
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 text-gray-500 pointer-events-none">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </span>
                                <input type="search" x-ref="umSearch" x-model="query"
                                       placeholder="Escribe para filtrar (ej. uni, kg…)"
                                       class="w-full pl-9 rounded-md border border-white/10 bg-white/5 text-gray-100 text-sm placeholder:text-gray-500"
                                       @keydown.enter.prevent="if (filtered.length) select(filtered[0])">
                            </div>
                        </div>
                        <ul class="max-h-48 overflow-y-auto py-1 text-sm">
                            <li x-show="query.trim().length < 1" class="px-3 py-3 text-gray-500 text-center text-xs">
                                Escribe al menos 1 carácter para ver coincidencias
                            </li>
                            <template x-for="u in filtered" :key="u.codigo">
                                <li>
                                    <button type="button"
                                            @click="select(u)"
                                            class="w-full px-3 py-2 text-left text-gray-200 hover:bg-brand/20"
                                            :class="String(u.codigo) === String(selected) ? 'bg-brand/15 text-brand' : ''"
                                            x-text="u.label"></button>
                                </li>
                            </template>
                            <li x-show="query.trim().length >= 1 && filtered.length === 0" class="px-3 py-3 text-gray-500 text-center">
                                Sin coincidencias
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <template x-if="tipo === 'producto'">
            <div class="flex flex-wrap gap-6 pt-2">
                <label class="inline-flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="es_inventariable" value="0">
                    <input type="checkbox" name="es_inventariable" value="1" x-model="esInventariable"
                           class="rounded border-white/20 bg-white/5 text-brand focus:ring-brand">
                    <span class="text-sm text-gray-200">Producto inventariable</span>
                </label>
                <label class="inline-flex items-center gap-3 cursor-pointer">
                    <input type="hidden" name="visible_en_ventas" value="0">
                    <input type="checkbox" name="visible_en_ventas" value="1" x-model="visibleVentas"
                           class="rounded border-white/20 bg-white/5 text-brand focus:ring-brand">
                    <span class="text-sm text-gray-200">Visible en facturas de venta</span>
                </label>
            </div>
        </template>

        <template x-if="tipo === 'servicio'">
            <div class="space-y-3 pt-2">
                <div class="rounded-lg border border-sky-500/30 bg-sky-950/40 px-4 py-3 text-sm text-sky-100">
                    Los servicios no son inventariables
                </div>
                <input type="hidden" name="es_inventariable" value="0">
                <input type="hidden" name="visible_en_ventas" value="1">
            </div>
        </template>
    </div>

    {{-- Datos adicionales --}}
    <div class="bg-dark-card border border-white/5 rounded-xl p-5 sm:p-6 space-y-4">
        <h3 class="text-lg font-semibold text-white">Datos adicionales</h3>

        <div class="border-b border-white/10 flex flex-wrap gap-1">
            <button type="button" @click="tabExtra = 'impuestos'"
                    :class="tabExtra === 'impuestos' ? 'border-brand text-brand' : 'border-transparent text-gray-400 hover:text-gray-200'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px">Impuestos</button>
            <button type="button" @click="tabExtra = 'descripcion'"
                    :class="tabExtra === 'descripcion' ? 'border-brand text-brand' : 'border-transparent text-gray-400 hover:text-gray-200'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
                    x-text="tipo === 'servicio' ? 'Descripción' : 'Descripción y stock'"></button>
            <button type="button" @click="tabExtra = 'imagenes'"
                    :class="tabExtra === 'imagenes' ? 'border-brand text-brand' : 'border-transparent text-gray-400 hover:text-gray-200'"
                    class="px-4 py-2 text-sm font-medium border-b-2 -mb-px">Subir imágenes</button>
        </div>

        <div x-show="tabExtra === 'impuestos'" x-cloak class="space-y-4 pt-2">
            <p class="text-sm text-gray-400">Estos impuestos aplican solo para documentos de ventas</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Impuesto cargo</label>
                    <select name="impuesto_cargo_id" x-model="impuestoCargoId"
                            class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100">
                        <option value="">No aplica</option>
                        @foreach($impuestosCargo as $imp)
                            <option value="{{ $imp->id }}" @selected((string) $v('impuesto_cargo_id') === (string) $imp->id)>
                                {{ $imp->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Retención</label>
                    <select name="impuesto_retencion_id" class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100">
                        <option value="">No aplica</option>
                        @foreach($impuestosRetencion as $imp)
                            <option value="{{ $imp->id }}" @selected((string) $v('impuesto_retencion_id') === (string) $imp->id)>
                                {{ $imp->nombre }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <template x-if="impuestoCargoPorValor">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">
                            Valor impuesto cargo (COP) <span class="text-red-400">*</span>
                        </label>
                        <input type="number" name="valor_impuesto_cargo"
                               value="{{ $v('valor_impuesto_cargo') }}"
                               min="0" step="0.01" required
                               class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100">
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="inline-flex items-center gap-3 cursor-pointer">
                            <input type="hidden" name="aplica_impuesto_bolsas" value="0">
                            <input type="checkbox" name="aplica_impuesto_bolsas" value="1"
                                   x-model="aplicaImpuestoBolsas"
                                   class="rounded border-white/20 bg-white/5 text-brand focus:ring-brand">
                            <span class="text-sm text-gray-200">El valor aplica al impuesto de bolsas</span>
                        </label>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="tabExtra === 'descripcion'" x-cloak class="space-y-4 pt-2">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Referencia</label>
                    <input type="text" name="referencia" value="{{ $v('referencia') }}" maxlength="120"
                           class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Unidad de medida de la factura</label>
                    <input type="text" name="unidad_medida_factura" value="{{ $v('unidad_medida_factura', 'unidad') }}" maxlength="60"
                           class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100">
                </div>
                <div x-show="tipo === 'producto'" x-cloak>
                    <label class="block text-sm text-gray-400 mb-1">Stock mínimo</label>
                    <input type="number" name="stock_minimo" value="{{ $v('stock_minimo') }}" min="0" step="0.0001"
                           class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100">
                </div>
            </div>
            <div>
                <label class="block text-sm text-gray-400 mb-1">Descripción larga</label>
                <textarea name="descripcion" rows="4"
                          class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100">{{ $v('descripcion') }}</textarea>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4" x-show="tipo === 'producto'" x-cloak>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Marca</label>
                    <input type="text" name="marca" value="{{ $v('marca') }}" maxlength="120"
                           class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Modelo</label>
                    <input type="text" name="modelo" value="{{ $v('modelo') }}" maxlength="120"
                           class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100">
                </div>
                <div>
                    <label class="block text-sm text-gray-400 mb-1">Código arancelario</label>
                    <input type="text" name="codigo_arancelario" value="{{ $v('codigo_arancelario') }}" maxlength="30"
                           class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100">
                </div>
            </div>
        </div>

        <div x-show="tabExtra === 'imagenes'" x-cloak class="pt-2 space-y-3"
             x-data="{
                maxFiles: {{ (int) $cupoImagenes }},
                maxBytes: {{ (int) (\App\Models\Product::MAX_IMAGEN_KB * 1024) }},
                error: '',
                pending: [],
                cropper: null,
                previewUrl: null,
                objectUrl: null,
                localError: '',
                showCrop: false,
                destroyCropper() {
                    if (this.cropper) { this.cropper.destroy(); this.cropper = null; }
                },
                clearCrop() {
                    this.destroyCropper();
                    if (this.objectUrl) { URL.revokeObjectURL(this.objectUrl); this.objectUrl = null; }
                    this.previewUrl = null;
                    this.localError = '';
                    if (this.$refs.cropFile) this.$refs.cropFile.value = '';
                },
                openCrop() {
                    @if($isEdit && $product)
                        Livewire.dispatch('open-add-product-photo', { productId: {{ $product->id }} });
                    @else
                        this.showCrop = true;
                        this.clearCrop();
                    @endif
                },
                onPick(e) {
                    const file = e.target.files?.[0];
                    if (!file) return;
                    this.localError = '';
                    if (!['image/jpeg','image/jpg','image/png'].includes(file.type)) {
                        this.localError = 'Solo JPG o PNG.';
                        return;
                    }
                    if (file.size > this.maxBytes) {
                        this.localError = 'La imagen supera 1 MB.';
                        return;
                    }
                    this.destroyCropper();
                    if (this.objectUrl) URL.revokeObjectURL(this.objectUrl);
                    this.objectUrl = URL.createObjectURL(file);
                    this.previewUrl = this.objectUrl;
                    this.$nextTick(() => {
                        if (typeof Cropper === 'undefined') {
                            this.localError = 'Recarga la página para cargar el recortador.';
                            return;
                        }
                        const img = this.$refs.formCropImg;
                        img.onload = () => {
                            this.destroyCropper();
                            this.cropper = new Cropper(img, {
                                aspectRatio: 1, viewMode: 1, autoCropArea: 1,
                                movable: true, zoomable: true, rotatable: true, background: false,
                            });
                        };
                        img.src = this.previewUrl;
                    });
                },
                async confirmCrop() {
                    if (!this.cropper) { this.localError = 'Selecciona una foto.'; return; }
                    if (this.pending.length >= this.maxFiles) {
                        this.localError = 'Máximo ' + this.maxFiles + ' imagen(es) en esta carga.';
                        return;
                    }
                    const canvas = this.cropper.getCroppedCanvas({ width: 800, height: 800 });
                    let blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.9));
                    if (blob && blob.size > this.maxBytes) blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.7));
                    if (!blob || blob.size > this.maxBytes) {
                        this.localError = 'El recorte supera 1 MB.';
                        return;
                    }
                    const file = new File([blob], 'foto-' + (this.pending.length + 1) + '.jpg', { type: 'image/jpeg' });
                    this.pending.push({ file, url: URL.createObjectURL(file) });
                    this.syncInput();
                    this.showCrop = false;
                    this.clearCrop();
                },
                removePending(i) {
                    const item = this.pending[i];
                    if (item?.url) URL.revokeObjectURL(item.url);
                    this.pending.splice(i, 1);
                    this.syncInput();
                },
                syncInput() {
                    const dt = new DataTransfer();
                    this.pending.forEach(p => dt.items.add(p.file));
                    this.$refs.imagenesInput.files = dt.files;
                }
             }">
            @if($imagenesExistentes->isNotEmpty())
                <div>
                    <p class="text-sm text-gray-400 mb-2">Imágenes actuales ({{ $imagenesExistentes->count() }} / {{ \App\Models\Product::MAX_IMAGENES }})</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($imagenesExistentes as $img)
                            <img src="{{ asset('storage/'.$img->path) }}" alt=""
                                 class="h-16 w-16 rounded-lg object-cover border border-white/10">
                        @endforeach
                    </div>
                </div>
            @endif

            @if($cupoImagenes > 0)
                <p class="text-sm text-gray-400">
                    Regla: máximo {{ \App\Models\Product::MAX_IMAGENES }} imágenes; cada una máx. 1 MB; recorte cuadrado.
                </p>
                <button type="button" @click="openCrop()"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-brand/40 text-brand text-sm font-semibold hover:bg-brand/10">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Agregar foto (recortar)
                </button>
                <input type="file" name="imagenes[]" x-ref="imagenesInput" multiple class="hidden" accept=".png,.jpg,.jpeg,image/png,image/jpeg">

                <div x-show="pending.length" x-cloak class="flex flex-wrap gap-2">
                    <template x-for="(item, i) in pending" :key="i">
                        <div class="relative">
                            <img :src="item.url" class="h-16 w-16 rounded-lg object-cover border border-white/10" alt="">
                            <button type="button" @click="removePending(i)"
                                    class="absolute -top-1 -right-1 h-5 w-5 rounded-full bg-red-500 text-white text-xs">×</button>
                        </div>
                    </template>
                </div>

                {{-- Modal recorte (solo creación; en edición usa Livewire) --}}
                @unless($isEdit)
                <div x-show="showCrop" x-cloak class="fixed inset-0 z-[90] flex items-center justify-center px-4"
                     @keydown.escape.window="showCrop = false; clearCrop()">
                    <div class="absolute inset-0 bg-black/60" @click="showCrop = false; clearCrop()"></div>
                    <div class="relative w-full max-w-2xl rounded-xl border border-white/10 bg-dark-card p-5 space-y-4 shadow-xl">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-white">Agregar foto</h3>
                            <button type="button" class="text-gray-400 hover:text-white" @click="showCrop = false; clearCrop()">×</button>
                        </div>
                        <p class="text-xs text-gray-400">Recorte cuadrado · PNG/JPG · Máx. 1 MB</p>
                        <div class="rounded-xl border-2 border-dashed border-brand/40 bg-brand/5 min-h-[260px] flex items-center justify-center overflow-hidden">
                            <div x-show="!previewUrl" class="text-center py-10 space-y-3">
                                <button type="button" @click="$refs.cropFile.click()"
                                        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-brand/50 text-brand text-sm font-semibold hover:bg-brand/10">
                                    Seleccionar una foto
                                </button>
                                <p class="text-sm text-gray-400">o elige JPG/PNG — Máx. 1 MB</p>
                            </div>
                            <div x-show="previewUrl" class="w-full h-[300px] bg-black/40">
                                <img x-ref="formCropImg" class="max-w-full block" alt="">
                            </div>
                            <input type="file" x-ref="cropFile" class="hidden" accept=".png,.jpg,.jpeg,image/png,image/jpeg" @change="onPick($event)">
                        </div>
                        <div class="flex justify-center gap-2" x-show="cropper">
                            <button type="button" class="px-3 py-1.5 rounded-lg border border-white/10 text-sm text-gray-300" @click="cropper?.zoom(-0.1)">−</button>
                            <button type="button" class="px-3 py-1.5 rounded-lg border border-white/10 text-sm text-gray-300" @click="cropper?.zoom(0.1)">+</button>
                            <button type="button" class="px-3 py-1.5 rounded-lg border border-white/10 text-sm text-gray-300" @click="cropper?.rotate(-90)">↺</button>
                            <button type="button" class="px-3 py-1.5 rounded-lg border border-white/10 text-sm text-gray-300" @click="cropper?.rotate(90)">↻</button>
                            <button type="button" class="px-3 py-1.5 rounded-lg border border-red-500/40 text-sm text-red-400" @click="clearCrop()">Quitar</button>
                        </div>
                        <p x-show="localError" x-text="localError" class="text-sm text-red-400"></p>
                        <div class="flex justify-between">
                            <button type="button" class="text-sm text-brand" @click="showCrop = false; clearCrop()">Cancelar</button>
                            <button type="button" class="px-5 py-2 rounded-lg bg-brand text-white text-sm font-semibold" @click="confirmCrop()">Guardar</button>
                        </div>
                    </div>
                </div>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
                <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
                @endunless
            @else
                <p class="text-sm text-amber-300">Ya alcanzaste el máximo de {{ \App\Models\Product::MAX_IMAGENES }} imágenes (máx. 1 MB c/u).</p>
            @endif
            <p x-show="error" x-text="error" x-cloak class="text-sm text-red-400"></p>
        </div>
    </div>

    {{-- Lista de precios --}}
    <div class="bg-dark-card border border-white/5 rounded-xl p-5 sm:p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-lg font-semibold text-white">Lista de precios</h3>
        </div>
        <p class="text-xs text-gray-500">Moneda local (COP)</p>

        <label class="inline-flex items-center gap-3 cursor-pointer">
            <input type="hidden" name="precio_incluye_iva" value="0">
            <input type="checkbox" name="precio_incluye_iva" value="1" x-model="precioIncluyeIva"
                   class="rounded border-white/20 bg-white/5 text-brand focus:ring-brand">
            <span class="text-sm text-gray-200">Incluir IVA en el precio de venta</span>
        </label>

        @if($listasActivas->isEmpty())
            <p class="text-sm text-amber-300">No hay listas de precios activas. Se crearon los slots por defecto; activa al menos una desde configuración.</p>
        @else
            <div class="space-y-3">
                @foreach($listasActivas as $lista)
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-end">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">{{ $lista->nombre }}</label>
                            <input type="text" disabled value="{{ $lista->nombre }}"
                                   class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-400">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Precio (COP)</label>
                            <input type="number" name="precios[{{ $lista->id }}]"
                                   value="{{ $precioLista($lista->id) }}"
                                   min="0" step="0.01"
                                   class="w-full rounded-lg border border-white/10 bg-white/5 text-gray-100 text-right">
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="flex flex-wrap justify-end gap-3 pb-8">
        <a href="{{ $isEdit ? route('stores.products.show', [$store, $product]) : route('stores.products', $store) }}"
           class="inline-flex items-center px-5 py-2.5 rounded-lg border border-white/10 text-gray-200 text-sm font-medium hover:bg-white/5">
            Cancelar
        </a>
        @unless($isEdit)
            <button type="submit" name="guardar_y_nuevo" value="1"
                    class="inline-flex items-center px-5 py-2.5 rounded-lg border border-brand/40 text-brand text-sm font-semibold hover:bg-brand/10">
                Guardar y crear nuevo
            </button>
        @endunless
        <button type="submit"
                class="inline-flex items-center px-5 py-2.5 rounded-lg bg-brand text-white text-sm font-semibold hover:opacity-95">
            Guardar
        </button>
    </div>
</form>
