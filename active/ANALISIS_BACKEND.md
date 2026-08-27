# Análisis Backend — DatagridService + endpoints + optimización de assets

## Decisión de ubicación (justificada)

`app/Datagrid/` (carpeta nueva) en vez de `app/Crud/Base/`:

- `app/Crud/Base/` contiene `BaseCrudService`/`BaseCrudController`, pensados para el CRUD administrativo por modelo (create/update/delete + search simple).
- DatagridService es un subsistema de **consulta JSON de solo lectura** con whitelist explícita, query base personalizable (join Pokédex) y contrato de respuesta propio. Meterlo en `app/Crud/Base/` mezclaría dos responsabilidades distintas.
- La tarea autoriza explícitamente elegir; las convenciones del proyecto agrupan por responsabilidad (`app/Crud/`, `app/Livewire/`, `app/Enums/`).
- `app/` en inglés (infraestructura Laravel) → clases en inglés: `DatagridDefinition`, `DatagridRegistry`, `DatagridService`, `RelationFilter`.

## Qué voy a tocar

### Crear
| Archivo | Propósito |
|---|---|
| `app/Datagrid/DatagridDefinition.php` | Whitelist inmutable por modelo: clase, searchable/filterable/sortable (mapa clave pública → columna SQL), relationFilters, with, visible, boolFields, baseQuery (Closure), counts (Closure), detail (Closure). |
| `app/Datagrid/RelationFilter.php` | Filtro de relación: `{relation, column, map?}` para whereHas. |
| `app/Datagrid/DatagridRegistry.php` | Registro slug → definición (slugs en minúscula: `pokemon`, `pokedex`, `reclutado`, `team`, `habitat`, `province`). |
| `app/Datagrid/DatagridService.php` | `list(slug, params): array{data, meta}` + `detail(slug, id): ?array` + `registered(slug): bool`. |
| `app/Http/Controllers/DatagridController.php` | `index(Request, string $model)` y `show(Request, string $model, int $id)` → JsonResponse; modelo no registrado → `abort(404)`. |
| `app/Providers/DatagridServiceProvider.php` | Registra el registry como singleton con las 6 definiciones; se añade a `bootstrap/providers.php`. |
| `routes/datagrid.php` | `GET /datagrid/{model}`, `GET /datagrid/{model}/{id}/detalle` (+ `whereNumber('id')`). Require desde `routes/web.php` (convención del proyecto: archivos de rutas por dominio). |
| `app/Support/WebpConverterInterface.php` + `app/Support/WebpConverter.php` | Lógica de conversión PNG→WebP extraíble: `available(): bool`, `convert(input, output): bool`, `backend(): string`. Backends: GD (`imagecreatefrompng` + `imagesavealpha` + `imagepalettetotruecolor` + `imagewebp`) → Imagick (`setImageFormat('webp')`, preserva alfa) → CLI (`cwebp` / `convert`). |
| `app/Console/Commands/OptimizarIconosWebp.php` | `iconos:optimizar-webp` con `--dir` (default `public/images/iconos`). `process(WebpConverterInterface, dir): array{converted, skipped, errors}` testeable con fake; solo raíz (`*.png`, sin subdirectorios); idempotente (skip si `.webp` existe); no borra PNGs. Si no hay backend → mensaje claro + FAILURE. |
| `public/images/iconos/.htaccess` | `Cache-Control: public, max-age=31536000, immutable` para `.webp`/`.png`. |
| `tests/Feature/DatagridTest.php` | Tests del contrato (abajo). |
| `tests/Unit/WebpConverterTest.php` | Lógica extraíble (skip de conversión real si no hay backend). |
| `tests/Feature/OptimizarIconosWebpTest.php` | Comando con fake converter + .htaccess. |

### Modificar
| Archivo | Cambio |
|---|---|
| `routes/web.php` | `require __DIR__.'/datagrid.php';` |
| `bootstrap/providers.php` | Añadir `DatagridServiceProvider`. |
| `app/Http/Controllers/PlayerController.php` | `pokedex()` delega en `DatagridService::list('pokemon', ['per_page' => 100])`; viewData: `pokemons` (respuesta completa `{data, meta}`), `counts` (meta.counts), `tipos` (`TipoEnum::options()`). Se elimina el N+1 (hoy hace stats()+types() por cada uno de los 1350). |
| `tests/Feature/PlayerControllerTest.php` | Actualizar contrato de viewData (justificado: la spec cambia el contrato de la vista, punto 3). |

### No toco (fronteras)
- `resources/views/` → agente FRONTEND (la vista pokedex se adaptará al nuevo contrato por su lado).
- `src/` (dominio DDD).

## Contrato JSON acordado (no cambia sin avisar al frontend)

