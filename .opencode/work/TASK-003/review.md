# TASK-003 — Revisión independiente

- **Revisor:** `reviewer`
- **Fecha:** 2026-11-01
- **Plan revisado:** `plan.md` v2
- **Veredicto final:** `APPROVED`

## Resumen

La Fase 1 se implementó conforme al plan aprobado: economía con política aislada y aplicada una
sola vez, reparto 50/50 con invariante, ciclo de vida temporal, procesamiento bajo demanda con lock
fuera de la transacción, bloqueo individual del reclutado, guarda de combate centralizada y capa de
presentación sin consultas ni datos inventados. La dirección de dependencias se mantiene.

## Hallazgos iniciales y resolución

| Severidad | Hallazgo | Estado |
|---|---|---|
| HIGH | El preview no aplicaba el factor 0,5 ni devolvía labels no nulos | Resuelto: `EstimadorRecompensasExploracion` aplica `FACTOR_RECOMPENSAS`; labels `Caramelos de familia`, `Caramelos EV`, `Caramelos de tipo <Tipo>` |
| HIGH | El contrato de `preview` no exponía la política económica ni el aviso | Resuelto: `economia` + `aviso_economia`; la vista los pinta con `role="note"` |
| MEDIUM | Factor de capturas/hallazgos duplicado fuera de la política | Resuelto: una única `EconomiaRecompensas::individual()` en `FinalizarExploracionHandler` |
| MEDIUM | Faltaban tests de captura ×0,5 | Resuelto: `ProbabilidadCapturaTest` + `ExploracionesPreviewTest` |
| MEDIUM | Test de presupuesto incompleto | No bloqueante; queda como mejora de cobertura |
| LOW | `recoger` devuelve el mismo mensaje para lock ocupado y fallo | Aceptado; no duplica recompensas |
| LOW | Descanso muestra información duplicada | Aceptado; cosmético |
| LOW | Clamps/clases en Blade | Aceptado; presentación menor |
| NIT | Alias `equiposEnExploracion` vacío | Aceptado; deprecado para Fase 2 |

## Verificación

- `EconomiaRecompensas` aplica el factor una sola vez; `CalculadorRecompensas` conserva la rama
  legada para ruta y entrenadores.
- Invariante `expCuenta + expReclutado = pool` con `intdiv` y resto al destino.
- `expTipoCuenta` con fallback a `expTipoPorMiembro`; `sumarHallazgos` y `restarPerdidas` lo
  propagan.
- Lock adquirido fuera de `UnitOfWork::transaction()` y liberado en `finally`.
- `GET /exploraciones` particiona activas/terminadas en PHP tras procesar.
- `Team::isExploring()` eliminado; guardas de equipo retiradas; `ConstruirEquipoJugador` cubre los
  cuatro flujos de combate.
- `declare(strict_types=1)` en archivos nuevos; sin dependencias ni migraciones.

## Fallos de suite

Los cuatro fallos que quedaban abiertos durante la revisión se corrigieron después:

- `ProcesarExploracionCombateTest`: expectativa actualizada a 40 min con 1 %/min y multiplicador
  1.75.
- `CombateExploracionTest`: aserción determinista de daño con bonus.
- `ExploracionesAutoResolucionTest`: aserción determinista sobre `eventos['resultado']`.
- `EquiposPayloadTest`: `ReclutadoSerializer` usa el nivel derivado de `exp`.

Suite relevante final: **456 tests / 2705 aserciones en verde**. El único rojo de PHPStan está en
`SendasController.php` (WIP ajeno, 8 errores preexistentes).

## Conclusión

`APPROVED` para el alcance de TASK-003. Los puntos abiertos de RNG, nivel del payload, constantes de
balance y `SendasController` son deuda del árbol de trabajo y deben abordarse en tareas separadas.
