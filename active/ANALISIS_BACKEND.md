# Análisis Backend — Fixes del Code Review del Arquitecto

## Resumen
Implementación de 14 issues (3 CRITICAL, 4 HIGH, 3 MEDIUM + relaciones faltantes) del code review del Arquitecto.

---

## CRITICAL FIXES

### C1: PlayerController + routes
- **Archivos nuevos**: `app/Http/Controllers/PlayerController.php`, `routes/player.php`
- **Archivos modificados**: `routes/web.php`
- Métodos: `pokedex()`, `reclutamiento()`, `equipos()`
- Vistas existentes: `pokedex/index.blade.php`, `reclutamiento/index.blade.php`, `equipos/index.blade.php`
- Patrón: copiar de ReclutadosController (DI por constructor)

### C2: ExploracionActivaController + POST route
- **Archivos nuevos**: `app/Http/Controllers/ExploracionActivaController.php`, `routes/exploraciones.php`
- **Archivos modificados**: `routes/web.php`
- Validación + uso de ValidadorExploracion + creación de ExploracionActiva

### C3: PUT route para team rename
- **Archivos modificados**: `app/Http/Controllers/TeamController.php`, `routes/reclutados.php`
- Añadir `update()` method + route PUT

---

## HIGH FIXES

### H1: Mover ServicioCaptura Domain→App
- **Archivos modificados**: `src/Reclutamiento/Domain/ServicioCaptura.php` → `src/Reclutamiento/App/ServicioCaptura.php`
- **Tests afectados**: `tests/Feature/ServicioCapturaTest.php`
- Cambio de namespace: `Src\Reclutamiento\Domain` → `Src\Reclutamiento\App`

### H2: Fix HabitatsController::show()
- **Archivo**: `app/Http/Controllers/HabitatsController.php`
- Añadir `sightedPokemonIds` desde Pokedex
- Scope properly `$equiposEnExploracion`

### H3: Fix N+1 en RecompilarHabitatJsonJob
- **Archivo**: `app/Jobs/RecompilarHabitatJsonJob.php`
- Batch-load Pokedex entries en vez de N queries

### H4: Fix N+1 en CalcularRecompensasJob
- **Archivo**: `app/Jobs/CalcularRecompensasJob.php`
- Pre-fetch defeated Pokemon with evolution chain

---

## MEDIUM FIXES

### M1: Guard ActualizarPokedexJob
- **Archivo**: `app/Jobs/ActualizarPokedexJob.php`
- No degradar atrapado→false cuando estado=AVISTADO

### M2: Remove empty $casts
- **Archivo**: `app/Models/TeamMember.php`

### M4: Fix ValidadorExploracion return type
- **Archivo**: `src/Habitats/App/ValidadorExploracion.php`
- Return `array` en vez de `Collection`

---

## Missing relationships
- `app/Models/Reclutado.php`: añadir `teamMember()`
- `app/Models/Pokemon.php`: añadir `stats()` y `types()`

---

## Riesgos
- H1 puede romper tests — se actualiza import
- C2 requiere ValidadorExploracion en App layer (usa Eloquent directamente, aceptable)
- M4 cambiar Collection→array puede afectar llamadores — se revisa

## Files to create
1. `app/Http/Controllers/PlayerController.php`
2. `app/Http/Controllers/ExploracionActivaController.php`
3. `routes/player.php`
4. `routes/exploraciones.php`
5. `src/Reclutamiento/App/ServicioCaptura.php` (nueva ubicación)

## Files to modify
1. `routes/web.php`
2. `routes/reclutados.php`
3. `app/Http/Controllers/TeamController.php`
4. `app/Http/Controllers/HabitatsController.php`
5. `app/Jobs/RecompilarHabitatJsonJob.php`
6. `app/Jobs/CalcularRecompensasJob.php`
7. `app/Jobs/ActualizarPokedexJob.php`
8. `app/Models/TeamMember.php`
9. `app/Models/Reclutado.php`
10. `app/Models/Pokemon.php`
11. `src/Habitats/App/ValidadorExploracion.php`
12. `tests/Feature/ServicioCapturaTest.php`

---

# Análisis Backend — URGENTE: Fix Blade Alpine + Test DB separado

## Contexto
Dos fixes urgentes solicitados por el usuario:
1. **Blade error**: `resources/views/equipos/index.blade.php:131` interpola variable Alpine `slot` con `{{ }}` de Blade → "Undefined constant 'slot'".
2. **Test DB**: phpunit.xml usa `DB_DATABASE=laravel` (DB de desarrollo) y RefreshDatabase la destruye con migrate:fresh.

## Qué voy a tocar

