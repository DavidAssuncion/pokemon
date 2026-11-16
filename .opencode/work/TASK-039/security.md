# TASK-039 — Seguridad

- **Estado:** PASS_WITH_FINDINGS — no bloqueante
- **Auditor:** `security` (sesión `ses_e3ff7a345ffeN3iK4DUKZh2L42`)
- **Nota:** resultado del auditor, sin alterar sus conclusiones.

---

# Auditoría de seguridad — TASK-039

## 1. Veredicto

**PASS_WITH_FINDINGS — no bloquea la entrega.**

No se ha identificado ningún hallazgo **CRITICAL**, **HIGH** ni **MEDIUM** atribuible a TASK-039. Los dos hallazgos son de severidad **LOW/INFO**, con impacto limitado a la propia sesión del atacante (auto-perjuicio), y las dependencias con avisos son deuda preexistente no introducida por este cambio.

## 2. Superficie revisada

Diff de TASK-039 (6 archivos, `+38/−61`), ignorando los cambios de TASK-035/038 presentes en el árbol:

| Archivo | Cambio auditado |
|---|---|
| `app/Livewire/Combate/Soporte/GestorCicloTurnosBatalla.php` | Fallback en `avanzar` (`:152-163`) + `sinAccionLegal()` (`:199-214`) |
| `app/Livewire/Combate.php` | Eliminación de `pasarTurno()`; 17 métodos públicos |
| `app/Livewire/Combate/Soporte/OrquestadorBatalla.php` | Eliminación de la delegación |
| `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` | Eliminación del método y ajuste de docblock |
| `app/Livewire/Presenters/BatallaVistaPresenter.php` | Eliminación de `mostrarEsperar` |
| `resources/views/livewire/partials/turn-bar.blade.php` | Eliminación del botón «Esperar» |

Contexto de confianza revisado: `BattleSessionService`, `SincronizadorVistaBatalla`, `ObtenerOpcionesObjeto`, `GestorObjetosBatalla`, `ResolutorActorBatalla`, `GestorTurnos`, `EstadoTurno`, `GestorPreparacionBatalla`, `GestorPrioridadesBatalla`, `HandleComponents` (Livewire 4.4.2), `battle-log.blade.php` y `config/auth.php`.

## 3. Hallazgos por severidad

### [LOW] `preparacionPendiente` es una compuerta de seguridad basada en propiedad pública manipulable

- **Ubicación:** `GestorCicloTurnosBatalla.php:153` (consumo) y `:59` (apertura); propiedad en `Combate.php:48`.
- **Vector:** un cliente manipulado altera `preparacionPendiente` en el snapshot de Livewire (propiedad pública sin `#[Locked]`):
  - `false` → el fallback se evalúa y puede consumir la acción mientras el modal de preparación está abierto (se incumple CA8 de forma transitoria).
  - `true` → suprime el fallback y puede dejar al actor sin salida mientras la acción no se consuma.
- **Evidencia:** `Combate.php:48` declara `public bool $preparacionPendiente = false;` sin atributo de bloqueo; `GestorCicloTurnosBatalla.php:153` la lee directamente como condición del fallback. Livewire solo restringe propiedades con `#[Locked]`; el resto se hidrata desde el cliente.
- **Impacto:** **solo la batalla del propio usuario**. Al reentrar en `avanzar`, `:59` vuelve a llamar a `GestorPreparacionBatalla::abrir()` (que fija `preparacionPendiente = true`), por lo que la preparación se reabre y no se pierde de forma permanente. El caso `true` es recuperable abriendo «Preparación» → «Listo/Saltar».
- **Remediación:** marcar `#[Locked]` en `preparacionPendiente` (y propiedades derivadas) o derivar la compuerta de estado de servidor (`$battle->rondaPreparada() !== $turnos->round()` / flag en el agregado) y fijarla al inicio de `avanzar`.

### [INFO] El fallback no limpia todo el estado transitorio de selección

- **Ubicación:** `GestorCicloTurnosBatalla.php:155-162`.
- **Evidencia:** el fallback limpia `selectedMoveIdx` y `processing`, pero no `objetoSeleccionado`, `objetivosItem` ni `selectedTargetTeam/Idx/RefId`, y no llama a `GestorAnimacionBatalla::reiniciar()`, a diferencia del `pasarTurno` eliminado.
- **Impacto:** ninguno de autorización demostrado. `GestorObjetosBatalla::confirmarObjetivoItem()` revalida en servidor, por lo que un estado obsoleto no permite usar objetos ni objetivos no autorizados. Es una inconsistencia de presentación/paridad.
- **Remediación:** limpiar el estado de selección y reiniciar animación por paridad con el flujo anterior.

### [INFO] Invariante frágil: el fallback depende de que `sincronizar()` se ejecute antes

- **Ubicación:** `GestorCicloTurnosBatalla.php:140-141` frente a `:152-154`.
- **Evidencia:** `team1`, `team2` y `objetosCombate` son propiedades públicas manipulables (`Combate.php:14-15,52`). El fallback es seguro **solo porque** `:140` las sobrescribe con la proyección de servidor antes de `localizar()` y de `sinAccionLegal()`.
- **Impacto:** hoy no explotable. Un futuro reordenamiento convertiría la decisión en manipulable por el cliente.
- **Remediación:** `#[Locked]` en `battleId`, `team1`, `team2`, `objetosCombate`, `currentMoves`, `turnQueue`, `actingRefId`; o pasar la proyección como parámetro local.

