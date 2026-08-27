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
