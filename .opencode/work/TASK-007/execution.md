# TASK-007 — Ejecución

## Preflight (2026-11-02)

- Rama `master`, WIP extenso (TASK-003/TASK-004).
- Bug confirmado: `fase` usaba `species_id`; Pichu 172 → 3, Pikachu 25 → 1.
- Aprobación del desarrollador: «Si, adelante».

## Implementación

- `app/Support/CadenasEvolutivas.php`: `etapasDe()` con BFS desde `pokemon_evolution`; omite cadenas sin aristas.
- `src/Exploraciones/App/NormalizadorPokemonDerrotado.php`: tercer parámetro opcional `$etapasPorCadena`; fallback legacy.
- Callers: `FinalizarExploracionHandler`, `OtorgarRecompensasRuta`, `OtorgarRecompensasEntrenador`, `ReclutamientoController`.
- `ExploracionActivaController::cadenasDelPool()` + `EstimadorRecompensasExploracion::fase()`: mapa real o lista legacy.
- Tests: helper de etapas, normalizador, calculador, estimador y reclutamiento.

## Validación

- Filtro del plan → **OK (111 tests, 418 aserciones)**.
- Ruta/entrenador → **OK (14 tests, 73 aserciones)**.
- BD real: Pichu 1, Pikachu 2, Raichu 3.
- PHPStan: 0 en TASK-007; 8 `SendasController` + 3 untracked ajenos.
- Pint: pass.
- Sin migraciones ni dependencias.

## Revisión y QA

- Reviewer: **APPROVED**.
- QA: **PASS WITH KNOWN ISSUES** (suite global roja por WIP ajeno; falta test E2E de recompensas reales).
- Seguridad: no aplica.

## Estado

`COMPLETED` — TASK-007 implementada, revisada, probada y documentada. Sin commit ni `git push`.
