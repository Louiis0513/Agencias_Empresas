# Propuesta: Módulo de Comprobantes de Egreso

## Resumen del problema actual

- **Rigidez**: 1 pago = 1 cuenta por pagar (1 `AccountPayablePayment` → 1 `account_payable_id`)
- **Sin gastos directos**: No hay forma de registrar egresos rápidos (taxi, café) sin crear cuenta por pagar
- **Integración Compras**: `PurchaseService` llama a `AccountPayableService::registrarPago($store, $accountPayableId, $userId, $data)` — debe seguir funcionando sin cambios

---

## Solución propuesta

### 1. Nueva estructura de base de datos

#### Tabla `comprobantes_egreso` (cabecera del comprobante)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | bigint | PK |
| store_id | FK | Tienda |
| number | string | Consecutivo: CE-001, CE-002 |
| total_amount | decimal(15,2) | Monto total del egreso |
| payment_date | date | Fecha del comprobante |
| notes | string nullable | Notas |
| type | enum | `PAGO_CUENTA`, `GASTO_DIRECTO`, `MIXTO` (para filtros) |
| **beneficiary_name** | **string nullable** | **"A quién se le pagó" — visible en listado sin JOINs. Si es mixto (varios destinos distintos): "Varios"** |
| user_id | FK | Usuario que registró |
| reversed_at | datetime nullable | Si fue revertido |
| reversal_user_id | FK nullable | Quién revirtió |
| timestamps | | |

#### Tabla `comprobante_egreso_destinos` (¿a qué se destinó el dinero?)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | bigint | PK |
| comprobante_egreso_id | FK | Comprobante padre |
| type | enum | `CUENTA_POR_PAGAR`, `GASTO_DIRECTO` |
| account_payable_id | FK nullable | Solo si type=CUENTA_POR_PAGAR |
| concepto | string nullable | Solo si type=GASTO_DIRECTO (ej: "Taxi", "Café") |
| beneficiario | string nullable | Solo si type=GASTO_DIRECTO (ej: "Juan Pérez") |
| amount | decimal(15,2) | Monto destinado a esta línea |

**Un comprobante puede tener N destinos** → Pagos múltiples a varias facturas + gastos directos en el mismo egreso.

#### Tabla `comprobante_egreso_origenes` (¿de qué bolsillos salió el dinero?)

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | bigint | PK |
| comprobante_egreso_id | FK | Comprobante padre |
| bolsillo_id | FK | Bolsillo de origen |
| amount | decimal(15,2) | Monto de este bolsillo |
| **reference** | **string nullable** | **Número de cheque o transacción bancaria de este bolsillo** |

**Reemplaza conceptualmente** `account_payable_payment_parts`.

#### Cambio en `movimientos_bolsillo`

- **Agregar**: `comprobante_egreso_id` (nullable, FK)
- **Mantener temporalmente**: `account_payable_payment_id` para datos existentes
- Los **nuevos** movimientos usarán `comprobante_egreso_id`

---

## 2. Estrategia de migración (sin romper Compras)

### Fase 1: Crear nuevas tablas (additive)

1. Crear `comprobantes_egreso`, `comprobante_egreso_destinos`, `comprobante_egreso_origenes`
2. Agregar `comprobante_egreso_id` a `movimientos_bolsillo`
3. **Migrar datos**: Por cada `account_payable_payment` existente, crear:
   - 1 `comprobante_egreso` (number CE-XXX, type=PAGO_CUENTA, beneficiary_name=proveedor de la compra)
   - 1 `comprobante_egreso_destino` (account_payable_id, amount)
   - N `comprobante_egreso_origen` desde `account_payable_payment_parts` (reference=null en migración)
   - Actualizar `movimientos_bolsillo` que tenían `account_payable_payment_id` → poner `comprobante_egreso_id`

### Fase 2: Adapter en AccountPayableService (compatibilidad)

**La firma de `registrarPago` NO cambia:**

