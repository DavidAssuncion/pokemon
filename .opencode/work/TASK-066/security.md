# TASK-066 — Seguridad

- **ID:** TASK-066
- **Estado:** APTO CON OBSERVACIONES
- **Auditor:** `security`
- **Fecha:** 2026-11-19

## Veredicto

**APTO CON OBSERVACIONES**

Sin hallazgos CRITICAL ni HIGH. El cambio no introduce SQLi, no amplía el
scoping por usuario, no añade escrituras, rutas ni permisos. Las observaciones
son de hardening y deuda preexistente; ninguna bloquea la entrega.

## Hallazgos

### LOW — `search` sin límite de longitud ni validación de codificación

- **Ubicación:** `app/Datagrid/DatagridService.php:113-119`.
- **Vector:** `GET /datagrid/pokemon?search=<cadena arbitraria>` con sesión.
- **Evidencia:** solo `is_string($search)` y `trim($search) !== ''`; no hay `max:`
  ni validación UTF-8. El término se convierte en `'%'.trim($search).'%'`.
- **Impacto:** cadenas muy largas fuerzan un `ILIKE` innecesario; bytes inválidos
  UTF-8 pueden provocar error PostgreSQL `22021` (500). Sin impacto de integridad
  ni confidencialidad.
- **Remediación:** acotar en controlador/servicio (`mb_substr(..., 0, 100)` o
  `string|max:100`). No urgente.
- **Bloquea:** no.

### INFO — Comodines `%`/`_` interpretados como patrón

- **Ubicación:** `app/Datagrid/DatagridService.php:119`.
- **Impacto:** búsqueda amplia (equivalente a listado completo, ya paginado a
  máximo 200 y accesible con búsqueda vacía). No permite lectura fuera del
  scoping, ni escritura, ni cruce de usuarios.
- **Remediación:** escapar `\`, `%`, `_` si se desea búsqueda literal. Fuera del
  alcance v1.
- **Bloquea:** no.

### INFO — `/datagrid/*` sin rate limiting

- **Ubicación:** `routes/datagrid.php:9-10`, `routes/web.php:18-49`.
- **Evidencia:** solo middleware `auth`; no hay `throttle`.
- **Impacto:** un usuario autenticado puede repetir consultas; con 1083 filas y
  `per_page` acotado a 200 el coste es bajo.
- **Remediación:** valorar `throttle` si el datagrid crece. Preexistente.
- **Bloquea:** no.

### INFO — Deuda de dependencias preexistente

- `composer audit --no-interaction` → exit 10:
  - `laravel/framework v12.67.0`: CVE-2026-114509 (TokenGuard, `<12.68.0`) y
    CVE-2026-114508 (relationship inclusion, `<12.68.0`), entre otros.
  - `symfony/http-foundation`, `symfony/routing`, `symfony/http-kernel`,
    `symfony/mime`, `symfony/config` en `v7.4.17` y `symfony/yaml v8.1.9` con
    CVEs publicados.
- **Explotabilidad actual acotada:** `config/auth.php` solo define guard
  `session`; no hay guard `token`/API, por lo que CVE-2026-114509 no es
  explotable en esta configuración. El resto requiere revisión aparte.
- **Remediación:** planificar `composer update` controlado, fuera de TASK-066.
- **Bloquea TASK-066:** no; deuda previa y ajena al diff.

## Controles verificados

- **SQL injection:** `orWhereLike` usa `addBinding($value)` y placeholder. Término
  malicioso `%' OR 1=1 --%` queda como binding literal. La columna procede de
  `DatagridDefinition::searchable` (whitelist estática), no del cliente.
- **Agrupación del OR:** `orWhereLike` dentro de `where(function ...)`; SQL real
  `where ("pokemon"."name"::text ilike ?) and "pokedex"."visto" in (?)`.
- **Tipo de entrada:** arrays/objetos se ignoran (`is_string`); `null`/vacío/solo
  espacios → listado completo; `searchable === []` → sin búsqueda.
- **Autorización:** rutas dentro de `auth`; `LEFT JOIN pokedex` con
  `pokedex.user_id = Auth::id()`; `Pokedex`, `Reclutado`, `Team` con
  `BelongsToUser`. Sin cruce de usuarios.
- **XSS:** el término no se renderiza en HTML; placeholder/aria-label estáticos.
- **CSRF:** GET de solo lectura; no aplica.
- **Escrituras/rutas/permisos:** `list()`/`detail()` solo `SELECT`; sin cambios en
  `routes/datagrid.php` ni `DatagridController`.
- **Secretos:** no se leyó `.env` ni credenciales.
- **Alcance colateral:** `orWhereLike` afecta a `pokemon`, `reclutado`, `team`,
  `habitat`, `province`, `gym`; solo cambia la sensibilidad a mayúsculas, no
  columnas, joins, filtros ni scoping.

## Riesgos residuales

- Búsqueda amplia por comodines; acotada por auth, scoping y paginación.
- Coste de `ILIKE` con comodín inicial; 1083 filas, irrelevante hoy.
- Sin límite de longitud de `search`; solo carga/errores controlados.
- Deuda de dependencias con CVEs; tarea de actualización independiente.

## Archivos modificados

**Ninguno.** Auditoría de solo lectura.
