<div
    x-data="productPhotoCropper({{ \App\Models\Product::MAX_IMAGEN_KB * 1024 }})"
    x-on:open-modal.window="
        const d = $event.detail;
        if (d === 'add-product-photo' || d?.[0] === 'add-product-photo' || d?.name === 'add-product-photo') {
            show = true;
            resetCropper();
        }
    "
    x-on:close-modal.window="
        const d = $event.detail;
        if (d === 'add-product-photo' || d?.[0] === 'add-product-photo' || d?.name === 'add-product-photo') {
            show = false;
            destroyCropper();
        }
    "
    x-cloak
>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">

    <div
        x-show="show"
        x-cloak
        class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0"
        style="z-index: 90; display: none;"
        @keydown.escape.window="if (show) { show = false; destroyCropper(); }"
    >
        <div
            x-show="show"
            class="fixed inset-0 transform transition-all"
            @click="show = false; destroyCropper()"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
        >
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
        </div>

        <div
            x-show="show"
            @click.stop
            class="mb-6 relative bg-dark-card border border-white/5 shadow-xl rounded-xl overflow-hidden transform transition-all sm:w-full sm:max-w-2xl sm:mx-auto"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
            x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        >
            <div class="p-5 sm:p-6 space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="text-lg font-semibold text-white truncate">
                        Foto para {{ $productNombre !== '' ? $productNombre : 'producto' }}
                    </h2>
                    <button type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:text-white hover:bg-white/10"
                            @click="show = false; destroyCropper()"
                            aria-label="Cerrar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <p class="text-xs text-gray-400">
                    Recorte cuadrado · PNG/JPG · Máx. 1 MB ·
                    {{ $imagenesCount }} / {{ \App\Models\Product::MAX_IMAGENES }} imágenes
                </p>

                <div class="relative rounded-xl border-2 border-dashed border-brand/40 bg-brand/5 min-h-[280px] flex items-center justify-center overflow-hidden"
                     @dragover.prevent="dragging = true"
                     @dragleave.prevent="dragging = false"
                     @drop.prevent="dragging = false; onDrop($event)"
                     :class="dragging ? 'border-brand bg-brand/10' : ''">
                    <div x-show="!previewUrl" class="text-center px-4 py-10 space-y-3">
                        <button type="button"
                                @click="$refs.fileInput.click()"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-brand/50 text-brand text-sm font-semibold hover:bg-brand/10">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3.75 21h16.5A2.25 2.25 0 0022.5 18.75V5.25A2.25 2.25 0 0020.25 3H3.75A2.25 2.25 0 001.5 5.25v13.5A2.25 2.25 0 003.75 21z"/></svg>
                            Seleccionar una foto
                        </button>
                        <p class="text-sm text-gray-400">o arrastra aquí el JPG o PNG — Máx. 1 MB</p>
                    </div>

                    <div x-show="previewUrl" x-cloak class="w-full h-[320px] bg-black/40">
                        <img x-ref="cropImage" alt="Vista previa" class="max-w-full block">
                    </div>

                    <input type="file" x-ref="fileInput" accept=".png,.jpg,.jpeg,image/png,image/jpeg" class="hidden"
                           @change="onFileSelected($event)">
                </div>

                <div class="flex flex-wrap items-center justify-center gap-2">
                    <button type="button" @click="zoom(-0.1)" :disabled="!cropper" title="Alejar"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 text-gray-300 hover:bg-white/5 disabled:opacity-40">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM13 10H7"/></svg>
                    </button>
                    <button type="button" @click="zoom(0.1)" :disabled="!cropper" title="Acercar"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 text-gray-300 hover:bg-white/5 disabled:opacity-40">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/></svg>
                    </button>
                    <button type="button" @click="rotate(-90)" :disabled="!cropper" title="Girar izquierda"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 text-gray-300 hover:bg-white/5 disabled:opacity-40">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h7a4 4 0 014 4v1M3 10l3-3m-3 3l3 3"/></svg>
                    </button>
                    <button type="button" @click="rotate(90)" :disabled="!cropper" title="Girar derecha"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-white/10 text-gray-300 hover:bg-white/5 disabled:opacity-40">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-7a4 4 0 00-4 4v1m11-5l-3-3m3 3l-3 3"/></svg>
                    </button>
                    <button type="button" @click="$refs.fileInput.click()" title="Cambiar foto"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-brand/40 text-brand hover:bg-brand/10">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    </button>
                    <button type="button" @click="clearImage()" :disabled="!previewUrl" title="Quitar"
                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-500/40 text-red-400 hover:bg-red-500/10 disabled:opacity-40">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </div>

                <p x-show="localError" x-text="localError" x-cloak class="text-sm text-red-400"></p>
                <x-input-error :messages="$errors->get('photo')" class="mt-1" />

                <div class="flex items-center justify-between gap-3 pt-2">
                    <button type="button"
                            class="text-sm text-brand hover:underline"
                            @click="show = false; destroyCropper()">
                        Cancelar
                    </button>
                    <button type="button"
                            @click="saveCropped()"
                            wire:loading.attr="disabled"
                            class="inline-flex items-center px-5 py-2.5 rounded-lg bg-brand text-white text-sm font-semibold hover:opacity-95 disabled:opacity-50">
                        <span wire:loading.remove wire:target="save,photo">Guardar</span>
                        <span wire:loading wire:target="save,photo">Subiendo…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@script