### FIX 1 — Blade
- `resources/views/equipos/index.blade.php`
  - Línea 131: `Slot {{ slot }}` → `Slot <span x-text="slot"></span>` (slot es var Alpine del `x-for="slot in [1,2,3]"`).
  - Línea 254: `{{ searchQuery || typeFilter ? 'No se encontraron Pokémon' : 'No hay Pokémon disponibles para asignar' }}` → `x-text` con el mismo ternario (searchQuery/typeFilter son vars Alpine, no PHP).
- Barrido completo de `{{` en resources/views (sin `$`): solo esos 2 puntos están rotos.
  - `habitats/show.blade.php`: todos los `{{ }}` son PHP (`$habitat`, `$team`, `$exp`...) → OK.
  - `reclutamiento/index.blade.php`: usa `x-text` para vars Alpine → OK.
  - `pokedex/index.blade.php`: usa `x-text` → OK.
  - `habitats/_level-preview.blade.php:12` y `livewire/habitats/family-modal.blade.php:151`: ya usan `@{{ }}` escapado → OK (patrón a seguir).
  - `layouts/app.blade.php`, `reclutados/index.blade.php`: `{{ session(...) }}` es PHP → OK.

### FIX 2 — Test DB separado
- `phpunit.xml`: `DB_DATABASE=laravel` → `laravel_test` (resto igual: pgsql/127.0.0.1/5432/laravel/laravel).
- `.env.testing` (nuevo): copia de .env con `APP_ENV=testing`, `DB_DATABASE=laravel_test`.
- Crear DB `laravel_test` en PostgreSQL (no existe; `psql -U laravel` disponible).
- Verificar que `RefreshDatabase` apunte a laravel_test vía `tests/Feature/EquiposControllerTest.php` (usa RefreshDatabase).
- Baseline dev DB: 7 provinces, 46 habitats, 1350 pokemon (debe mantenerse idéntico antes/después).

## Tests
- No escribo tests nuevos: son 2 fixes de configuración/vista. Verificación:
  - `php artisan test --compact tests/Feature/EquiposControllerTest.php` (usa RefreshDatabase → migra laravel_test).
  - Suite completa `php artisan test --compact`.
  - Recuento dev DB antes/después (7/46/1350).
  - Render de la vista equipos sin error de Blade: el test de controlador ya renderiza la vista index (get /equipos).
- PHPStan: no aplicable a vistas; correré igualmente por si hay PHP tocado (no lo hay).

## Riesgos
- **CRÍTICO**: no ejecutar migrate:fresh contra `laravel`. El fix de phpunit.xml evita esto (env de phpunit precede a .env.testing).
- La DB `laravel_test` debe crearse antes del primer test (RefreshDatabase no crea la DB, solo migra).
- No hay DB_DATABASE hardcodeado en otro sitio (config/database.php usa env() con defaults; .env.example comentado).

## Estado final (pendiente)
- ✅ Blade fixes aplicados (línea 131 y 254 de equipos/index.blade.php).
- ✅ phpunit.xml → DB_DATABASE=laravel_test (verificado: el test intenta conectar a laravel_test, NO a laravel).
- ✅ .env.testing creado.
- ⚠️ BLOQUEADO: no se pudo crear la DB `laravel_test` — el rol `laravel` no tiene CREATEDB y no hay acceso al superusuario `postgres` (sudo requiere password).
  - Comando necesario (ejecutar como usuario con sudo): `sudo -u postgres createdb -O laravel laravel_test`
- Dev DB intacta: 7 provinces, 46 habitats, 1350 pokemon (antes y después de intentar tests).
- Pendiente tras crear la DB: `php artisan test --compact tests/Feature/EquiposControllerTest.php`, suite completa, re-verificar dev DB, pint.

## Verificación final (completada con DB laravel_test creada por el usuario)

### Fix adicional encontrado: MissingAppKeyException
- `.env.testing` y `phpunit.xml` no definían `APP_KEY` → todos los tests fallaban con `MissingAppKeyException` ("No application encryption key has been specified").
- **Fix**: generada nueva key `base64:b/re2ajuwqkEv23jWBY2+kMDOu3ZO6s/4mGoffsqGRk=` añadida a:
  - `.env.testing` (línea APP_KEY)
  - `phpunit.xml` (env APP_KEY, para que `php artisan test` siempre la tenga)

### Resultados
- ✅ `tests/Feature/EquiposControllerTest.php`: 8 passed (19 assertions).
- ✅ Suite completa: 105 passed (246 assertions) contra laravel_test (RefreshDatabase migra solo la test DB).
- ✅ Dev DB intacta: 7 provinces / 46 habitats / 1350 pokemon (idéntico al baseline).
- ✅ `vendor/bin/pint --dirty --format agent`: pass (sin cambios).
- ✅ No se ha hecho commit (pendiente revisión del usuario).

