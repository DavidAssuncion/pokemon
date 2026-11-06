# TASK-028 — Plan v1 — Verificación diferencial 3F

- **ID:** TASK-028
- **Versión:** v1
- **Estado:** PENDIENTE DE APROBACIÓN — no autoriza ejecución
- **Fecha:** 2026-11-06
- **Base comparada:** `fad7c65` (pre-fachada) vs `8cc7fc0` (fachada)
- **Alcance:** verificación runtime; **sin cambios de código de aplicación**.

---

## 1. Objetivo

Demostrar que la fachada `Combate` (149 líneas + 14 colaboradores) produce el mismo estado, log, eventos, sesión y recompensas que el componente original en 11 escenarios.

---

## 2. Arnés

### 2.1 Preparación

- `git worktree add /tmp/opencode/task-028/base fad7c65`.
- Symlink `/tmp/opencode/task-028/base/vendor -> /home/david/pokemon/vendor`.
- Copiar `.env` o definir variables:
  - `APP_ENV=testing`
  - `APP_KEY=base64:...` de prueba (32 bytes)
  - `SESSION_DRIVER=array`
  - `CACHE_STORE=array`
  - `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` (solo si alguna ruta lo exige).
- Script `harness.php` en `/tmp/opencode/task-028`:
  - Recibe `--project=<ruta>` y `--scenario=<n>`.
  - Bootstrap: `require <project>/vendor/autoload.php` y `<project>/bootstrap/app.php`.
  - Configura sesión array y `Auth` con un usuario falso estable (id 1) para rutas que lo usan.
  - Instancia `App\Livewire\Combate` con `new`, llama `boot()` y, si aplica, fija `battle_id` en la request antes de `mount()`.
  - Ejecuta el escenario y captura el estado.

### 2.2 Captura

`get_object_vars($componente)` para las 32 propiedades públicas, más:

- `session_payload` = `session($battleId)`.
- `session_hash` = hash del payload.
- `session_version` = prefijo antes de `|`.
- `log`, `eventos`, `phase`, `round`, `processing`, `actingRefId`, `turnQueue`, `rewards`, `habitatId`.

Serializar a JSON con claves ordenadas.

### 2.3 Comparación

- Ejecutar el mismo escenario en base y actual con el mismo `mt_srand`.
- `diff -u base.json actual.json`.
- Guardar evidencia en `/tmp/opencode/task-028/resultados/`.

---

## 3. Escenarios

| # | Escenario | Acciones |
|---|---|---|
| 1 | Nueva batalla | `nuevaBatalla()` |
| 2 | Montaje | `mount()` con `battle_id` previamente guardado |
| 3 | Selección | `previewTarget()` + `selectMove()` |
| 4 | Acción de jugador | `commitAction()` |
| 5 | Turno IA | Tras acción, simular el `commitAction()` que dispara el JS |
| 6 | Ronda y preparación | Avanzar hasta `preparacionPendiente` |
| 7 | Preparación | `cambiarPostura()` + `confirmarPreparacion()` |
| 8 | Salto de preparación | `saltarPreparacion()` y auto-salto |
| 9 | Objetos | `seleccionarObjeto()`, `cancelarObjeto()`, `confirmarObjetivoItem()` |
| 10 | Turno | `swapTurno()`, `retrasarTurno()`, `pasarTurno()` |
| 11 | Fin de batalla | Forzar KO/estado final y comparar `rewards`/`habitatId` |

Cada escenario fija `mt_srand(12345)` al inicio.

---

## 4. Criterios de aceptación

1. **11/11 escenarios** con diff vacío, o lista explícita de escenarios no reproducibles con causa.
2. Mismo `session_hash` y `session_version` por escenario.
3. Mismo número/orden de `log` y `eventos`.
4. Mismas propiedades públicas.
5. Sin cambios de código de aplicación; `git status` solo muestra artefactos de `.opencode/work/TASK-028`.
6. Informe con evidencia por escenario.

---

## 5. Riesgos y limitaciones

| Riesgo | Nivel | Mitigación |
|---|---|---|
| El arnés manual no reproduce el ciclo Livewire real | Alto | Instanciar + `boot()` + `mount()`; validar un escenario simple antes de los 11 |
| `Auth`/inventario requieren BD | Medio | Usuario falso y `DB=:memory:`; si no, marcar objetos no cubiertos |
| Handshake JS/IA (`commitAction` en dos pasos) | Medio | Simular la segunda llamada cuando `pendingAction` sea de IA |
| Sesión array difiere de producción | Bajo | Solo se compara base vs actual con el mismo driver |
| Worktree sin vendor | Bajo | Symlink a `vendor` |
| Ruido por timestamps/uniqid | Medio | Normalizar ids de batalla y claves volátiles antes del diff |
| Animaciones/JS no cubiertas | Alto | Fuera de alcance; smoke manual posterior |

---

## 6. Rollback

- No hay cambios de aplicación.
- `git worktree remove /tmp/opencode/task-028/base`.
- Borrar scripts temporales.
- Sin migraciones ni datos.

---

## 7. Fuera de alcance

- Navegador, DOM, Alpine/JS y animaciones.
- Camino de éxito de objetos con BD real.
- Concurrencia.
- Tests del proyecto.
- Cambios de código.

---

## 8. Aprobación solicitada

Aprobar la ejecución de 3F:

- Arnés en `/tmp` y worktree base en `fad7c65`.
- 11 escenarios comparados.
- Informe con diffs y limitaciones.
