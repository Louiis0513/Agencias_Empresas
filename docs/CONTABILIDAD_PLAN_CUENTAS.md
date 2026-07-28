# Contabilidad — plan de cuentas (Centradia)

## Qué es
Catálogo PUC por tienda (`cuentas_contables`), scoped por `store_id`.

## Reglas
- **Clase** se deriva del primer dígito del código (1 Activo … 9 Orden acreedoras).
- **Import PUC base**: solo códigos de **hasta 6 dígitos** (clase/grupo/cuenta/subcuenta). Se omiten auxiliares de otras empresas.
- **Auxiliares**: las crea el usuario bajo una subcuenta de 6 dígitos (ej. `110505` → `11050501`). Quedan `es_auxiliar=true`, `nivel_agrupacion=Transaccional`, `origen=manual`.
- El Excel de referencia está en `docs/cuentas-contables-puc.xlsx` (no pegar auxiliares sucios de otra empresa).

## Bolsillos ↔ Disponible (11)

Cada bolsillo de caja apunta a una **cuenta auxiliar** del grupo 11 (`bolsillos.cuenta_contable_id`).

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
- **Desde Plan de cuentas** (Crear auxiliar): si el padre es `1105` / `1110` / `1120` y el nivel es `Transaccional`, también crea el bolsillo (visible según `activo`).
- Visibilidad: `bolsillos.is_active` ↔ `cuentas_contables.activo`.

## Mercancía / ingresos / costo — reconocimiento por código

Al crear un auxiliar con **+ Auxiliar**, el sistema infiere defaults según el código del padre:

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
3. Crear auxiliares con **+ Auxiliar** (el sistema sugiere categoría según el código).
4. Ir a **Categorías contables** y crear p. ej. «Productos» con las 4 auxiliares.
5. Al crear/editar un producto, la categoría contable es obligatoria (default «Productos»).
6. Ir a **Tipos de comprobante** (se crean FV/RC/FC/RP/CC si faltan).
7. Crear bolsillos desde Caja/Configuración, o auxiliares del 11 desde Plan de cuentas.

## Servicios
- `ImportacionPucService` — lee Excel e importa.
- `CuentaContableService` — listar, crear auxiliar (+ bolsillo si aplica), padres, reconstruir jerarquía, backfill.
- `CategoriaContableService` — categorías producto/servicio y validación de cuentas por rol.
- `TipoComprobanteService` — catálogo de tipos FV/RC/FC/RP/CC, defaults y consecutivos.
- `CajaService` — crear/actualizar bolsillo con cuenta auxiliar.

## Siguiente (no implementado aún)
1. Vincular documentos operativos al catálogo: `Invoice`→FV, `ComprobanteIngreso`→RC (reemplazar `CI-`), `Purchase`→FC, `ComprobanteEgreso`→RP.
2. Motor de asientos al vender/devolver/comprar usando `categoria_contable` + bolsillo de pago + tipo de comprobante (incl. CC manual).
