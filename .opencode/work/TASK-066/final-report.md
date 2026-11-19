# TASK-066 — Informe final

- **ID:** TASK-066
- **Estado:** COMPLETED
- **Fecha:** 2026-11-19
- **Proyecto:** `/home/david/pokemon`

## Resumen

Se corrigió el filtro por nombre de la Pokédex. La causa era que
`DatagridService::applySearch()` usaba `LIKE`, sensible a mayúsculas en
PostgreSQL, mientras los nombres están guardados capitalizados (`Pikachu`). Por
eso `pikachu` y `PIKACHU` devolvían 0 resultados.

La corrección cambia el operador a `orWhereLike()` (`caseSensitive: false`), que
compila a `ILIKE` en PostgreSQL y mantiene compatibilidad con SQLite. También se
retiró del placeholder la promesa de búsqueda por número, que queda fuera del
alcance aprobado.

## Estado final

**COMPLETED**

- Revisión independiente: **APPROVED**.
- Auditoría de seguridad: **APTO CON OBSERVACIONES** (sin hallazgos críticos ni
  altos; observaciones de hardening y deuda preexistente).
- Sin tests, según la política del proyecto.

## Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Datagrid/DatagridService.php` | `orWhere(..., 'like', $term)` → `orWhereLike(..., $term)` en `applySearch()` |
| `resources/views/pokedex/index.blade.php` | placeholder y `aria-label` a "Buscar por nombre..." |

No se crearon archivos de aplicación. Los artefactos de coordinación están en
`.opencode/work/TASK-066/`.

## Decisiones

- Búsqueda solo por nombre, sin distinguir mayúsculas/minúsculas.
- Se mantiene la búsqueda parcial (`Pika` sigue encontrando `Pikachu`).
- Se mantiene el respeto a la pestaña activa (Vistos / Todos / Atrapados).
- Búsqueda por número fuera de alcance; se elimina la promesa del placeholder.
- Sin migraciones, dependencias nuevas ni cambios de rutas/permisos.

## Verificación ejecutada

- `php -l app/Datagrid/DatagridService.php` → sin errores.
- Pint sobre el archivo → `pass`.
- Tinker de solo lectura:
  - `pikachu` → total 1, id 25, nombre `Pikachu`.
  - `Pikachu` → total 1.
  - `PIKACHU` → total 1.
  - `Pika` → total 1.
  - `search` vacío → total 1083.
- SQL capturado: `pokemon.name::text ilike ?`; el `orWhereLike` sigue agrupado y
  se combina con `AND` con `pokedex.visto`.
- Filtros combinados verificados: tipos, esfuerzo, movimiento, habilidad,
  paginación y otros datagrids (`team`, `habitat`, `province`, `gym`, `reclutado`).
- `view:cache` / `view:clear` → OK.
- `git diff` acotado: una línea en el servicio y dos atributos en la vista; el
  resto del working tree es preexistente (TASK-061/TASK-064).

## Hallazgos de revisión y seguridad

- **Revisión (LOW):** el docblock de `DatagridService` sigue diciendo `LIKE`;
  actualizarlo a `LIKE/ILIKE` en una limpieza posterior. No bloquea.
- **Seguridad (LOW):** `search` no tiene límite de longitud; acotarlo a ~100
  caracteres en una tarea de hardening.
- **Seguridad (INFO):** comodines `%`/`_` del usuario se interpretan como patrón;
  comportamiento preexistente y acotado por auth/scoping/paginación.
- **Seguridad (INFO):** `/datagrid/*` sin rate limiting; preexistente.
- **Seguridad (INFO):** `composer audit` reporta CVEs en
  `laravel/framework v12.67.0` y componentes Symfony. Es deuda preexistente, ajena
  a este cambio; requiere una tarea de actualización controlada.

## Riesgos residuales

- SQLite no se pudo ejecutar (PDO sqlite no instalado); la equivalencia se
  verificó por compilación del grammar (`like`) y los nombres son ASCII.
- El cambio afecta a todos los datagrids con `searchable`: pasan a búsqueda
  insensible a mayúsculas. Verificado en los cinco restantes.
- Nombres con grafía canónica (`Mr. Mime`, `Farfetch'd`) siguen sin encontrarse
  porque en BD están normalizados (`Mr-mime`, `Farfetchd`). Fuera de alcance.
- `ILIKE` no usa índice btree; irrelevante con 1083 filas.

## Próximos pasos

1. Opcional: actualizar el docblock de `DatagridService` y acotar la longitud de
   `search` en una tarea de hardening.
2. Opcional: planificar `composer update` por los CVEs de framework/Symfony.
3. TASK-064 (vista `/debug` y acciones QA en Pokédex) sigue pausada, pendiente de
   auditoría de seguridad y revisión independiente; no se ha declarado completada.
