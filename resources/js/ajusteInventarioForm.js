/**
 * Formulario Alpine: ajuste de inventario (aumenta / disminuye).
 * Registrado en alpine:init para que exista con Livewire/wire:navigate.
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('ajusteInventarioForm', (cfg = {}) => {
        let keySeq = 1;

        const emptyLine = () => ({
            key: keySeq++,
            product_id: '',
            product_search: '',
            descripcion: '',
            direccion: 'AUMENTA',
            bodega_id: '',
            bodega_search: '',
            centro_costo_id: '',
            centro_search: '',
            cuenta_contable_id: '',
            cuenta_search: '',
            cantidad: 0,
            cantidad_display: '0.00',
            costo_unitario: 0,
            costo_display: '0.00',
            costo_touched: false,
        });

        const formatSiigo = (num, { forceDecimals = true } = {}) => {
            const n = Number(num) || 0;
            if (forceDecimals) {
                return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            return n.toLocaleString('en-US', { maximumFractionDigits: 2 });
        };

        const parseSiigo = (str) => {
            const s = String(str || '').trim();
            if (s === '' || s === '.') return 0;
            return parseFloat(s.replace(/,/g, '')) || 0;
        };

        const formatLiveSiigo = (raw, selectionStart) => {
            const str = String(raw || '');
            if (str.trim() === '') {
                return { display: '', cursor: 0, value: 0 };
            }

            const cleaned = str.replace(/[^\d.]/g, '');
            const firstDot = cleaned.indexOf('.');
            let intPart = firstDot === -1 ? cleaned : cleaned.slice(0, firstDot);
            let decPart = firstDot === -1 ? null : cleaned.slice(firstDot + 1).replace(/\./g, '').slice(0, 2);

            intPart = intPart.replace(/^0+(?=\d)/, '');
            if (intPart === '' && decPart === null && !str.includes('.')) {
                return { display: '', cursor: 0, value: 0 };
            }
            if (intPart === '') intPart = '0';

            const intNum = parseInt(intPart, 10) || 0;
            const intFormatted = intNum.toLocaleString('en-US', { maximumFractionDigits: 0 });

            let display;
            if (decPart !== null) {
                display = `${intFormatted}.${decPart}`;
            } else if (str.includes('.')) {
                display = `${intFormatted}.`;
            } else {
                display = intFormatted;
            }

            const value = decPart !== null
                ? (parseFloat(`${intNum}.${decPart}`) || 0)
                : intNum;

            const digitsBefore = str.slice(0, selectionStart ?? str.length).replace(/\D/g, '').length;
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
                display = `${intFormatted}.`;
                cursor = display.length;
            }

            return { display, cursor, value };
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

        const displayKey = (field) => (field === 'cantidad' ? 'cantidad_display' : 'costo_display');
        const isQtyField = (field) => field === 'cantidad';

        return {
            productos: cfg.productos || [],
            bodegas: cfg.bodegas || [],
            centros: cfg.centros || [],
            terceros: cfg.terceros || [],
            cuentas: cfg.cuentas || [],
            manejaBodegas: !!cfg.manejaBodegas,
            moneda: cfg.moneda || 'COP',
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
                const valid = this.lines.filter((l) => l.product_id
                    && l.direccion
                    && l.cuenta_contable_id
                    && Number(l.cantidad) > 0
                    && Number(l.costo_unitario) > 0);
                return valid.length > 0;
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
                    return this.filterCatalog(this.bodegas, line.bodega_search);
                }
                if (this.dd.type === 'cuenta') {
                    return this.filterCatalog(this.cuentas, line.cuenta_search);
                }
                return this.filterCatalog(this.centros, line.centro_search);
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

            openDd(line, type, event) {
                const el = event?.currentTarget || event?.target;
                if (!el || !el.getBoundingClientRect) return;
                const r = el.getBoundingClientRect();
                const minW = (type === 'product' || type === 'tercero' || type === 'cuenta') ? 360 : 280;
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
                else if (this.dd.type === 'cuenta') this.selectCuenta(line, item);
                else this.selectCentro(line, item);
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
                if (!typed) return null;
                // Si quedó el formato "id — nombre" sin selección limpia, usa solo el texto.
                return typed;
            },

            addLine() {
                this.lines.push(emptyLine());
            },
            removeLine(index) {
                const line = this.lines[index];
                if (!line) return;

                // Una sola fila: limpiarla en lugar de dejar la tabla vacía.
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
                    costo_touched: false,
                });
                this.ensureTrailingEmptyLine();
            },

            // Para agregar la fila siguiente: producto + cantidad + costo bastan.
            // La cuenta sigue siendo obligatoria solo al contabilizar (puedeContabilizar).
            lineCompleta(line) {
                if (!line?.product_id) return false;
                if (!(Number(line.cantidad) > 0)) return false;
                if (!(Number(line.costo_unitario) > 0)) return false;
                return true;
            },

            ensureTrailingEmptyLine() {
                const last = this.lines[this.lines.length - 1];
                if (!last || this.lineCompleta(last)) {
                    this.addLine();
                }
            },

            costoInvalido(line) {
                if (!line.product_id || !line.costo_touched) return false;
                return !(Number(line.costo_unitario) > 0);
            },

            onLineEnter(index) {
                const line = this.lines[index];
                if (!line?.product_id) return;

                line.costo_touched = true;
                if (!(Number(line.costo_unitario) > 0)) {
                    return;
                }

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
                    line.costo_unitario = 0;
                    line.costo_display = '0.00';
                    line.costo_touched = false;
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
                line.costo_unitario = 0;
                line.costo_display = '0.00';
                line.costo_touched = false;

                this.closeDd();
                this.$nextTick(() => {
                    const row = document.querySelector(`[data-dd-anchor="product-${line.key}"]`)?.closest('tr');
                    const cantidadInput = row?.querySelector('input[inputmode="decimal"]');
                    cantidadInput?.focus();
                    cantidadInput?.select();
                });
            },

            onBodegaSearchInput(line) {
                if (line.bodega_id) line.bodega_id = '';
            },
            selectBodega(line, b) {
                line.bodega_id = String(b.id);
                line.bodega_search = `${b.codigo} — ${b.nombre}`;
                this.closeDd();
                this.ensureTrailingEmptyLine();
            },

            onCentroSearchInput(line) {
                if (line.centro_costo_id) line.centro_costo_id = '';
            },
            selectCentro(line, c) {
                line.centro_costo_id = String(c.id);
                line.centro_search = `${c.codigo} — ${c.nombre}`;
                this.closeDd();
            },

            onCuentaSearchInput(line) {
                if (line.cuenta_contable_id) line.cuenta_contable_id = '';
            },
            selectCuenta(line, c) {
                line.cuenta_contable_id = String(c.id);
                line.cuenta_search = `${c.codigo} — ${c.nombre}`;
                this.closeDd();
                this.ensureTrailingEmptyLine();
            },

            onMoneyInput(line, field, event) {
                const el = event.target;
                const original = String(el.value || '');
                const sel = el.selectionStart ?? original.length;

                let raw;
                let cursorHint = sel;
                if (isQtyField(field)) {
                    // Cantidad: coma puede ser decimal.
                    raw = original.replace(/,/g, '.');
                } else {
                    // Costo Siigo: coma = miles → quitar, no convertir a punto.
                    const commasBefore = (original.slice(0, sel).match(/,/g) || []).length;
                    raw = original.replace(/,/g, '');
                    cursorHint = Math.max(0, sel - commasBefore);
                }

                const result = isQtyField(field)
                    ? formatLiveQty(raw, cursorHint)
                    : formatLiveSiigo(raw.replace(/[^\d.]/g, ''), cursorHint);

                line[field] = result.value;
                line[displayKey(field)] = result.display;
                if (field === 'costo_unitario') {
                    line.costo_touched = true;
                }
                this.$nextTick(() => {
                    try {
                        el.setSelectionRange(result.cursor, result.cursor);
                    } catch (_) { /* ignore */ }
                    this.ensureTrailingEmptyLine();
                });
            },
            onMoneyBlur(line, field) {
                const key = displayKey(field);
                if (isQtyField(field)) {
                    const parsed = parseQty(line[key]);
                    line[field] = parsed;
                    line[key] = formatQty(parsed);
                    this.ensureTrailingEmptyLine();
                    return;
                }
                // Comas = separador de miles (en-US), no decimal.
                const parsed = parseSiigo(String(line[key] || '').replace(/,/g, ''));
                line[field] = parsed;
                line[key] = formatSiigo(parsed);
                line.costo_touched = true;
                this.ensureTrailingEmptyLine();
            },

            lineTotal(line) {
                return (Number(line.cantidad) || 0) * (Number(line.costo_unitario) || 0);
            },
            get grandTotal() {
                return this.lines.reduce((sum, line) => sum + this.lineTotal(line), 0);
            },
            formatMoney(n) {
                return formatSiigo(n);
            },

            async contabilizar() {
                if (this.saving || !this.puedeContabilizar || !this.storeUrl) return;

                this.errorMsg = '';
                this.saving = true;

                const lineas = this.lines
                    .filter((l) => l.product_id
                        && l.direccion
                        && l.cuenta_contable_id
                        && Number(l.cantidad) > 0
                        && Number(l.costo_unitario) > 0)
                    .map((l) => ({
                        product_id: Number(l.product_id),
                        bodega_id: l.bodega_id ? Number(l.bodega_id) : null,
                        centro_costo_id: l.centro_costo_id ? Number(l.centro_costo_id) : null,
                        cuenta_contable_id: Number(l.cuenta_contable_id),
                        direccion: l.direccion,
                        cantidad: Number(l.cantidad),
                        costo_unitario: Number(l.costo_unitario),
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
