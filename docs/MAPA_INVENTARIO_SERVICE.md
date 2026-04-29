# Mapa de uso de InventarioService

Responsabilidad del servicio: **registrar movimientos de entrada y salida** y **consultar stock disponible**. Este documento indica **dónde** se usa **cada** función del servicio (públicas y protegidas) y el **flujo** (vista → controlador/servicio → InventarioService) para poder mejorarlo de forma coherente.

---

## 1. Índice completo de funciones de InventarioService

En `InventarioService` hay varios métodos públicos de primer nivel; esta tabla resume los más usados desde fuera del servicio y los puntos de entrada habituales (los números son solo referencia, no numeración rígida del archivo).

| # | Método | Visibilidad | ¿Usado desde fuera del servicio? | Dónde se usa (resumen) |
|---|--------|-------------|-----------------------------------|------------------------|
| 1 | **detectorDeVariantesEnLotes** | public static | Sí | ProductService, SelectBatchVariantModal, vistas (producto-detalle, compiladas) |
| 2 | **resolveUnitCostForMovement** | protected | No (solo interno) | Llamado por registrarMovimiento al crear el registro MovimientoInventario |
| 3 | **actualizarStock** | public | No (solo interno) | Llamado por registrarMovimiento tras cada entrada/salida (actualiza products.stock) |
| 4 | **actualizarCostoPonderado** | public | No (solo interno) | Llamado por registrarMovimiento tras cada entrada/salida (recalcula product.cost) |
| 5 | **registrarMovimiento** | public | Sí | PurchaseService, ProductService, CreateMovimientoInventarioModal |
| 6 | **registrarSalidaPorCantidadFIFO** | public | Sí | InvoiceService, VentaService, CreateMovimientoInventarioModal (producto **simple**, SALIDA manual) |
| 7 | **registrarSalidaPorSeriales** | public | Sí | VentaService |
| 8 | **stockDisponible** | public | No (solo interno) | Llamado por validarStockDisponible para cada ítem |
| 9 | **validarStockDisponible** | public | Sí | VentasCarrito (Livewire), VentaService |
| 10 | **movimientosQuery** | public | Sí | InventarioService::listarMovimientos (wrapper), InventoryMovementsExcelExportService (export Excel) |
| 11 | **listarMovimientos** | public | Sí | Paginador sobre `movimientosQuery`; uso interno / pantallas que paginan el mismo criterio que el export |
| 12 | **productosConInventario** | public | Sí | Servicios o vistas que listan catálogo apto para inventario (evolucionar según necesidad) |
| 13 | **buscarProductosInventario** | public | Sí | SelectItemModal, flujos que delegan en InventarioService para búsqueda |
| — | **registrarSalidaPorVarianteFIFO** | public | Sí | CreateMovimientoInventarioModal (producto **por lotes**, SALIDA manual por variante) |

---

## 1b. Métodos públicos con uso externo (resumen)

| Método | Dónde se llama | Flujo (vista / origen) |
|--------|----------------|-------------------------|
| **registrarMovimiento** | PurchaseService, ProductService, CreateMovimientoInventarioModal | Ver sección 2 |
| **registrarSalidaPorCantidadFIFO** | InvoiceService, VentaService, CreateMovimientoInventarioModal (simple manual) | Factura, venta a crédito, salida manual sin variante |
| **registrarSalidaPorVarianteFIFO** | CreateMovimientoInventarioModal | Salida manual por variante (batch) |
| **registrarSalidaPorSeriales** | VentaService | Venta a crédito (detalles con serial_numbers) |
| **validarStockDisponible** | VentasCarrito (Livewire), VentaService | Carrito de ventas y al facturar a crédito |
| **stockDisponible** | Solo internamente en validarStockDisponible | — |
| **movimientosQuery / listarMovimientos** | Export Excel movimientos, listados paginados que reutilicen el mismo filtro | Historial de movimientos (ya no hay vista dedicada `/inventario`; el listado masivo es en Excel desde productos) |
| **productosConInventario** | Donde se necesite listar productos con inventario | Catálogo auxiliar |
| **buscarProductosInventario** | SelectItemModal y patrones similares | Selector de producto en compras, movimientos, etc. |
| **detectorDeVariantesEnLotes** (static) | ProductService, SelectBatchVariantModal, producto-detalle.blade.php, compilado de vistas | Comparar/detectar variantes en lotes (features normalizados) |
| **actualizarStock** | Solo internamente en registrarMovimiento | — |
| **actualizarCostoPonderado** | Solo internamente en registrarMovimiento | — |

