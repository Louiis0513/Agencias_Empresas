# Contabilidad — plan de cuentas (Centradia)

## Qué es
Catálogo PUC por tienda (`cuentas_contables`), scoped por `store_id`.

## Reglas
- **Clase** se deriva del primer dígito del código (1 Activo … 9 Orden acreedoras).
- **Import PUC base**: solo códigos de **hasta 6 dígitos** (clase/grupo/cuenta/subcuenta). Se omiten auxiliares de otras empresas.
- **Jerarquía (creación de hijos):** Clase(1) → Grupo(2) → Cuenta(4) → Subcuenta(6) → Auxiliar(8) → Subauxiliar(10). Sufijo: **1 dígito** (1–9) de Clase→Grupo; **2 dígitos** (`01`–`99`) en el resto. No se crean clases 1–9 desde la UI (vienen del import); no hay hijos bajo longitud 10.
- **Transaccional:** al crear, `nivel_agrupacion=Transaccional` desde longitud **≥ 8** (auxiliar/subauxiliar). Subcuenta de 6 queda como estructura salvo que el import ya la marcara. Asientos / formas / impuestos exigen auxiliar + transaccional + activa.
- **Traslado Siigo (primer hijo):** si el padre era hoja con usos (`movimientos_contables`, bolsillos, formas de pago, impuestos, categorías contables), al crear el **primer** hijo con `confirmar_traslado` se reasignan esos FKs al hijo y el padre deja de ser `Transaccional`. Si ya tenía hijos, se crea el nuevo sin migrar.
- **Atajo + Auxiliar:** eliminado de la UI; usar el botón contextual **+ Auxiliar** en subcuentas de 6 dígitos (`crearAuxiliar` sigue disponible para otros servicios).
- El Excel de referencia está en `docs/cuentas-contables-puc.xlsx` (no pegar auxiliares sucios de otra empresa).

## Bolsillos ↔ Disponible (11)

> **Puente operativo temporal.** El cobro/pago por bolsillo sigue activo en caja/CI/CE/facturas, pero con aviso de *flujo incompleto* en la UI. El flujo contable definitivo será **Forma de pago → cuenta PUC → asiento** (POS/documentos aún no rewirados).

Cada bolsillo de caja apunta a una **cuenta auxiliar** del grupo 11 (`bolsillos.cuenta_contable_id`). Es la capa de **saldo** de disponible, no el catálogo de medios de pago de documentos.

| Tipo en Caja | Padre PUC (6 dígitos) | Significado |
|---|---|---|
| Efectivo y equivalentes | `110505` | Caja general |
| Cuenta corriente (pesos) | `111005` | Bancos – moneda nacional (COP) |
| Cuenta de ahorro | `112005` | Cuentas de ahorro |
| Cuenta en moneda extranjera | `111010` | Bancos – moneda extranjera |

Notas PUC:
- `111005` **Moneda nacional** = depósito bancario en pesos, no “banco nacional”.
- Cuentas en divisas van en `111010`, **no** en `111510` (eso es remesas en tránsito).
- Remesas `1115` no crean bolsillo automático.

### Creación bidireccional
- **Desde Caja** (Crear bolsillo): crea la auxiliar con defaults `Caja - Bancos`, `Formas de pago`, `No maneja vencimiento`, clase `Activo`, nivel `Transaccional`, y el bolsillo vinculado.
- **Desde Plan de cuentas** (crear hijo auxiliar/subauxiliar): si el código es Disponible operable (`1105` / `1110` / `1120`) y el nivel es `Transaccional`, también crea el bolsillo (visible según `activo`).
- Visibilidad: `bolsillos.is_active` ↔ `cuentas_contables.activo`.

## Mercancía / ingresos / costo — reconocimiento por código

Al crear un auxiliar/subauxiliar con el botón contextual, el sistema infiere defaults según el código del padre:

| Prefijo del padre | Categoría | Relacionado con | Clase |
|---|---|---|---|
| `11…` | Caja - Bancos | Formas de pago | Activo |
| `14…` | Inventarios | Grupo de inventarios - Inventario | Activo |
| `61…` / `62…` | Costo de ventas | Costo de ventas | Costos de venta |
| `4175…` | Ingresos | Devoluciones en ventas | Ingresos |
| resto de `4…` | Ingresos | Ingresos operacionales | Ingresos |

