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

---

## Corrección QA (bloqueo `52c44d54` → commit de fix)

### Hallazgo 1 (BLOQUEANTE) — `filter[visto]=0` / `filter[atrapado]=0` sobre leftJoin
**Causa**: `pokedex` solo tiene filas con `visto=true` (el job `ActualizarPokedexJob` nunca escribe `false`); los no avistados son `pokedex.visto = NULL` tras el leftJoin. `WHERE pokedex.visto IN (false)` no matchea NULL → 0 items con `meta.counts.no_vistos = 1350`.

**Fix** (`app/Datagrid/DatagridService.php`, `applyFilters`): cuando un filtro bool incluye `false`, se genera `WHERE col IN (valores) OR col IS NULL` (la fila ausente del leftJoin también significa false). Aplica a `visto` y `atrapado` (ambos boolFields) y es inocuo para columnas no-nullable (`es_shiny`/`visto` de la propia tabla pokedex tienen default false).

**Tests añadidos** (`tests/Feature/DatagridTest.php`):
- `test_pokemon_list_filter_visto_0_returns_unseen` — sin registro pokedex aparece con `filter[visto]=0` + counts coherentes.
- `test_pokemon_list_filter_visto_1_returns_seen` — solo avistados.
- `test_pokemon_list_filter_atrapado_1_returns_captured` — solo atrapados.
- `test_pokemon_list_filter_atrapado_0_returns_not_captured` — NULL y false cuentan como "no atrapado".

### Hallazgo 2 (CONTRATO) — item del listado con `icon` y `types[]`
**Fix**: nuevo `itemFields: array<string, Closure(Model): mixed>` en `DatagridDefinition` (resueltos en `toVisibleArray` tras los campos visibles). Registro de `pokemon` en `DatagridServiceProvider`:
- `with: ['types']` (eager load, 1 query extra por página).
- `icon` → `/images/iconos/{id}.webp` (derivado del id).
- `types` → labels en español vía `tipo_nombre` (mismo mapeo TipoEnum que `filter[types]`).
- `requirePokemon(Model): Pokemon` con `instanceof` (contravariance PHPStan, mismo patrón que el resolver `detail`).

**Test añadido**: `test_pokemon_list_items_include_icon_and_types` (icon termina en `.webp`, types con labels en español).

El contrato coincide con el que ya exige `tests/Feature/PokedexViewTest.php` del frontend (PASS).

### Verificación
- ✅ TDD rojo → verde (3 rojos → 25 passed en DatagridTest+PlayerControllerTest+PokedexViewTest).
- ✅ Suite completa: **153 passed, 1 skipped** (432 assertions).
- ✅ Pint: pass.
- ✅ PHPStan: 497 totales (+8 vs 489) — los 8 son `staticMethod.dynamicCall` de asserts en DatagridTest (categoría tolerada preexistente); **0 errores en `app/Datagrid/` y `app/Providers/DatagridServiceProvider.php`**.
- ✅ Commit nuevo de corrección (el anterior quedó en el historial, no se amendeó por trazabilidad del bloqueo).

---

# Análisis Backend — Motor de exploraciones (encuentros, vuelta, recompensas)

## Qué voy a tocar

### Crear
| Archivo | Propósito |
|---|---|
| `database/migrations/2026_08_28_000001_add_experiencia_to_users_table.php` | Columna `experiencia` en `users` (default 0). |
| `database/migrations/2026_08_28_000002_create_caramelos_ev_table.php` | Tabla `caramelos_ev` (stat único + cantidad). |
| `app/Models/CarameloEv.php` | Modelo simple (patrón `Caramelo`). |
| `src/Shared/Domain/NivelHelper.php` | Curva medium-fast + fórmula EXP derrota (puro, sin Laravel; español → `src/`). |
| `src/Exploraciones/Domain/SimuladorEncuentros.php` | Pool ponderado (capture_rate/hatch) + generación de eventos con timestamps orgánicos (puro). |
| `src/Exploraciones/App/ProcesarExploracionService.php` | Orquestación: intervalo, encuentros por tick, vuelta, recompensas (caramelos familia/EV, EXP, pokedex, captura, regreso). |
| `app/Jobs/ProcesarExploracionJob.php` | Un job por exploración (aislamiento de fallos), patrón `CalcularRecompensasJob`. |
| `app/Console/Commands/ProcesarExploraciones.php` | `exploraciones:procesar` — selecciona activas y despacha jobs. |
| `tests/Unit/NivelHelperTest.php` | Matemática nivel/EXP. |
| `tests/Unit/SimuladorEncuentrosTest.php` | Ponderación, distribución, timestamps, tipos de evento. |
| `tests/Feature/ExploracionesTest.php` | Pipeline completo: command, vuelta, indefinido, recoger, recompensas. |

### Modificar
| Archivo | Cambio |
|---|---|
| `app/Models/User.php` | `experiencia` en fillable + cast + `nivel()` vía NivelHelper. |
| `routes/console.php` | `Schedule::command('exploraciones:procesar')->everyFiveMinutes()`. |
| `routes/exploraciones.php` | `POST /exploraciones/{exploracion}/recoger`. |
| `app/Http/Controllers/ExploracionActivaController.php` | `recoger()` → pipeline forzado + redirect/json. |
| `tests/Feature/MigrationStatusTest.php` | Checks de `users.experiencia` y `caramelos_ev`. |

## Decisiones de diseño (a validar)

1. **NivelHelper en `src/Shared/Domain/`** (no `app/Support/`): es lógica de dominio pura, nombre en español (convención `src/`), y queda cubierta por Infection (config preexistente solo cubre `src/`).
2. **No reutilizo `CalcularRecompensasJob` en la finalización**: repartiría EXP plana (10/pokémon) y fijaría `regreso` con doble recompensa. Replico su algoritmo de caramelos de familia (phase × count) dentro del servicio y dejo el job intacto (sus tests siguen verdes).
3. **Estructura `eventos` JSON ampliada y retrocompatible**: `{ bitacora: [...], derrotados: [...], ultimo_procesado: ISO }`. La clave `derrotados` se conserva (la lee `CalcularRecompensasJob`); se añaden `bitacora` (eventos con timestamp) y `ultimo_procesado` (frontera de tick para no duplicar encuentros entre corridas del scheduler).
4. **Nivel del pool = `pivot.level == exploracion.nivel`**: la UI muestra filas por nivel (1-3) con "N pokémon" y el usuario elige UN nivel; el pool de encuentros es exactamente el de esa fila (no acumulativo).
5. **Captura**: `capture_rate / 255` (misma convención que `ServicioCaptura`).
6. **EXP**: `expDerrota(base_experience, nivelUsuario)` por derrotado; `user.experiencia += total`; **cada** miembro del equipo suma el total completo ("guardar en cada pokemon que explora la recompensa por derrotar" — spec). Sin usuario → nivel 1 y se omite el incremento de usuario.
7. **Caramelos EV**: de `pokemon_stats.effort` (>0) por derrotado, incrementando por stat.
8. **Intervalos**: `inicio = inicio_exploracion ?? created_at`; `fin = inicio + duracion_horas` (o `hora_limite` de hoy si está fijada; si quedó en el pasado, el tick siguiente completa solo); `inicio_vuelta = fin − duración/4` (clamped a ≥ inicio); indefinido → sin fin ni vuelta (solo `recoger()` completa).
9. **1 encuentro por slot de 5 min** (`intdiv(minutos, 5)`), timestamp dentro de cada slot (jitter aleatorio inyectable para tests deterministas).
10. **`recoger()`** fuerza la finalización vía `procesar($exploracion, forzarRegreso: true)`.