### Dependencias (preexistente, no atribuible a TASK-039)

- `composer audit` → **17 avisos en 8 paquetes**; `composer.lock` no fue modificado.
  - `laravel/framework 12.67.0` (3): TokenGuard bypass (no aplica: solo guard `web`/session), destrucción por inclusión de relaciones, XSS en debug (requiere `APP_DEBUG=true`).
  - `livewire/livewire 4.4.2` (1): bypass de `#[Authorize]`; no se usa `#[Authorize]` ni `#[Json]`.
  - `league/commonmark 2.10.0` y `symfony/*` 7.4.17: no alcanzables por TASK-039.
- **Recomendación:** actualizar en tarea separada. No bloquea.

## 4. Controles verificados

- **`$wire.pasarTurno()` no muta estado.** Livewire 4.4.2 valida el método contra los métodos públicos antes de invocarlo; al no existir, lanza `MethodNotFoundException` sin ejecutar lógica ni `session()->put`. `sinAccionLegal()` es privado y no es invocable desde Livewire.
- **Sin referencias residuales.** `grep -rn "pasarTurno\|mostrarEsperar"` en `app/`, `resources/`, `src/`, `routes/` → 0 coincidencias.
- **Contrato público:** `grep -c "public function" app/Livewire/Combate.php` → **17**.
- **Actor confiable.** El fallback usa `$actor` resuelto por `getNextActor()` o el actor forzado validado contra `team1`, vivo y con acción pendiente; fija `actingRefId`/`actorActualId` desde él. Nunca lee `actingRefId` para decidir.
- **`$isPlayer` no manipulable en la ruta del fallback:** `localizar()` opera sobre `team1`/`team2` recién sobrescritos por `sincronizar()`.
- **`objetosCombate` no manipulable en la ruta del fallback:** `sincronizar()` lo reemplaza con la proyección para el actor y el `usuarioId` de `Guard::id()`; `ObtenerOpcionesObjeto` exige `cantidad > 0` del inventario real. Un `aplicable` falsificado se descarta antes de la evaluación.
- **Sin consumo indebido ni bucles:** `consumeAction()` siempre muta estado; `accionesExtra` está acotado a 1; `getNextActor()` filtra vivos y pendientes. El conjunto de actores es finito y decreciente. Un actor con acción legal nunca se salta.
- **Preparación pendiente:** el fallback se difiere; `Guardia`/`Cubrir` se consumen antes, por lo que a la altura del fallback el actor está en `ATACAR`.
- **Guardado antes de reentrar:** `session->guardar(...)` precede a `nextActor()`.
- **Sin IDOR / cruce de usuarios:** `BattleSessionService` usa la sesión del usuario autenticado; un `battle_id` ajeno no existe en su sesión.
- **Sin XSS en el log nuevo:** la entrada se escapa con `e($entry)` en `BatallaVistaPresenter::logVista()`.
- **Sin SQL, archivos ni secretos** en la superficie; sin rutas/controladores/migraciones nuevos.
- **CSRF/sesiones:** endpoint Livewire estándar bajo `web`/`auth`; `SESSION_VERSION` en 21 y mínimo 9; sin cambios en serialización.
- **Verificación:** `php -l` 5/5 sin errores; PHPStan sobre los 5 PHP modificados sin errores. No se ejecutaron tests.

## 5. Riesgos residuales

1. **Pestañas abiertas con HTML antiguo:** pulsar «Esperar» produce `MethodNotFoundException` sin mutación. Confirmar `APP_DEBUG=false` en producción y desplegar backend+Blade juntos con `view:clear`.
2. **`preparacionPendiente` y demás propiedades públicas** siguen siendo manipulables (LOW/INFO). Impacto auto-limitado.
3. **`nextActor()` sigue siendo público:** puede reentrar en `avanzar` y consumir acciones de actores propios sin acción legal. No otorga ventaja ni afecta a otros usuarios.
4. **Predicado de disponibilidad sin comprobación de objetivo:** si existiera un movimiento disponible sin objetivo legal, el fallback no se dispararía. No reproducible con los datos actuales.
5. **Dependencias:** 17 avisos preexistentes; ninguno explotable por TASK-039.

## 6. Recomendación final

**Aprobar TASK-039 desde seguridad.** El cambio **reduce** la superficie de ataque (elimina un método público que consumía la acción leyendo `actingRefId`) y el fallback nuevo toma todas sus decisiones de estado de servidor: actor del agregado, movimientos/condiciones del dominio y `objetosCombate` recién sincronizado con el inventario del usuario autenticado. No hay hallazgos que bloqueen.

Antes de cerrar, como endurecimiento no bloqueante: `#[Locked]` en `preparacionPendiente`, `battleId`, `team1`, `team2`, `objetosCombate`, `currentMoves` y `turnQueue`; limpiar el estado de selección/animation en el fallback; y programar la actualización de `laravel/framework`, `livewire/livewire` y Symfony.
