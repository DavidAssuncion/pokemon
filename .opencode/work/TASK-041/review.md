# TASK-041 — Revisión

- **Estado:** APPROVED
- **Revisor:** `reviewer` (sesión `ses_e3f743222ffeeD80dtfkBAlTRR`)
- **Nota:** resultado del revisor, sin alterar sus conclusiones.

---

# Revisión independiente — TASK-041 (plan v1)

## 1. Veredicto

**APPROVED** — sin CRITICAL, HIGH ni MEDIUM atribuibles a TASK-041. Quedan observaciones LOW/NIT y verificaciones manuales fuera del alcance estático.

## 2. Resumen

El cambio se ajusta al alcance autorizado:

- **IA:** `declararIa()` decide con `AgregadoBatalla::elegirAccionPara()`; pre-chequeo `tienePrioridadDisponible()`; limpieza de prioridad/postura/objetivo; declara solo si `move->priority > 0`; filtra apoyo a aliado; guard si `team1` no tiene vivos. No toca `resolver()`, `pendingAction`, acciones extra ni el filtro de TASK-040.
- **Debug:** gate server-side con `(bool) config('app.debug')` solo en `SincronizadorVistaBatalla`, aplicado solo a `team2`; `team1` y `CombatienteVista` intactos. Presentador nuevo sin Eloquent/Livewire/Blade. Tooltip hover-only con `pointer-events: none`, todo con `{{ }}`, sin JS ni dependencias.
- **Compatibilidad:** `SESSION_VERSION = 21`; sin cambios en `MovimientoBatalla`, `PerfilMovimiento`, `CombatienteVista`, `SelectorAccionIA`, `AgregadoBatalla`, migraciones, dependencias ni tests.
- Archivos ajenos de TASK-035 fuera del diff de TASK-041.

## 3. Hallazgos

### CRITICAL / HIGH / MEDIUM

Ninguno.

### LOW

- **Desviación del mecanismo de overlay aprobado.** `combate.css:105-140` y `_pokemon-card.blade.php:6-74`: el plan/arquitectura especificaban overlay `inset: 0`; la implementación usa panel lateral `right: 100%` con `overflow: visible`. Documentado en `execution.md`. Impacto: variante de UI; `overflow: visible` altera el recorte de pintado de la carta debug y `position: relative` cambia el bloque contenedor de `.vital-delta` (píldora flotante) solo en debug. Sin impacto funcional/seguridad. Recomendación: documentar la variante en el informe final.
- **Filtro de apoyo latente.** `GestorPrioridadesBatalla.php:58-61`: `ClasificadorAccionIA::esApoyo(...) && ! $movimiento->esAutodirigido()` no filtra literalmente todo apoyo a aliado. Un apoyo generado sobre un aliado con movimiento `SELF` tendría `esApoyo=true` y `esAutodirigido=true`, por lo que no se filtraría y `resolver()` lo resolvería contra el actor. Hoy no hay movimientos de apoyo con `priority > 0`; impacto nulo. Recomendación: simplificar a `if (ClasificadorAccionIA::esApoyo($battle, $accion)) { continue; }`.
- **Tooltip lateral sin scroll usable.** `combate.css:113-123`: `max-height: 70vh; overflow-y: auto` con `pointer-events: none`; si el contenido excediera 70vh no se podría desplazar. Con 6 stats + 6 movimientos no se alcanza en viewports normales; riesgo bajo, solo debug. Aceptar como limitación documentada.

### NIT

- `limpiarPrioridad()` duplicado con `GestorPreparacionBatalla` (inocuo).
- Comentario de `DebugCombatientePresenter` sobre efectos de objeto frente a `CatalogoObjetos::clavesDeEfecto()`.
- Stats actual/máx no comparables bajo etapas/clima (contrato aprobado).
- `@php(...)` inline y comentario CSS “overlay interno” vs panel lateral.

## 4. Criterios

### IA