## Riesgos
- Aleatoriedad: los tests de recompensas derivan las expectativas de la bitácora real (no de conteos fijos).
- `hora_limite` es columna `time` (string) → `Carbon::today()->setTimeFromTimeString()` (mismo patrón que `store`).
- PHPStan level 6: tipos estrictos en phpdoc de pool/eventos.
- Infection mutará `src/Exploraciones/` y `src/Shared/Domain/` → tests que cubren ramas (sin usuario, ya completada, etc.).

---

# Análisis Backend — Hardening (bloqueo Hardener, HEAD 11a52486)

## Qué voy a tocar (B2/B3/B4)
| Item | Archivo | Cambio |
|---|---|---|
| B2 | `app/Http/Controllers/DatagridController.php:22` | `/** @var array<string, mixed> $params */ $params = $request->query();` (stub Larastan: `query()` retorna `array` plano → error level 8 "expects array<string, mixed>, array given"). |
| B3 | `tests/Feature/DatagridTest.php` | Asserts de normalización bool en 5 tests existentes (matan mutantes de `toVisibleArray` y casts del detalle). |
| B3 | `tests/Feature/DatagridTest.php` | Nuevo `test_pokemon_detail_unseen_returns_false_booleans` (pokémon sin registro pokedex: `visto`/`atrapado` false, `types`/`stats` `[]`, `habitat_name` null — mata RemoveCast de `DatagridServiceProvider`). |
| B3 | `tests/Unit/DatagridRegistryTest.php` (nuevo) | Case-insensitive de `register/has/get`, slug desconocido → `InvalidArgumentException`, re-registro sobrescribe (mata mutantes de `strtolower`). |
| B4 | `app/Providers/DatagridServiceProvider.php:139-146` | `use LogicException;` (orden alfabético) + `sprintf('Datagrid pokemon resolvers require a Pokemon model, got %s.', $model::class)`. |
| — | `active/ANALISIS_BACKEND.md` | Esta sección (anexo, sin tocar la sección de exploraciones de otro agente). |

## Tests (TDD — cobertura anti-mutantes; la implementación ya es correcta, los tests faltaban)
- 5 asserts bool en tests de listado existentes (índices verificados contra la implementación real: `visto=0` → data[0] es el no visto; `atrapado=1` → data[0] es el capturado; etc.).
- Detalle unseen: `(bool) getAttribute('visto')` con NULL → false; `loadMissing` → colecciones vacías; `habitats->first()?->name` → null.
- Registry: los 3 tests unitarios.

## Riesgos / notas
- No tocar cambios sin commitear de otros agentes (`resources/views/exploraciones/`, `tests/Feature/ExploracionesViewTest.php`, `active/*`, `layouts/app.blade.php`) — fuera de mi alcance.
- PHPStan level 8 solo sobre los archivos de la task (los 497 errores a level 6 del repo son preexistentes tolerados).
- Commits atómicos `harden: ...` separados (tests / código).

## Estado final (hardening completado)

