# TASK-003 — Ejecución y estado

## Estado

`COMPLETED` — Fase 1 implementada, revisada y validada. Quedan issues preexistentes y deuda de
Fase 2 documentados en `final-report.md`. La auditoría de seguridad no se realizó por decisión
explícita del desarrollador (`security.md` = `NO APLICA`).

## Decisiones de producto (2026-11-01)

1. **Modelo**: exploración individual coherente. Un `reclutado` por expedición. Se elimina la
   nomenclatura y el bloqueo de equipo heredados.
2. **Alcance**: dos fases. Fase 1 = bug + coherencia individual + economía + personalidad con datos
   existentes. Fase 2 = refactor estructural (god-handler, preview único, concurrencia), con plan
   separado.
3. **Economía**:
   - Todas las recompensas de la expedición se reducen al **50 %** de la tasa actual.
   - La EXP resultante se reparte **50 % cuenta / 50 % reclutado**.
   - Caramelos, capturas y objetos van íntegros a la cuenta del jugador (los reclutados no tienen
     inventario).
4. **Personalidad**: derivada de datos existentes (nombre, rol, nivel, EXP, shiny, HP, descanso,
   incidentes, diario). Sin migraciones ni rasgos persistentes en esta fase.
5. **Bloqueo**: solo se bloquea al `reclutado` que explora; el equipo de combate no queda bloqueado
   entero. Se marcará al miembro ausente.

## Preflight (2026-11-01)

- Proyecto: `/home/david/pokemon`
- Rama: `master`
- `git status --short`: 133 entradas.
- `git diff --stat`: 84 archivos, +3762 / -2656.
- Último commit: `efa5cfd fixes`.

### WIP local relevante al alcance

Modificados:

- `app/Http/Controllers/TeamController.php`
- `resources/views/equipos/index.blade.php`
- `src/Exploraciones/App/FinalizarExploracionHandler.php`
- `src/Exploraciones/App/ProcesarExploracionHandler.php`
- `src/Exploraciones/Domain/CalculadorMatchups.php`
- `src/Exploraciones/Domain/CalculadorRecompensas.php`
- `src/Exploraciones/Domain/CalculadorRiesgo.php`
- `src/Exploraciones/Domain/EstimadorRecompensasExploracion.php`
- `src/Exploraciones/Domain/EvaluadorExploracion.php`
- `src/Exploraciones/README.md`
- `tests/Unit/Exploraciones/CalculadorRecompensasTest.php`
- `tests/Unit/Exploraciones/CalculadorRiesgoTest.php`
- `tests/Unit/Exploraciones/EvaluadorExploracionTest.php`
- `tests/Unit/Exploraciones/MatchupsTest.php`

Sin seguimiento (nuevos, no commitear sin autorización):

- `src/Equipos/App/*` (6 casos de uso + DTO)
- `src/Equipos/context.md`
- `src/Exploraciones/Domain/Collections/MatchupCollection.php`
- `src/Exploraciones/Domain/DataTransferObjects/*`
- `src/Exploraciones/Domain/ReglasExploracion.php`
- `src/Exploraciones/context.md`
- `src/Shared/Domain/ReglasEquipo.php`

Regla: no revertir ni sobrescribir ninguno de estos cambios. Cualquier implementación debe partir
del árbol de trabajo actual y limitarse a los archivos autorizados en el plan aprobado.

## Registro de delegaciones

| Fecha | Especialista | Objetivo | Estado |
|---|---|---|---|
| 2026-11-01 | `analyst` A | Flujo funcional, bug individual/equipo, deuda y modelo de datos | completado |
| 2026-11-01 | `analyst` B | Presentación, eventos, personalidad y cobertura de pruebas | completado |
| 2026-11-01 | `architect` | Diseño Fase 1 + addendum de procesamiento bajo demanda | completado |
| 2026-11-01 | `backend` 1 | Economía 50 % / EXP 50-50 | completado |
| 2026-11-01 | `backend` 2a | Ciclo de vida, `inicio_exploracion`, `return_time` | completado |
| 2026-11-01 | `backend` 2b | Procesamiento bajo demanda, lock, consola | completado |
| 2026-11-01 | `backend` 3a | Bloqueo individual y desbloqueo de equipo | completado |
| 2026-11-01 | `backend` 3b | Guarda de combate con miembro ausente | completado |
| 2026-11-01 | `backend` 4a | DTOs de personalidad y eventos | completado |
| 2026-11-01 | `backend` 4b | Integración de presentación y PHPStan | completado |
| 2026-11-01 | `frontend` F1 | UI de exploraciones, ficha y diario | completado |
| 2026-11-01 | `frontend` F2 | Equipos editables y miembro ausente en hábitats | completado |
| 2026-11-01 | `frontend` | Ajuste de aserciones de vistas | completado |
| 2026-11-01 | `reviewer` | Revisión independiente y re-revisión | completado: `APPROVED` |
| 2026-11-01 | `qa` | QA funcional, PHPStan y veredicto final | completado: `PASS WITH KNOWN ISSUES` |
| 2026-11-01 | `security` | Auditoría de seguridad | no aplica por decisión del desarrollador |
| 2026-11-01 | `documenter` | Documentación del módulo | completado |

