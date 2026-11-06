# TASK-027 — Plan v1 — Fachada de `Combate` (≤150 líneas)

- **ID:** TASK-027
- **Versión:** v1
- **Estado:** PENDIENTE DE APROBACIÓN — no autoriza implementación
- **Fecha:** 2026-11-06
- **Base:** `.opencode/work/TASK-027/architecture.md`, `commit fad7c65`
- **Alcance autorizado por este plan:** fachada completa + 14 colaboradores + limpieza de una entrada del baseline.

---

## 1. Objetivo

Convertir `app/Livewire/Combate.php` (1.491 líneas) en una fachada de **≤150 líneas** que:

- conserva las **32 propiedades públicas** y los **18 métodos públicos**;
- delega toda la lógica en `OrquestadorBatalla` + 12 gestores + `PresentadorVistaCombate`;
- mantiene `render()`, el layout, las vistas, el JS, las rutas, la sesión y el comportamiento.

**Nota de presupuesto:** ~120 líneas no es alcanzable con PSR-12 y el contrato actual. El suelo real es **143-148 líneas**; el plan fija ≤150.

---

## 2. Subolas y archivos

### 3A — Base

Crear:

- `app/Livewire/Combate/Soporte/ContextoEjecucionBatalla.php`
- `app/Livewire/Combate/Soporte/ResolutorActorBatalla.php`
- `app/Livewire/Combate/Soporte/GestorVistaBatalla.php`
- `app/Livewire/Combate/Soporte/GestorAnimacionBatalla.php`
- `app/Livewire/Combate/Soporte/GestorEventosBatalla.php`

### 3B — Acción y prioridades

Crear:

- `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php`
- `app/Livewire/Combate/Soporte/GestorAccionBatalla.php`

### 3C — Preparación y ciclo

Crear:

- `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php`
- `app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php`

### 3D — Selección y objetos

Crear:

- `app/Livewire/Combate/Soporte/GestorObjetosBatalla.php`
- `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php`

### 3E — Integración

Crear:

- `app/Livewire/Combate/Soporte/GestorLifecycleBatalla.php`
- `app/Livewire/Combate/Soporte/OrquestadorBatalla.php`
- `app/Livewire/Presenters/PresentadorVistaCombate.php`

Modificar:

- `app/Livewire/Combate.php`
- `phpstan-baseline.php` (solo la entrada obsoleta `booleanNot` de `Combate.php`).

### 3F — Escenarios

Scripts en `/tmp/opencode/task-027`, fuera del repo.

---

## 3. Reglas de implementación

- `Combate` solo recibe colaboradores en `boot()` con `??=`; los gestores reciben `Combate` por parámetro de método.
- Ningún colaborador guarda una referencia a `Combate` ni duplica propiedades públicas.
- `ContextoEjecucionBatalla` es el único estado efímero compartido; lo crea el orquestador por request.
- `$grabandoEventos` sigue privado en `Combate`; solo `GestorEventos::iniciar/cerrar` lo escriben por referencia.
- `render()` conserva `view('livewire.combate', $datos)->extends('layouts.app')->section('content')`.
- Sin `app()` en colaboradores; `Guard`, `Request`, `BattleSessionService`, `InventarioJugador` y `UsarObjetoEnCombate` por constructor.
- Sin traits, sin interfaces nuevas, sin DTOs de estado.
- No añadir ni quitar `guardar()` de sesión.
- No tocar vistas, JS, rutas, `layout`, `SESSION_VERSION`, `BattleSessionService`, `src/**`, tests ni migraciones.

---

## 4. Criterios de aceptación

1. **Contrato congelado:** 32 propiedades y 18 métodos públicos idénticos a `fad7c65` (nombre, tipo, default, parámetros, retorno y visibilidad).
2. `Combate.php` **≤150 líneas**.
3. `render()` conserva la vista y el layout; sin cambios en Blade/JS.
4. `SESSION_VERSION` y `BattleSessionService` sin cambios; mismo número y orden de `guardar()`.
5. `$eventos`, `$eventoTick` y `$grabandoEventos` siguen en `Combate`.
6. Eventos con mismos tipos, orden, ids y claves.
7. `nextActor` conserva guardas y orden; sin recursión infinita en escenario largo.
8. Pint pass; PHPStan ≤34; deptrac ≤100; `php -l` y `view:cache` OK; `git diff --check` OK.
9. Baseline: solo se elimina la entrada obsoleta indicada; sin entradas nuevas.
10. Revisión independiente APPROVED.

---

## 5. Verificación

- Contrato tokenizado/reflexión contra `fad7c65`.
- `wc -l app/Livewire/Combate.php`.
- Greps: sin `app()` en colaboradores, sin traits, sin lógica de dominio en la fachada.
- Auditoría de `guardar(` contra la tabla de puntos de guardado.
- **Escenarios comparados** base `fad7c65` vs refactor, con `mt_srand(12345)`:
  1. nueva batalla;
  2. montaje con `battle_id`;
  3. `previewTarget` + `selectMove`;
  4. `commitAction` de jugador;
  5. turno de IA;
  6. avance de ronda y preparación;
  7. `cambiarPostura` + `confirmarPreparacion`;
  8. `saltarPreparacion` y auto-salto;
  9. objetos (seleccionar, cancelar, confirmar);
  10. `swapTurno`/`retrasarTurno`/`pasarTurno`;
  11. fin de batalla.
- Comparar propiedades públicas, log, eventos, `phase`, `round`, `processing`, `actingRefId`, `turnQueue`, prefijo `21|`, hash del payload, `rewards` y `habitatId`.
- Pint, PHPStan, deptrac, `php -l`, `view:cache`, `git diff --check`.
- Sin tests.

---

## 6. Riesgos y mitigaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Recursión de `nextActor` | Alto | Conservar guardas y `consumeAction`; auditoría de call sites; escenario largo |
| Acoplamiento bidireccional | Medio | `Combate` solo por parámetro; sin referencias almacenadas |
| Orden de guardados | Alto | Tabla de puntos de guardado + escenarios |
| Eventos | Alto | Gestor único + comparación exacta |
| Flag por referencia | Medio | Solo `iniciar`/`cerrar` lo escriben |
| Baseline | Medio | Eliminar solo la entrada obsoleta |
| Superar 150 líneas | Medio | Presupuesto 143-148; sin docblocks nuevos |
| Sin tests | Alto | Escenarios comparados + revisión manual |
| Deriva de comportamiento en interacciones | Alto | Escenarios 3-10 y revisión del diff |

---

## 7. Rollback

- 3A puede commitearse como cambio aditivo.
- 3B-3E se consolidan en un commit atómico (desviación documentada del commit por subola).
- `git revert` del commit; sin operaciones destructivas.
- `SESSION_VERSION` intacto: sesiones existentes válidas.
- Sin migraciones ni datos que revertir.

---

## 8. Fuera de alcance

- Cambios en Blade/JS/rutas/layout.
- Restaurar docblocks eliminados en TASK-025.
- Ola 1C (scripts de Equipos/Hábitats).
- Ola 4 (modales compartidos).
- Tests, migraciones, dependencias, `git push`.

---

## 9. Aprobación solicitada

Aprobar el plan v1 de TASK-027:

- Fachada de `Combate` ≤150 líneas.
- 14 colaboradores de presentación.
- Limpieza de una entrada obsoleta del baseline.

Se acepta explícitamente que el objetivo realista es **143-148 líneas**, no 120, por las restricciones de PSR-12 y del contrato público.