### Resultados de verificación
- ✅ B3 (TDD): 24 tests verdes en DatagridTest + DatagridRegistryTest (6 asserts bool nuevos en listado, `test_pokemon_detail_unseen_returns_false_booleans`, 3 unit tests del registry).
- ✅ B2: `vendor/bin/phpstan analyse app/Datagrid/ app/Providers/DatagridServiceProvider.php app/Http/Controllers/DatagridController.php app/Support/ app/Console/Commands/OptimizeIconsToWebp.php --level=8` → **0 errores en app/**. Error adicional encontrado y corregido: `typeNames()` a level 8 devolvía `array<mixed>` → `array_values(...->all())` (garantiza `list<string>`).
- ✅ B4: `use LogicException;` (orden alfabético) + `sprintf('Datagrid pokemon resolvers require a Pokemon model, got %s.', $model::class)`.
- ✅ Pint: pass en mis archivos (Pint --dirty también retocó 2 archivos del WIP de exploraciones — no incluidos en mis commits).
- ⚠️ Suite completa: **31 failed** — TODOS de la tarea de exploraciones de otro agente (WIP sin commitear: NivelHelperTest/SimuladorEncuentrosTest/ExploracionesTest con `CommandNotFoundException`). Mis archivos: **34 passed, 1 skipped** (DatagridTest 21 + PlayerControllerTest 2 + PokedexViewTest 3 + OptimizeIconsToWebpTest 4 + DatagridRegistryTest 3 + WebpConverterTest 1+1skip).
- ✅ PHPStan level 6 global: 606 (+109 vs 497) — el incremento es del WIP de exploraciones (src/Shared, src/Exploraciones, jobs); mis archivos: 0 errores (solo asserts tolerados en tests).

### Commits
- `harden: ...` tests (B3) y `harden: ...` código (B2+B4), atómicos.

---

# Análisis Backend — WebP en carpeta separada `public/images/iconos_webp/` (decisión del usuario)

## Contexto
- cwebp 1.3.2 instalado en `/usr/bin/cwebp` (GD/Imagick siguen ausentes) → `WebpConverter` lo detecta vía CLI, sin cambios.
- Decisión del usuario: los WebP NO van junto al PNG; salida en `public/images/iconos_webp/` (no existe aún).
- Realidad del input: 1032 PNG, 188 MB (el Analista estimaba ~7 MB; reporto el real).

## Cambios
| Archivo | Cambio |
|---|---|
| `app/Console/Commands/OptimizeIconsToWebp.php` | Nueva opción `--out=` (default `public/images/iconos_webp`). `process(string $dir, string $out)`: crea `$out` con `File::makeDirectory(recursive)`, valida escribible; por cada PNG de la raíz de `$dir` escribe `$out/{base}.webp`; idempotente contra la SALIDA; PNG originales intactos. |
| `app/Providers/DatagridServiceProvider.php` (~L58) | `icon` → `/images/iconos_webp/{id}.webp`. |
| `public/images/iconos_webp/.htaccess` (nuevo) | Mismos headers que el de `iconos/` (`Cache-Control: public, max-age=31536000, immutable`). NO se borra el de `public/images/iconos/`. |
| `tests/Feature/DatagridTest.php` | `test_pokemon_list_items_include_icon_and_types` → `/images/iconos_webp/1.webp` y `/2.webp`. |
| `tests/Feature/OptimizeIconsToWebpTest.php` | Adaptar a `--out` (carpeta salida separada, idempotencia contra salida, input intacto, subdir no tocado) + test REAL de conversión con cwebp (file_exists + tamaño > 0; skip solo si no hay backend) + test `.htaccess` de `iconos_webp/`. |
| `tests/Feature/PokedexViewTest.php` | Fixtures de contrato `icon` → `/images/iconos_webp/{id}.webp` (coherencia; no asertan URL en HTML). |

## Tests (TDD: rojo → verde)
1. DatagridTest icon → iconos_webp (falla con implementación actual → rojo).
2. OptimizeIconsToWebpTest: reescrito para `--out` + test real cwebp + htaccess nuevo.
3. PokedexViewTest: fixtures actualizados.

## Riesgos
- `test_command_returns_failure_without_backend` ahora se SKIPEA (cwebp disponible) — correcto, el camino de fallo ya no aplica en este entorno.
- La conversión real de 1032 PNG (188 MB) puede tardar varios minutos → timeout amplio.
- El `.htaccess` se crea antes de la conversión; `glob('*.png')` no lo afecta.
- Suite completa tiene 13 failed preexistentes de Exploraciones (WIP de otro agente) — se confirman y se ignoran; los míos deben pasar.
- Commit: NO incluir WIP ajeno (exploraciones) ni `active/ANALISIS_BACKEND.md` si contiene secciones de otro agente — se decide al stagear.

## Estado final (webp en carpeta separada — completado)

### Resultados
- ✅ TDD: 5 tests rojos → verde. `OptimizeIconsToWebpTest` reescrito (process($dir, $out), idempotencia contra salida, input intacto, subdir no tocado, test REAL con cwebp — file_exists + filesize > 0), `test_htaccess_iconos_webp_sets_cache_headers` nuevo, DatagridTest con `/images/iconos_webp/{id}.webp`.
- ✅ PokedexViewTest: ya actualizado por el FRONTEND en paralelo (fixtures `iconos_webp` + asserts de fallback explícito a `/images/iconos/{id}.png`). Sin cambios míos.
- ✅ Suite completa: **199 passed, 1 skipped, 0 failed** (el otro agente arregló su WIP de exploraciones; ya no hay 13 failed).
- ✅ Pint: pass. PHPStan level 8 en mis archivos: 0 errores reales.
- ✅ **Conversión real ejecutada**: `php artisan iconos:optimize-webp` → Backend cwebp, **Converted: 1032, Skipped: 0, Errors: 0**. Resultado: 1032 webp en `public/images/iconos_webp/` (**5.2 MB** vs 188 MB de PNG → ~97% de reducción; el Analista estimaba 3-4 MB sobre una base de ~7 MB, la base real era 188 MB). Segunda corrida: 0 convertidos / 1032 skipped (idempotencia real verificada). PNG originales intactos (1032).
- ✅ `.htaccess` nuevo en `public/images/iconos_webp/` (max-age=31536000, immutable); el de `public/images/iconos/` NO se borró (sigue sirviendo los PNG como fallback).
- ⚠️ `active/ANALISIS_BACKEND.md` actualizado pero NO commiteado (contiene secciones del WIP de exploraciones de otro agente — se evita arrastrarlas al commit).

### Contrato (para FRONTEND)
- `icon` del datagrid ahora apunta a `/images/iconos_webp/{id}.webp`. Los PNG siguen en `/images/iconos/{id}.png` como fallback (la vista ya lo maneja, ver PokedexViewTest).

---

## Estado final (implementado)

### Resultados de verificación
- ✅ TDD rojo → verde: 45 tests nuevos/actualizados en la primera pasada; suite completa **199 passed, 1 skipped** (el skip preexistente de WebpConverter sin backend).
- ✅ Pint: pass.
- ✅ PHPStan level 6: **580 totales** (la baseline preexistente era ~623) → **reduje errores**; **0 errores no-tolerados en mi código de producción nuevo** (las 2 categorías restantes en mis archivos son `staticMethod.dynamicCall` de asserts en tests, patrón tolerado en todo el repo).
- ✅ Infection (src/Exploraciones + NivelHelper): **Covered MSI 99%** (251/256 mutantes eliminados; 1 escapado es falso positivo equivalente: `return 0.0` para capture_rate ≤ 0 es idéntico a `0/divisor`; 3 timeouts), umbral 80% superado.
- ✅ `php artisan route:list`: `POST /exploraciones/{exploracion}/recoger` registrada.
- ✅ `php artisan schedule:list`: `*/5 * * * * php artisan exploraciones:procesar` registrada.

### Archivos creados
`database/migrations/2026_08_28_000001_add_experiencia_to_users_table.php`, `2026_08_28_000002_create_caramelos_ev_table.php`, `app/Models/CarameloEv.php`, `src/Shared/Domain/NivelHelper.php`, `src/Exploraciones/Domain/SimuladorEncuentros.php`, `src/Exploraciones/App/ProcesarExploracionService.php`, `app/Jobs/ProcesarExploracionJob.php`, `app/Console/Commands/ProcesarExploraciones.php`, `tests/Unit/NivelHelperTest.php`, `tests/Unit/SimuladorEncuentrosTest.php`, `tests/Feature/ExploracionesTest.php`.

### Archivos modificados
`app/Models/User.php` (experiencia + `nivel()`), `app/Models/EvolutionChain.php` / `TeamMember.php` / `Reclutado.php` (tipos de retorno en relaciones — elimina errores PHPStan preexistentes y desbloquea la resolución de relaciones), `app/Http/Controllers/ExploracionActivaController.php` (+`recoger()`), `routes/exploraciones.php` (+ruta), `routes/console.php` (+Schedule), `tests/Feature/MigrationStatusTest.php` (+checks esquema).

### Decisiones tomadas durante la implementación
- **Carbon 3 `diffInMinutes/diffInSeconds` son FIRMADOS** (devuelven negativo cuando `$this > $other`) y **float** → `abs()` + `(int)` en los tres puntos de cálculo (vuelta, encuentros por tick, slots). Fue el bug principal (vuelta mal calculada → la exploración "completaba" sin encuentros).
- **`derrotados` solo se escribe al finalizar** (no durante la exploración activa) — los tests se ajustaron a ese contrato.
- **Nivel de pool = `pivot.level == exploración.nivel`** (la UI ofrece filas de nivel 1-3; se explora exactamente esa fila). Implementado con `wherePivot` (evita acceso a `$pivot` y N+1).
- **Timestamps en bitácora con `toIso8601String()`** (formato `+00:00`, legible; parseable con `Carbon::parse`).
- **EXP**: cada miembro del equipo recibe el total completo (spec: "guardar en cada pokemon que explora la recompensa por derrotar"); `user.experiencia` suma el total; sin usuario → nivel 1.
- **Captura**: `capture_rate / 255` (convención `ServicioCaptura`).
- **No se reutiliza `CalcularRecompensasJob`** en la finalización (repartiría EXP plana y doblaría recompensas); su algoritmo de caramelos de familia (phase × count) se replicó en el servicio y el job quedó intacto con sus tests verdes.
- **Modelos con relaciones sin tipo** (`EvolutionChain::pokemon`, `TeamMember::reclutado/team`, `Reclutado::pokemon`) recibieron tipos de retorno genéricos — solo firma, cero cambio de comportamiento, y reduce la baseline de PHPStan en ~43 errores (incluidos `CalcularRecompensasJob`/`ValidadorExploracion`).

### Nota QA
La notación `eventos` JSON ahora es `{ bitacora: [...], derrotados: [...], ultimo_procesado: ISO }` — `derrotados` se conserva retrocompatible (la lee `CalcularRecompensasJob`). No se tocó la BD de desarrollo (solo `laravel_test`).

---

# Análisis Backend — Ajuste de curva de EXP (medio ×10, sin tope de nivel)

## Cambio solicitado
- Curva media ×10: `exp_total = 10 × nivel³` → nivel 100 = 10.000.000 exp (antes: `nivel = cbrt(exp)` → 1.000.000).
- **Sin tope de nivel**: `nivelDesdeExperiencia` devuelve niveles > 100 (no clamp).
- Mantener la corrección de precisión flotante del código actual (potencias exactas), aplicada sobre `exp/10` ANTES de la raíz cúbica.
- `expDerrota` (Gen V: `floor(base × nivel / 5)`) NO cambia.

## ⚠️ Decisión sobre inconsistencia off-by-one en la spec
La spec mezcla dos reglas incompatibles (no pueden cumplirse ambas):
- **Ejemplos pequeños** (nivel 2 en exp 10, 3 en 80, 4 en 270) → umbral(N) = 10×(N−1)³.
- **Fórmula principal + sketch de código** (`floor(cbrt(exp/10))`) y ejemplos grandes (nivel 100 EXACTO en 10.000.000, nivel 101 en 10.303.010 = 10×101³) → umbral(N) = 10×N³.

**Decisión: se implementa la FÓRMULA** (`nivel = floor(cbrt(exp/10))`, nivel 100 en 10.000.000 exacto), porque:
1. Es el enunciado principal, repetido dos veces: "experiencia = 10 × nivel³ → level 100 requires 10.000.000 exp".
2. Es exactamente el sketch que el usuario adjuntó como implementación nueva (`(int) floor($experiencia / 10) ** (1/3)`), que devuelve nivel 1 en exp 10 y 2 en exp 80 — contradice sus propios ejemplos pequeños.
3. "Level 100 exactly at 10,000,000" y "Level 101 at 10,303,010 (10×101³)" solo son ciertos con la fórmula.
Consecuencia: exp 10 → nivel 1 (no 2), exp 80 → nivel 2 (no 3), exp 270 → nivel 3 (no 4). Los tests reflejan la fórmula.
Si se prefiriera la regla de los ejemplos pequeños (umbral(N) = 10×(N−1)³), el cambio es de una línea: `return max(1, $nivel + 1);` — quedaría nivel 100 en [9.702.990, 10.000.000) y 10.000.000 → nivel 101 (rompería "level 100 requires 10M").

## Qué voy a tocar
| Archivo | Cambio |
|---|---|
| `src/Shared/Domain/NivelHelper.php` | `nivelDesdeExperiencia`: guard `<= 0 → 1`; `base = exp / 10`; `nivel = floor(base ** (1/3))` + loops de corrección de potencia exacta (patrón actual); `max(1, nivel)` para que exp 1-9 sea nivel 1. |
| `tests/Unit/NivelHelperTest.php` | Umbrales de la curva nueva: 1 (0-79), 2 (80-269), 3 (270-639), 4 (640-1.249), 5 (1.250, caso precisión `125**(1/3)`), 100 exacto en 10.000.000, 101 en 10.303.010 (sin tope), 200 en 80.000.000. `expDerrota` intacto. |
| `tests/Feature/ExploracionesTest.php` | Solo EXPECTATIVAS de nivel derivado: fixture `experiencia 1.250 → nivel 5` (antes 125 → 5 con curva vieja), `+1_250` en el assert de exp del usuario, y `0 → nivel 1` en el test de derivación. |

## Qué NO toco (verificado)
- `app/Models/User.php::nivel()` — firma intacta, solo cambia el valor derivado.
- `src/Exploraciones/App/ProcesarExploracionService.php` — solo usa `NivelHelper::expDerrota` (línea 234), sin cambios.
- `tests/Unit/SimuladorEncuentrosTest.php`, resto de suite de exploraciones — sin dependencia de `nivelDesdeExperiencia` (grep).

## Umbrales nuevos (10 × nivel³)
| Nivel | exp mínima | Nivel | exp mínima |
|---|---|---|---|
| 1 | 0 | 100 | 10.000.000 |
| 2 | 80 | 101 | 10.303.010 |
| 3 | 270 | 102 | 10.612.080 |
| 4 | 640 | 200 | 80.000.000 |
| 5 | 1.250 | — | — |

## Tests (TDD rojo → verde)
1. Nivel 1: exp 0, 9, 79 → 1.
2. Nivel 2: exp 80, 269 → 2.
3. Nivel 3/4/5: exp 270/639 → 3; 640/1.249 → 4; 1.250 → 5.
4. Precisión flotante: exp 1.250 (base 125, `125**(1/3)=4.999...`) → 5; exp 10.000.000 → 100.
5. Nivel 100 exacto: exp 9.999.999 → 99; 10.000.000 → 100.
6. Sin tope: exp 10.000.001 y 10.303.009 → 100; 10.303.010 y 10.303.011 → 101; 80.000.000 → 200.
7. `expDerrota` sin cambios (Gen V, floor).
8. ExploracionesTest: recompensas con usuario nivel 5 (exp 1.250) y sin usuario (nivel 1) — fórmulas intactas.

## Riesgos
- ExploracionesTest tenía asserts de nivel derivado de la curva vieja (exp 125 → 5 y exp 0 → 0): actualizados, NO borrados.
- `exp/10` en float: misma corrección de potencia exacta que ya usa el código → robusto en umbrales; valores > 2^53 pierden precisión, irrelevante para el juego.
- No tocar BD dev; solo `laravel_test`.

## Verificación
- `php artisan test --compact tests/Unit/NivelHelperTest.php` → 9 passed (25 assertions).
- `php artisan test --compact tests/Feature/ExploracionesTest.php` → 13 passed (50 assertions).
- `vendor/bin/pint --dirty --format agent`

---

# Análisis Backend — Iconos WebP en src/Habitats/Infra/HabitatRepository.php (especificación del Analista)

## Cambio
`src/Habitats/Infra/HabitatRepository.php` — 4 líneas que generan el campo `icon`:
- L90 (`getHabitatDetail`), L313 (`getFamilyMembersByChain`), L331 (`buildAvailableFamilyFromChain`), L364 (`buildUnassignedFamilyFromChain`): `/images/iconos/{id}.png` → `/images/iconos_webp/{id}.webp`.
- PHPDoc breve en la clase indicando que el icon servido es WebP (`/images/iconos_webp/{id}.webp`; los PNG originales quedan en `/images/iconos/` como fuente/fallback).

## Tests
- Grep en tests/: NINGÚN test aserta los `icon` del HabitatRepository (los hits de `images/iconos` en tests son de PokedexViewTest/ExploracionesViewTest/OptimizeIconsToWebpTest → NO tocar, los gestiona el frontend o ya son correctos).
- Infraestructura de test para hábitats: existe `tests/Feature/Habitats/FamiliesTest.php` (familias) y `HabitatsControllerTest` (usan RefreshDatabase + factories de Province/Habitat). Añadiré asserts mínimos de `icon` en los tests existentes de hábitats si cubren el JSON; si no, un test mínimo del repository (detalle/familias) que aserte `/images/iconos_webp/{id}.webp`.

## Fronteras
- NO tocar `app/` (datagrid ya sirve webp), ni vistas Blade (frontend en paralelo), ni OptimizeIconsToWebpTest/PokedexViewTest/ExploracionesViewTest.
- La línea 109 (`/habitats-img/{$habitat->id}.webp`) ya es webp — sin cambios.

---

# Análisis Backend — Share nivel + progreso del jugador a TODAS las vistas (View::share)

## Qué voy a tocar
| Archivo | Cambio |
|---|---|
| `src/Shared/Domain/NivelHelper.php` | +`experienciaParaNivel(int): int` (10 × nivel³) +`progresoHaciaSiguienteNivel(int): int` (0-100, clamp [0,100]). |
| `app/Providers/AppServiceProvider.php` | `boot()` → `View::share('nivelJugador', ...)` + `View::share('progresoNivel', ...)` vía `User::first()?->experiencia ?? 0`. |
| `tests/Unit/NivelHelperTest.php` | Nuevos tests de los dos métodos (TDD rojo → verde). |

## Verificación de consistencia con `nivelDesdeExperiencia` (números REALES computados)
La curva actual: `nivel = floor(cbrt(exp/10))` + guard `exp ≤ 0 → 1` → eres nivel N con `exp ∈ [10·N³, 10·(N+1)³)` (y exp 0..9 → nivel 1 por el guard). Por tanto `experienciaParaNivel(N) = 10·N³` es EXACTAMENTE el umbral de entrada del nivel N:
- `experienciaParaNivel(1) = 10` (umbral de nivel 1; exp < 10 también es nivel 1 por el guard) ✓
- `experienciaParaNivel(2) = 80` (exp 80 → nivel 2, test existente) ✓
- `experienciaParaNivel(100) = 10.000.000` (exp 10M → nivel 100 exacto) ✓

## ⚠️ Decisión de diseño: CLAMP a [0, 100] (imprescindible)
La fórmula cruda devuelve **progreso NEGATIVO** para exp 0..9 (nivel 1, inicio=10): `(0−10)/(80−10) = −14 %`. Eso rompería:
1. El test WIP del FRONTEND `tests/Feature/HeaderNivelViewTest::test_layout_falls_back_without_shared_variables` (aserta `style="width: 0%"` sin usuario; con −14 renderizaría `width: -14%` → CSS inválido → barra a ancho completo).
2. Las expectativas de la propia spec ("exp 0→0%, exp 9→0%").
→ Se añade `max(0, min(100, ...))` al retorno. Sin usuario → exp 0 → nivel 1, progreso 0% (los valores por defecto que espera el layout `?? 1` / `?? 0`).

## Valores de test (computados y verificados por ejecución)
| exp | nivel | inicio | fin | progreso |
|---|---|---|---|---|
| 0 | 1 | 10 | 80 | **0** (crudo −14 → clamp) |
| 9 | 1 | 10 | 80 | **0** (crudo −1 → clamp) |
| 10 | 1 | 10 | 80 | **0** |
| 45 | 1 | 10 | 80 | **50** (midpoint) |
| 79 | 1 | 10 | 80 | **99** |
| 80 | 2 | 80 | 270 | **0** (umbral nuevo nivel) |
| 175 | 2 | 80 | 270 | **50** (midpoint) |
| 269 | 2 | 80 | 270 | **99** |
| 270 | 3 | 270 | 640 | **0** |
| 10.000.000 | 100 | 10.000.000 | 10.303.010 | **0** |
| 10.303.009 | 100 | 10.000.000 | 10.303.010 | **100** |

## Riesgos
- `User::first()` en `boot()` consulta la BD en cada boot (tests incluidos). BD de tests = PostgreSQL persistente `laravel_test`, MIGRADA y con **0 usuarios** (verificado) → sin error y con valores nivel 1/0% en el fallback. Las clases RefreshDatabase dejan la BD commitada vacía (transacciones) → el share de boot es estable.
- `boot()` corre también en consola (artisan): consulta contra BD dev `laravel` (migrada, existe) → OK.
- No toco vistas Blade (`layouts/app.blade.php` es WIP del FRONTEND) ni `bootstrap/app.php` (AppServiceProvider ya está registrado por defecto en Laravel 12).
- PHPStan level 6: `$user?->experiencia ?? 0` → `int`; firmas tipadas.
- No tocar BD dev; solo `laravel_test` (nada se escribe: solo SELECT).

## Verificación
- `php artisan test --compact tests/Unit/NivelHelperTest.php`
- `php artisan test --compact` (suite completa; HeaderNivelViewTest debe seguir verde)
- `vendor/bin/pint --dirty --format agent`
- PHPStan level 6 sobre archivos tocados.

## Estado final (completado)
- ✅ TDD: 5 tests nuevos rojos → verde (14 passed, 42 assertions en NivelHelperTest).
- ✅ `HeaderNivelViewTest` (WIP del FRONTEND) verde con el share activo (3 passed): `Nv 7`/`width: 45%` explícitos, y el fallback sin usuario → `Nv 1`/`width: 0%` (compartido desde boot: BD de tests sin usuarios commitados).
- ✅ Suite completa: **212 passed, 1 skipped** en 4 corridas consecutivas. La PRIMERA corrida dio 17 failed (excepción de BD en `RecompilarHabitatJsonJobTest`, estado commitado residual de sesiones previas de otros agentes); el test pasa aislado y las corridas posteriores son verdes → flakiness preexistente de orden, no causada por este cambio (boot solo hace SELECT de solo lectura).
- ✅ PHPStan level 6: 0 errores en `NivelHelper.php` y `AppServiceProvider.php` (test file: solo `staticMethod.dynamicCall` de asserts, categoría tolerada en todo el repo).
- ✅ Pint: pass.

### Desviaciones de la spec (justificadas y verificadas)
1. **Clamp a [0,100] en `progresoHaciaSiguienteNivel`**: la fórmula cruda devuelve −14 % con exp 0 (nivel 1, inicio 10) → habría roto `HeaderNivelViewTest::test_layout_falls_back_without_shared_variables` (`width: 0%`) y renderizado CSS inválido. Los tests asertan los valores clampados: 0→0, 9→0, 10→0, 45→50, 79→99, 80→0 (nivel nuevo), 175→50, 269→99, 270→0, 10.000.000→0, 10.303.009→100.
2. **`(int) $user->getAttribute('experiencia')` en vez de `$user?->experiencia ?? 0`**: la BD dev `laravel` NO tiene la columna `experiencia` (migración WIP sin aplicar; prohibido tocar BD dev) → `$user->experiencia` es `null` en runtime pese al cast del modelo → `nivelDesdeExperiencia(null)` TypeErrors en boot (rompía PHPStan bootstrap y TODOS los comandos artisan contra dev). `getAttribute()` + `(int)` normaliza null→0 sin disparar `cast.useless` de Larastan y sin tocar la BD.
3. Sin usuario (tabla vacía): `User::first()` → null → exp 0 → nivel 1, progreso 0 % (requisito "con no users no debe errorar" ✓).

### Archivos finales
- `src/Shared/Domain/NivelHelper.php` (+`experienciaParaNivel`, +`progresoHaciaSiguienteNivel` con clamp).
- `app/Providers/AppServiceProvider.php` (`boot()` con `View::share('nivelJugador')` y `View::share('progresoNivel')`).
- `tests/Unit/NivelHelperTest.php` (+5 tests, 17 asserts).
- `active/ANALISIS_BACKEND.md` (esta sección).
- NO se tocó: vistas Blade, `bootstrap/app.php`, BD dev, tests del frontend.

## Estado final (iconos webp en HabitatRepository — completado)

### Cambios
- `src/Habitats/Infra/HabitatRepository.php`: 4 líneas `icon` → `/images/iconos_webp/{id}.webp` (L90 `getHabitatDetail`, L313 `getFamilyMembersByChain`, L331 `buildAvailableFamilyFromChain`, L364 `buildUnassignedFamilyFromChain`) + PHPDoc en la clase (webp servido; PNG en `/images/iconos/` como fuente/fallback).

### Tests (TDD rojo → verde)
- `tests/Feature/Habitats/FamiliesTest.php`: asserts de icon webp en `test_obtener_familias_disponibles...` (base + 2 evoluciones) y `test_obtener_familias_sin_habitat...` (genérico por id) + nuevo `test_detalle_habitat_iconos_son_webp` (vía `HabitatRepository::getHabitatDetail`, `levels` y `toArray()`).
- No existían tests del repository que asertaran icon; se usó la infraestructura existente de FamiliesTest.

### Verificación
- ✅ 3 tests rojos → verde; FamiliesTest 13 + HabitatsControllerTest 10 = 23 passed.
- ✅ Suite completa: **207 passed, 1 skipped, 0 failed** (3 corridas consecutivas).
- ⚠️ Durante la verificación hubo deadlocks PostgreSQL intermitentes (`laravel_test` compartida con la suite del WIP de exploraciones de otro agente, que corre en paralelo): `migrate:fresh --env=testing` limpió la DB corrupta; tras finalizar el otro agente, la suite pasa estable 3/3.
- ✅ Pint: pass. PHPStan sobre `HabitatRepository.php`: 8 errores preexistentes (idénticos antes/después del cambio vía stash) — 0 nuevos.
- ✅ `grep images/iconos src/` → solo el PHPDoc de fallback (correcto).
- ⚠️ `active/ANALISIS_BACKEND.md` actualizado pero NO commiteado (contiene secciones del WIP de exploraciones de otro agente).

---

# Análisis Backend — Página /exploraciones (index + cerrar) y resumen de resultados

## Contexto
El motor de exploraciones está implementado (command, service, jobs, migraciones) y la vista `resources/views/exploraciones/index.blade.php` está commiteada (`d85fd418`) con un contrato de datos que NINGUNA ruta sirve todavía. Esta tarea implementa las rutas/controlador que materializan el contrato y hace que el servicio persista un resumen `resultado` en `eventos` al completar.

## Qué voy a tocar
| Archivo | Cambio |
|---|---|
| `src/Exploraciones/App/ProcesarExploracionService.php` | `finalizar()` persiste `eventos['resultado']` = `{avistados, capturados, caramelos_familia, caramelos_ev, exp}` (con nombres). La captura se resuelve en el servicio (roll síncrono con la MISMA regla que `CapturarPokemonJob`) para que el resumen sea exacto; se deja de despachar `CapturarPokemonJob` desde la finalización (el job queda para `ServicioCaptura`, intacto). |
| `app/Http/Controllers/ExploracionActivaController.php` | +`index()` (contrato completo activas/terminadas) +`cerrar()` (delete + redirect/json, 404 si activa). |
| `routes/exploraciones.php` | `GET /exploraciones` + `POST /exploraciones/{exploracion}/cerrar`. |
| `tests/Feature/ExploracionesPageTest.php` | Nuevo: página (activas/terminadas/bitácora/resultado) + cerrar. |
| `tests/Feature/ExploracionesTest.php` | +test de `eventos['resultado']` (TDD sobre el servicio). |
| `active/ANALISIS_BACKEND.md` | Esta sección. |

## Decisiones de diseño
1. **Resumen persistido en `eventos['resultado']`** (decisión del enunciado): el servicio lo escribe al completar con nombres resueltos (pokemon `name`, familia = base de la cadena por `species_id`, EV solo `stat` → `stat_nombre` lo enriquece el controlador).
2. **Captura síncrona en el servicio**: para que `resultado.capturados` refleje EXACTAMENTE los reclutables, el roll (`mt_rand(1,100)/100 <= capture_rate/255`, misma fórmula que el job) se hace en `finalizar()` y se incrementa `Reclutable` directamente. `CapturarPokemonJob` queda para `ServicioCaptura` (patrón de batalla, sus tests intactos). Tests existentes con capture_rate 255 (chance 1.0) no cambian su expectativa.
3. **`stat_nombre` en el controlador** con el mapa EXACTO de la vista (1→PS, 2→Ataque, 3→Defensa, 4→Ataque Especial, 5→Defensa Especial, 6→Velocidad); `StatEnum::label()` devuelve 'PS (HP)' para HP y divergiría del fallback JS de la vista (`statName(1) === 'PS'`).
4. **Cálculos duplicados controlador/servicio** (fin, vuelta, estado, progreso): el controlador replica las reglas del servicio (`hora_limite` de hoy, duración, indefinido → sin fin) para que la página muestre lo mismo que procesa el motor.
5. **`cerrar()`** solo borra completadas (`regreso !== null` → 404 si no); devuelve `{success: true}` en JSON o redirect con flash (patrón `recoger()`).
6. **Progreso**: % de tiempo transcurrido entre inicio y fin, clamp [0,100]; indefinido → 0.

## Tests (TDD rojo → verde)
1. `tests/Feature/ExploracionesPageTest.php`: GET 200 + `assertViewIs('exploraciones.index')`; activas con bitácora transformada (nombre pokemon + stat_nombre); 'volviendo'/progreso con `travelTo`; indefinida (sin fin/vuelta, 0%, 'explorando'); terminadas con resultado (stat_nombre añadido); terminadas sin resultado → `[]`; cerrar (redirect + JSON `{success:true}` + 404 si activa).
2. `tests/Feature/ExploracionesTest.php`: `test_servicio_guarda_resumen_de_resultado` — exp/avistados/capturados/familia/EV derivados de la bitácora real (nunca conteos fijos).

## Riesgos
- Carbon 3: `diffInMinutes/diffInSeconds` firmados y float → `abs()` + `(int)` (mismo patrón que el servicio).
- Nombres en BD en minúscula (bulbasaur) — la vista los muestra tal cual (contrato existente).
- `viewData()` de `TestResponse` para asertar el contrato completo de activas/terminadas.
- No tocar BD dev; solo `laravel_test`.
- El cambio de captura síncrona modifica el flujo de finalización: verificar que `ServicioCapturaTest` y `CapturarPokemonJobTest` sigan verdes (no tocan la exploración).

---

# Análisis Backend — Cierre paquete Hardener (FAIL re-verificado + B4)

## Verificación B1/B2/B3 (ya aplicados en rondas anteriores — comprobado en el árbol)
| Hallazgo | Estado | Evidencia |
|---|---|---|
| B1 listener leak (frontend) | ✅ Verificado | `resources/views/pokedex/index.blade.php`: `clickHandler: null,` L359; `addEventListener('click', this.clickHandler)` L410; `removeEventListener` + reset null L420-421. |
| B2 PHPStan DatagridController:22 | ✅ Verificado | `app/Http/Controllers/DatagridController.php` L21-22: `/** @var array<string, mixed> $params */` antes de `$params = $request->query();`. |
| B3 tests anti-mutantes | ✅ Verificado | DatagridTest L81-82/L308/L324/L340/L357 (asserts bool), L273 `test_pokemon_detail_unseen_returns_false_booleans`, `tests/Unit/DatagridRegistryTest.php` presente. |

## B4 — nuevo guard dir ≠ out (aplicado con TDD)
- `app/Console/Commands/OptimizeIconsToWebp.php` handle(): tras los guards de `$dir`/`$out`, `if (realpath($out) === realpath($dir))` → error "Output directory must differ from the source directory." + FAILURE. (Simplificado: `is_string($dir)` eliminado por `function.alreadyNarrowedType` a level 8 — el guard previo ya lo estrecha.)
- Test `test_command_rejects_same_input_and_output_directory`: `--dir=x --out=x` → exit 1, mensaje, no genera `.webp` junto a los PNG, 0 llamadas al converter (rojo → verde).
- No rompe el flujo normal (dir ≠ out por defecto).

## Verificación final
- ✅ Suite completa: **213 passed, 1 skipped, 0 failed** (2º intento; el 1º tuvo 2 failed transitorios por interferencia de DB del WIP de exploraciones — DatagridTest pasa 21/21 aislado).
- ✅ PHPStan level 8 sobre DatagridController + OptimizeIconsToWebp: **No errors**.
- ✅ Pint: pass.
- ⚠️ `active/ANALISIS_BACKEND.md` actualizado pero NO commiteado (contiene secciones del WIP de exploraciones de otro agente).

---

# Análisis Backend — Filtro de esfuerzo (EVs) en datagrid de pokémon

## Decisión de diseño (documentada)
`filter[effort]=Ataque|2` debe matchear pokémon que otorgan EVs en esa stat:
`whereHas('stats', fn ($q) => $q->where('stat', $id)->where('effort', '>', 0))`.

El `RelationFilter` actual solo aplica `whereIn(columna, mapped)` (usado por `types`). **Opción elegida: A — extender `RelationFilter`** con un 4º parámetro opcional `?Closure $constraint` (default `null` → comportamiento actual de whereIn intacto; `types` no se toca). `DatagridService::applyRelationFilter` despacha al constraint si existe. Es la opción más simple: un parámetro opcional, cero cambios de contrato público, sin tocar el servicio de paginación/filtros.

## Cambios
| Archivo | Cambio |
|---|---|
| `app/Datagrid/RelationFilter.php` | Nuevo `public readonly ?Closure $constraint` (opcional). PHPDoc: `Closure(Builder, list<mixed>): void\|null`. |
| `app/Datagrid/DatagridService.php` | `applyRelationFilter`: si `$filter->constraint !== null` → `($filter->constraint)($q, $mapped)`; si no, whereIn actual. |
| `app/Providers/DatagridServiceProvider.php` | `relationFilters['effort'] = new RelationFilter('stats', 'stat', map: statId, constraint: whereIn('stat', $mapped) + where('effort', '>', 0))`. Helper `statId(mixed): ?int` espejo de `tipoId` con `StatEnum` (int/numeric directo o label español case-insensitive). Import `StatEnum`. |

## Semántica declarada
- `filter[effort]=Ataque` o `filter[effort]=2` → mismo resultado.
- Label inválido / id inexistente → `statId` devuelve `null` → `$mapped = []` → **filtro ignorado silenciosamente** (misma semántica que `types`).
- Se combina con `filter[types]` como AND (cada relationFilter es un whereHas independiente).

## Tests (TDD rojo → verde) en `tests/Feature/DatagridTest.php`
1. `test_pokemon_list_filter_effort_by_label` — Ataque effort>0 aparece; Ataque effort=0 no.
2. `test_pokemon_list_filter_effort_by_id` — `filter[effort]=2` mismo resultado.
3. `test_pokemon_list_filter_effort_label_invalid_ignored` — label inexistente → 200 sin filtrar (ignorado).
4. `test_pokemon_list_filter_effort_combines_with_types` — AND con types.
El helper `createPokemon` gana un 4º parámetro opcional `$efforts` (map stat => effort) para crear stats con effort>0.

## Riesgos
- `constraint` con closure tipado para PHPStan level 8 (contravariance: el closure recibe `Builder` del whereHas — tipo `Builder<PokemonStat>`; el closure declarado `Builder` es supertipo, OK).
- WIP de exploraciones ajeno en el árbol: git add EXPLÍCITO, nunca -A.

## Estado final (filtro effort — completado)

### Implementado
- **Opción A**: `RelationFilter` con 4º parámetro opcional `?Closure $constraint` (`Closure(Builder<Model>, list<mixed>): void|null`). `applyRelationFilter` despacha al constraint si existe; si no, el whereIn actual (contrato de `types` intacto).
- Provider: `'effort' => new RelationFilter('stats', 'stat', map: statId, constraint: whereIn('stat', $mapped) + where('effort', '>', 0))`.
- Helper `statId()`: espejo de `tipoId` con `StatEnum` (int/numeric directo o label español case-insensitive → `$case->value` o `null`).

### Semántica (declarada para QA/frontend)
- `filter[effort]=Ataque` == `filter[effort]=2` (label español o id de stat).
- Label inválido → filtro **ignorado silenciosamente** (misma semántica que `types`).
- Se combina con `filter[types]` en AND (whereHas independientes).

### Tests (TDD rojo → verde)
- 4 nuevos en DatagridTest: `by_label` (Ataque effort>0 aparece, effort=0 no), `by_id` (2), `label_invalid_ignored` (200 sin filtrar), `combines_with_types` (AND). Helper `createPokemon` gana `$efforts` opcional.
- DatagridTest: **25 passed** (21 previos + 4 nuevos); mis tests aislados: **55 passed, 1 skipped**.
- Suite completa: corrida verde obtenida (**230 passed, 0 failed** en intento 3 de 4); el resto de corridas con fallos caóticos por interferencia de DB del WIP de exploraciones (otro agente corriendo su suite en paralelo — verificado: todos mis archivos pasan aislados).

### Verificación
- ✅ PHPStan level 8 sobre RelationFilter + DatagridService + DatagridServiceProvider: **No errors** (2 iteraciones de fix: import de `Builder`/`Model` para el PHPDoc y genérico `Builder<Model>`).
- ✅ Pint: pass.
- ✅ Git add explícito (4 archivos); WIP ajeno intacto.

---

# Análisis Backend — M1-M4 (mejoras del Arquitecto, paquete pokedex-filtro-effort)

## Tabla de aplicación
| Item | Aplicado (archivo:línea) | Test |
|---|---|---|
| **M1** DRY `labelToId` | `app/Providers/DatagridServiceProvider.php`: `labelToId(mixed, list<StatEnum\|TipoEnum>): ?int` (helper privado, mismo cuerpo que antes); `statId()` y `tipoId()` delegan (`StatEnum::cases()` / `TipoEnum::cases()`). Sin cambio de comportamiento: tests existentes de types/effort pasan sin modificar. | — (regresión cubierta por los 26 tests de DatagridTest) |
| **M2** guard `filter[effort]=0` | `statId()`: `$id === 0 ? null : $id` → `filter[effort]=0` ahora es "filtro ignorado" (antes "0 resultados"). `tipoId` NO cambia. | `test_pokemon_list_filter_effort_zero_ignored` (rojo → verde): 2 pokémon, `filter[effort]=0` → 200 con count 2. |
| **M3** viewData `$stats` | `app/Http/Controllers/PlayerController.php`: `use App\Enums\StatEnum;` (orden alfabético tras `TipoEnum`) + `'stats' => StatEnum::options(),` en `pokedex()`. | `PlayerControllerTest::test_pokedex_passes_counts_and_types` ampliado: `viewData('stats') === StatEnum::options()`. |
| **M4** comentario blade | `resources/views/pokedex/index.blade.php:449`: `// Close type filter on outside click` → `// Close filter dropdowns on outside click`. | — |