### GET /datagrid/{model}?search=&filter[field]=&sort=&order=&page=&per_page=
```json
{
  "data": [ { "id": 1, "name": "bulbasaur", "visto": true, "atrapado": false }, ... ],
  "meta": {
    "total": 1350, "page": 1, "per_page": 100, "last_page": 14,
    "counts": { "total": 1350, "vistos": 100, "atrapados": 50, "no_vistos": 1250 }
  }
}
```
- `counts` es `null` para modelos sin contadores definidos (solo `pokemon` los tiene: total/vistos/atrapados/no_vistos globales, independientes de filtros/paginación).
- `per_page` clamp 1–200 (default 100). `page` mínimo 1.
- Filtro/sort no whitelisted → **ignorado silenciosamente**. Modelo no registrado → **404** (nunca se revela la clase).
- `filter[field]=a,b` → whereIn. `filter[types]=Eléctrico` → whereHas('types', whereIn type ids). Relación configurada en whitelist (`types` → columna `type`, map label→id vía `TipoEnum`); el cliente no puede inventar columnas de relación (anti inyección).

### GET /datagrid/pokemon/{id}/detalle
```json
{
  "id": 1, "name": "bulbasaur", "visto": true, "atrapado": true,
  "types": ["Planta", "Veneno"],
  "stats": [ { "name": "PS (HP)", "value": 45 }, ... 6 stats ],
  "habitat_name": "Bosque"
}
```

### Decisión `habitat_name` (documentada)
El modelo `Pokemon` no tiene columna/atributo `habitat_name` ni relación directa `habitat`; se usa la relación `habitats()` (BelongsToMany vía `pokemon_habitat`) existente: `$model->habitats->first()?->name`. En el detalle se carga con `loadMissing(['stats', 'types', 'habitats'])`. Primer hábitat de la relación (orden natural de la tabla pivote).

### Vista pokedex (contrato nuevo para FRONTEND)
- `pokemons` = respuesta del datagrid `{ data: [...], meta: {...} }` (primera página, 100 items ligeros sin stats).
- `counts` = `{ total, vistos, atrapados, no_vistos }`.
- `tipos` = `App\Enums\TipoEnum::options()` (la vista ya lo usa en el blade; se pasa también por viewData).
- El modal de detalle deberá consumir `GET /datagrid/pokemon/{id}/detalle`.

## Query base de Pokémon (join Pokédex)
```php
$query->leftJoin('pokedex', 'pokedex.pokemon_id', '=', 'pokemon.id')
      ->select('pokemon.*', 'pokedex.visto', 'pokedex.atrapado');
```
- Join 1:1 (unique `pokemon_id`) → sin duplicados en count/paginación.
- Columnas SQL siempre cualificadas (`pokemon.id`, `pokedex.visto`...) para evitar ambigüedad con el join.
- `visto`/`atrapado` normalizados a bool (`'t'/'f'/1/0/true/false`) — PDO pgsql puede devolver strings.

## Tests (TDD: rojo → verde)

### tests/Feature/DatagridTest.php (nuevo)
1. `test_pokemon_list_returns_normalized_response` — shape data/meta completo, per_page default 100.
2. `test_pokemon_list_applies_exact_filter` — `filter[id]=2`.
3. `test_pokemon_list_applies_in_filter` — `filter[id]=1,2` → 2 resultados.
4. `test_pokemon_list_applies_relation_filter_types` — `filter[types]=Eléctrico` → whereHas (solo el pokémon con ese tipo).
5. `test_pokemon_list_applies_relation_filter_types_in` — `filter[types]=Eléctrico,Agua` → whereIn.
6. `test_pokemon_list_applies_search_like` — `search=bulb` → bulbasaur.
7. `test_pokemon_list_sorts_whitelisted_column` — `sort=name&order=desc`.
8. `test_pokemon_list_ignores_non_whitelisted_filter_and_sort` — `filter[hack]=1&sort=hack` → 200, sin error, sin orderBy (orden natural).
9. `test_pokemon_list_clamps_per_page` — 500→200, 0→1.
10. `test_pokemon_list_paginates` — 150 pokémon, per_page=100, page=2 → 50.
11. `test_unregistered_model_returns_404` — `/datagrid/secreto` → 404.
12. `test_pokemon_list_returns_global_counts` — pokedex: 2 visto/1 atrapado/1 no visto sobre 3 pokémon.
13. `test_pokemon_detail_returns_full_shape` — stats (6, orden stat 1..6, stat_nombre/base_stat), types (tipo_nombre), habitat_name (primer hábitat), visto/atrapado.
14. `test_pokemon_detail_missing_returns_404`.
15. `test_registered_models_respond_200` — pokedex, reclutado, team, habitat, province.

### tests/Feature/PlayerControllerTest.php (actualizado)
- `test_pokedex_orders_pokemon_by_id` — adaptado: `$pokemons['data']` (justificación: contrato nuevo de la spec, punto 3).
- `test_pokedex_passes_counts_and_types` — nuevo: viewData `counts` correctos + `tipos` == TipoEnum::options().

### tests/Unit/WebpConverterTest.php (nuevo)
- `test_available_returns_bool_and_backend_name` — sin dependencia de GD.
- `test_convert_png_to_webp` — skip si `!available()` (CI sin GD/Imagick/CLI).

