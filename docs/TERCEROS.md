# Terceros (Centradia)

## Qué es
Maestro único por tienda (`terceros`) que unifica **clientes**, **proveedores** y **trabajadores**. Una identificación = un tercero; los roles son múltiples.

## Modelo

```
terceros
├── tercero_roles          (cliente | proveedor | trabajador | otro)
├── tercero_contactos
├── tercero_direcciones
├── tercero_cliente_perfiles
│   └── tercero_cliente_gym_perfiles
├── tercero_proveedor_perfiles
├── tercero_trabajador_perfiles  (+ role_id → roles de permisos)
└── contratos_laborales
```

Pivot productos↔proveedor: `producto_tercero` (`product_id`, `tercero_id`).

Documentos operativos usan `tercero_id` (facturas, CxC, CI, compras, CE, docs soporte, suscripciones, horarios, CxP).

## Reglas
- Unique lógico: `(store_id, numero_identificacion)` vía servicio (y unique compuesto con tipo en DB).
- Fusión histórica (migración cutover): mismo número de documento en la tienda → un tercero; prioridad cliente > proveedor > trabajador.
- Consumidor final: NIT `222222222222` por tienda, rol cliente (`TerceroService::asegurarConsumidorFinal`).
- Soft delete; no eliminar consumidor final.
- Acceso al sistema del trabajador: `users` + `store_user` sincronizados desde perfil trabajador (`role_id`).

## UI
- Personas → **Terceros** (`/stores/{slug}/terceros`)
- Filtro `?rol=cliente|proveedor|trabajador`
- Pestañas: Datos, Roles, Cliente, Proveedor, Trabajador, Contactos, Direcciones
- Permisos: `terceros.view|create|edit|destroy`
- Rutas legacy `/clientes`, `/proveedores`, `/trabajadores` redirigen a terceros con el filtro de rol.
- Registro de horarios sigue en `/trabajadores/registro-horarios` (usa `tercero_id`).

## Servicios
- `TerceroService` — CRUD, roles, contactos, direcciones, perfiles, sync `store_user`, consumidor final, búsqueda por rol.
- `CustomerService` / `ProveedorService` / `WorkerService` — adaptadores temporales hacia `TerceroService`.
- Modelos alias: `Customer`, `Proveedor`, `Worker` extienden `Tercero` (tabla `terceros`). Preferir `Tercero` en código nuevo.

## Artisan
```bash
php artisan terceros:migrar
php artisan terceros:migrar {store_id|slug}
```
Asegura consumidor final y reporta conteos. El cutover de datos customers/proveedores/workers → terceros corre en la migración `2026_07_29_191300_cutover_terceros_from_legacy`.

## Contabilidad
Los asientos futuros (FV/FC/RC/RP/CC) deben referenciar `tercero_id` del documento operativo.