---

## 1c. Funciones solo de uso interno (detalle)

Estas funciones **no** son llamadas desde ningún otro servicio ni controlador; solo desde dentro de `InventarioService`. Incluirlas en el mapa evita olvidarlas al refactorizar.

### resolveUnitCostForMovement (protected)

- **Qué hace:** Calcula el `unit_cost` que se guarda en la fila de `movimientos_inventario` (para reportes y valorización). Para ENTRADA batch usa promedio ponderado del lote; para ENTRADA serializado usa promedio de costos por unidad; para SALIDA usa el `unit_cost` pasado en los datos si existe.
- **Quién la llama:** Solo `registrarMovimiento` (línea ~391), justo antes de crear el registro `MovimientoInventario`.
- **Importante al mejorar:** Cualquier cambio en cómo se calcula el costo del movimiento debe mantenerse aquí o en el flujo que llama a `registrarMovimiento`.

### actualizarStock (public)

- **Qué hace:** Actualiza el campo `stock` de la tabla `products`: ENTRADA suma `quantity`, SALIDA resta (y lanza excepción si no hay suficiente).
- **Quién la llama:** Solo `registrarMovimiento` (líneas ~223 y ~404), después de haber actualizado ya BatchItems o ProductItems.
- **Importante al mejorar:** Es la única lógica que escribe en `products.stock`. Si se centraliza o se cambia la política de “stock total”, debe hacerse aquí (o en un único punto que esta función use).

### actualizarCostoPonderado (public)

- **Qué hace:** Recalcula el costo ponderado del producto desde la fuente de verdad (batch_items o product_items) y actualiza `products.cost`.
- **Quién la llama:** Solo `registrarMovimiento` (líneas ~224 y ~405), después de `actualizarStock`.
- **Importante al mejorar:** ProductService tiene su propio `updateProductCost` para otros flujos (p. ej. después de crear stock inicial serializado/lote); no llama a `actualizarCostoPonderado`. Si en el futuro se unifica el “costo del producto”, habría que decidir si todo pasa por esta función o por una compartida.

---

## 2. registrarMovimiento — entradas y salidas “a mano”

Se usa en **cuatro** contextos: crear producto con stock inicial, compra aprobada, movimiento manual desde inventario y añadir variantes al producto.

### 2.1 Crear producto con stock inicial

- **Vista/origen:** Modal “Crear producto” (Livewire `CreateProductModal`).
- **Flujo:** `CreateProductModal` → `ProductService::createProduct()` → **InventarioService::registrarMovimiento**.
- **Dónde en ProductService:**
  - **Producto simple con stock inicial:** `createProduct()` llama `registrarMovimiento` con ENTRADA, quantity y unit_cost (líneas ~131-138).
  - **Producto serializado con unidades:** `createSerializedItems()` llama `registrarMovimiento` con ENTRADA y `serial_items` (líneas ~571-579).
  - **Producto por lote con variantes y stock:** `createBatchFromVariants()` llama `registrarMovimiento` con ENTRADA y `batch_data` (líneas ~663-674).
- **Tipo de movimiento:** siempre **ENTRADA** (stock inicial).

### 2.2 Aprobar compra de productos

- **Vista/origen:** Detalle de compra → botón “Aprobar” (`stores.compras.{purchase}.aprobar`).
- **Flujo:** `StoreController::approvePurchase()` → `PurchaseService::approvePurchase()` → `registrarMovimientosPorAprobacion()` → **InventarioService::registrarMovimiento** (varias veces por detalle).
- **Dónde en PurchaseService:** `registrarMovimientosPorAprobacion()` (líneas ~157-231).
  - Detalle INVENTARIO serializado: `registrarMovimiento` con `serial_items`.
  - Detalle INVENTARIO simple: `registrarMovimiento` con `unit_cost` y `reference`.
  - Detalle INVENTARIO batch: `registrarMovimiento` con `batch_data` (items por variante).
- **Tipo de movimiento:** siempre **ENTRADA**.

### 2.3 Movimiento manual de inventario (entrada/salida)

