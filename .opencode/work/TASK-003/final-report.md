# TASK-003 — Informe final

## Resumen

Se refactorizó el módulo de Exploraciones para que sea coherente con su modelo real: **una
expedición individual por reclutado**, sin nomenclatura ni bloqueo de equipo heredados. Además se
ajustó la economía aprobada (50 % de recompensas y EXP 50/50), se corrigió el ciclo de vida
temporal, se añadió procesamiento bajo demanda desde `/exploraciones` y se construyó una capa de
personalidad con datos existentes (ficha del explorador, diario, hitos y ánimo).

## Estado final

**COMPLETED con issues conocidos.**

- Análisis y diseño: completados.
- Implementación Fase 1: completada.
- Revisión independiente: `APPROVED`.
- QA: `PASS WITH KNOWN ISSUES`.
- Seguridad: **no aplica** por decisión del desarrollador; no se realizó auditoría.
- Documentación del módulo: actualizada.
- Fase 2: identificada y fuera de alcance.

## Causa del síntoma original

No se enviaban equipos. El flujo ya guardaba un único `reclutado_id` y combatía 1v1. El síntoma se
explicaba por:

1. La presentación publicaba la clave `equipo` y la UI hablaba de «tus equipos».
2. Un miembro en exploración bloqueaba y etiquetaba su equipo completo mediante `team_members` y
   `Team::isExploring()`.
3. La EXP se dividía entre 3 (`MIEMBROS_EQUIPO`), aunque solo explorara un reclutado.

## Cambios principales

### Economía

- `EconomiaRecompensas` (`legado()`/`individual()`).
- Factor 0,5 sobre EXP, caramelos y probabilidad de captura.
- EXP: `pool = floor(T × multiplicador × 0,5)`, `expCuenta = intdiv(pool, 2)`,
  `expReclutado = pool - expCuenta`.
- `expTipoCuenta` para Sendas y `expTipoPorMiembro` para el reclutado.
- Caramelos/capturas a la cuenta; capturas al 50 % de probabilidad.
- Ruta conserva la economía legada (80 %/3).

### Ciclo de vida y procesamiento

- `inicio_exploracion = now()` al crear.
- `return_time` como siguiente ocurrencia desde el inicio (23:00 → 01:00 del día siguiente).
- Rechazo 422 de `duracion_horas` + `return_time`.
- `store-individual` responde 201 con `id`, `inicio`, `fin`, `estado`.
- `ProcesarExploracionesPendientes` + `ResultadoProcesamientoPendiente`.
- `GET /exploraciones` procesa antes de renderizar.
- Lock por expedición TTL 60 s, throttle 30 s, límite 10 y presupuesto 2 s en HTTP.
- Consola y `recoger` comparten servicio y lock.

### Bloqueo individual y combate

- `Reclutado::estaEnExploracion()` y `ValidadorExploracion::reclutadoEnExploracion()`.
- `Team::isExploring()` eliminado; guardas de equipo retiradas.
- Solo se bloquea al reclutado que explora; el equipo sigue visible y editable.
- `ConstruirEquipoJugador::desdeEquipo()` exige 3 miembros y que ninguno esté en exploración.

### Presentación y personalidad

- `ExploradorVista`, `DiarioExploracion`, `HitoExploracion`, `FraseAnimo`.
- Ficha con nombre, nivel, EXP, shiny, rol, tipos, HP y ánimo.
- Diario agrupado por fecha; narración de retirada/derrota/cancelada.
- Corrección del evento `descanso` (`duracion_minutos`/`hp_recuperado`).
- Preview con `economia` y `aviso_economia`; labels no nulos.
- `eventos['resultado']` añade `exp_reclutado`.
- Alias `equipo` y `equiposEnExploracion` deprecados.

### Vistas

- `exploraciones/index`, `_explorador`, `_diario`, `_evento`.
- `equipos/index`: equipo editable con miembro ausente.
- `habitats/show`: miembro ausente marcado; combate deshabilitado para ese equipo.

## Archivos creados (principales)

