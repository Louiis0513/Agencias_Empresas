/**
 * Alpine component for cotización detail: price selection per item and reactive total.
 * Registered in app.js so it exists before Livewire/Alpine init.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('cotizacionFacturar', (rows, customerId, cotizacionId) => ({
        rows: rows,
        customerId: customerId,
        cotizacionId: cotizacionId,
        itemSelections: {},
        totalSum: 0,

        init() {
            this.rows.forEach((r, i) => {
                if (r.precio_cambio) {
                    this.itemSelections[i] = 'cotizado';
                }
            });
            this.totalSum = this.computeTotalSum();
            this.$watch('itemSelections', () => {
                this.totalSum = this.computeTotalSum();
            }, { deep: true });
        },

        getPrice(row, index) {
            if (row.precio_cambio) {
                return this.itemSelections[index] === 'actual' ? row.unit_price_actual : row.unit_price;
            }
            return row.unit_price;
        },

        getSubtotal(row, index) {
            return this.getPrice(row, index) * row.quantity;
        },

        computeTotalSum() {
            return this.rows.reduce((sum, row, i) => sum + this.getSubtotal(row, i), 0);
        },

        formatNum(n) {
            return typeof n === 'number'
                ? n.toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                : n;
        },

        facturar() {
            const items = this.rows.map((row, i) => ({
                product_id: row.product_id,
                name: row.name,
                quantity: row.quantity,
                price: this.getPrice(row, i),
                type: row.type,
                product_variant_id: row.product_variant_id,
                variant_features: row.variant_features || [],
                variant_display_name: row.variant_display_name || '',
                serial_numbers: row.serial_numbers || [],
            }));

            window.Livewire.dispatch('load-items-from-cart', {
                items,
                customer_id: this.customerId,
                cotizacion_id: this.cotizacionId,
            });
        },
    }));
});
