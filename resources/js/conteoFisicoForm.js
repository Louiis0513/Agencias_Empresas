/**
 * Formulario Alpine: conteo físico (existencias contadas → delta en ledger).
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('conteoFisicoForm', (cfg = {}) => {
        let keySeq = 1;
        const SIN_ASIGNAR = 'sin_asignar';
        const OPCION_SIN_ASIGNAR = {
            id: SIN_ASIGNAR,
            codigo: '—',
            nombre: 'Sin asignar',
        };

        const emptyLine = () => ({
            key: keySeq++,
            product_id: '',
            product_search: '',
            descripcion: '',
            bodega_id: '',
            bodega_search: '',
            stock_sistema: null,
            stock_loading: false,
            cantidad_contada: 0,
            cantidad_contada_display: '0.00',
        });

        const formatQty = (num) => {
            const n = Number(num) || 0;
            return n.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
                useGrouping: false,
            });
        };

        const parseQty = (str) => {
            const s = String(str || '').trim().replace(/,/g, '.');
            if (s === '' || s === '.') return 0;
            return parseFloat(s) || 0;
        };

        const formatLiveQty = (raw, selectionStart) => {
            let str = String(raw || '').replace(/,/g, '.');
            if (str.trim() === '') {
                return { display: '', cursor: 0, value: 0 };
            }

            str = str.replace(/[^\d.]/g, '');
            const firstDot = str.indexOf('.');
            let intPart = firstDot === -1 ? str : str.slice(0, firstDot);
            let decPart = firstDot === -1 ? null : str.slice(firstDot + 1).replace(/\./g, '').slice(0, 4);

            intPart = intPart.replace(/^0+(?=\d)/, '');
            if (intPart === '' && decPart === null && !str.includes('.')) {
                return { display: '', cursor: 0, value: 0 };
            }
            if (intPart === '') intPart = '0';

            let display;
            if (decPart !== null) {
                display = `${intPart}.${decPart}`;
            } else if (str.includes('.')) {
                display = `${intPart}.`;
            } else {
                display = intPart;
            }

            const value = parseFloat(display) || 0;
            const src = String(raw || '');
            const digitsBefore = src.slice(0, selectionStart ?? src.length).replace(/\D/g, '').length;
            let cursor = display.length;
            let seen = 0;
            for (let i = 0; i < display.length; i++) {
                if (/\d/.test(display[i])) {
                    seen++;
                    if (seen >= digitsBefore) {
                        cursor = i + 1;
                        break;
                    }
                }
            }
            if (str.endsWith('.') && !display.endsWith('.')) {
                display = `${intPart}.`;
                cursor = display.length;
            }

            return { display, cursor, value };
        };

        const labelBodega = (b) => {
            if (!b || b.id === SIN_ASIGNAR || b.id === null) {
                return 'Sin asignar';
            }
            return `${b.codigo} — ${b.nombre}`;
        };

        const idBodegaParaApi = (id) => {
            if (!id || id === SIN_ASIGNAR) return null;
            return Number(id);
        };

        return {
            productos: cfg.productos || [],
            bodegas: cfg.bodegas || [],
            terceros: cfg.terceros || [],
            fecha: cfg.fechaDefault || '',
            tercero_id: '',
            tercero_search: '',
            tercero: '',
            observaciones: '',
            lines: [emptyLine()],
            storeUrl: cfg.storeUrl || '',
            stockUrlTemplate: cfg.stockUrlTemplate || '',
            plantillaUrl: cfg.plantillaUrl || '',
            parsePlantillaUrl: cfg.parsePlantillaUrl || '',
            plantillaBodegaId: (cfg.bodegas && cfg.bodegas.length) ? String(cfg.bodegas[0].id) : 'sin_asignar',
            importingPlantilla: false,
            saving: false,
            errorMsg: '',
            dd: {
                open: false,
                type: null,
                lineKey: null,
                top: 0,
                left: 0,
                width: 320,
            },

            get plantillaDownloadHref() {
                if (!this.plantillaUrl) return '#';
                const params = new URLSearchParams();
                if (this.plantillaBodegaId && this.plantillaBodegaId !== 'sin_asignar') {
                    params.set('bodega_id', String(this.plantillaBodegaId));
                } else {
                    params.set('bodega_id', 'sin_asignar');
                }
                const qs = params.toString();
                return qs ? `${this.plantillaUrl}?${qs}` : this.plantillaUrl;
            },

            get puedeRegistrar() {
                if (!this.fecha) return false;
                return this.lines.some((l) => this.lineValidaParaGuardar(l));
            },

            get ddStyle() {
                return {
                    top: `${this.dd.top}px`,
                    left: `${this.dd.left}px`,
                    width: `${this.dd.width}px`,
                };
            },

            get ddLine() {
                if (!this.dd.lineKey) return null;
                return this.lines.find((l) => l.key === this.dd.lineKey) || null;
            },

            get ddItems() {
                if (!this.dd.type) return [];
                if (this.dd.type === 'tercero') {
                    return this.filterCatalog(this.terceros, this.tercero_search);
                }
                const line = this.ddLine;
                if (!line) return [];
                if (this.dd.type === 'product') {
                    return this.filterCatalog(this.productos, line.product_search);
                }
                if (this.dd.type === 'bodega') {
                    return this.filterBodegas(line.bodega_search);
                }
                return [];
            },

            filterCatalog(items, query) {
                const q = String(query || '').trim().toLowerCase();
                let list = items || [];
                if (q) {
                    list = list.filter((item) => {
                        const codigo = String(item.codigo || '').toLowerCase();
                        const nombre = String(item.nombre || '').toLowerCase();
                        return codigo.includes(q) || nombre.includes(q);
                    });
                }
                return list.slice(0, 10);
            },

            filterBodegas(query) {
                const q = String(query || '').trim().toLowerCase();
                const sinAsignarMatch = !q
                    || 'sin asignar'.includes(q)
                    || 'asignar'.includes(q)
                    || q === '—'
                    || q === '-';

                let list = [...(this.bodegas || [])];
                if (q) {
                    list = list.filter((item) => {
                        const codigo = String(item.codigo || '').toLowerCase();
                        const nombre = String(item.nombre || '').toLowerCase();
                        return codigo.includes(q) || nombre.includes(q);
                    });
                }

                const out = [];
                if (sinAsignarMatch) {
                    out.push(OPCION_SIN_ASIGNAR);
                }
                return out.concat(list).slice(0, 11);
            },

            deltaPreview(line) {
                if (line.stock_sistema === null || line.stock_sistema === undefined) return null;
                if (!line.product_id) return null;
                return Number(line.cantidad_contada) - Number(line.stock_sistema);
            },

            openDd(line, type, event) {
                const el = event?.currentTarget || event?.target;
                if (!el || !el.getBoundingClientRect) return;
                const r = el.getBoundingClientRect();
                const minW = (type === 'product' || type === 'tercero') ? 360 : 280;
                this.dd = {
                    open: true,
                    type,
                    lineKey: line?.key ?? null,
                    top: r.bottom + 4,
                    left: r.left,
                    width: Math.max(r.width, minW),
                };
            },

            closeDd() {
                this.dd.open = false;
                this.dd.type = null;
                this.dd.lineKey = null;
            },

            onDdOutside(event) {
                const t = event?.target;
                if (!t || !this.dd.open) return;
                const anchor = this.dd.type === 'tercero'
                    ? 'tercero-header'
                    : `${this.dd.type}-${this.dd.lineKey}`;
                if (t.closest && t.closest(`[data-dd-anchor="${anchor}"]`)) {
                    return;
                }
                this.closeDd();
            },

            pickDdItem(item) {
                if (this.dd.type === 'tercero') {
                    this.selectTercero(item);
                    return;
                }
                const line = this.ddLine;
                if (!line) return;
                if (this.dd.type === 'product') this.selectProduct(line, item);
                else if (this.dd.type === 'bodega') this.selectBodega(line, item);
            },

            onTerceroSearchInput() {
                if (this.tercero_id) {
                    this.tercero_id = '';
                    this.tercero = '';
                }
            },
            selectTercero(t) {
                this.tercero_id = String(t.id);
                this.tercero = t.nombre;
                this.tercero_search = `${t.codigo} — ${t.nombre}`;
                this.closeDd();
            },
            terceroNombreParaGuardar() {
                if (this.tercero) return this.tercero;
                const typed = String(this.tercero_search || '').trim();
                return typed || null;
            },

            addLine() {
                this.lines.push(emptyLine());
            },
            removeLine(index) {
                const line = this.lines[index];
                if (!line) return;
                if (this.lines.length <= 1) {
                    const key = line.key;
                    Object.assign(line, emptyLine(), { key });
                    this.closeDd();
                    return;
                }
                this.lines.splice(index, 1);
                this.ensureTrailingEmptyLine();
                this.closeDd();
            },
            duplicateLine(index) {
                const src = this.lines[index];
                this.lines.splice(index + 1, 0, {
                    ...src,
                    key: keySeq++,
                });
                this.ensureTrailingEmptyLine();
            },

            lineCompleta(line) {
                if (!line?.product_id) return false;
                if (line.bodega_id === '' || line.bodega_id === null || line.bodega_id === undefined) {
                    return false;
                }
                if (!(Number(line.cantidad_contada) >= 0)) return false;
                const delta = this.deltaPreview(line);
                if (delta === null) return Number(line.cantidad_contada) >= 0;
                return Math.abs(delta) > 0.00005;
            },

            lineValidaParaGuardar(line) {
                return !!(line?.product_id)
                    && line.bodega_id !== ''
                    && line.bodega_id !== null
                    && line.bodega_id !== undefined
                    && Number(line.cantidad_contada) >= 0;
            },

            ensureTrailingEmptyLine() {
                const last = this.lines[this.lines.length - 1];
                if (!last || this.lineCompleta(last)) {
                    this.addLine();
                }
            },

            onProductSearchInput(line) {
                if (line.product_id) {
                    line.product_id = '';
                    line.descripcion = '';
                    line.stock_sistema = null;
                }
            },
            selectProduct(line, p) {
                line.product_id = String(p.id);
                line.product_search = `${p.codigo} — ${p.nombre}`;
                line.descripcion = p.nombre;
                this.closeDd();
                this.refreshStock(line);
                this.ensureTrailingEmptyLine();
            },

            onBodegaSearchInput(line) {
                if (line.bodega_id) line.bodega_id = '';
                line.stock_sistema = null;
            },
            selectBodega(line, b) {
                const esSin = !b || b.id === SIN_ASIGNAR || b.id === null;
                line.bodega_id = esSin ? SIN_ASIGNAR : String(b.id);
                line.bodega_search = labelBodega(esSin ? OPCION_SIN_ASIGNAR : b);
                this.closeDd();
                this.refreshStock(line);
                this.ensureTrailingEmptyLine();
            },

            async refreshStock(line) {
                if (!line.product_id || !this.stockUrlTemplate) {
                    line.stock_sistema = null;
                    return;
                }
                if (line.bodega_id === '' || line.bodega_id === null || line.bodega_id === undefined) {
                    line.stock_sistema = null;
                    return;
                }

                line.stock_loading = true;
                try {
                    const url = this.stockUrlTemplate.replace('__PRODUCT__', String(line.product_id));
                    const params = new URLSearchParams();
                    const bodegaApi = idBodegaParaApi(line.bodega_id);
                    if (bodegaApi !== null) {
                        params.set('bodega_id', String(bodegaApi));
                    }
                    const qs = params.toString();
                    const res = await fetch(qs ? `${url}?${qs}` : url, {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        line.stock_sistema = null;
                        return;
                    }
                    line.stock_sistema = Number(data.stock ?? 0);
                } catch (_) {
                    line.stock_sistema = null;
                } finally {
                    line.stock_loading = false;
                    this.ensureTrailingEmptyLine();
                }
            },

            onQtyInput(line, event) {
                const el = event.target;
                const original = String(el.value || '');
                const sel = el.selectionStart ?? original.length;
                const raw = original.replace(/,/g, '.');
                const result = formatLiveQty(raw, sel);
                line.cantidad_contada = result.value;
                line.cantidad_contada_display = result.display;
                this.$nextTick(() => {
                    try {
                        el.setSelectionRange(result.cursor, result.cursor);
                    } catch (_) { /* ignore */ }
                    this.ensureTrailingEmptyLine();
                });
            },
            onQtyBlur(line) {
                const parsed = parseQty(line.cantidad_contada_display);
                line.cantidad_contada = parsed;
                line.cantidad_contada_display = formatQty(parsed);
                this.ensureTrailingEmptyLine();
            },

            async onPlantillaSelected(event) {
                const file = event?.target?.files?.[0];
                if (!file || !this.parsePlantillaUrl) return;

                this.errorMsg = '';
                this.importingPlantilla = true;

                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const body = new FormData();
                    body.append('archivo', file);

                    const res = await fetch(this.parsePlantillaUrl, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body,
                    });

                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        throw new Error(data.message || 'No se pudo leer la plantilla.');
                    }

                    this.applyPlantillaLineas(data.lineas || []);
                } catch (err) {
                    this.errorMsg = err?.message || 'Error al cargar la plantilla.';
                } finally {
                    this.importingPlantilla = false;
                    if (event?.target) event.target.value = '';
                }
            },

            applyPlantillaLineas(lineas) {
                if (!Array.isArray(lineas) || lineas.length === 0) {
                    this.errorMsg = 'La plantilla no trajo líneas para cargar.';
                    return;
                }

                const mapped = lineas.map((l) => {
                    const esSin = l.bodega_id === null || l.bodega_id === undefined;
                    const bodegaId = esSin ? SIN_ASIGNAR : String(l.bodega_id);
                    const bodegaSearch = esSin
                        ? 'Sin asignar'
                        : `${l.bodega_codigo} — ${l.bodega_nombre}`;
                    const contado = Number(l.cantidad_contada) || 0;

                    return {
                        key: keySeq++,
                        product_id: String(l.product_id),
                        product_search: `${l.product_codigo} — ${l.product_nombre}`,
                        descripcion: l.descripcion || l.product_nombre || '',
                        bodega_id: bodegaId,
                        bodega_search: bodegaSearch,
                        stock_sistema: null,
                        stock_loading: false,
                        cantidad_contada: contado,
                        cantidad_contada_display: formatQty(contado),
                    };
                });

                this.lines = [...mapped, emptyLine()];
                mapped.forEach((line) => this.refreshStock(line));
            },

            async registrar() {
                if (this.saving || !this.puedeRegistrar || !this.storeUrl) return;

                this.errorMsg = '';
                this.saving = true;

                const lineas = this.lines
                    .filter((l) => this.lineValidaParaGuardar(l))
                    .map((l) => ({
                        product_id: Number(l.product_id),
                        bodega_id: idBodegaParaApi(l.bodega_id),
                        cantidad_contada: Number(l.cantidad_contada),
                        descripcion: l.descripcion || null,
                    }));

                try {
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const res = await fetch(this.storeUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify({
                            fecha: this.fecha,
                            tercero_nombre: this.terceroNombreParaGuardar(),
                            observaciones: this.observaciones || null,
                            lineas,
                        }),
                    });

                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const firstFieldError = data.errors
                            ? Object.values(data.errors).flat()[0]
                            : null;
                        throw new Error(firstFieldError || data.message || 'No se pudo registrar el conteo.');
                    }

                    if (data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }

                    this.errorMsg = 'Registrado, pero no se recibió URL de redirección.';
                } catch (err) {
                    this.errorMsg = err?.message || 'Error al registrar el conteo.';
                } finally {
                    this.saving = false;
                }
            },
        };
    });
});