## Verificación
- ✅ TDD M2/M3: 2 tests rojos → verde. DatagridTest 26 + PlayerControllerTest 3 + PokedexViewTest 3 = **32 passed**.
- ✅ Suite completa: verde en 2 de 3 intentos (**231 passed, 0 failed**); el intento intermedio falló por interferencia de DB del WIP de exploraciones (otro agente; todos mis archivos pasan aislados).
- ✅ PHPStan level 8 sobre `DatagridServiceProvider.php` + `PlayerController.php`: 3 errores, todos PREEXISTENTES en `reclutamiento()`/`equipos()` (verificado con stash: 6 errores sin mis cambios → 3 con ellos; ninguno en las líneas de M1/M3).
- ✅ Pint: pass. `c3fcaa0b62` verificado en la cadena de commits (HEAD ~ a80269b5).
- ✅ Git add explícito (5 archivos); WIP ajeno intacto.

---

# Análisis Backend — Fix: procesamiento síncrono de exploraciones (sin dependencia de cola)

## Problema reportado
La bitácora no se genera para exploraciones activas. Causa raíz (confirmada con tinker read-only contra BD dev):
- `QUEUE_CONNECTION=database` en `.env`; `ProcesarExploraciones` despacha `ProcesarExploracionJob` (ShouldQueue) a la cola `database` y **no hay worker** → los jobs quedan en `jobs` para siempre.
- **Evidencia en BD dev**: tabla `jobs` con **8 filas** de `App\Jobs\ProcesarExploracionJob` (queue=default), exploraciones activas 34 y 35 (habitat 13, nivel 1, duración 4h) con `eventos = null`.
- El scheduler (`Schedule::command('exploraciones:procesar')->everyFiveMinutes()`) tampoco corre sin `schedule:work`/cron.

