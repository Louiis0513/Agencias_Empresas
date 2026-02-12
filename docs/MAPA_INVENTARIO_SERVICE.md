# Mapa de uso de InventarioService

Responsabilidad del servicio: **registrar movimientos de entrada y salida** y **consultar stock disponible**. Este documento indica **dónde** se usa **cada** función del servicio (públicas y protegidas) y el **flujo** (vista → controlador/servicio → InventarioService) para poder mejorarlo de forma coherente.

---

## 1. Índice completo de funciones de InventarioService

En `InventarioService` existen **12 métodos** en total. Esta tabla los lista todos (públicos, estáticos y protegidos) y si tienen uso externo o solo interno.

| # | Método | Visibilidad | ¿Usado desde fuera del servicio? | Dónde se usa (resumen) |
|---|--------|-------------|-----------------------------------|------------------------|
| 1 | **detectorDeVariantesEnLotes** | public static | Sí | ProductService, SelectBatchVariantModal, vistas (producto-detalle, compiladas) |
| 2 | **resolveUnitCostForMovement** | protected | No (solo interno) | Llamado por registrarMovimiento al crear el registro MovimientoInventario |
| 3 | **actualizarStock** | public | No (solo interno) | Llamado por registrarMovimiento tras cada entrada/salida (actualiza products.stock) |
| 4 | **actualizarCostoPonderado** | public | No (solo interno) | Llamado por registrarMovimiento tras cada entrada/salida (recalcula product.cost) |
| 5 | **registrarMovimiento** | public | Sí | PurchaseService, ProductService, StoreController, CreateMovimientoInventarioModal |
| 6 | **registrarSalidaPorCantidadFIFO** | public | Sí | InvoiceService, VentaService |
| 7 | **registrarSalidaPorSeriales** | public | Sí | VentaService |
| 8 | **stockDisponible** | public | No (solo interno) | Llamado por validarStockDisponible para cada ítem |
| 9 | **validarStockDisponible** | public | Sí | VentasCarrito (Livewire), VentaService |
| 10 | **listarMovimientos** | public | Sí | StoreController::inventario |
| 11 | **productosConInventario** | public | Sí | StoreController::inventario, CreateMovimientoInventarioModal |
| 12 | **buscarProductosInventario** | public | Sí | StoreController::buscarProductosInventario, SelectItemModal |

---

## 1b. Métodos públicos con uso externo (resumen)

| Método | Dónde se llama | Flujo (vista / origen) |
|--------|----------------|-------------------------|
| **registrarMovimiento** | PurchaseService, ProductService, StoreController, CreateMovimientoInventarioModal | Ver sección 2 |
| **registrarSalidaPorCantidadFIFO** | InvoiceService, VentaService | Factura (contado) y venta a crédito |
| **registrarSalidaPorSeriales** | VentaService | Venta a crédito (detalles con serial_numbers) |
| **validarStockDisponible** | VentasCarrito (Livewire), VentaService | Carrito de ventas y al facturar a crédito |
| **stockDisponible** | Solo internamente en validarStockDisponible | — |
| **listarMovimientos** | StoreController::inventario | Vista Inventario |
| **productosConInventario** | StoreController::inventario, CreateMovimientoInventarioModal | Vista Inventario y modal de movimiento |
| **buscarProductosInventario** | StoreController::buscarProductosInventario, SelectItemModal | API búsqueda y selector de producto (compras/carrito) |
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

- **Vista/origen:** Vista “Inventario” (`/inventario`) con formulario de movimiento O modal `CreateMovimientoInventarioModal`.
- **Flujos:**
  1. **StoreController::storeMovimientoInventario** (POST `/inventario/movimientos`): valida request y llama **InventarioService::registrarMovimiento** con product_id, type (ENTRADA/SALIDA), quantity, description, unit_cost (líneas ~1127-1135).
  2. **CreateMovimientoInventarioModal::save()**: prepara payload (serial_items, batch_data o batch_item_id según tipo de producto y ENTRADA/SALIDA) y llama **InventarioService::registrarMovimiento** (líneas ~317-322).
- **Tipo de movimiento:** **ENTRADA** o **SALIDA** según el usuario.

### 2.4 Añadir variantes a producto por lote

- **Vista/origen:** Detalle del producto → “Añadir variantes” (formulario que envía a `storeVariants`).
- **Flujo:** `StoreController::storeVariants()` → `ProductService::addVariantsToProduct()` → **InventarioService::registrarMovimiento** para variantes con stock (líneas ~348-359).
- **Tipo de movimiento:** **ENTRADA** cuando la variante lleva cantidad; si no, solo se crean BatchItems con cantidad 0 (sin llamar a InventarioService).

---

## 3. registrarSalidaPorCantidadFIFO — salida por cantidad (FIFO)

