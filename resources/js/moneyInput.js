/**
 * Alpine.js component for money input with locale-aware formatting.
 * COP: 16.000 (punto miles, sin decimales)
 * USD: 16,000.00 (coma miles, 2 decimales)
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

        return {
            displayValue: '',
            wireModel: wireModel || null,
            currency: c,
            noDecimals,

            init() {
                const val = initialValue !== undefined && initialValue !== null && initialValue !== '' ? parseFloat(initialValue) : null;
                this.displayValue = val !== null && !isNaN(val) ? format(val) : '';
            },

            onBlur() {
                const parsed = parse(this.displayValue);
                this.displayValue = parsed > 0 || this.displayValue !== '' ? format(parsed) : '';
                if (this.wireModel && typeof this.$wire !== 'undefined') {
                    this.$wire.set(this.wireModel, parsed);
                }
            },

            onInput(e) {
                this.displayValue = e.target.value;
            },
        };
    });
});