## Incidencias conocidas durante implementación

- `tests/Feature/EquiposPayloadTest.php` falla por `nivel` 2 vs 1; se ha identificado como
  preexistente y ajeno a TASK-003. No se ha modificado.
- `ExploracionesAutoResolucionTest` presentaba un fallo intermitente por RNG de combate en
  ejecución combinada; **resuelto post-QA** con una aserción determinista sobre `eventos['resultado']`.
- El agente `frontend` tiene permitido editar `resources/**` y `public/**`; los tests de vista se
  actualizaron por `backend`/`frontend` con autorización explícita.
- Pint reordenó imports en `FinalizarExploracionHandler` (WIP ajeno) sin cambio semántico.

## Notas de coordinación

- Proyecto objetivo: `/home/david/pokemon` (no el clon `pokemon-showdown`).
- La petición mezcla diagnóstico y refactor. No se implementará nada hasta aprobar `plan.md`.
- El síntoma «individual vs equipo» puede ser de UI, de contrato de datos o de ambos; el análisis
  debe separar evidencia de hipótesis.

## Cierre de implementación

- Backend: economía, ciclo de vida, procesamiento bajo demanda, bloqueo individual, guarda de
  combate, presentación y correcciones de revisión.
- Frontend: página de exploraciones, ficha/diario, equipos editables y hábitats con miembro ausente.
- Correcciones de revisión: preview con factor 0,5 y labels; una única `EconomiaRecompensas` en el
  handler; tests de captura; aviso económico en la vista.
- Limpieza PHPStan: `Reclutado::estaEnExploracion`, baseline de `Team`/`ProcesarExploraciones`/
  `HabitatsController`, `src/Equipos/App/*` a `void` y `TeamController` adaptado.
- El único ajuste de test ajeno fue actualizar expectativas obsoletas de vistas; no se relajaron
  aserciones de comportamiento.

## Validación final

- Reviewer: `APPROVED`.
- QA: `PASS WITH KNOWN ISSUES`.
- PHPStan: 0 errores en archivos de TASK-003; 8 preexistentes en `SendasController`.
- Pint: pass en archivos de TASK-003.
- Sin migraciones ni dependencias nuevas.

## Issues conocidos (actualizado tras correcciones)

- `phpstan` completo: 8 errores preexistentes en `SendasController` (WIP ajeno).
- Seguridad no auditada por decisión del desarrollador.
- Fase 2: god-handler, preview único, idempotencia fuerte, `withoutOverlapping()`, cast de
  `hora_limite`, limpieza de alias/columnas muertas.
- Huecos de cobertura: tests end-to-end de los cuatro flujos de combate, ausencia de payload
  `equipo` en 201, lock con driver de producción y presupuesto real.
- `ExploracionesAutoResolucionTest` es determinista, pero su aserción de combate es más débil;
  reforzarla en Fase 2.

## Correcciones post-QA (2026-11-01)

El desarrollador confirmó los valores canónicos `MINUTOS_POR_ENCUENTRO = 30` y
`HP_POR_MINUTO_DESCANSO = 1`, y autorizó corregir los errores del módulo.

- Tests/docblocks obsoletos: descanso 40 min (1 %/min × 1.75), docblocks de
  `ProcesarExploracionHandler` a 1 %/min, comentario de `ExploracionesAutoResolucionTest`.
- Tests frágiles: `CombateExploracionTest` mide daño con bonus; `ExploracionesAutoResolucionTest` ya
  no depende de una victoria RNG.
- `EquiposPayloadTest`: `ReclutadoSerializer` usa el nivel derivado de `exp` para `nivel`;
  `stats_calculados`/`cp` siguen usando el nivel del jugador.
- `objetos_perdidos`: `PresentadorExploraciones::toTerminada()` lo reenvía a `resultado`; la vista
  lo muestra y hay test.
- Documentación del módulo actualizada: 30/1 canónicos, sin fallos obsoletos ni deuda de
  `objetos_perdidos`.

### Validación final

- Suite relevante: **456 tests / 2705 aserciones en verde**.
- Fallos corregidos: **26 tests / 114 aserciones en verde**.
- PHPStan: 0 errores en TASK-003; 8 preexistentes en `SendasController`.
- Pint: pass.
- Seguridad: no aplica por decisión del desarrollador.
