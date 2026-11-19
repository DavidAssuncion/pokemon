# TASK-067 — Ejecución

- **ID:** TASK-067
- **Proyecto:** `/home/david/pokemon`
- **Estado:** RECEIVED
- **Fecha de preflight:** 2026-11-19

## Preflight

### `git branch --show-current`

```text
master
```

### `git status --short`

```text
 M .githooks/pre-commit
 M app/Datagrid/DatagridService.php
 M app/Http/Controllers/PlayerController.php
 M app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php
 M app/Providers/DatagridServiceProvider.php
 M bootstrap/app.php
 M database/seeders/AbilitySeeder.php
 M resources/views/pokedex/index.blade.php
 M routes/web.php
 M src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php
 M storage/data/pokemon_habitat.csv
?? .opencode/work/TASK-061/
?? .opencode/work/TASK-062/
?? .opencode/work/TASK-063/
?? .opencode/work/TASK-064/
?? .opencode/work/TASK-065/
?? .opencode/work/TASK-066/
?? app/Http/Controllers/DebugController.php
?? app/Http/Controllers/PokedexQaController.php
?? app/Http/Middleware/
?? app/Support/Pokedex/
?? app/Support/Qa/
?? docs/adr/0005-filtros-pokedex-y-descripciones-habilidades.md
?? resources/views/components/ui/combobox.blade.php
?? resources/views/debug/
?? resources/views/partials/combobox-filtro-script.blade.php
?? routes/debug.php
?? storage/data/abilities_descripciones.json
```

### `git diff --stat`

```text
 .githooks/pre-commit                                | 0
 app/Datagrid/DatagridService.php                    | 2 +-
 app/Http/Controllers/PlayerController.php           | 3 +
 app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php | 10 +-
 app/Providers/DatagridServiceProvider.php           | 21 +
 bootstrap/app.php                                   | 4 +-
 database/seeders/AbilitySeeder.php                  | 290 +++-
 resources/views/pokedex/index.blade.php             | 162 +-
 routes/web.php                                      | 3 +
 src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php | 5 +-
 storage/data/pokemon_habitat.csv                    | 1574 ++++++++---------
```

### Notas

- Hay cambios locales no relacionados (Pokédex, combate, seeder de habilidades,
  CSV de hábitats y artefactos de TASK-061..TASK-066). No se sobrescribirán.
- El alcance de TASK-067 se limitará a los archivos que autorice el plan.

## Delegaciones

### 2026-11-19 — `analyst`

- Resultado guardado en `analysis.md`.
- Causa raíz: `ObtenerGranja` devuelve todas las familias y no consulta
  `pokedex`; el modal pinta `base` y `types` sin filtro.
- Addendum con verificaciones de mutaciones, caché, tests, DTOs, código muerto
  de tabs, ausencia de roles admin y punto único de guard.
- Sin modificaciones de archivos.

### 2026-11-19 — `architect`

- Resultado guardado en `architecture.md`.
- Diseño: puerto `PokedexLecturaInterface`, proyector puro de familias visibles,
  guard único en `FamiliaEnHabitat` (404 neutro), prefiltro de `CosecharTodo`,
  filtrado de `inventario.familia` y propagación del flag QA.
- Sin modificaciones de archivos.

### 2026-11-19 — decisiones del desarrollador

- Familia visible si al menos un miembro ha sido avistado.
- Bloquear también en backend las acciones sobre familias no avistadas.
- Gestión admin fuera de alcance (deuda de seguridad documentada).

## Estado

`IMPLEMENTING` — plan v1 aprobado (`approval.md`, texto literal `Aprobar plan v1`).

## Implementación

Delegación a `backend` completada: dominio, infraestructura, lectura, escritura y
controlador. Verificación estática del agente: Pint limpio, rutas intactas,
PHPStan sin errores nuevos en los archivos de la tarea y comprobación manual del
proyector.

Delegación a `frontend` completada: mensaje de vacío neutro y helper
`urlGranja()` con propagación de `?unlock=1` en las 9 URLs de granja del modal.

Delegación a `documenter` completada: `src/Granjas/context.md` actualizado con
la regla de visibilidad, guard 404, inventario, cosecha global, QA y deuda de
seguridad fuera de alcance.

Verificación del orquestador: `php -l` de los archivos backend sin errores;
`vendor/bin/phpstan analyse` sobre el alcance de TASK-067 sin errores;
`php artisan route:list --path=farm` con las 9 rutas intactas; `git diff --check`
sin errores de whitespace.

## Revisión

Delegaciones a `reviewer` y `security` en curso.

Corrección tras revisión: `harvest-all` dejó de aceptar `?unlock=1`; en QA usa
solo `POKEDEX_UNLOCK_ALL` (`config('pokedex.unlock_all')`). Archivo:
`src/Granjas/Infra/Controllers/GranjaController.php`. Re-revisión en curso.

## Cierre

- `reviewer`: APPROVED tras la corrección.
- `security`: PASS_CON_HALLAZGOS, sin críticos/altos nuevos.
- `final-report.md` redactado.
- `git diff --check` limpio (solo aviso preexistente de CRLF en
  `storage/data/pokemon_habitat.csv`).
- `tests/` sin cambios.
- Estado final: `COMPLETED`.