- **Vista/origen:** Solo modal Livewire **`CreateMovimientoInventarioModal`** en la vista **Productos** (`productos.blade.php`). El usuario elige primero **Entrada o Salida** (paso 1 del asistente) y después producto y datos.
- **Ya no aplica:** pantalla dedicada `/inventario` ni `StoreController::storeMovimientoInventario` para este flujo.
- **Flujos en `save()` (resumen):**
  - **Serializado:** `registrarMovimiento` con `serial_items` (ENTRADA) o seriales en SALIDA (vía payload + array de seriales).
  - **Por lotes:** ENTRADA con `batch_data` → `registrarMovimiento`; SALIDA manual → **`registrarSalidaPorVarianteFIFO`** por variante elegida.
  - **Simple:** ENTRADA con `reference`, `quantity`, `unit_cost` → **`registrarMovimiento`** (rama simple del servicio); SALIDA manual → **`registrarSalidaPorCantidadFIFO`** (FIFO sin elegir variante en UI).
- La suma de ítems en `batch_data` (entrada por lote) se compara con la cantidad del movimiento usando valores normalizados (`Quantity::normalize`) para evitar fallos por tipo int/float.

### 2.4 Añadir variantes a producto por lote

- **Vista/origen:** Detalle del producto → “Añadir variantes” (formulario que envía a `storeVariants`).
- **Flujo:** `StoreController::storeVariants()` → `ProductService::addVariantsToProduct()` → **InventarioService::registrarMovimiento** para variantes con stock (líneas ~348-359).
- **Tipo de movimiento:** **ENTRADA** cuando la variante lleva cantidad; si no, solo se crean BatchItems con cantidad 0 (sin llamar a InventarioService).

---

## 3. registrarSalidaPorCantidadFIFO — salida por cantidad (FIFO)

- **Uso:** Cuando se descuenta inventario “por cantidad” sin indicar variante ni seriales; el sistema elige origen con política FIFO sobre los `batch_items` del producto (simple o batch internamente).
- **Dónde se llama:**
  1. **InvoiceService::crearFactura()** (líneas ~167-173): tras crear la factura y sus detalles, por cada detalle con quantity se llama `registrarSalidaPorCantidadFIFO(store, userId, product_id, qty, 'Venta Factura #…')`.
  2. **VentaService::ventaACredito()** (líneas ~84-90): para detalles que **no** tienen `serial_numbers`, se llama `registrarSalidaPorCantidadFIFO` con product_id y quantity.
  3. **CreateMovimientoInventarioModal::save()**: salida manual de producto **simple** (tipo `simple`), después de validar cantidad frente a stock.
- **Vista/origen:** Facturas, venta a crédito, modal “Registrar movimiento” en Productos (solo simple/SALIDA).

---

## 4. registrarSalidaPorSeriales — salida por seriales elegidos

- **Uso:** Descontar unidades serializadas concretas (el cliente/vendedor eligió qué seriales vender).
- **Dónde se llama:** **VentaService::ventaACredito()** (líneas ~72-78): para cada detalle que tiene `serial_numbers`, se llama `registrarSalidaPorSeriales(store, userId, product_id, serial_numbers, description)`.
- **Vista/origen:** Flujo de venta a crédito cuando los detalles incluyen seriales.

---

## 5. validarStockDisponible — solo validar, no modificar

- **Uso:** Comprobar que hay stock (o que los seriales existen y están disponibles) antes de comprometer una venta o agregar al carrito.
- **Dónde se llama:**
  1. **VentasCarrito (Livewire):** En `agregarSerializadosAlCarrito`, `agregarSimpleAlCarrito` y `validarCarritoConStock` (líneas ~294, 327, 387, 476). Valida ítems del carrito antes de agregar o al validar todo el carrito.
  2. **VentaService::ventaACredito()** (línea ~56): Valida `$details` antes de crear factura pendiente y descontar inventario.
- **Vista/origen:** Vista carrito de ventas y flujo de venta a crédito.

---

## 6. movimientosQuery, listarMovimientos y productosConInventario

- **movimientosQuery:** Query base de movimientos con filtros (producto, tipo, fechas, búsqueda). La usa **`InventoryMovementsExcelExportService`** para volcar filas al Excel de “Movimientos de inventario”.
- **listarMovimientos:** Paginador (`LengthAwarePaginator`) sobre la misma query; útil si alguna pantalla lista movimientos con los mismos filtros que el export.
- **productosConInventario:** Lista productos de la tienda aptos para inventario; no está acoplado de forma obligatoria al modal actual (el modal selecciona producto vía **SelectItemModal** / `VentaService::buscarProductos` para la fila `movimiento-inventario`).

---

## 7. buscarProductosInventario — búsqueda para selectores

