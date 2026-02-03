# Contexto del proyecto para asistencia de IA (planificación y dudas)

Usa este documento como **contexto o prompt** al hablar con una IA que te ayude a **organizar ideas, planificar o resolver dudas** sobre el proyecto. Así esa IA tendrá en cuenta la visión, la estructura y las reglas que seguimos, y podrás trabajar de forma ordenada sin saturar a la IA que te ayuda con el código.

---

## 1. Visión del proyecto

- **Qué es**: Una **plataforma para administrar distintos tipos de negocio** (tiendas, locales, talleres, etc.). No es solo “una tienda”: cada **Store** representa un negocio/tenant; el sistema sirve para muchos tipos de negocio.
- **Objetivo**: Construir todo de forma **modular** y **reutilizable**, con **servicios** que concentren la lógica de negocio y que sean usados por controladores y vistas. Así el sistema escala bien y se mantiene.
- **Estado actual**: Se están terminando **servicios** y **vistas** por módulos. Primero se cierra la capa de servicios de cada módulo y luego las pantallas que los consumen.

---

## 2. Arquitectura: módulos y servicios

### Regla de oro

- **Módulo** = área de negocio (Compras, Cuentas por pagar, Comprobantes de egreso, Caja, Inventario, Facturación, Clientes, Proveedores, Activos, etc.).
- **Servicio** = donde vive **toda la lógica de negocio** de ese módulo. Los controladores y las vistas **no** implementan reglas de negocio; solo orquestan y llaman a los servicios.
- **Reutilización**: Un servicio puede usar otros servicios (ej: `ComprobanteEgresoService` usa `CajaService`; `AccountPayableService` crea comprobantes de egreso). La lógica se escribe una vez y se reutiliza.

### Estructura actual (Laravel)

| Capa | Ubicación | Responsabilidad |
|------|-----------|-----------------|
| **Servicios** | `app/Services/*.php` | Lógica de negocio, validaciones de dominio, transacciones, llamadas entre servicios. |
| **Modelos** | `app/Models/*.php` | Eloquent: relaciones, scopes, atributos. Sin lógica pesada. |
| **Controladores** | `app/Http/Controllers/StoreController.php` (y otros) | Reciben request, inyectan servicios por método, llaman al servicio y devuelven vista o redirect. |
| **Vistas / Livewire** | `resources/views/`, `app/Livewire/` | UI: formularios, listados, modales. Llaman a servicios vía controlador o inyectando el servicio en el componente. |
| **Rutas** | `routes/web.php` | Bajo `tienda/{store:slug}`: todo es por tienda/negocio. |

### Servicios existentes (referencia)

- `StoreService` — negocio/tienda  
- `CategoryService`, `ProductService`, `AttributeService` — catálogo  
- `CustomerService`, `InvoiceService` — clientes y facturación  
- `ProveedorService`, `PurchaseService`, `AccountPayableService` — compras y cuentas por pagar  
- `CajaService` — bolsillos y movimientos de dinero  
- `ComprobanteEgresoService` — comprobantes de egreso (pagos flexibles: cuentas por pagar + gastos directos)  
- `InventarioService` — movimientos de inventario  
- `ActivoService` — activos (equipos, muebles, etc.)

Los controladores **inyectan estos servicios por parámetro** en los métodos (Laravel DI) y delegan en ellos.

---

## 3. Reglas que seguimos (para que la IA las respete al planificar)

1. **Nueva funcionalidad de negocio** → va en un **Service** (nuevo o existente). No en el controlador ni en Livewire.
2. **Nuevo “módulo”** (nueva área de negocio) → implica al menos un **Service** dedicado y vistas que lo consuman.
3. **Reutilizar antes que duplicar**: si otro servicio ya hace algo (ej: registrar movimiento de bolsillo), se llama a ese servicio desde el nuevo código.
4. **Rutas por tienda**: las rutas de negocio van bajo `tienda/{store:slug}`; siempre hay un `Store` en contexto.
5. **Seguridad**: el usuario solo puede actuar sobre tiendas donde es trabajador; eso se valida en controlador (o middleware) antes de llamar al servicio.
6. **Transacciones**: operaciones que tocan varias tablas o varios servicios se hacen dentro de `DB::transaction()` en el servicio que orquesta.
7. **Compatibilidad**: si hay flujos antiguos (ej: pagos de cuentas por pagar), los nuevos módulos (Comprobantes de egreso) se integran sin romper esos flujos; los servicios antiguos pueden delegar en los nuevos internamente.

---

## 4. Cómo usar este contexto con otra IA

- **Al iniciar la conversación**: Pega o adjunta este documento y di algo como: *“Voy a planificar / resolver dudas sobre un proyecto. Usa este contexto para entender la visión, la estructura por módulos y servicios y las reglas que seguimos.”*
- **Al planificar un módulo nuevo**: Pide que te proponga qué Service(s), métodos y vistas harían falta, respetando que la lógica va en servicios y que se reutilicen los existentes.
- **Al resolver dudas**: Pregunta “¿esto debería ir en un servicio o en el controlador?”, “¿qué servicio existente podría usar para X?”; la IA puede responder en base a las reglas de arriba.
- **Al organizar ideas**: Pide listas de tareas, orden de implementación (servicios primero, luego vistas) o checklist por módulo; todo alineado con “módulos + servicios reutilizados”.

---

## 5. Resumen en una frase

*“Plataforma multi-negocio, modular: cada área de negocio es un módulo con su Service; la lógica vive en servicios reutilizables; controladores y vistas solo orquestan y muestran; todo bajo `tienda/{store}` y con transacciones y compatibilidad con flujos ya existentes.”*

---

Si algo no cuadra con cómo estás trabajando, ajusta este documento y vuelve a usarlo como contexto para la otra IA.