<script>
    if (!window.__cropperJsLoading) {
        window.__cropperJsLoading = true;
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js';
        document.head.appendChild(s);
    }

    Alpine.data('productPhotoCropper', (maxBytes) => ({
        maxBytes: maxBytes,
        show: false,
        previewUrl: null,
        cropper: null,
        dragging: false,
        localError: '',
        objectUrl: null,
        saving: false,

        destroyCropper() {
            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }
        },

        resetCropper() {
            this.clearImage();
            this.localError = '';
            this.saving = false;
        },

        clearImage() {
            this.destroyCropper();
            if (this.objectUrl) {
                URL.revokeObjectURL(this.objectUrl);
                this.objectUrl = null;
            }
            this.previewUrl = null;
            if (this.$refs.fileInput) this.$refs.fileInput.value = '';
        },

        onDrop(e) {
            const file = e.dataTransfer?.files?.[0];
            if (file) this.loadFile(file);
        },

        onFileSelected(e) {
            const file = e.target.files?.[0];
            if (file) this.loadFile(file);
        },

        loadFile(file) {
            this.localError = '';
            if (!['image/jpeg', 'image/jpg', 'image/png'].includes(file.type)) {
                this.localError = 'Solo se permiten JPG o PNG.';
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
            this.$nextTick(() => this.initCropper());
        },

        initCropper() {
            const start = () => {
                if (typeof Cropper === 'undefined') {
                    this.localError = 'No se pudo cargar el recortador. Recarga la página.';
                    return;
                }
                const img = this.$refs.cropImage;
                if (!img) return;

                const boot = () => {
                    this.destroyCropper();
                    this.cropper = new Cropper(img, {
                        aspectRatio: 1,
                        viewMode: 1,
                        autoCropArea: 1,
                        movable: true,
                        zoomable: true,
                        rotatable: true,
                        scalable: false,
                        background: false,
                        responsive: true,
                    });
                };

                img.onload = boot;
                img.src = this.previewUrl;
                if (img.complete) boot();
            };

            if (typeof Cropper === 'undefined') {
                setTimeout(start, 200);
            } else {
                start();
            }
        },

        zoom(delta) {
            this.cropper?.zoom(delta);
        },

        rotate(deg) {
            this.cropper?.rotate(deg);
        },

        saveCropped() {
            this.localError = '';
            if (this.saving) return;
            if (!this.cropper) {
                this.localError = 'Selecciona una foto para recortar.';
                return;
            }
            const canvas = this.cropper.getCroppedCanvas({
                width: 800,
                height: 800,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high',
            });
            if (!canvas) {
                this.localError = 'No se pudo generar el recorte.';
                return;
            }

            const exportBlob = (quality) => new Promise((resolve) => {
                canvas.toBlob((blob) => resolve(blob), 'image/jpeg', quality);
            });

            this.saving = true;

            (async () => {
                try {
                    let blob = await exportBlob(0.9);
                    if (blob && blob.size > this.maxBytes) blob = await exportBlob(0.75);
                    if (blob && blob.size > this.maxBytes) blob = await exportBlob(0.6);
                    if (!blob) {
                        this.localError = 'No se pudo generar la imagen.';
                        this.saving = false;
                        return;
                    }
                    if (blob.size > this.maxBytes) {
                        this.localError = 'El recorte supera 1 MB. Prueba otra imagen.';
                        this.saving = false;
                        return;
                    }
                    const file = new File([blob], 'foto-producto.jpg', { type: 'image/jpeg' });
                    if (!this.$wire || typeof this.$wire.upload !== 'function') {
                        this.localError = 'No se pudo conectar con Livewire. Recarga la página.';
                        this.saving = false;
                        return;
                    }
                    this.$wire.upload(
                        'photo',
                        file,
                        () => {
                            this.$wire.save()
                                .then(() => { this.saving = false; })
                                .catch(() => { this.saving = false; });
                        },
                        () => {
                            this.localError = 'Error al subir la imagen.';
                            this.saving = false;
                        },
                        () => {}
                    );
                } catch (e) {
                    this.localError = 'Error al procesar la imagen.';
                    this.saving = false;
                }
            })();
        },
    }));
</script>
@endscript