Padres típicos de mercancía (revenda): `143501`, `613505`, `413501`, `417505`.

**Maneja vencimientos** = detalle de cartera/proveedores. Inventario, ingreso y costo van en `No maneja vencimiento`.

## Artisan
```bash
php artisan contable:importar-puc {store_id|slug}
php artisan contable:importar-puc {store} --con-auxiliares
php artisan contable:vincular-bolsillos {store_id|slug}
```

`contable:vincular-bolsillos` crea auxiliares para bolsillos antiguos sin cuenta (efectivo → `110505`, banco → `111005`).

Si falta una subcuenta padre (ej. `111010`), al crear el bolsillo se crea automáticamente bajo su cuenta de 4 dígitos (`1110`), siempre que exista el grupo `11`.

## Categorías contables de productos/servicios

Tabla `categorias_contables`: puente entre el catálogo y las cuentas auxiliares (estilo Siigo).

| Campo | Producto | Servicio |
|---|---|---|
| Inventario | auxiliar `14…` (ej. mercancía) | auxiliar propia (ej. Inventario – servicios) |
| Costo de ventas | auxiliar `61…`/`62…` | auxiliar propia (ej. Costo de ventas – servicios) |
| Ingreso | auxiliar `4…` | auxiliar propia |
| Devolución | auxiliar `4175…` | auxiliar propia |

- UI: Financiero → **Categorías contables**
- Al abrir la pantalla se aseguran por defecto (si faltan) **Productos** y **Servicios** con sus 4 auxiliares (estilo Siigo).
- Si **Servicios** ya existía sin inventario/costo, se completan al recargar.
- Permisos: `contabilidad.categorias.view|create|edit`
- Productos: `products.categoria_contable_id` **obligatoria**. Al crear se preselecciona «Productos»; si falta, `ProductService` la asigna automáticamente.

## Tipos de comprobante

Tabla `tipos_comprobante`: catálogo por tienda estilo Siigo (`familia` + `codigo` + consecutivo).

| Familia | Nombre | Uso futuro en Centradia |
|---|---|---|
| `FV` | Factura de venta | `Invoice` |
| `RC` | Recibo de caja | `ComprobanteIngreso` (hoy `CI-`) |
| `FC` | Factura de compra | `Purchase` |
| `RP` | Recibo de pago / egreso | `ComprobanteEgreso` (hoy `CE-`) |
| `CC` | Comprobante contable | Ajustes / asientos manuales |

Campos clave: `prefijo`, `numeracion_automatica`, `siguiente_numero`, `activo`, `maneja_centro_costos`, `libro_oficial` (`ventas`\|`compras`\|null). Unique `(store_id, familia, codigo)`.

- UI: Financiero → **Tipos de comprobante**
- Al abrir se aseguran por defecto (si faltan) FV/RC/FC/RP/CC con código `1`.
- `TipoComprobanteService::tomarSiguienteNumero()` reserva el consecutivo con `lockForUpdate` (aún no enganchado a CI/CE).
- Permisos: `contabilidad.tipos.view|create|edit`

## Cómo usar
1. Entrar a la tienda → Financiero → **Plan de cuentas**.
2. Pulsar **Importar PUC base**.
3. En el **árbol**, despliega Clase → Grupo → … y usa el botón contextual (**+ Grupo**, **+ Cuenta**, etc.). La búsqueda muestra lista plana.
4. Si el padre tiene movimientos/vínculos, confirmar el traslado al nuevo hijo en el modal.
5. Ir a **Categorías contables** y crear p. ej. «Productos» con las 4 auxiliares.
6. Al crear/editar un producto, la categoría contable es obligatoria (default «Productos»).
7. Ir a **Tipos de comprobante** (se crean FV/RC/FC/RP/CC si faltan).
8. Crear bolsillos desde Caja/Configuración, o auxiliares del 11 desde Plan de cuentas.

