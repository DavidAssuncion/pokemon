# TASK-006 — Ejecución

## Preflight (2026-11-02)

- Rama `master`, WIP local extenso.
- El análisis confirmó que «Reclutado eliminado» se debe a `nombre=null`, no a un borrado.
- El modal de combate apilaba caramelos y capturas en una sola tarjeta.

## Implementación

### A. Nombre del reclutado en hábitat

- `app/Http/Controllers/HabitatsController.php`: `with('reclutado')` → `with('reclutado.pokemon')`.
- `resources/views/habitats/show.blade.php`: fallback `nombre → pokemon.name → 'Reclutado eliminado'` con `filled()`.
- Test en `tests/Feature/HabitatsControllerTest.php` con `nombre=null` y `rhyperior`.

### B. Recompensas de combate

- `resources/views/livewire/combate.blade.php` (rama `layout='ruta'`): grid `lg:grid-cols-3`, capturas `lg:col-span-1`, caramelos `lg:col-span-2` en tres filas; full width si falta una columna.
- Tests en `tests/Feature/CombateLivewireTest.php`: con capturas + caramelos, sin capturas, y solo capturas.

### Validación

- `HabitatsControllerTest|HabitatsViewTest|CombateLivewireTest` → **OK (19 tests, 54 aserciones)**.
- `CombateLivewireTest` → OK (6 tests, 16 aserciones).
- `Habitats|Combate` → 143 tests, 558 aserciones, 2 errores ajenos en `PokemonHabitatSeederTest` (FK `province_id=8` por WIP de `ProvinceSeeder`/`habitats.csv`).
- PHPStan: 0 en TASK-006; 8 preexistentes en `SendasController`.
- Pint: pass.
- Sin migraciones ni dependencias.

## Revisión y QA

- Reviewer: **APPROVED**.
- QA: **PASS WITH KNOWN ISSUES** (issues LOW/NIT y seeder ajeno).
- Seguridad: no aplica.

## Estado

`COMPLETED` — TASK-006 implementada, revisada, probada y documentada. Sin commit ni `git push`.
