# TASK-004 — Revisión

## Fase 1 — Estabilización contable y cadencia

- **Revisor:** `reviewer`
- **Veredicto inicial:** `CHANGES_REQUESTED` (2 mayores, varios menores)
- **Veredicto tras correcciones:** `APPROVED`

### Hallazgos mayores corregidos

1. **Backfill de `eventos_generados`:** las exploraciones antiguas sin contador podían reprogramar un backlog histórico. Corregido con `min(CadenciaEventos::esperados(...), count($bitacora))` persistido en el tick. Test: 12 eventos previos + 15 esperados → 3 nuevos, sin duplicar.
2. **Captura de exploración ×0,5:** el factor de la economía individual se aplicaba a la probabilidad de captura, fuera del plan v5. Corregido: `rollAleatorio()` solo aplica el factor de refugio; la captura vuelve a la fórmula del plan. Test con un roll entre `p×0,5` y `p`.

### Hallazgos menores pendientes (Fase 4)

- `src/Exploraciones/README.md`, `src/Exploraciones/context.md` y `docs/architecture.md` aún mencionan captura ×0,5.
- `src/Mazmorras/context.md` y `src/Mazmorras/README.md` aún dicen «7× gimnasio» para EXP; el código aplica caramelos ×70 y EXP ×10.
- `ReglasExploracion` y `EconomiaRecompensas` deben precisar «EXP y caramelos» en sus docblocks.
- `darEvs` mantiene una carrera menor de concurrencia (cupo calculado fuera de la transacción); se revisará en una fase posterior.
- `EvaluadorExploracion::categoriaFinalDeEventos` sigue contando `'evitada'` como victoria para la categoría; confirmar intencionalidad.

### Evidencia de pruebas (revisor)

- `EconomiaRecompensasTest`, `CadenciaEventosTest`, `ProcesarExploracionCombateTest`, `ExploracionesTest`: 59 passed (240 assertions).
- `FinalizarExploracionPerdidasTest`, `PersistirRecompensasTest`, `TopeEvTest`, `ProbabilidadCapturaTest`, `ReglasEvTest`: 25 passed (109 assertions).
- `tests/Feature/Exploraciones/`: 29 passed (123 assertions).
- `php -l` sin errores en los archivos modificados.
- `phpstan`/`pint` pendientes de QA de cierre.

## Fase 4/5 — Estado final

- **Fase 2B:** `CHANGES_REQUESTED`; correcciones aplicadas (mazmorra sin Senda, exploración con Senda, nivel derivado de `exp`, config de beneficios, experiencia, vector 144). No hubo re-revisión completa.
- **Fase 2C:** sin revisión independiente final; verificación estática (`php -l`, PHPStan) y manual.
- **Fase 2D:** sin revisión independiente final; verificación estática y vectores manuales.
- **Fase 3:** sin revisión independiente final; `view:cache` correcto.
- **Documentación:** Fase 4 incompleta (el `documenter` agotó pasos).
- **Seguridad:** omitida por el desarrollador; ver `security.md`.
- **Tests:** desactivados por el desarrollador; no se ejecutó la suite.

## Fase 2A — Perfiles IV/EV y multiplicadores

- **Veredicto:** `CHANGES_REQUESTED`
- **Mayores:**
  1. `PresentadorResultadoBatalla::procesarGimnasio` usa `CatalogoGimnasios` (código) en vez de `GymCatalogoRepositoryInterface` (BD) para índice/medalla; puede desincronizarse con el combate.
  2. `procesarEntrenador` usa `DificultadHabitat::para()` (perfil de ruta) en vez de `perfilEntrenador()`; hoy coincide porque Nulo/TopUno comparten 1,00, pero es semánticamente incorrecto.
- **Menores:** docblock de `GeneradorPokemonGimnasio` sobre el default; `m_tipo` desactualizado en `architecture.md`; `indiceDe` carga etapas innecesariamente.
- **Fortalezas:** perfiles, top-N, vectores, ruta/entrenador/gimnasio, shiny, mazmorra 70/10 y multiplicadores exactos.
- **Tests:** unitarios e integración 2A verdes (71 + 47 tests). Los 4 feature de recompensas no se pudieron ejecutar en el entorno del revisor por `users.experiencia`; el orchestrator los ejecutó con `php artisan test` y pasaron (8 + 6 + 4 + 2 tests).

### Correcciones pendientes

- Usar el repositorio de gimnasios en el presentador.
- Usar `perfilEntrenador()` para el multiplicador de entrenador.
- Corregir docblock y documentación de `m_tipo`.