---

# Análisis Backend — Bug: pokemons de `reclutables` no aparecen en Reclutamiento

## Causa raíz (confirmada)
`app/Http/Controllers/PlayerController.php::reclutamiento()` consulta el modelo `Reclutado` (capturados/equipos) en vez de `Reclutable` (cola de reclutamiento). La vista `reclutamiento/index.blade.php` espera `reclutables` con shape `{id, pokemon_id, nombre, cantidad}`.

Además, la vista llama `POST /reclutamiento/recruit` y `POST /reclutamiento/discard-all` — rutas inexistentes (verificado en `routes/player.php` y `route:list` implícito por lectura de web.php → player.php).

## Qué voy a tocar

### Modificar
1. `app/Http/Controllers/PlayerController.php`
   - `reclutamiento()`: consultar `Reclutable::with('pokemon')->orderBy('updated_at','desc')->get()` → mapear a arrays `{id, pokemon_id, nombre, cantidad}`.
   - Añadir `use App\Models\Reclutable;`.
   - **Mantener** `use App\Models\Reclutado;` — sigue usándose en `equipos()` (líneas 75-79).

### Crear
2. `app/Http/Controllers/ReclutamientoController.php` (vía `php artisan make:controller`)
   - `recruit(Request)`: valida `reclutable_id` (required|exists), decrementa `cantidad` si > 1, si no borra la fila. Retorna `JsonResponse {success: true}`.
   - `discardAll()`: `Reclutable::query()->delete()`. Retorna `JsonResponse {success: true}`.
   - Sin lógica de caramelos por ahora (pendiente futuro).

3. `routes/player.php`: añadir
   - `Route::post('/reclutamiento/recruit', [ReclutamientoController::class, 'recruit']);`
   - `Route::post('/reclutamiento/discard-all', [ReclutamientoController::class, 'discardAll']);`

4. `tests/Feature/ReclutamientoControllerTest.php` (TDD — test primero):
   - GET /reclutamiento lista los `Reclutable` (no los `Reclutado`) con nombre/cantidad correctos.
   - POST /reclutamiento/recruit decrementa cantidad (5 → 4) y responde JSON success.
   - POST /reclutamiento/recruit borra la fila cuando cantidad llegaría a 0 (cantidad 1).
   - POST /reclutamiento/recruit valida `reclutable_id` requerido (422).
   - POST /reclutamiento/discard-all vacía la tabla y responde JSON success.

## Riesgos
- La vista ya actualiza el estado en cliente (optimista); el servidor debe ser idempotente y tolerante (fetch con .catch, respuestas JSON 200).
- `decrement` sobre `unsignedInteger` no puede bajar de 0; se protege borrando la fila cuando cantidad ≤ 1.
- No hay migración nueva: `reclutables` ya existe (2026_08_26_000002).
- Infection sólo cubre `src/` (config); el cambio es en `app/` — se corre igualmente para confirmar que no regresa nada.

## Validación
- `php artisan test --compact` (suite completa, DB laravel_test).
- `vendor/bin/phpstan analyse` level 6.
- `vendor/bin/infection` (MSI ≥ 80% en src/).
- `vendor/bin/pint --dirty --format agent`.

## Resultado (completado)
- ✅ TDD: 6 tests nuevos en `tests/Feature/ReclutamientoControllerTest.php` (rojo → verde).
- ✅ `PlayerController::reclutamiento()` consulta `Reclutable` (con `?->` para pokemon null-safe).
- ✅ `ReclutamientoController` creado con `recruit()` y `discardAll()` → `JsonResponse {success: true}`.
- ✅ Rutas registradas: `POST /reclutamiento/recruit`, `POST /reclutamiento/discard-all` (route:list OK).
- ✅ Suite completa: 111 passed (265 assertions) — incluye los 8 de EquiposControllerTest.
- ✅ Pint: pass.
- ✅ PHPStan: 421 errores totales vs 415 baseline = +6, todos de categorías preexistentes toleradas en el repo (`staticMethod.dynamicCall` en `$request->validate()` — igual que TeamController:21/ExploracionActivaController:22 — y `$this->assert*` en tests — igual que FamiliesTest/HabitatsControllerTest). Cero errores nuevos en controladores.
- ⚠️ Infection: 0 mutaciones generadas — estado preexistente (config source = `src/` solo; el fix es en `app/`). Verificado idéntico antes/después del cambio.
- ✅ Verificación dev DB (sin mutar): la fila existente `reclutables` (id=10, pokemon_id=1 bulbasaur, cantidad=1) ya NO se pierde — la query corregida la devuelve para la vista.
- Sin commit (pendiente revisión del usuario).