## Diagnóstico habitat 13 (read-only, BD dev)
- Habitat 13 = **Corriente Marina**.
- Pool nivel 1: **1 pokémon** (bulbasaur, capture_rate=45, hatch=20) → **NO vacío**.
- Pool nivel 2: 0. Pool nivel 3: 0. (Solo relevante si el usuario inicia exploraciones nivel 2/3 en este hábitat → bitácora legítimamente vacía.)

## Qué voy a tocar
| Archivo | Cambio |
|---|---|
| `app/Console/Commands/ProcesarExploraciones.php` | Sustituir `ProcesarExploracionJob::dispatch($exploracion->id)` por llamada directa síncrona `app(ProcesarExploracionService::class)->procesar($exploracion)`. Import nuevo `Src\Exploraciones\App\ProcesarExploracionService`; se elimina el import del Job. |
| `tests/Feature/ExploracionesTest.php` | Nuevo test que reproduce la config de producción: `queue.default=database`, correr el comando, assert bitácora poblada + `jobs` sin filas (sin Queue::fake: queremos el camino síncrono real). |
| `active/ANALISIS_BACKEND.md` | Esta sección. |

## Qué NO toco
- `app/Jobs/ProcesarExploracionJob.php` — se queda en su sitio (ya no lo usa el comando; grep: solo lo usaba el comando).
- BD dev (solo lecturas; sin migraciones).
- Scheduler (`routes/console.php`) — intacto; el fix no lo afecta.
- WIP ajeno: `active/ANALISIS_FRONTEND.md`, `scripts/rename_pokemon_icon_files.sh` (sin commitear, fuera de alcance; no commit).