```php
// Sigue siendo exactamente esto:
public function registrarPago(Store $store, int $accountPayableId, int $userId, array $data): AccountPayablePayment|ComprobanteEgreso
```

**Implementación interna** (nueva lógica):

1. Crear `ComprobanteEgreso` (type=PAGO_CUENTA, beneficiary_name=proveedor de la cuenta por pagar)
2. Crear 1 `ComprobanteEgresoDestino` (account_payable_id, amount)
3. Crear N `ComprobanteEgresoOrigen` desde `$data['parts']` (cada parte puede incluir `reference` para cheque/transacción)
4. Registrar movimientos en caja con `comprobante_egreso_id`
5. Actualizar balance de la cuenta por pagar (igual que ahora)
6. Retornar el comprobante (o un DTO que tenga la misma "forma" para las vistas que lo usen)

**Vistas de Cuentas por Pagar**: En lugar de `$accountPayable->payments`, usar la relación:

```php
// En AccountPayable:
public function comprobanteDestinos()
{
    return $this->hasMany(ComprobanteEgresoDestino::class)->where('type', 'CUENTA_POR_PAGAR');
}
// Cada destino tiene ->comprobanteEgreso
```

Así el historial de pagos de una cuenta por pagar = los destinos que la referencian.

### Fase 3: Nuevo ComprobanteEgresoService

```php
// Para el módulo nuevo de Comprobantes de Egreso
public function crearComprobante(Store $store, int $userId, array $data): ComprobanteEgreso
{
    // $data = [
    //   'payment_date' => '...',
    //   'notes' => '...',
    //   'destinos' => [
    //     ['type' => 'CUENTA_POR_PAGAR', 'account_payable_id' => 1, 'amount' => 100],
    //     ['type' => 'CUENTA_POR_PAGAR', 'account_payable_id' => 2, 'amount' => 50],
    //     ['type' => 'GASTO_DIRECTO', 'concepto' => 'Taxi', 'beneficiario' => 'Juan', 'amount' => 20],
    //   ],
    //   'origenes' => [
    //     ['bolsillo_id' => 1, 'amount' => 120, 'reference' => 'CHQ-001'],  // cheque
    //     ['bolsillo_id' => 2, 'amount' => 50, 'reference' => 'TRF-12345'], // transacción bancaria
    //   ],
    // ];
    // Validar: sum(destinos.amount) == sum(origenes.amount)
    // Calcular beneficiary_name: 1 destino → nombre; varios destinos distintos → "Varios"
    // Crear comprobante + destinos + origenes + movimientos + actualizar balances
}
```

### Fase 4: Deprecar tablas viejas (opcional, posterior)

- Dejar de escribir en `account_payable_payments` y `account_payable_payment_parts`
- Las vistas leen desde `comprobante_egreso_destinos` + `comprobantes_egreso`
- Eventualmente eliminar tablas viejas si ya no hay referencias

---

## 3. Relaciones y modelos

```
ComprobanteEgreso (1)
  ├── ComprobanteEgresoDestino (N)  [a qué se destinó]
  │     ├── account_payable_id (nullable) → AccountPayable
  │     └── concepto, beneficiario (para gasto directo)
  └── ComprobanteEgresoOrigen (N)   [de qué bolsillo salió]
        ├── bolsillo_id → Bolsillo
        └── reference (cheque, transacción bancaria)

AccountPayable
  └── comprobanteDestinos() → ComprobanteEgresoDestino (where account_payable_id = id)
        └── comprobanteEgreso
```

---

## 4. Vista del módulo Comprobantes de Egreso

### Rutas nuevas

```
GET  /tiendas/{store}/comprobantes-egreso          → Listado paginado
GET  /tiendas/{store}/comprobantes-egreso/crear     → Formulario crear (múltiples destinos + gastos directos)
POST /tiendas/{store}/comprobantes-egreso           → Guardar comprobante
GET  /tiendas/{store}/comprobantes-egreso/{comprobante}  → Detalle (qué pagó, de qué bolsillos)
POST /tiendas/{store}/comprobantes-egreso/{comprobante}/reversar  → Reversar
```