- `src/Exploraciones/Domain/EconomiaRecompensas.php`
- `src/Exploraciones/Domain/ReglasExploracion.php`
- `src/Exploraciones/App/ProcesarExploracionesPendientes.php`
- `src/Exploraciones/App/ResultadoProcesamientoPendiente.php`
- `src/Exploraciones/Presentation/ExploradorVista.php`
- `src/Exploraciones/Presentation/DiarioExploracion.php`
- `src/Exploraciones/Presentation/HitoExploracion.php`
- `src/Exploraciones/Presentation/FraseAnimo.php`
- `tests/Unit/Exploraciones/EconomiaRecompensasTest.php`
- `tests/Unit/Exploraciones/CalculadorFinExploracionTest.php`
- `tests/Unit/Exploraciones/ProbabilidadCapturaTest.php`
- `tests/Unit/Exploraciones/ProcesarExploracionesPendientesTest.php`
- `tests/Feature/Exploraciones/ExploracionesIndexProcesaTest.php`
- `tests/Feature/Exploraciones/ExploracionesRecogerLockTest.php`
- `tests/Feature/ProcesarExploracionesCommandTest.php`
- `resources/views/exploraciones/_explorador.blade.php`
- `resources/views/exploraciones/_diario.blade.php`

## Archivos modificados (principales)

- `app/Http/Controllers/ExploracionActivaController.php`
- `app/Http/Controllers/PlayerController.php`
- `app/Http/Controllers/HabitatsController.php`
- `app/Http/Controllers/TeamController.php`
- `app/Http/Controllers/ReclutadoController.php`
- `app/Models/Reclutado.php`
- `app/Models/Team.php`
- `app/Console/Commands/ProcesarExploraciones.php`
- `src/Exploraciones/Domain/CalculadorRecompensas.php`
- `src/Exploraciones/Domain/Recompensas/ResultadoRecompensas.php`
- `src/Exploraciones/Domain/CalculadorFinExploracion.php`
- `src/Exploraciones/Domain/EstimadorRecompensasExploracion.php`
- `src/Exploraciones/App/PersistirRecompensas.php`
- `src/Exploraciones/App/FinalizarExploracionHandler.php`
- `src/Exploraciones/Presentation/*`
- `src/Habitats/App/ValidadorExploracion.php`
- `src/CombateEntrenadores/App/ConstruirEquipoJugador.php`
- `src/Equipos/App/*`
- `resources/views/exploraciones/index.blade.php`
- `resources/views/exploraciones/_evento.blade.php`
- `resources/views/equipos/index.blade.php`
- `resources/views/habitats/show.blade.php`
- `src/Exploraciones/context.md`, `src/Exploraciones/README.md`, `docs/context.md`,
  `docs/architecture.md`
- `phpstan-baseline.php` (limpieza de 5 entradas obsoletas)

## Pruebas y validación

- QA funcional: `PASS WITH KNOWN ISSUES` (issues ajenos y seguridad no aplica).
- Suite relevante final: **456 tests / 2705 aserciones en verde**.
- Fallos post-QA corregidos: **26 tests / 114 aserciones en verde** para
  `ProcesarExploracionCombateTest|CombateExploracionTest|ExploracionesAutoResolucionTest|EquiposPayloadTest`.
- PHPStan: 0 errores en archivos de TASK-003; 8 errores preexistentes en `SendasController`.
- Pint: pass en archivos de TASK-003.
- Sin migraciones ni dependencias nuevas.

## Revisión y seguridad

- Reviewer: `APPROVED` para TASK-003, con hallazgos HIGH/MEDIUM corregidos.
- Seguridad: no aplica por decisión del desarrollador. No se realizó auditoría; ver `security.md`.

## Issues conocidos (preexistentes, fuera de TASK-003)

1. `phpstan` completo sigue en rojo por 8 errores en `SendasController` (WIP ajeno).
2. La auditoría de seguridad no se realizó por decisión del desarrollador.

Los fallos de `CombateExploracionTest`, `EquiposPayloadTest`, `ProcesarExploracionCombateTest` y
`ExploracionesAutoResolucionTest` se corrigieron; las constantes canónicas son 30 min/encuentro y
1 % HP/min de descanso. `objetos_perdidos` ya se propaga a `resultado` y se muestra en la vista.

## Deuda y próximos pasos

- Fase 2: god-handler, preview único, idempotencia fuerte, `withoutOverlapping()`, cast de
  `hora_limite`, eliminar alias/columnas muertas.
- Añadir tests end-to-end de los cuatro flujos de combate, ausencia de payload `equipo` en 201,
  lock con driver de producción y presupuesto real.
- Reforzar la aserción de combate de `ExploracionesAutoResolucionTest` con un combate inyectado.
- Abrir tarea separada para `SendasController`.
- Auditar seguridad antes de exponer el módulo a usuarios reales, si producto lo requiere.

## Artefactos

- `analysis.md`, `architecture.md`, `plan.md`, `approval.md`, `tasks.md`, `execution.md`,
  `review.md`, `qa.md`, `security.md`, `final-report.md`.
