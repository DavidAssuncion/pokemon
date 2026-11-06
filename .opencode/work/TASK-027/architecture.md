# TASK-027 — Arquitectura

- **Estado:** COMPLETADO
- **Autor:** `architect` (`ses_e4d7e2585ffe8l55bk8KoQdzWH`)
- **Base:** `.opencode/work/TASK-024/analysis.md`, `.opencode/work/TASK-026/architecture.md`, commit `fad7c65`.

## 1. Decisión

`Combate` queda como fachada de estado y delegación:

- **32 propiedades públicas y 18 métodos públicos** intactos; solo cambian los cuerpos a delegaciones.
- `OrquestadorBatalla` como composition root de presentación.
- 12 gestores especializados + `ContextoEjecucionBatalla` + `ResolutorActorBatalla` + `PresentadorVistaCombate`.
- Los colaboradores reciben `Combate` **como parámetro de método**, nunca por constructor: grafo acíclico.
- `$grabandoEventos` sigue privado en `Combate` y se pasa por referencia solo a `iniciar`/`cerrar`.
- `render()` conserva `extends('layouts.app')->section('content')`; solo delega los datos.

## 2. Presupuesto de líneas

| Bloque | Líneas |
|---|---:|
| 32 propiedades + 5 docblocks | 37 |
| 18 métodos PSR-12 con delegación | ~90 |
| Cabecera, namespace, imports, clase, privadas | ~16 |
| **Total** | **143-148** |

**120 no es alcanzable** sin mover propiedades, usar traits o métodos de una línea que Pint expande. Objetivo del plan: **≤150 líneas**.

## 3. Colaboradores

| Archivo | Responsabilidad |
|---|---|
| `ContextoEjecucionBatalla` | `servicioEjecucion` y `forzarSiguienteActorId` efímeros |
| `ResolutorActorBatalla` | `porRefId`, `jugadorAutorizado`, `objetivoPorSeleccion` |
| `GestorVistaBatalla` | `syncViewData` |
| `GestorAnimacionBatalla` | `setAnimState`, `setAnimStateObjeto`, `resetAnimState` |
| `GestorEventosBatalla` | `iniciar`, `cerrar`, `capturar`, `registrar`, `batallaTerminada` |
| `GestorPrioridadesBatalla` | `declararIa`, `resolver`, `objetivoPara` |
| `GestorAccionBatalla` | `commit`, `terminar`, `prepararAnimacionIa`, `resolver`, `resolverObjeto` |
| `GestorPreparacionBatalla` | `abrir`, `confirmar`, `saltar`, `autoSaltar`, `cambiarPostura`, `aplicar`, `limpiar` |
| `GestorCicloTurnosBatalla` | `avanzar`, `avanzarRonda`, `avanzarRondaOTerminar` |
| `GestorObjetosBatalla` | `seleccionar`, `cancelar`, `confirmarObjetivoItem`, `limpiar` |
| `GestorSeleccionBatalla` | `previewTarget`, `selectMove`, `cancelTarget`, `swapTurno`, `retrasarTurno`, `pasarTurno` |
| `GestorLifecycleBatalla` | `nueva`, `montar`, `iniciarMock` |
| `OrquestadorBatalla` | composition root y delegación pública |
| `PresentadorVistaCombate` | datos de `BatallaVistaPresenter` + `MovesPreviewPresenter` |

## 4. Delegación de los 18 métodos públicos

| Método | Delegado |
|---|---|
| `nuevaBatalla` | `Orquestador::nuevaBatalla` |
| `boot` | resuelve `OrquestadorBatalla` con `??=` |
| `mount` | `Orquestador::montar` |
| `render` | `Orquestador::datosVista` + layout |
| `nextActor` | `GestorCicloTurnos::avanzar` |
| `confirmarPreparacion` | `GestorPreparacion::confirmar` |
| `saltarPreparacion` | `GestorPreparacion::saltar` |
| `cambiarPostura` | `GestorPreparacion::cambiarPostura` |
| `commitAction` | `GestorAccion::commit` |
| `previewTarget` | `GestorSeleccion::previewTarget` |
| `selectMove` | `GestorSeleccion::selectMove` |
| `cancelTarget` | `GestorSeleccion::cancelTarget` |
| `seleccionarObjeto` | `GestorObjetos::seleccionar` |
| `cancelarObjeto` | `GestorObjetos::cancelar` |
| `confirmarObjetivoItem` | `GestorObjetos::confirmarObjetivoItem` |
| `swapTurno` | `GestorSeleccion::swapTurno` |
| `retrasarTurno` | `GestorSeleccion::retrasarTurno` |
| `pasarTurno` | `GestorSeleccion::pasarTurno` |

## 5. Sesión y eventos

- `BattleSessionService` se inyecta en los gestores que persisten; no se añade ni elimina ningún `guardar`.
- El doble guardado de `iniciarMockBattle` se conserva.
- `$eventos`, `$eventoTick` y `$grabandoEventos` siguen en `Combate`.
- `GestorEventosBatalla` orquesta `iniciar/cerrar/registrar`; no guarda el flag.
- `commitAction` pasa el flag por referencia; `nextActor` y `GestorAccion` lo reciben por valor.

## 6. Subolas

1. **3A:** `ContextoEjecucionBatalla`, `ResolutorActorBatalla`, `GestorVistaBatalla`, `GestorAnimacionBatalla`, `GestorEventosBatalla`.
2. **3B:** `GestorPrioridadesBatalla`, `GestorAccionBatalla`.
3. **3C:** `GestorPreparacionBatalla`, `GestorCicloTurnosBatalla`.
4. **3D:** `GestorObjetosBatalla`, `GestorSeleccionBatalla`.
5. **3E:** `GestorLifecycleBatalla`, `OrquestadorBatalla`, `PresentadorVistaCombate`, `Combate.php`, baseline.
6. **3F:** escenarios comparados en `/tmp`.

## 7. Verificación

- Contrato tokenizado/reflexión: 32 propiedades y 18 métodos idénticos.
- `Combate.php` ≤150 líneas.
- Pint, PHPStan ≤34, deptrac ≤100, `php -l`, `git diff --check`, `view:cache`.
- Greps: sin `app()` en colaboradores, sin traits, sin lógica de dominio en la fachada.
- Escenarios comparados base `fad7c65` vs refactor: 11 escenarios con `mt_srand`, comparando propiedades, log, eventos, sesión, rewards y habitatId.
- Revisión independiente APPROVED.

## 8. Riesgos

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Recursión `nextActor` | Alto | Guardas y consumo de acción; listar call sites; escenario largo |
| Acoplamiento bidireccional | Medio | `Combate` solo por parámetro; sin referencias almacenadas |
| Orden de guardados | Alto | Tabla de puntos de guardado + diff de escenarios |
| Eventos | Alto | Gestor único; comparación exacta |
| Flag por referencia | Medio | Solo `iniciar`/`cerrar` lo escriben |
| Baseline | Medio | Eliminar solo la entrada `booleanNot` obsoleta |
| Superar 150 líneas | Medio | Presupuesto 143-148; sin docblocks nuevos |
| Sin tests | Alto | Escenarios comparados + revisión manual |
