/**
 * Alpine.js component for money input with locale-aware formatting.
 * COP: 16.000 (punto miles, sin decimales) — formatea al escribir
 * USD: 16,000.00 (coma miles, 2 decimales) — formatea al escribir
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('moneyInput', (currency, initialValue, wireModel) => {
        const c = (currency || 'COP').toUpperCase();
        const noDecimals = ['COP', 'CLP', 'JPY'].includes(c);

        const format = (num) => {
            const n = parseFloat(num) || 0;
            if (noDecimals) {
                return Math.round(n).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
            }
            return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        const parse = (str) => {
            const s = String(str || '').trim();
            if (s === '') return 0;
            if (noDecimals) {
                return parseFloat(s.replace(/\./g, '').replace(/,/g, '')) || 0;
            }
            return parseFloat(s.replace(/,/g, '')) || 0;
        };

        /** Formatea mientras escribe, preservando la posición del cursor lo mejor posible. */
        const formatLive = (raw, selectionStart) => {
            const str = String(raw || '');
            if (str.trim() === '') {
                return { display: '', cursor: 0, value: 0 };
            }

            if (noDecimals) {
                const digits = str.replace(/\D/g, '');
                if (digits === '') {
                    return { display: '', cursor: 0, value: 0 };
                }
                const value = parseInt(digits, 10) || 0;
                const display = format(value);
                // Cursor al final tras reformatear (simple y estable)
                return { display, cursor: display.length, value };
            }

            // Con decimales: permitir escribir parte decimal
            const cleaned = str.replace(/[^\d.]/g, '');
            const firstDot = cleaned.indexOf('.');
            let intPart = firstDot === -1 ? cleaned : cleaned.slice(0, firstDot);
            let decPart = firstDot === -1 ? null : cleaned.slice(firstDot + 1).replace(/\./g, '').slice(0, 2);

            intPart = intPart.replace(/^0+(?=\d)/, '') || (decPart !== null ? '0' : intPart);
            if (intPart === '' && decPart === null) {
                return { display: '', cursor: 0, value: 0 };
            }

            const intNum = parseInt(intPart || '0', 10) || 0;
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
                ? parseFloat(`${intNum}.${decPart}`) || 0
                : intNum;

            // Intentar conservar dígitos a la izquierda del cursor
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
            if (str.endsWith('.') && !display.includes('.', display.indexOf('.') === display.length - 1 ? -1 : 0)) {
                // no-op
            }
            if (raw.endsWith('.') && !display.endsWith('.')) {
                display = `${intFormatted}.`;
                cursor = display.length;
            }

            return { display, cursor, value };
        };

        return {
            displayValue: '',
            numericValue: 0,
            wireModel: wireModel || null,
            currency: c,
            noDecimals,

            init() {
                const val = initialValue !== undefined && initialValue !== null && initialValue !== '' ? parseFloat(initialValue) : null;
                this.numericValue = val !== null && !isNaN(val) ? val : 0;
                this.displayValue = val !== null && !isNaN(val) ? format(val) : '';
            },

            syncWire() {
                if (this.wireModel && typeof this.$wire !== 'undefined') {
                    this.$wire.set(this.wireModel, this.numericValue);
                }
            },

            onBlur() {
                if (this.displayValue === '' || this.displayValue === '.') {
                    this.displayValue = '';
                    this.numericValue = 0;
                } else {
                    this.numericValue = parse(this.displayValue);
                    this.displayValue = this.numericValue > 0 || this.displayValue !== ''
                        ? format(this.numericValue)
                        : '';
                }
                this.syncWire();
            },

            onInput(e) {
                const el = e.target;
                const result = formatLive(el.value, el.selectionStart);
                this.displayValue = result.display;
                this.numericValue = result.value;
                this.$nextTick(() => {
                    try {
                        el.setSelectionRange(result.cursor, result.cursor);
                    } catch (_) { /* ignore */ }
                });
                this.syncWire();
            },
        };
    });
});
