# Análisis y plan de trabajo: servicios de compra de productos

## 1. Estado actual del flujo

### 1.1 Guardar como borrador (crear compra)
- **Servicio:** `PurchaseService::crearCompra()`
- **Qué hace:** `validarDatosCompra()` → crea `Purchase` (BORRADOR) → `crearDetalle()` por cada línea.
- **Conclusión:** Correcto. Solo persiste datos; no crea cuenta por pagar ni movimientos. Los detalles ya incluyen `batch_items` con `expiration_date` si se envió desde el formulario (StoreController normaliza y el servicio guarda el JSON tal cual en `crearDetalle` → `batch_items` se guarda con lo que venga en `$d['batch_items']`; hay que asegurar que `crearDetalle` no filtre `expiration_date` en cada ítem).

### 1.2 Aprobar compra
- **Servicio:** `PurchaseService::aprobarCompra()`
- **Orden actual:**
  1. Comprueba que la compra esté en BORRADOR.
  2. **Crea la cuenta por pagar** (y si es contado, registra el pago con `accountPayableService->registrarPago()`).
  3. Actualiza estado a APROBADO.
  4. Por cada detalle: si es inventario → `inventarioService->registrarMovimiento()`; si es activo → `activoService->registrarEntrada()` etc.

**Problemas / diferencias con tu lógica deseada:**

| Aspecto | Estado actual | Lo que planteas |
|--------|----------------|------------------|
| Validación antes de aprobar | No se vuelve a validar la compra (solo se exige que esté en BORRADOR). | Tener una “validar compra” que asegure que la compra es coherente antes de aprobar (datos, crédito/contado, seriales, etc.). |
| Redundancia con actualizar | `actualizarCompra` y `aprobarCompra` no comparten un mismo “paso de validación”. Actualizar solo se usa al editar borrador. | Reutilizar una validación común (o que aprobar llame a algo tipo “validar para aprobar”) para no duplicar reglas. |
| Orden de operaciones | 1) Cuenta por pagar (y pago si contado) 2) Estado APROBADO 3) Movimientos de inventario. | 1) Validar 2) Estado APROBADO 3) Movimientos de inventario 4) Si es a crédito → crear cuenta por pagar. |
| Referencia (lote y serie) | Se usa `"Compra #{$purchase->id}"` para batch y para serializado (campo `batch` en ProductItem). | **Mantener así.** Al ver el lote o la unidad serializada en inventario, la referencia permite ir a la compra y ahí ver el número de factura. No cambiar a número de factura. |
| Fecha de caducidad del lote | Ya se envía `expiration_date` en `batch_data` desde `aprobarCompra` (desde `batch_items[0]['expiration_date']`). | Mantener; la vista ya envía el dato y el servicio ya lo pasa a inventario. |

### 1.3 Movimiento de inventario
- **Servicio:** `InventarioService::registrarMovimiento()`
- **Uso en aprobar:** Por cada detalle de tipo inventario, se llama con:
  - `product_id`, `type` ENTRADA, `quantity`, `description` ("Compra #id - descripción"), `purchase_id`
  - Serializado: `reference` + `serial_items`
  - Por lote: `batch_data` con `reference`, `expiration_date`, `items` (quantity, cost, features)
- **Conclusión:** La firma y el uso ya permiten cantidad, costo, descripción, usuario (implícito en la transacción) y, para lote, referencia y fecha de caducidad. **Referencia:** tanto para lote (Batch.reference) como para serializado (ProductItem.batch) se usa `"Compra #{$purchase->id}"`; así en inventario se puede rastrear a la compra y ahí ver el número de factura. No modificar.

### 1.3.1 Referencia en productos serializados
- En el formulario de compra ya se pide el **número de serie** por unidad.
- Al aprobar, `PurchaseService` llama a `InventarioService::registrarMovimiento()` con `reference => "Compra #{$purchase->id}"` y `serial_items`.
- `InventarioService` crea cada `ProductItem` con el campo **`batch`** = esa referencia (igual que el “lote” para serializados).
- Así, en inventario la “referencia” de cada unidad serializada es la compra; desde la compra se ve el número de factura. **Comportamiento deseado ya implementado; no requiere cambios.**

### 1.4 Cuenta por pagar
- **Servicio:** `PurchaseService::crearCuentaPorPagar()` (método interno) y `AccountPayableService::registrarPago()` para contado.
- **Datos:** Se usa `due_date` de la compra (vencimiento de la factura) en la cuenta por pagar.
- **Conclusión:** Ya tiene la información de vencimiento; solo hay que llamarlo en el momento correcto del flujo (después de inventario, si es a crédito).