## Hallazgo secundario (reportar, NO arreglar — fuera de alcance)
`ProcesarExploracionService::finalizar()` despacha `ActualizarPokedexJob` (ShouldQueue) → con `QUEUE_CONNECTION=database` y sin worker, los AVISTADO de pokedex y la recompilación del JSON de hábitat (`RecompilarHabitatJsonJob`, encadenado desde el job) seguirían sin ejecutarse al completar la vuelta. El fix del comando resuelve la BITÁCORA; el pokedex queda pendiente de decisión del usuario (sync del job o worker).

## Tests (TDD rojo → verde)
1. `test_comando_procesa_sincronamente_sin_worker` (nuevo): `config()->set('queue.default', 'database')` → `artisan('exploraciones:procesar')` → `assertDatabaseCount('jobs', 0)` + bitácora poblada. ROJO con el código actual (el dispatch deja 1 fila en `jobs` y la bitácora queda vacía) → VERDE con el fix.
   - Se usa una exploración DENTRO de su duración (4h, inicio hace 1h) para que `finalizar()` no dispare `ActualizarPokedexJob` (evita falso positivo del hallazgo secundario; el assert de `jobs=0` queda limpio).
2. Suite existente de ExploracionesTest (13 tests) — ya invocan el comando y asertan bitácora; no necesitan cambios (ninguno aserta el dispatch del Job; verificado por grep).

