# Plan: Mejora de facturación y descripciones en inventario

## Objetivos

1. **Factura (detalle)**: Que cada línea muestre **producto + serial + atributos** para que el cliente sepa exactamente qué compró (no solo el nombre del producto).
2. **Movimiento de inventario (salida)**: Que la descripción especifique correctamente **qué salió** (variante o seriales) además del número de factura, y que en salidas serializadas se vea el **costo del producto serializado** que salió.

---

## 1. Factura: detalle con producto + serial + atributos

**Archivos:** [app/Services/InvoiceService.php](app/Services/InvoiceService.php).

**Enfoque:** Usar el campo existente `product_name` para guardar un texto “para mostrar”: nombre del producto + atributos (variante) + serial(es).

- En **`crearDetalleSinValidarStock`** (y en `procesarDetalle` si se sigue usando):
  - Construir el string a guardar en `product_name`:
    - Base: `$producto->name`.
    - Si existe `$item['product_variant_id']`: cargar `ProductVariant`, usar su nombre legible (ej. "Talla: M, Color: Rojo") y concatenar, ej.: `"Nombre (Talla: M, Color: Rojo)"`.
    - Si existe `$item['serial_numbers']` y no está vacío: concatenar los seriales, ej.: `" - Serial: SN1, SN2"`.
  - Así en la factura el detalle muestra **producto + serial + atributos** y se sabe exactamente qué se compró.

---

## 2. Movimiento de inventario: descripción que especifique qué salió

**Archivo:** [app/Services/VentaService.php](app/Services/VentaService.php).

**Cambio:** Antes de llamar a los métodos de salida de inventario, construir una descripción por ítem que indique **qué salió** (además de la factura) y pasarla como `$description`.

- En **`registrarVentaContado`** y **`ventaACredito`**, dentro del `foreach ($details as $item)`:
  - Obtener el producto (y si aplica la variante).
  - Base: `"Venta Factura #{$factura->id}"` (o "Venta a crédito Factura #...").
  - Añadir qué salió:
    - Si hay `product_variant_id`: cargar `ProductVariant`, obtener nombre legible y hacer `$desc .= " - {$product->name} - {$variant->display_name}"`.
    - Si hay `serial_numbers`: `$desc .= " - {$product->name} - Serial: " . implode(', ', $serialNumbers)`.
    - Si no hay variante ni seriales: `$desc .= " - {$product->name}"`.
  - Pasar `$desc` a `registrarSalidaPorCantidadFIFO`, `registrarSalidaPorVarianteFIFO` o `registrarSalidaPorSeriales`.

Así, en la vista de inventario la columna “Descripción” muestra correctamente qué fue lo que salió, además del número de factura.

---

## 3. Salida serializada: mostrar el costo del producto serializado que salió

**Archivo:** [app/Services/InventarioService.php](app/Services/InventarioService.php).

**Ajuste importante:** El costo **no se calcula ni se inventa**. Para producto serializado el costo ya está explícito en la tabla **`product_items`** (campo `cost`). Solo hay que **buscarlo** y **colocarlo** en el movimiento para que al ver el movimiento de inventario se vea el costo del producto serializado que salió.

- En **`registrarSalidaPorSeriales`** (antes de llamar a `registrarMovimiento`):
  - Buscar en la tabla **ProductItem** los registros que corresponden a los seriales que salen (mismo `store_id`, `product_id`, `serial_number` en el array).
  - Obtener el campo **`cost`** de cada uno de esos ítems.
  - **Asignar `unit_cost` al movimiento:**
    - Si sale **una sola unidad**: `unit_cost` = el `cost` de ese ProductItem.
    - Si salen **varias unidades**: el movimiento es un solo registro con `quantity` = N; se asigna `unit_cost` = promedio de los `cost` de esos N ítems (para que el movimiento tenga un costo unitario representativo en reportes). Todo proviene de ProductItem, no se inventa nada.
  - Pasar en `$datos` a `registrarMovimiento` además `'unit_cost' => $unitCost`.

Con esto, en el listado de movimientos de inventario (salida) se verá el costo unitario del producto serializado que salió, obtenido directamente de `product_items`.

---

## 4. Resumen de cambios por archivo

| Archivo | Cambio |
|---------|--------|
| **InvoiceService.php** | En `crearDetalleSinValidarStock`: construir `product_name` = producto + atributos (variante) + serial(es) para que en la factura se vea exactamente qué se compró. |
| **VentaService.php** | En el loop de detalles de venta: armar descripción “Factura #N - Producto - Variante/Seriales” y pasarla a los métodos de salida para que en inventario se especifique qué salió. |
| **InventarioService.php** | En `registrarSalidaPorSeriales`: buscar en **ProductItem** el/los `cost` de los seriales que salen y asignar ese valor (o promedio si son varios) a `unit_cost` del movimiento; no calcular, solo leer de la tabla y colocar. |

---

## 5. Orden de implementación sugerido

1. **InventarioService**: En `registrarSalidaPorSeriales`, consultar ProductItem por los seriales, leer `cost`, asignar `unit_cost` (uno = ese cost; varios = promedio) y pasarlo a `registrarMovimiento`.
2. **VentaService**: Construir descripción enriquecida por ítem y pasarla a los tres métodos de salida.
3. **InvoiceService**: Construir `product_name` enriquecido (producto + variante + seriales) en `crearDetalleSinValidarStock`.
