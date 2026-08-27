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