### tests/Feature/OptimizarIconosWebpTest.php (nuevo)
- `test_process_converts_only_root_pngs_and_is_idempotent` — fake converter (registra llamadas, crea el .webp): 2 PNG raíz + 1 en subdir → 1ª corrida converted=2, subdir intacto; 2ª corrida converted=0 (idempotente). No necesita GD.
- `test_command_returns_failure_without_backend` — fake unavailable → exit FAILURE.
- `test_htaccess_sets_cache_headers` — archivo existe con `max-age=31536000` e `immutable`.

## Riesgos / decisiones a validar por QA/Arquitecto
- **GD/Imagick NO están disponibles en este entorno** (verificado: `extension_loaded` false, sin `cwebp`/`convert` en PATH). El comando queda implementado y testeable con fake; la conversión real debe ejecutarse donde haya backend (CI/producción). Documentado en el comando (mensaje claro al ejecutar).
- `.htaccess` solo aplica en Apache; en Nginx/Herd/Valet hay que replicar los headers (decisión de deploy, no de código).
- El join de Pokémon añade `pokedex.visto/atrapado` como atributos dinámicos del modelo (no son columnas de `pokemon`) — normalizados a bool.
- Los contadores son globales (no respetan filtros) — necesario para el header de la Pokédex con paginación server-side.
- El contrato de `pokedex` cambia (viewData) → el FRONTEND debe adaptar la vista en paralelo. El test `test_pokedex_orders_pokemon_by_id` se actualiza (no se borra).
- PHPStan level 6: los closures de la whitelist llevan tipos; `$model::query()` con `class-string<Model>` tipado.
- Infection solo cubre `src/` (config preexistente) → nuestro código en `app/` no genera mutaciones (mismo comportamiento que tareas anteriores).
- LIKE estándar (portable SQLite/PG); en PG es case-sensitive, pero los nombres en BD están en minúsculas y el search del frontend normaliza — aceptable.

## Verificación final
- `vendor/bin/pint --dirty --format agent`
- `php artisan test --compact`
- `php artisan route:list` (rutas datagrid)
- Commit atómico + handoff QA.

---

## Estado final (completado)

### Resultados de verificación
- ✅ TDD: 24 tests nuevos/actualizados (rojo → verde).
- ✅ `php artisan test --compact`: **148 passed, 1 skipped** (el skip es `WebpConverterTest::test_convert_png_to_webp`: sin GD/Imagick/CLI en este entorno, como estaba previsto).
- ✅ Pint: pass.
- ✅ PHPStan level 6: 489 totales vs 421 baseline → **+68, todos `staticMethod.dynamicCall` de `$this->assert*` en tests** (categoría preexistente tolerada en todo el repo; verificado: 0 errores no-tolerados en mis archivos de test y **0 errores en todo el código nuevo de `app/`**). Los 6 errores de PlayerController son preexistentes (líneas de `reclutamiento()`/`equipos()` no tocadas).
- ✅ `php artisan route:list --path=datagrid`:
  - `GET datagrid/{model}` → DatagridController@index
  - `GET datagrid/{model}/{id}/detalle` → DatagridController@show
- ✅ Comando real en este entorno: `iconos:optimize-webp` reporta "No image conversion backend available (GD, Imagick or cwebp/convert CLI)" + exit 1 (esperado; la conversión real requiere instalar GD/Imagick/cwebp).
- ✅ Infection: no aplica a `app/` (config preexistente solo cubre `src/`).

### Archivos finales
**Creados**: `app/Datagrid/{DatagridDefinition,RelationFilter,DatagridRegistry,DatagridService}.php`, `app/Http/Controllers/DatagridController.php`, `app/Providers/DatagridServiceProvider.php`, `routes/datagrid.php`, `app/Support/{WebpConverterInterface,WebpConverter}.php`, `app/Console/Commands/OptimizeIconsToWebp.php`, `public/images/iconos/.htaccess`, `tests/Feature/DatagridTest.php`, `tests/Feature/OptimizeIconsToWebpTest.php`, `tests/Unit/WebpConverterTest.php`, `tests/Support/FakeWebpConverter.php`.

**Modificados**: `routes/web.php` (+require datagrid), `bootstrap/providers.php` (+DatagridServiceProvider), `app/Http/Controllers/PlayerController.php` (pokedex delega en DatagridService; adiós N+1 de stats/types por los 1350), `app/Providers/AppServiceProvider.php` (+bind WebpConverterInterface), `tests/Feature/PlayerControllerTest.php` (contrato nuevo de viewData, test actualizado no borrado).

### Notas de implementación
- Los `staticMethod.dynamicCall` de Eloquent en `app/Datagrid/` se eliminaron usando `$query->getQuery()->whereIn/orderBy` y `find()`; solo quedó el patrón tolerado preexistente en el repo.
- El closure `detail` usa `Model $model` + `instanceof Pokemon` (contravariance PHPStan) y `getAttribute('visto'/'atrapado')` (columnas del join no declaradas en el modelo).
- `Pokedex` con `per_page` default 100, primera página ordenada por id (comportamiento histórico preservado: `sort=id&order=asc` desde PlayerController).