- **SelectItemModal (Livewire):** Según `rowId` y contexto usa **`InventarioService::buscarProductosParaCompra`** o **`VentaService::buscarProductos`** (para carrito/factura/movimiento manual); las dos rutas convergen en datos compatibles con selectores de líneas de inventario.
- Rutas API históricas citadas en revisiones antiguas pueden haber cambiado; la fuente de verdad es el modal Livewire que despacha `item-selected`.

---

## 8. detectorDeVariantesEnLotes — utilidad para variantes en lotes

- **ProductService:** Varias veces al comparar/agrupar variantes por features (addVariantsToProduct, updateVariantFeatures, etc.) (líneas ~378, 380, 420, 421, 433, 437, 702, 704).
- **SelectBatchVariantModal:** Al cargar variantes existentes y generar clave normalizada (línea ~108).
- **Vistas:** `producto-detalle.blade.php` y vista compilada (líneas ~454 y 582): para agrupar o mostrar variantes por features.

---

## 9. Resumen por flujo de negocio (vistas que tocan inventario)

| Flujo | Vista / Acción | Servicio intermedio | Métodos InventarioService usados |
|-------|----------------|---------------------|----------------------------------|
| Crear producto (stock inicial) | CreateProductModal | ProductService | registrarMovimiento (ENTRADA) |
| Aprobar compra | Detalle compra → Aprobar | PurchaseService | registrarMovimiento (ENTRADA) |
| Movimiento manual | Productos → Registrar movimiento | CreateMovimientoInventarioModal | `registrarMovimiento`, `registrarSalidaPorCantidadFIFO` (simple SALIDA), `registrarSalidaPorVarianteFIFO` (batch SALIDA) |
| Export movimientos Excel | Productos | InventoryMovementsExcelExportService | `movimientosQuery` |
| Listado inventario (paginado) | (según pantalla que lo use) | — | `listarMovimientos` / `movimientosQuery` |
| Añadir variantes (con stock) | Producto → Añadir variantes | ProductService | registrarMovimiento (ENTRADA) |
| Factura al contado | Facturas → Crear factura | InvoiceService | registrarSalidaPorCantidadFIFO |
| Venta a crédito | (flujo VentaService) | VentaService | validarStockDisponible, registrarSalidaPorSeriales o registrarSalidaPorCantidadFIFO |
| Carrito de ventas | Ventas → Carrito | VentasCarrito (Livewire) | validarStockDisponible |
| Buscar producto (compras/carrito/movimiento) | Selector producto | SelectItemModal | buscarProductosInventario / buscarProductosParaCompra / VentaService |

---

## 10. Dónde “meter mano” para mejorar InventarioService

- **Unificar entrada/salida:** Toda escritura de inventario pasa por `registrarMovimiento`; FIFO y salida por seriales son capas encima. Mantener esa frontera al optimizar.
- **Consultas de stock:** `stockDisponible` ya centraliza la lógica por tipo (simple, lote, serializado); `validarStockDisponible` la usa. Cualquier nueva validación de stock debería apoyarse en `stockDisponible` para no duplicar reglas.
- **Carrito → factura:** Hoy el carrito usa `validarStockDisponible`; al conectar con VentaService/InvoiceService habrá que transformar líneas del carrito (con batch_item_id o serial_numbers) al formato `details` que esperan InvoiceService y VentaService, y decidir si la salida sigue siendo solo por cantidad (FIFO) o también por variante/serial cuando venga del carrito.
- **Movimientos manuales:** Actualizado el modal **`CreateMovimientoInventarioModal`**: paso 1 tipo de movimiento, ramas por tipo de producto (simple / batch / serializado), y llamadas directas a **`registrarSalidaPorCantidadFIFO`** o **`registrarSalidaPorVarianteFIFO`** cuando aplica. Cambios en `registrarMovimiento` deben alinear modal y compras/productos.
- **Compras:** PurchaseService ya adapta detalles de compra al formato que InventarioService espera; cualquier cambio en el contrato de `registrarMovimiento` (batch_data, serial_items, reference) debe reflejarse ahí.

Con este mapa se puede mejorar InventarioService (rendimiento, claridad, nuevos casos) sin perder coherencia con las vistas y servicios que lo usan.

---

## 11. Comprobación de cobertura

El archivo `app/Services/InventarioService.php` define más de una docena de métodos públicos; los citados en las tablas anteriores son los más relevantes para flujos de negocio. Para un inventario exacto de métodos y firma, revisar el código fuente del servicio.