## Riesgos
- `app(Foo::class)` con Larastan tipa el retorno a `Foo` → sin error PHPStan level 6.
- `config()->set('queue.default', ...)` resuelve la conexión por demanda → efectivo en tests.
- Sin commits (instrucción de la tarea): solo reportar.

## Verificación
- `php artisan test --compact tests/Feature/ExploracionesTest.php`
- `php artisan test --compact`
- `vendor/bin/pint --dirty --format agent`
- PHPStan sobre los archivos tocados.

---

# Análisis Backend — Pipeline pokedex síncrono (sin worker de cola)

## Contexto verificado (tinker read-only, BD dev)

- Exploraciones **34 y 35** (habitat 13 Corriente Marina, nivel 1, duración 4h): `regreso = NULL`, `eventos = null`.
- Tabla `jobs`: **8 filas** (jobs `ProcesarExploracionJob` atascados de ticks anteriores con el código viejo).
- El fix síncrono del comando (`ProcesarExploraciones` → `ProcesarExploracionService::procesar()` directo) está aplicado en el árbol pero SIN commitear, y **el proceso `schedule:work` en ejecución NO lo recogió** (proceso long-running; el tick de 16:30 despachó con el código viejo → eventos sigue null). Requiere reiniciar `schedule:work`.
- `QUEUE_CONNECTION=database` en `.env`; `QUEUE_CONNECTION=sync` en phpunit.xml (por eso los tests pasaban con ShouldQueue).

