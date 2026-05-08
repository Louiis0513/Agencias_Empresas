document.addEventListener('alpine:init', () => {
    Alpine.data('movimientosFiltros', (config) => {
        const cfg = config && typeof config === 'object' ? config : {};

        return {
            drawerOpen: false,
            panelEmpleados: false,
            empleadoSearch: '',
            selectedBolsillos: [...(cfg.bolsilloIds || [])],
            selectedEmpleadoUserIds: [...(cfg.empleadoUserIds || [])],
            empleadosList: cfg.empleados || [],
            movCustomerLabel: cfg.movCustomerLabel || '',
            movProveedorNombre: cfg.movProveedorNombre || '',
            labels: cfg.labels || {},
            clearUrl: cfg.clearUrl || '',

            toggleBolsillo(id) {
                const pid = parseInt(id, 10);
                const i = this.selectedBolsillos.indexOf(pid);
                if (i >= 0) {
                    this.selectedBolsillos.splice(i, 1);
                } else {
                    this.selectedBolsillos.push(pid);
                }
            },

            isBolsilloOn(id) {
                return this.selectedBolsillos.includes(parseInt(id, 10));
            },

            bolsilloChipClass(id) {
                return this.isBolsilloOn(id)
                    ? 'bg-gray-900 text-white dark:bg-brand dark:text-white border-gray-900 dark:border-brand ring-2 ring-brand/30'
                    : 'bg-white dark:bg-white/5 text-gray-700 dark:text-gray-200 border-gray-200 dark:border-white/10';
            },

            toggleEmpleado(uid) {
                const u = parseInt(uid, 10);
                const i = this.selectedEmpleadoUserIds.indexOf(u);
                if (i >= 0) {
                    this.selectedEmpleadoUserIds.splice(i, 1);
                } else {
                    this.selectedEmpleadoUserIds.push(u);
                }
            },

            isEmpOn(uid) {
                return this.selectedEmpleadoUserIds.includes(parseInt(uid, 10));
            },

            filteredEmpleados() {
                const q = this.empleadoSearch.toLowerCase().trim();
                if (! q) {
                    return this.empleadosList;
                }
                return this.empleadosList.filter((e) =>
                    (e.name || '').toLowerCase().includes(q) ||
                    (e.subtitle || '').toLowerCase().includes(q),
                );
            },

            handleCustomerChosen(e) {
                let p = e.detail;
                if (Array.isArray(p)) {
                    p = p[0];
                }
                const id = p && p.customer_id;
                const cust = p && p.customer;
                const el = document.getElementById('mov_customer_id_hidden');
                if (el && id) {
                    el.value = id;
                }
                this.movCustomerLabel = cust && cust.name ? cust.name : '';
            },

            handleCustomerClear() {
                const el = document.getElementById('mov_customer_id_hidden');
                if (el) {
                    el.value = '';
                }
                this.movCustomerLabel = '';
            },

            handleProveedorChosen(e) {
                let d = e.detail;
                if (Array.isArray(d)) {
                    d = d[0];
                }
                const id = d && d.proveedor_id;
                const nombre = d && d.nombre;
                const el = document.getElementById('mov_proveedor_id_hidden');
                if (el && id) {
                    el.value = id;
                }
                this.movProveedorNombre = nombre ? String(nombre) : '';
            },

            handleProveedorClear() {
                const el = document.getElementById('mov_proveedor_id_hidden');
                if (el) {
                    el.value = '';
                }
                this.movProveedorNombre = '';
            },

            empleadosSummary() {
                if (! this.selectedEmpleadoUserIds.length) {
                    return this.labels.todosEmpleados || '';
                }
                return `${this.labels.seleccionados || ''} (${this.selectedEmpleadoUserIds.length})`;
            },

            proveedorSummary() {
                return this.movProveedorNombre || (this.labels.todosProveedores || '');
            },

            clienteSummary() {
                return this.movCustomerLabel || (this.labels.todosClientes || '');
            },

            limpiarTodo() {
                window.location.href = this.clearUrl;
            },
        };
    });
});