| Criterio | Estado | Evidencia |
|---|---|---|
| CA-IA-1 | No verificable estáticamente | Delegado en `elegirAccionPara()`; requiere batalla manual Riolu vs Fantasma. |
| CA-IA-2 | Cumplido | `GestorPrioridadesBatalla.php:52-63`. |
| CA-IA-3 | Cumplido | `:54-56`; el flujo normal ejecuta. |
| CA-IA-4 | Cumplido | Pre-chequeo y `generarAccionesCandidatas()` usan disponibilidad/protección. |
| CA-IA-5 | Cumplido en el catálogo actual | Filtro `:58-61`; no hay apoyos con prioridad > 0. Ver LOW latente. |
| CA-IA-6 | Cumplido | `resolver()->objetivoPara()` devuelve el actor. |
| CA-IA-7 | Cumplido | `SelectorAccionIA` sustituye objetivos por el forzado vivo; verificación manual recomendada. |
| CA-IA-8 | Cumplido | `SESSION_VERSION` 21; sin serialización. |
| CA-IA-9 | Cumplido | Pre-chequeo evita `elegirAccionPara()`. |
| CA-IA-10 | Cumplido | Un `declararPrioridad()` por iteración; `declararIa()` una vez por ronda. |

### Tooltip

| Criterio | Estado | Evidencia |
|---|---|---|
| CA-DBG-1 | Cumplido | Único `config('app.debug')` en el sincronizador; `debug` solo en `team2`. |
| CA-DBG-2 | Cumplido | Presentador y Blade con tipos, habilidad, objeto, stats y movimientos. Render no verificado. |
| CA-DBG-3 | Cumplido | `team1` con `aArrayVista(0)`, sin debug. |
| CA-DBG-4 | Cumplido | `@media (hover: hover) and (pointer: fine)`; sin foco/tap/JS. |
| CA-DBG-5 | Cumplido con matiz | No se recorta; `overflow: visible`/`position: relative` en debug. |
| CA-DBG-6 | Cumplido | `pointer-events: none`; el clic atraviesa. |
| CA-DBG-7 | Cumplido | Sin `{!! !!}`; todo `{{ }}`. |
| CA-DBG-8 | Cumplido | Sin cambios de contratos/serialización. |
| CA-DBG-9 | Cumplido | Sin dependencias; solo Blade/CSS. |

## 5. Riesgos residuales

1. Balance de IA (aprobado): menos prioridades inefectivas; verificación manual.
2. Perturbación del RNG global por `CalculadoraDanioIA::estimar()` (aprobado).
3. Doble evaluación en preparación y turno normal (mitigado con pre-chequeo).
4. Objetivo reevaluado en `resolver()` (preexistente).
5. Fuga de debug mitigada por gate server-side; `APP_DEBUG=true` en producción riesgo operativo.
6. Filtro de apoyo latente si se añade un apoyo con prioridad > 0 y objetivo `SELF/ALLY_*`.
7. UX del panel lateral: al mover el cursor hacia el panel puede perderse el hover; sin scroll por `pointer-events: none`. Debug-only.
8. No verificado: hover real, `view:cache`, `npm run build`, CA-IA-1/7 en partida.

## 6. Verificación ejecutada

- `git status`/rama `task-011`; solo los 5 archivos de TASK-041 en el alcance.
- `git diff HEAD` de los 5 archivos revisado.
- `php -l` 3/3 sin errores.
- PHPStan: 34 errores preexistentes, 0 en archivos de TASK-041.
- Greps: único `config('app.debug')` en el sincronizador; `SESSION_VERSION` 21; sin `{!!` en la carta; sin `env()` en runtime.
- Contratos verificados: `elegirAccionPara`, `ClasificadorAccionIA::esApoyo`, `esAutodirigido`, `BattleStats`, `PerfilMovimiento`, `item*`, `SimuladorAccionIA` clona la batalla.

## 7. Recomendación final

**Aprobar el cambio.** No hay defectos CRITICAL/HIGH/MEDIUM ni regresiones de seguridad/capas. Correcciones mínimas no bloqueantes:
- Documentar la variante lateral + `overflow: visible` en el informe final.
- Opcional backend: simplificar el filtro a `if (ClasificadorAccionIA::esApoyo($battle, $accion)) { continue; }`.

Verificación manual pendiente: Riolu vs Fantasma, `DESAFIO` con prioridad, hover real con `APP_DEBUG` true/false, no recorte en 2v2/3v3, selección con `wire:click` bajo tooltip y sesión antigua.