- **Uso:** Cuando se descuenta inventario “por cantidad” sin indicar variante ni seriales; el sistema elige origen con política FIFO.
- **Dónde se llama:**
  1. **InvoiceService::crearFactura()** (líneas ~167-173): tras crear la factura y sus detalles, por cada detalle con quantity se llama `registrarSalidaPorCantidadFIFO(store, userId, product_id, qty, 'Venta Factura #…')`.
  2. **VentaService::ventaACredito()** (líneas ~84-90): para detalles que **no** tienen `serial_numbers`, se llama `registrarSalidaPorCantidadFIFO` con product_id y quantity.
- **Vista/origen:** Crear factura (contado) desde facturas; venta a crédito orquestada por VentaService (cuando se use).

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

## 6. listarMovimientos y productosConInventario — consultas para la vista

- **listarMovimientos:** `StoreController::inventario()` (línea ~1107). Pagina movimientos con filtros (product_id, type, fechas) para la vista de inventario.
- **productosConInventario:** `StoreController::inventario()` (línea ~1108) y `CreateMovimientoInventarioModal::getProductosInventarioProperty()` (línea ~79). Lista productos de la tienda aptos para movimientos (simple, serialized, batch) para desplegables/filtros.

---

## 7. buscarProductosInventario — búsqueda para selectores

- **StoreController::buscarProductosInventario** (línea ~1316): Ruta API `/api/productos-inventario/buscar`; devuelve productos por término (nombre, SKU, barcode) para inventario.
- **SelectItemModal (Livewire):** En la propiedad `getResultsProperty()` (línea ~47) para el modal de “Seleccionar producto” en compras y en carrito de ventas.

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
| Movimiento manual | Inventario → formulario o CreateMovimientoInventarioModal | StoreController / Livewire | registrarMovimiento (ENTRADA/SALIDA) |
| Añadir variantes (con stock) | Producto → Añadir variantes | ProductService | registrarMovimiento (ENTRADA) |
| Factura al contado | Facturas → Crear factura | InvoiceService | registrarSalidaPorCantidadFIFO |
| Venta a crédito | (flujo VentaService) | VentaService | validarStockDisponible, registrarSalidaPorSeriales o registrarSalidaPorCantidadFIFO |
| Carrito de ventas | Ventas → Carrito | VentasCarrito (Livewire) | validarStockDisponible |
| Listado inventario | Inventario | StoreController | listarMovimientos, productosConInventario |
| Buscar producto (compras/carrito) | Selector producto | SelectItemModal / API | buscarProductosInventario |

---

## 10. Dónde “meter mano” para mejorar InventarioService

- **Unificar entrada/salida:** Toda escritura de inventario pasa por `registrarMovimiento`; FIFO y salida por seriales son capas encima. Mantener esa frontera al optimizar.
- **Consultas de stock:** `stockDisponible` ya centraliza la lógica por tipo (simple, lote, serializado); `validarStockDisponible` la usa. Cualquier nueva validación de stock debería apoyarse en `stockDisponible` para no duplicar reglas.
- **Carrito → factura:** Hoy el carrito usa `validarStockDisponible`; al conectar con VentaService/InvoiceService habrá que transformar líneas del carrito (con batch_item_id o serial_numbers) al formato `details` que esperan InvoiceService y VentaService, y decidir si la salida sigue siendo solo por cantidad (FIFO) o también por variante/serial cuando venga del carrito.
- **Movimientos manuales:** StoreController y CreateMovimientoInventarioModal preparan el payload y llaman a `registrarMovimiento`; si se cambia la firma o el formato de `registrarMovimiento`, hay que actualizar ambos y el modal (serial_items, batch_data, batch_item_id).
- **Compras:** PurchaseService ya adapta detalles de compra al formato que InventarioService espera; cualquier cambio en el contrato de `registrarMovimiento` (batch_data, serial_items, reference) debe reflejarse ahí.

Con este mapa se puede mejorar InventarioService (rendimiento, claridad, nuevos casos) sin perder coherencia con las vistas y servicios que lo usan.

---

## 11. Comprobación de cobertura

El archivo `app/Services/InventarioService.php` contiene exactamente **12 métodos**:

- **Públicos (11):** detectorDeVariantesEnLotes, actualizarStock, actualizarCostoPonderado, registrarMovimiento, registrarSalidaPorCantidadFIFO, registrarSalidaPorSeriales, stockDisponible, validarStockDisponible, listarMovimientos, productosConInventario, buscarProductosInventario.
- **Protegidos (1):** resolveUnitCostForMovement.

Todos están referenciados en este documento (tabla de índice en §1, uso externo en §1b, uso interno en §1c, y detalle por flujo en §2–8).
