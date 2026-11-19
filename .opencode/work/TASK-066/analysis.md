# TASK-066 — Análisis

- **ID:** TASK-066
- **Estado:** COMPLETADO
- **Analista:** `analyst`
- **Fecha:** 2026-11-19

## 1. Resultado (causa raíz y confianza)

**Causa raíz principal — backend/SQL (confianza alta, reproducida).**
El buscador sí envía `search`, pero `DatagridService::applySearch()` aplica `LIKE`
(sensible a mayúsculas en PostgreSQL) sobre `pokemon.name`, y **todos los nombres
en BD están capitalizados** (`Str::ucfirst` en el seeder). Resultado: cualquier
término en minúsculas (o todo mayúsculas) devuelve **0 resultados**.

Bisagra histórica: el commit `c7dfcdc` (2026-08-27, "async pokedex with
server-side datagrid") **eliminó** el filtro cliente
`p.name.toLowerCase().includes(q) || String(p.id).includes(q)` y lo sustituyó por
el `LIKE` del servidor. No es una regresión de TASK-061 ni de TASK-064.

**Causas secundarias verificadas:**
- **Número:** el placeholder promete "Buscar por nombre o número"
  (`index.blade.php:169`) y `searchable: ['name']` solo cubre `name`. Buscar `25`
  devuelve 0 aunque Pikachu tenga `id=25`.
- **Pestaña "Vistos":** por defecto `activeFilter='vistos'` añade
  `filter[visto]=1`; buscar una especie no avistada devuelve 0 aunque el nombre
  coincida (comportamiento de la pestaña, no del buscador).
- **Forma del dato:** los nombres se guardan normalizados (`Mr-mime`,
  `Nidoran-f`, `Farfetchd`, `Mime-jr`), no con la grafía canónica
  (`Mr. Mime`, `Farfetch'd`, `Nidoran♀`). Quien teclee la grafía oficial obtiene 0.

**Naturaleza del bug:** backend (semántica SQL `LIKE`) + forma de los datos
(`ucfirst`/normalización). El frontend es correcto. **No** hay regresión de
TASK-061/064.

## 2. Evidencia

### Frontend (correcto)
- `resources/views/pokedex/index.blade.php:167-168` — `x-model="searchQuery"` +
  `@input.debounce.300ms="onSearchInput()"`; el modelo está actualizado a los 300 ms.
- `index.blade.php:941-943` — `onSearchInput()` → `resetAndFetch()`.
- `index.blade.php:915-925` — `resetAndFetch()` aborta el fetch previo, vacía
  `items`, `page=1`, `loading=false` y llama `fetchPage(1)`.
- `index.blade.php:841-842` — si hay texto, `params.set('search', ...)`.
- `index.blade.php:886-910` — guardas de carrera por `controller`; no hay
  respuestas obsoletas.
- No hay filtrado cliente posterior: `fetchPage` empuja todo `data.data` (solo
  dedupe por `id`).

### Backend (el fallo)
- `app/Datagrid/DatagridController.php:23-25` — `$params = $request->query()`;
  `search` llega intacto (sin alias `q`).
- `app/Datagrid/DatagridService.php:115-119` — valida `is_string`, `trim`,
  construye `$term = '%'.trim($search).'%'`.
- `app/Datagrid/DatagridService.php:122-126` — **`orWhere(..., 'like', $term)`**:
  operador `LIKE`, no `ILIKE`.
- El `orWhere` está dentro de un closure → agrupado; no rompe la combinación con
  filtros.
- `app/Providers/DatagridServiceProvider.php:44` — `searchable: ['name']` (sin
  `id`). Sin cambios respecto a `HEAD` (`git show HEAD:...` idéntico).
- `baseQuery` hace `leftJoin('pokedex')` y `select('pokemon.*', ...)`; `pokedex`
  no tiene `name`, así que `pokemon.name` no es ambiguo.
- `database/seeders/PokemonSeeder.php:238` — `'name' => Str::ucfirst($row['name'])`.

### SQL y datos (ejecutado, solo lectura)
SQL real capturado con `DB::listen` para `search=pikachu&filter[visto]=1`:

```sql
select count(*) as aggregate from "pokemon"
left join "pokedex" on "pokedex"."pokemon_id" = "pokemon"."id" and 1 = 0
where ("pokemon"."name"::text like ?) and "pokedex"."visto" in (?)
BINDINGS: ["%pikachu%",true]
```

Comparativa en la BD real (`Database: laravel`, driver `pgsql`):

| término | total |
|---|---|
| `pikachu` | 0 |
| `Pikachu` | 1 (id 25) |
| `PIKACHU` | 0 |
| `pikachu` con `ilike` | 1 |
| `25` | 0 |
| `Nidoran` / `nidoran` | 2 / 0 |
| `Farfetch` / `farfetch` | 2 / 0 |
| `Mr. Mime`, `mr. mime` | 0 / 0 (en BD es `Mr-mime`) |

- `SELECT count(*) FROM pokemon` → **1083**; `whereRaw("name = lower(name)")` →
  **0** (ningún nombre en minúsculas).
- `'Pikachu' LIKE '%pikachu%'` → `false`; `'Pikachu' ILIKE '%pikachu%'` → `true`.
- Nombres no-ASCII: **0** (todos ASCII: `Farfetchd`, `Nidoran-f`, `Mr-mime`), por
  lo que `ILIKE` basta; no hace falta `unaccent`.

### Llamada real al controlador (con `Auth` de usuario 1)
```
{"search":"pikachu","filter":{"visto":"1"}} => total=0
{"search":"Pikachu","filter":{"visto":"1"}} => total=0   (Pikachu no visto para user 1)
{"search":"Pikachu"}                        => total=1  names=Pikachu
```

### Comportamiento anterior (regresión)
- `git show 8d0777e:resources/views/pokedex/index.blade.php:229-247`:
```js
const q = this.searchQuery.toLowerCase().trim();
result = result.filter(p => p.name.toLowerCase().includes(q) || String(p.id).includes(q) || ('#' + p.id).includes(q));
```
- `git show HEAD:resources/views/pokedex/index.blade.php | grep filteredPokemons` →
  vacío: el filtro cliente ya no existe.
- `vendor/.../Builder.php:1268,1291` expone `whereLike()`/`orWhereLike()`;
  `PostgresGrammar::whereLike()` compila `ilike` cuando `caseSensitive=false`
  (`vendor/.../PostgresGrammar.php:93-100`).

### TASK-061 / TASK-064
`git diff` de la vista y del provider limita los cambios a combobox
habilidad/movimiento, `buildParams`, `onComboboxSeleccionado`, bloque QA e
`idPositivo`. **Ninguna línea toca `searchQuery`, `search` ni `applySearch`.**

## 3. Reproducción

Ejecutado sin modificar datos (`php artisan tinker --execute`, consultas de solo
lectura, `Auth::login` de usuario existente):

1. `DatagridService::list('pokemon', ['search'=>'Pikachu'])` → `total=1`, `ids=[25]`.
2. `DatagridService::list('pokemon', ['search'=>'pikachu'])` → `total=0`.
3. `DatagridService::list('pokemon', ['search'=>'PIKACHU'])` → `total=0`.
4. `DatagridService::list('pokemon', ['search'=>'25'])` → `total=0`.
5. `DatagridController::index()` con `search=pikachu&filter[visto]=1` → `total=0`.
6. `DB::select("select ... ilike ...")` → `true`.

Respuestas a las preguntas del encargo:

| # | Respuesta |
|---|---|
| 1 | El fetch se dispara y el valor llega a `params`; `x-model` + debounce 300 ms correctos. |
| 2 | `request->query()` → `$params['search']`; sin alias `q` ni descarte. |
| 3 | `LIKE` es case-sensitive en PostgreSQL; no hay acentos/no-ASCII en `pokemon.name`. |
| 4 | La combinación con filtros es correcta: `orWhere` agrupado; `whereHas` con `AND`. |
| 5 | No hay carrera: abort + comparación de `controller` + guardas. |
| 6 | TASK-061 no afecta a la búsqueda; la regresión viene de `c7dfcdc`. |
| 7 | No hay filtro cliente posterior al fetch. |
| 8 | Reproducido con `Pikachu/pikachu/PIKACHU`, `Nidoran/nidoran`, `Farfetch/farfetch`, `25`. |

Hipótesis no verificadas (requieren el caso del usuario):
- Que el síntoma sea solo la pestaña "Vistos" (especies no avistadas). En QA
  (`unlockAll`) la pestaña inicial es "Todos" y ese factor desaparece.
- Que el usuario teclee grafías canónicas (`Mr. Mime`, `Farfetch'd`), que fallan
  porque el dato está normalizado.

## 4. Corrección mínima propuesta

**Fix principal — 1 línea, `app/Datagrid/DatagridService.php:124`:**

```php
// actual
$q->orWhere($this->qualifyColumn($table, $column), 'like', $term);
// propuesto
$q->orWhereLike($this->qualifyColumn($table, $column), $term); // caseSensitive: false
```

- En PostgreSQL compila a `"pokemon"."name"::text ilike ?`; en SQLite/MySQL a
  `like` (ya insensible en ASCII/colación). Sin migración ni dependencias.
- `orWhereLike` existe en el framework instalado (`Builder.php:1291`) y encaja en
  el closure actual.
- Se aplica a todos los datagrids con `search` (`team`, `habitat`, `gym`,
  `reclutado`), coherente con el filtro cliente anterior.

**Ajustes de contrato/UX (decidir antes de implementar):**
1. **Placeholder "o número"** — corregir el texto a "Buscar por nombre…", o
   implementar búsqueda por `id`. Si se implementa, **no** añadir `'id'` a
   `searchable` sin más: `whereLike('id','%25%')` devuelve 25, 125, 225, 250…
   Requiere decisión exacta vs prefijo.
2. **Búsqueda dentro de la pestaña activa** — ¿buscar solo entre vistos o en toda
   la Pokédex? Cambiarlo altera comportamiento de producto.
3. **Grafía canónica** (`Mr. Mime`, `Farfetch'd`) — fuera del alcance mínimo;
   exigiría un campo de nombre buscable normalizado o alias.

**Preguntas bloqueantes:**
- ¿Cuál fue el texto exacto tecleado y en qué pestaña/entorno (QA `unlockAll` o
  normal)?
- ¿"Buscar por número" es requisito o texto heredado?

## 5. Archivos afectados y responsable

| Archivo | Cambio | Responsable |
|---|---|---|
| `app/Datagrid/DatagridService.php:124` | `orWhere('like')` → `orWhereLike()` | backend |
| `resources/views/pokedex/index.blade.php:169-170` | placeholder/aria-label (opcional) | frontend |
| `app/Providers/DatagridServiceProvider.php` (solo si se aprueba búsqueda por `id`) | contrato `searchable`/`applySearch` numérico | backend + decisión |

No hay que tocar TASK-061 ni TASK-064. No se requiere migración ni cambio de
índices.

## 6. Riesgos / regresiones

- **[bajo] Rendimiento:** `ILIKE` no usa índice btree; con 1083 filas es
  irrelevante. Si el catálogo crece, índice GIN `pg_trgm`.
- **[bajo] Cambio de semántica global:** otros datagrids pasan a búsqueda
  insensible a mayúsculas. Es una mejora consistente; no hay consumidores que
  dependan de la sensibilidad.
- **[bajo] Comodines del usuario:** `%` y `_` no se escapan (hoy tampoco).
- **[medio] Búsqueda por `id`:** con `LIKE` genera ruido (`25` → 25/125/225/250).
  Requiere decisión exacta vs prefijo.
- **[bajo] Confusión de diagnóstico:** el filtro de pestaña "Vistos" se puede
  percibir como fallo del buscador.
- **[nulo] TASK-061/064:** el fix no toca sus líneas.

## 7. Criterios de aceptación

1. `GET /datagrid/pokemon?search=pikachu` devuelve `id=25` y el mismo
   `meta.total` que `search=Pikachu` (en la BD actual: 1).
2. `search=PIKACHU` devuelve el mismo total que `search=Pikachu`.
3. `search=Pika` sigue devolviendo 1 (búsqueda parcial preservada).
4. `search=` vacío o ausente devuelve el listado completo.
5. `search=pikachu&filter[visto]=1` con un usuario que tenga Pikachu visto
   devuelve 1; con `filter[visto]=0` devuelve 0.
6. SQL generado en `pgsql` contiene `ilike`; en SQLite sigue siendo insensible.
7. Filtros de tipo/esfuerzo/habilidad/movimiento y paginación/orden sin cambios.
8. Otros datagrids (`team`, `habitat`, `gym`, `reclutado`) mantienen resultados
   con el mismo término en el caso que ya usaban.
9. Si se decide búsqueda por número: `search=25` devuelve exactamente `id=25`
   (no 125/225/250) y queda documentado en el placeholder/aria-label.

## 8. Verificación ejecutada y problemas

- Lectura estática de vista, controlador, servicio, definición, provider, modelo,
  rutas y reglas.
- `git status`, `git branch --show-current`, `git diff` de la vista y del
  provider, `git show HEAD:...`, `git show 8d0777e:...`, `git log -S`.
- `php artisan route:list --path=datagrid` (2 rutas dentro de auth).
- Consultas de solo lectura vía `php artisan tinker --execute` (conteos, `pluck`,
  `DB::listen`, `Request::create`, `DatagridController::index` con usuario 1).
- Consulta directa de `LIKE`/`ILIKE` en `pgsql`.
- Verificación de `whereLike`/`orWhereLike` y `PostgresGrammar` en `vendor/`.

Problemas:
- `.env` no leído. `config:show database.default` confirma `pgsql`.
- Conexión `sqlite` no disponible; no se creó ningún archivo.
- La suite no se ejecutó (prohibido).
- No se pudo confirmar el término exacto del usuario.

## 9. Archivos modificados

**Ninguno.**
