/**
 * Formulario Alpine: nota de traslado entre bodegas.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('trasladoBodegasForm', (cfg = {}) => {
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
            bodega_origen_id: '',
            bodega_origen_search: '',
            bodega_destino_id: '',
            bodega_destino_search: '',
            cantidad: 0,
            cantidad_display: '0.00',
        });

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

            get puedeContabilizar() {
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
                if (this.dd.type === 'bodega_origen') {
                    return this.filterBodegas(line.bodega_origen_search);
                }
                if (this.dd.type === 'bodega_destino') {
                    return this.filterBodegas(line.bodega_destino_search);
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
                    || 'sin_asignar'.includes(q)
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
                else if (this.dd.type === 'bodega_origen') this.selectBodegaOrigen(line, item);
                else this.selectBodegaDestino(line, item);
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
                if (!(Number(line.cantidad) > 0)) return false;
                const origen = line.bodega_origen_id ? String(line.bodega_origen_id) : '';
                const destino = line.bodega_destino_id ? String(line.bodega_destino_id) : '';
                // Ambas ubicaciones deben elegirse (bodega real o «Sin asignar»).
                if (!origen || !destino) return false;
                if (origen === destino) return false;
                return true;
            },

            lineValidaParaGuardar(line) {
                return this.lineCompleta(line);
            },

            ensureTrailingEmptyLine() {
                const last = this.lines[this.lines.length - 1];
                if (!last || this.lineCompleta(last)) {
                    this.addLine();
                }
            },

            onLineEnter(index) {
                const line = this.lines[index];
                if (!line?.product_id) return;
                if (!this.lineCompleta(line)) return;

                this.ensureTrailingEmptyLine();
                this.$nextTick(() => {
                    const next = this.lines[index + 1];
                    if (!next) return;
                    const el = document.querySelector(`[data-dd-anchor="product-${next.key}"] input`);
                    el?.focus();
                });
            },

            onProductSearchInput(line) {
                if (line.product_id) {
                    line.product_id = '';
                    line.descripcion = '';
                }
            },
            selectProduct(line, p) {
                line.product_id = String(p.id);
                line.product_search = `${p.codigo} — ${p.nombre}`;
                line.descripcion = p.nombre;
                if (!line.cantidad_display) {
                    line.cantidad = 0;
                    line.cantidad_display = '0.00';
                }
                this.closeDd();
                this.$nextTick(() => {
                    const row = document.querySelector(`[data-dd-anchor="product-${line.key}"]`)?.closest('tr');
                    const qty = row?.querySelector('input[inputmode="decimal"]');
                    qty?.focus();
                    qty?.select();
                });
            },

            onBodegaOrigenSearchInput(line) {
                if (line.bodega_origen_id) line.bodega_origen_id = '';
            },
            selectBodegaOrigen(line, b) {
                const esSin = !b || b.id === SIN_ASIGNAR || b.id === null;
                line.bodega_origen_id = esSin ? SIN_ASIGNAR : String(b.id);
                line.bodega_origen_search = labelBodega(esSin ? OPCION_SIN_ASIGNAR : b);
                this.closeDd();
                this.ensureTrailingEmptyLine();
            },

            onBodegaDestinoSearchInput(line) {
                if (line.bodega_destino_id) line.bodega_destino_id = '';
            },
            selectBodegaDestino(line, b) {
                const esSin = !b || b.id === SIN_ASIGNAR || b.id === null;
                line.bodega_destino_id = esSin ? SIN_ASIGNAR : String(b.id);
                line.bodega_destino_search = labelBodega(esSin ? OPCION_SIN_ASIGNAR : b);
                this.closeDd();
                this.ensureTrailingEmptyLine();
            },

            onQtyInput(line, event) {
                const el = event.target;
                const original = String(el.value || '');
                const sel = el.selectionStart ?? original.length;
                const raw = original.replace(/,/g, '.');
                const result = formatLiveQty(raw, sel);
                line.cantidad = result.value;
                line.cantidad_display = result.display;
                this.$nextTick(() => {
                    try {
                        el.setSelectionRange(result.cursor, result.cursor);
                    } catch (_) { /* ignore */ }
                    this.ensureTrailingEmptyLine();
                });
            },
            onQtyBlur(line) {
                const parsed = parseQty(line.cantidad_display);
                line.cantidad = parsed;
                line.cantidad_display = formatQty(parsed);
                this.ensureTrailingEmptyLine();
            },

            async contabilizar() {
                if (this.saving || !this.puedeContabilizar || !this.storeUrl) return;

                this.errorMsg = '';
                this.saving = true;

                const lineas = this.lines
                    .filter((l) => this.lineValidaParaGuardar(l))
                    .map((l) => ({
                        product_id: Number(l.product_id),
                        bodega_origen_id: idBodegaParaApi(l.bodega_origen_id),
                        bodega_destino_id: idBodegaParaApi(l.bodega_destino_id),
                        cantidad: Number(l.cantidad),
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
                        throw new Error(firstFieldError || data.message || 'No se pudo contabilizar.');
                    }

                    if (data.redirect) {
                        window.location.href = data.redirect;
                        return;
                    }

                    this.errorMsg = 'Contabilizado, pero no se recibió URL de redirección.';
                } catch (err) {
                    this.errorMsg = err?.message || 'Error al contabilizar.';
                } finally {
                    this.saving = false;
                }
            },
        };
    });
});
