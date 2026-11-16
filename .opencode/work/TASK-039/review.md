# TASK-039 — Revisión

- **Estado:** APPROVED
- **Revisor:** `reviewer` (sesión `ses_e3ff7a347ffe7uKgegTTPVEBfH`)
- **Nota:** resultado del revisor, sin alterar sus conclusiones.

---

# Revisión independiente — TASK-039 (plan v1)

## 1. Veredicto

**APPROVED**

Sin blockers, sin HIGH y sin MEDIUM. Quedan solo observaciones LOW/NIT, ninguna bloqueante.

## 2. Resumen

Se contrastaron el plan v1, la aprobación, el diseño y el diff real contra `HEAD` de los 6 archivos autorizados:

- `Combate::pasarTurno()`, `OrquestadorBatalla::pasarTurno()` y `GestorSeleccionBatalla::pasarTurno()` eliminados; `Combate` expone **17** métodos públicos.
- `mostrarEsperar` eliminado del presentador y el bloque del botón «Esperar» eliminado de `turn-bar.blade.php`; 0 referencias en `app/`, `src/`, `resources/` y `tests/`.
- Fallback añadido en `GestorCicloTurnosBatalla::avanzar`, en la rama del jugador, **después** de `gestorVista->sincronizar()` y **antes** de `currentMoves`/`phase`; exige `! preparacionPendiente` y `sinAccionLegal($actor, $componente->objetosCombate)`.
- Orden de persistencia correcto: log → `consumeAction` → `selectedMoveIdx=null` → `processing=false` → `guardar` → `nextActor` → `return`. Sin doble consumo ni recursión infinita.
- `sinAccionLegal` usa `movimientoDisponible()` del dominio y el flag `aplicable` de la proyección ya sincronizada, el mismo que deshabilita los botones en `items-panel.blade.php`.
- `SESSION_VERSION` sigue en 21; sin cambios en `BattleSessionService`, serialización, tests, dependencias ni migraciones.
- Verificación estática: `php -l` 5/5; PHPStan sin errores en los 6 archivos (los 34 errores del proyecto están en archivos ajenos).
- Diff de TASK-039: 6 archivos, +38/−61, exactamente los autorizados.

## 3. Hallazgos

### CRITICAL / HIGH / MEDIUM

Ninguno.

### LOW

- **`GestorCicloTurnosBatalla.php:155-162` — el fallback no limpia el estado de selección de objetivo.**
  - Evidencia: el método eliminado `GestorSeleccionBatalla::pasarTurno()` llamaba a `gestorAnimacion->reiniciar($componente)`, que resetea `selectedTargetTeam`, `selectedTargetIdx`, `selectedTargetRefId`, `objetoSeleccionado` y `objetivosItem` (`GestorAnimacionBatalla.php:55-67`). La nueva rama solo hace `selectedMoveIdx = null`.
  - Impacto: estético/UX y en combinaciones raras (seleccionar objetivo → «Retrasar turno»/«Atacar con X» → siguiente actor auto-saltado). Puede quedar un resaltado de objetivo obsoleto y el botón «Cambiar objetivo» visible sin haber elegido objetivo en el turno actual. No hay corrupción de estado ni consumo incorrecto.
  - Recomendación: opcional; resetear el estado de selección en la rama del fallback. No necesario para aceptar el cambio.

### NIT

- `instanceof MovimientoBatalla` redundante en `sinAccionLegal`; se mantiene por consistencia con otros métodos.
- Orden de log/consumo semánticamente equivalente al plan.

## 4. Criterios CA1–CA11

| CA | Estado | Evidencia |
|---|---|---|
| CA1 | Cumplido | `grep "Esperar"` solo deja `Esperando...` (estado vacío de la cola). |
| CA2 | Cumplido | `mostrarEsperar` eliminado; 0 referencias. |
| CA3 | Cumplido | 17 métodos públicos; 0 `pasarTurno` en la cadena. |
| CA4 | Cumplido por inspección estática | Método inexistente → `MethodNotFoundException` sin mutar estado. |
| CA5 | Cumplido | Preparación/Retrasar/Atacar con X/Registro, `cancelTarget`, objetos y preparación intactos. |
| CA6 | Cumplido por inspección estática | Log exacto, consumo, guardado y `nextActor`. |
| CA7 | Cumplido | `sinAccionLegal` devuelve `false` ante cualquier movimiento disponible o `aplicable === true`. |
| CA8 | Cumplido | Condición `! preparacionPendiente` y consumo previo de postura. |
| CA9 | Cumplido por inspección estática | Un `consumeAction` por pasada, guardado antes de `nextActor`, conjunto finito. |
| CA10 | Cumplido | `SESSION_VERSION = 21`; sin cambios de serialización. |
| CA11 | Cumplido | Solo los 6 archivos autorizados; sin tests, dependencias ni migraciones. |

## 5. Riesgos residuales

1. Detección basada en «movimiento disponible», no en «objetivo legal»; grieta teórica LOW.
2. `nextActor` sigue siendo público (preexistente).
3. Fricción de preparación: el jugador debe abrir «Preparación» y pulsar «Listo»/«Saltar» cuando el fallback se difiere.
4. IA sin objetivo/movimiento: `prepararAnimacionIa` puede retornar sin consumir (preexistente, no TASK-039).
5. Sin verificación runtime (tests/manual fuera del flujo); CA4/CA6/CA8/CA9 solo por inspección estática.
6. Cambios ajenos de TASK-035/038 en el árbol, no revisados.

## 6. Recomendación final

**Aprobar la implementación de TASK-039 (plan v1).** Cumple CA1–CA11; la eliminación del botón, de `mostrarEsperar` y de la cadena pública `pasarTurno` es completa, y el fallback está correctamente ubicado, condicionado y persistido sin doble consumo. Las observaciones LOW/NIT son opcionales; la única corrección sugerida es resetear el estado de selección en la rama del fallback (`backend`).
