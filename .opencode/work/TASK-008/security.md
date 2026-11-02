# TASK-008 — Seguridad

- **Auditor:** `security`
- **Fecha:** 2026-11-02
- **Veredicto:** `PASS CON HALLAZGOS`
- **Bloqueantes:** ninguno (0 CRITICAL, 0 HIGH)

## Resumen

El guard del detalle se aplica solo al registro `pokemon` y usa la pokédex del usuario autenticado. Verificado en runtime:

- Pokémon visto por el usuario → detalle.
- Pokémon visto solo por otro usuario → `NULL` → 404.
- Pokémon sin fila → 404.
- ID inexistente → el mismo 404, sin oráculo de existencia.
- SQL parametrizado y columnas whitelisted; sin inyección.
- Sin XSS en el nuevo detalle: `x-text`, `@json` escapado, `src`/`href` con IDs enteros.
- Sin CSRF (GET sin estado) ni mass assignment.

## Hallazgos

### MEDIUM

1. **El listado y las APIs de hábitat exponen datos de Pokémon no avistados.**
   - `GET /datagrid/pokemon?filter[visto]=0` devuelve nombre, tipos, icono y hábitat de no vistos; `/api/habitats/{id}/pokemon|families` también.
   - El 404 del detalle no evita revelar existencia/identidad si el catálogo ya es accesible.
   - Es preexistente; requiere decisión de producto: redactar `itemFields`/APIs o aceptar que el catálogo es público.
2. **`?unlock=1` se activaba con `APP_DEBUG=true`.** `DesbloqueoPokedex::activo()` incluía `|| config('app.debug')`, y `.env.example` trae `APP_DEBUG=true`. En un despliegue con debug activo, cualquier usuario autenticado podía saltarse el guard.
   - **Corregido durante el cierre:** se eliminó `config('app.debug')`; `?unlock=1` solo se honra en `local`/`testing` (o con `POKEDEX_UNLOCK_ALL=true`). `DatagridTest` 32 passed tras el cambio.

### LOW

- `(bool)` sobre `visto` sin cast en el modelo: fragilidad si el driver devuelve `'f'`; en PostgreSQL actual llega booleano nativo.
- Sin rate limiting ni caché: cada detalle carga las 514 aristas; abuso de recursos por usuario autenticado.
- `BelongsToUser` falla abierto sin autenticación (hoy todas las rutas afectadas están bajo `auth`).

### INFO

- La familia muestra nombres/iconos/niveles de evoluciones no avistadas: decisión de producto aceptada.
- Sin CSP y `DEBUGBAR_ENABLED=true` en `.env.example`: hardening pendiente, fuera del diff.
- Mutaciones de hábitat solo con `auth`, sin rol admin: deuda de producto preexistente.
- Dependencias: 8 advisories / 5 paquetes (`laravel/framework 12.67`, `livewire 4.4.2`, `symfony/* 7.4.17`, `commonmark 2.10`); ninguno explotable en el flujo auditado con la configuración actual. Planificar actualización con autorización.

## Riesgos aceptados

- Mostrar nombre, icono y nivel de evoluciones no avistadas dentro de la familia.
- Mostrar provincia/hábitat del Pokémon actual.
- `POKEDEX_UNLOCK_ALL=true` en local; no debe usarse en producción.

## Riesgo residual

- La confidencialidad de «no avistado» no está garantizada fuera del detalle si el listado/APIs siguen exponiendo el catálogo.
- Si se despliega con `POKEDEX_UNLOCK_ALL=true`, el control de visibilidad desaparece; `APP_DEBUG` ya no lo habilita.

## Conclusión

`PASS CON HALLAZGOS`: sin bloqueantes. El guard y el aislamiento entre usuarios son correctos. Quedan como deuda la redacción del listado/APIs de no vistos, el rate limiting/caché y la actualización de dependencias.