## Problema secundario confirmado

`ProcesarExploracionService::finalizar()` y `ServicioCaptura`/`ReclutamientoController` despachan `ActualizarPokedexJob` (ShouldQueue) → encadena `RecompilarHabitatJsonJob` (ShouldQueue) → con cola `database` y sin worker, los AVISTADO/RECLUTADO de pokedex y la recompilación del JSON de hábitat no ocurren. `CapturarPokemonJob` (ShouldQueue) igual desde `ServicioCaptura`.

## Decisión (juego single-player)

Quitar `ShouldQueue` de `ActualizarPokedexJob`, `RecompilarHabitatJsonJob` y `CapturarPokemonJob` → `dispatch()` ejecuta inline vía `Dispatcher::dispatchNow()` (verificado en `vendor/.../Bus/Dispatcher.php`: `dispatch()` → `commandShouldBeQueued() === false` → `dispatchNow`). Misma clase, misma firma, mismo `handle()`.

## Qué voy a tocar

| Archivo | Cambio |
|---|---|
| `app/Jobs/ActualizarPokedexJob.php` | Quitar `implements ShouldQueue` + import `Illuminate\Contracts\Queue\ShouldQueue`. El `RecompilarHabitatJsonJob::dispatch()` interno también queda síncrono. |
| `app/Jobs/RecompilarHabitatJsonJob.php` | Ídem. |
| `app/Jobs/CapturarPokemonJob.php` | Ídem. |
| `tests/Feature/ServicioCapturaTest.php` | `Queue::fake()`/`assertPushed` → `Bus::fake()`/`assertDispatched` (ver mecanismo abajo). |
| `tests/Feature/Jobs/ActualizarPokedexJobTest.php` | `test_dispatches_recompilar_habitat_json_job` (Queue::fake + assertPushed) → aserción del EFECTO en BD: el JSON `pokemons` del hábitat queda recompilado síncronamente tras el dispatch. |
| `active/ANALISIS_BACKEND.md` | Esta sección. |

## Mecanismo verificado (por qué Queue::fake() ya no sirve)

`Dispatchable::dispatch()` → `PendingDispatch::__destruct()` → Bus `Dispatcher::dispatch()`: si el job NO implementa `ShouldQueue` → `dispatchNow()` → ejecución inline **sin tocar la cola**. `Queue::fake()` reemplaza el binding `queue` (solo intercepta `push` de jobs ShouldQueue), así que `Queue::assertPushed` fallaría para jobs síncronos. `Bus::fake()` reemplaza el `Dispatcher` y SÍ registra `dispatch()` de cualquier job → `Bus::assertDispatched` con closures de parámetros mantiene el espíritu del contrato (params de captura, AVISTADO, etc.). Sin fake, los tests de jobs (`RecompilarHabitatJsonJobTest`, `CapturarPokemonJobTest`, resto de `ActualizarPokedexJobTest`) ejecutan inline como ya hacían con QUEUE_CONNECTION=sync → sin cambios.

## Riesgos

- `Bus::fake()` intercepta TODOS los dispatch del test (sin ejecutar) → los tests de ServicioCaptura siguen validando el contrato de dispatch, no efectos (los efectos los cubren los tests de jobs, que corren sin fake).
- `ActualizarPokedexJobTest::test_avistado_recompila...` sin fake → ejecuta el pipeline completo inline (pokedex + JSON hábitat) → prueba real del camino síncrono sin worker.
- `ProcesarExploracionJob` / `CalcularRecompensasJob` siguen con `ShouldQueue` pero ya no los despacha nadie en producción (grep) — fuera de alcance (no se tocan; se reportan como candidatos a limpieza).
- No tocar BD dev (solo lecturas). No tocar WIP ajeno (`ANALISIS_FRONTEND.md`, `scripts/rename_pokemon_icon_files.sh`).

## Verificación

- `php artisan test --compact` (suite completa)
- `vendor/bin/pint --dirty --format agent`
- PHPStan level 6 sobre los archivos tocados.
- Commit atómico con el fix del comando + jobs síncronos + tests.