---

## 2. Qué falta para alinearlo con tu lógica

1. **Validación antes de aprobar**
   - Crear un método atómico que valide que la compra está lista para aprobar (por ejemplo `validarCompraParaAprobar(Store $store, Purchase $purchase, ?array $paymentData, ?array $serialsByDetailId)`).
   - Incluir:
     - Misma lógica de datos que `validarDatosCompra` (pero leyendo desde el modelo: detalles, due_date si es crédito).
     - Si es contado: exigir que venga `paymentData` con `parts` que sumen el total.
     - Si hay productos serializados (inventario o activo): exigir que vengan los seriales por detalle cuando corresponda.
   - Opcional: que `validarDatosCompra` y esta validación compartan reglas (por ejemplo un método privado común de “reglas de detalles y crédito”).

2. **Orden del flujo en `aprobarCompra`**
   - Cambiar a:
     1. Validar (nuevo `validarCompraParaAprobar`).
     2. Actualizar estado a APROBADO.
     3. Por cada detalle: movimientos de inventario (y entradas de activos).
     4. Si es a crédito: crear cuenta por pagar.
     5. Si es contado: crear cuenta por pagar y registrar pago.
   - Así, si falla inventario, no se habrá creado aún la cuenta por pagar.

3. **Referencia (lote y serializado)** — *Sin cambios*
   - Mantener `"Compra #{$purchase->id}"` como referencia tanto para batch como para productos en serie. En inventario, lote y unidad serializada muestran esa referencia; desde la compra se ve el número de factura. No usar número de factura como referencia.

4. **Funciones atómicas y mantenibilidad**
   - **PurchaseService**
     - `validarCompraParaAprobar()`: validación única antes de aprobar (reutilizable si en el futuro hay otro punto de “aprobar”).
     - Mantener `registrarMovimientosPorCompra()` o equivalente: un único método que reciba la compra aprobada y el `userId`, recorra los detalles y llame a `inventarioService->registrarMovimiento()` (y activos); así `aprobarCompra` solo orquesta: validar → estado → registrar movimientos → cuenta por pagar.
   - **InventarioService**
     - Ya tiene `registrarMovimiento()` atómico; no hace falta partirlo más; solo asegurar que la referencia y `expiration_date` vengan bien desde PurchaseService.

5. **Borrador: persistencia de `expiration_date` en batch_items**
   - En `PurchaseService::crearDetalle()` se hace `$batchItems = array_values($d['batch_items'])`; no se filtra por claves, así que si el controlador ya envía `batch_items` con `expiration_date` en cada ítem, se guarda en JSON. Verificar que `StoreController::normalizeProductPurchaseDetails` siga incluyendo `expiration_date` en cada ítem (ya lo añadimos). Nada más que revisar en servicios para que la fecha de caducidad del lote quede guardada en el borrador y luego se use al aprobar.

---

## 3. Plan de trabajo sugerido (orden recomendado)

| # | Tarea | Estado | Descripción |
|---|--------|--------|-------------|
| 1 | Validación para aprobar | ✅ Hecho | Añadido `validarCompraParaAprobar()` en PurchaseService: valida detalles, due_date si crédito, pago (parts que sumen total) si contado, serial_items para producto serializado y serialsByDetailId para activo serializado. |
| 2 | Refactor orden en aprobarCompra | ✅ Hecho | `aprobarCompra` ahora: (1) validar con `validarCompraParaAprobar`, (2) `$purchase->update(['status' => APROBADO])`, (3) `registrarMovimientosPorAprobacion()`, (4) si crédito crear cuenta por pagar, (5) si contado crear cuenta por pagar y registrar pago. |
| 3 | Referencia "Compra #id" | ✅ Sin cambios | Lote y serializado siguen usando "Compra #{$purchase->id}". |
| 4 | Extraer registro de movimientos | ✅ Hecho | Método protegido `registrarMovimientosPorAprobacion(Store $store, Purchase $purchase, int $userId, ?array $serialsByDetailId)` que recorre detalles y llama a InventarioService/ActivoService. |
| 5 | Pruebas / revisión manual | Pendiente | Probar: borrador con lote y fecha caducidad → aprobar → movimiento con expiration_date; contado con pago; crédito con cuenta por pagar y due_date. |

Con esto, los servicios quedan alineados con el flujo: validar → estado APROBADO → movimientos de inventario/activos → cuenta por pagar (y pago si contado).