### Contenido del listado

| Columna | Descripción |
|---------|-------------|
| Número | CE-001, CE-002 |
| Fecha | payment_date |
| Monto total | total_amount |
| **A quién** | `beneficiary_name` — sin JOINs (ej: "Proveedor XYZ", "Juan Pérez", "Varios" si es mixto) |
| Tipo | Pago cuenta / Gasto directo / Mixto |
| Destinos | Resumen: "Compra #5: 100 | Taxi: 20" |
| Usuario | Quién registró |
| Estado | Activo / Revertido |

### Contenido del detalle

- Cabecera: número, fecha, monto total, a quién (`beneficiary_name`), notas, usuario
- **Destinos**: tabla con cada línea (cuenta por pagar + monto, o gasto directo + concepto + beneficiario + monto)
- **Orígenes**: de qué bolsillos salió el dinero (incluye `reference`: cheque, transacción bancaria)
- Botón Reversar (si no está revertido)

---

## 5. Compatibilidad con Compras (garantizada)

| Componente | Cambio |
|------------|--------|
| `PurchaseService::aprobarCompra()` | **Cero** — sigue llamando `$accountPayableService->registrarPago(...)` |
| `AccountPayableService::registrarPago()` | **Solo implementación interna** — crea ComprobanteEgreso en lugar de AccountPayablePayment |
| Vista `cuenta-por-pagar-detalle` | **Cambio mínimo** — en lugar de `$accountPayable->payments`, usar `$accountPayable->comprobanteDestinos` con sus comprobantes |
| Controlador `payAccountPayable` | **Cero** — sigue llamando `registrarPago` |

---

## 6. Orden de implementación sugerido

1. **Migraciones**: Crear tablas nuevas + migrar datos existentes
2. **Modelos**: ComprobanteEgreso, ComprobanteEgresoDestino, ComprobanteEgresoOrigen
3. **AccountPayable**: Agregar relación `comprobanteDestinos`, mantener `payments` como alias temporal si hace falta
4. **AccountPayableService**: Refactorizar `registrarPago` para crear ComprobanteEgreso; refactorizar `reversarPago`
5. **ComprobanteEgresoService**: Nuevo servicio con `crearComprobante`, `listar`, `obtener`, `reversar`
6. **Vista cuenta-por-pagar-detalle**: Cambiar loop de payments a comprobanteDestinos
7. **Vistas nuevas**: Listado y detalle de Comprobantes de Egreso
8. **Formulario crear**: Modal o página para crear comprobante con múltiples destinos y gastos directos

---

## 7. Diagrama de flujo

```
                    ┌─────────────────────────┐
                    │   ComprobanteEgreso     │
                    │   CE-001 | 170.00       │
                    └───────────┬─────────────┘
                                │
        ┌───────────────────────┼───────────────────────┐
        │                       │                       │
        ▼                       ▼                       ▼
┌───────────────┐     ┌───────────────┐     ┌───────────────┐
│   DESTINO 1   │     │   DESTINO 2   │     │   DESTINO 3   │
│ Cuenta #5     │     │ Cuenta #7     │     │ Gasto directo │
│ 100.00        │     │ 50.00         │     │ Taxi: 20.00   │
└───────────────┘     └───────────────┘     └───────────────┘
        │                       │                       │
        └───────────────────────┼───────────────────────┘
                                │
        ┌───────────────────────┼───────────────────────┐
        │                       │                       │
        ▼                       ▼                       ▼
┌───────────────┐     ┌───────────────┐
│   ORIGEN 1    │     │   ORIGEN 2    │
│ Bolsillo Caja │     │ Bolsillo Banco│
│ 120.00        │     │ 50.00         │
│ ref: CHQ-001   │     │ ref: TRF-123  │
└───────────────┘     └───────────────┘
```

---

¿Procedemos con la implementación siguiendo este plan?