## Servicios
- `ImportacionPucService` — lee Excel e importa.
- `CuentaContableService` — listar, `crearHijo` / `crearAuxiliar` (+ bolsillo si aplica), `metaCrearHijo`, `padreTieneUsos`, traslado de usos, padres, reconstruir jerarquía, backfill.
- `CategoriaContableService` — categorías producto/servicio y validación de cuentas por rol.
- `TipoComprobanteService` — catálogo de tipos FV/RC/FC/RP/CC, defaults y consecutivos.
- `ImpuestoService` — catálogo de impuestos (IVA, retenciones, etc.) con cuentas ventas/compras/devoluciones.
- `FormaPagoService` — catálogo de formas de pago (aplica a + cuenta + medio DIAN) y defaults con auxiliares.
- `CajaService` — crear/actualizar bolsillo con cuenta auxiliar.

## Impuestos (catálogo v1)
- Tabla `impuestos` por tienda: en uso, código, nombre, tipo, por valor, tarifa y 4 cuentas auxiliares.
- Tipos fijos en código (`Impuesto::TIPOS`): IVA, Retefuente, ReteICA, ReteIVA, Impoconsumo, Bebidas azucaradas, Comestibles ultraprocesados.
- UI: Financiero → **Impuestos**. Permisos `contabilidad.impuestos.view|create|edit`.
- Aún no se aplica automáticamente en facturas/compras ni incluye autorretenciones.

## Formas de pago (catálogo v1)
- Tabla `formas_pago` por tienda: en uso, código, nombre, `aplica_a` (`cartera` | `proveedores` | `ambos`), `cuenta_contable_id`, `medio_pago_dian` (catálogo fijo DIAN PaymentMeansCode), `es_pago_en_linea`.
- Varias formas pueden apuntar a la **misma** cuenta auxiliar (N:1).
- Al abrir la pantalla se aseguran defaults (si faltan y existe el padre PUC de 6 dígitos): **Efectivo** (`110505` + medio `10`), **Transferencia** (`111005` + medio `45`), **Crédito** (`130505`, aplica cartera, medio `1`).
- UI: Financiero → **Formas de pago**. Permisos `contabilidad.formas-pago.view|create|edit`.
- Aún no se usa en facturas/POS/CI/CE (sigue bolsillo).

## Usado en (Plan de cuentas)
- Columna **Usado en** en el listado del PUC: se deriva de catálogos (Formas de pago, Impuestos - Ventas/Compras/Dev.), no se edita a mano.
- En crear auxiliar, «Relacionado con» queda como sugerencia de solo lectura según el código padre.

## Asientos manuales CC (implementado)
- Núcleo: `comprobantes_contables` + `movimientos_contables`, orquestado por `AsientoContableService`.
- Flujo: crear/editar borrador balanceado → contabilizar con consecutivo CC → reversar mediante un nuevo asiento inverso.
- Reglas: solo auxiliares transaccionales activas de la tienda; una línea usa débito o crédito; débitos = créditos; contabilizados no se editan ni eliminan.
- UI: Financiero → **Asientos manuales**.
- Permisos: `contabilidad.comprobantes.view|create|edit|post|reverse`.
- Libro Diario inicial: consulta cronológica de movimientos contabilizados (incluye originales reversados y sus asientos inversos).
- Libro Mayor: movimientos agrupados por cuenta auxiliar con saldo inicial, corrido y final (informe de consulta).
- Pendiente: Balance de comprobación.

## Pendiente UI PUC
- Panel detalle derecha estilo Siigo (el árbol expandible lazy-load ya está en el listado).

## Siguiente (automatización no implementada)
0. **Matriz de eventos (especificación v1):** ver [`docs/MATRIZ_EVENTOS_CONTABLES.md`](MATRIZ_EVENTOS_CONTABLES.md). Debe aprobarla el contador antes de automatizar documentos operativos.
1. Vincular documentos operativos al catálogo de tipos: `Invoice`→FV, `ComprobanteIngreso`→RC, `Purchase`→FC, `ComprobanteEgreso`→RP.
2. Motor de asientos al vender/devolver/comprar usando `categoria_contable` + **forma de pago** (cuenta PUC) + tipo de comprobante + **`tercero_id`** (ver `docs/TERCEROS.md`). El bolsillo queda solo para saldo de disponible `11…` cuando aplique.
3. Construir Balance de comprobación desde los saldos del Libro Mayor.

## Terceros
Maestro unificado (clientes/proveedores/trabajadores): ver [`docs/TERCEROS.md`](TERCEROS.md). Los asientos deben usar `tercero_id`.
