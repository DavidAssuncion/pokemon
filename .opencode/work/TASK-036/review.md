# TASK-036 — Revisión

- **Estado:** APPROVED
- **Revisor:** `reviewer` (sesión `ses_e40418f6dffegPCR6hq3IVYHp5`)
- **Nota:** resultado del revisor, sin alterar sus conclusiones.

---

# Revisión — TASK-036

## 1. Veredicto

```text
APPROVED
```

## 2. Resumen

Revisión independiente del plan v1 de TASK-036 ya implementado sobre la rama `task-011`. Se auditó el diff completo de los 8 archivos autorizados (7 PHP + 1 Blade), contrastándolo con `request.md`, `analysis.md`, `architecture.md`, `plan.md`, `approval.md` y `execution.md`.

Resultado: la implementación se ajusta al diseño aprobado (Opción B.2.b), no sale del alcance de forma funcionalmente relevante y mantiene la invariante de seguridad exigida: un movimiento normal con `defenderId === actorId` no puede llegar a `aplicarMovimiento()` ni desde `selectMove()` ni desde una `pendingAction` manipulada/antigua, porque `resolver()` rechaza explícitamente antes de la rama normal y solo enruta a `aplicarMovimientoAliado()` cuando el predicado de dominio es verdadero.

Verificación estática: `php -l` 7/7 correcto; PHPStan (`phpstan.neon`) sin errores en los archivos de TASK-036 (los 34 errores son baseline preexistente en otros archivos); Deptrac sin violaciones atribuibles a los archivos tocados (las 100 violaciones son preexistentes, ninguna menciona archivos de TASK-036); `git diff` sin cambios en `tests`, `composer.*`, migraciones, `Combate.php`, `DTOAccionBatalla`, `BattleSessionService`, IA ni `SESSION_VERSION` (=21).

Los cambios ajenos de TASK-035 y preexistentes (`ReclutadoSerializer`, `routes/*`, `src/Combate*`, `src/Gimnasios*`, `src/Pokemon*`, `src/Shared/Domain/Pc/`, `app/Support/Pc/`, vistas de gimnasios/habitats/comparación, `bugs.txt`) se excluyeron del análisis.

No hay hallazgos CRITICAL ni HIGH. No hay MEDIUM. Solo observaciones LOW/NIT no bloqueantes.

## 3. Hallazgos

- **[LOW] `app/Livewire/Presenters/MovesPreviewPresenter.php:139` y `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php:173-174` — Los movimientos autodirigidos quedan usables desde la propia carta cuando el actor además tiene uno de los tres movimientos autoobjetivables.**
  - Evidencia: `MovesPreviewPresenter::previewDe()` deja caer los autodirigidos por la “ruta normal” cuando `target === actor` (línea 139, condición `$sobreSiMismo && ! $move->esAutodirigido()`), por lo que `recover`/`swords-dance` se muestran con `disponible` real; y `selectMove()` enruta `$selfTargeting` con `$defender = $actor` (líneas 173-174). El plan §2.2 decía literalmente que los 43 movimientos `self` “no se habilitan desde la propia carta”.
  - Impacto: desviación menor de alcance, no de seguridad ni de datos. El enrutado es el autodirigido de siempre (`$objetivo = $actor` en `GestorAccionBatalla.php:242-244`) y no existen movimientos `target:self` con potencia (verificado sobre `storage/data/moves.json`: 0 de 78 `self` con `base_power > 0`), así que no hay autoataque. Además el plan pedía explícitamente “autodirigidos sin cambios”, lo que hace este efecto un corolario del flag B.2.b aprobado.
  - Recomendación: aceptarlo y dejarlo documentado en el informe final; si se quisiera strict-scope, habría que deshabilitar en `previewDe()` los autodirigidos cuando `target === actor` (cambio de frontend/backend, no necesario).

- **[NIT] `app/Livewire/Presenters/PresentadorVistaCombate.php:71-80` y `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php:309-319` — Duplicación del bucle de detección de autoobjetivo.**
  - Evidencia: dos `actorAdmiteAutoobjetivo()` con la misma forma (`foreach` + `permiteAutoobjetivo`), uno sobre `currentMoves` (presentación) y otro sobre `pokemon()->moves()` (servidor).
  - Impacto: duplicidad trivial; las fuentes de datos son distintas a propósito (estado de vista manipulable vs verdad de dominio) y no hay riesgo de divergencia porque el predicado es el mismo.
  - Recomendación: opcional; se podría añadir al catálogo un helper `algunaPermiteAutoobjetivo(iterable $slugs)` si se quiere una única fuente del bucle. No requerido.

- **[NIT] `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php:10-16`, `app/Livewire/Presenters/MovesPreviewPresenter.php:239-243` y `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php:150-154` — Docblocks desactualizados.**
  - Evidencia: siguen describiendo el catálogo/ejecutor como “sobre un aliado vivo”, aunque tres slugs ya admiten autoobjetivo (la nueva constante sí lo documenta en `:19-29`).
  - Impacto: solo documental.
  - Recomendación: ajustar el wording a “aliado vivo o el propio actor, según `permiteAutoobjetivo()`” en una limpieza posterior.

- **[NIT] `app/Livewire/Presenters/MovesPreviewPresenter.php:285-305` + `resources/views/livewire/partials/moves-panel.blade.php:50` — El preview “No usable sobre sí mismo” de un movimiento físico/especial se pinta con categoría “Estado”.**
  - Evidencia: `previewAliadoNoUsable()` reutiliza `previewAliadoBase()`, que fija `'categoria' => CategoriaMovimiento::ESTADO` (línea 288/302). Es el mismo comportamiento preexistente para “no usable sobre un aliado”; ahora también visible al pulsar la carta propia.
  - Impacto: cosmético; la tarjeta está deshabilitada y muestra el motivo correcto. No afecta a daño ni a ejecución.
  - Recomendación: opcional; conservar la categoría real en una mejora de UX.

- **[NIT] `app/Livewire/Presenters/BatallaVistaPresenter.php:79-90` — `actorAutoobjetivoSeleccionable` se pasa también a `marcarInteraccion()` del equipo rival.**
  - Evidencia: la condición de la cláusula nueva exige `$teamIdx === 0` (`:190-193`), por lo que el parámetro es inerte para `team2`.
  - Impacto: ninguno. Recomendación: opcional, pasar solo al equipo del jugador.

## 4. Criterios CA1–CA15

| CA | Estado | Evidencia |
|---|---|---|
| CA1 | Cumplido | La carta del actor se marca `selectable` solo si `currentMoves` contiene `sand-tomb`/`fire-spin`/`whirlpool` (`PresentadorVistaCombate.php:21-22,71-80`) y solo en `player_target`/`player_move` (`BatallaVistaPresenter.php:64-65`). `recover`/`swords-dance` solos no activan el flag. |
| CA2 | Cumplido (estático) | Autoobjetivo catalogado → `previewAliado()` con `modoAliado=true`, `daño=0`, `apoyoEtiqueta='SOBRE SÍ MISMO'`; movimiento normal sobre sí mismo → `previewAliadoNoUsable(..., 'No usable sobre sí mismo')` con `disponible=false` y `daño=0`; Blade no pinta fila de daño y deshabilita la tarjeta. |
| CA3 | Cumplido por código | `resolver()` enruta a `aplicarMovimientoAliado()`, que no aplica `secondaries` y consume PP/turno. `ESCUDO_FISICO` aplica `min(max, actual+incremento)`. |
| CA4 | Cumplido por código | Misma ruta con `ESCUDO_ESPECIAL`. |
| CA5 | Cumplido por código | `AQUA_RING` usa lanzador y aliado (coincidentes) y `reemplazarCondicion()` refresca 5 turnos. |
| CA6 | Cumplido | `selectMove()` rechaza “Objetivo no válido.” si el objetivo es el actor, no es autodirigido y no es `$esAutoobjetivo`. |
| CA7 | Cumplido | Guarda en `resolver()` antes de la rama normal: limpia pendiente, consume acción, sincroniza y avanza. Un ataque normal nunca llega a `aplicarMovimiento()` con atacante=defensor. |
| CA8 | Cumplido | La redirección por `DESAFIO` excluye `$esAutoobjetivo` (objetivo = actor), no la propiedad del movimiento; contra rival sigue redirigiendo. |
| CA9 | Cumplido | `refsAliado()` y `aliadosSeleccionables` no cambian; el bloque aliado queda intacto. |
| CA10 | Cumplido | Contra rival: rama normal con daño y `secondaries`. |
| CA11 | Cumplido | `items-panel.blade.php:37` sigue leyendo `aliadosSeleccionables`; la cláusula nueva está fuera de la rama `$faseItem` y el flag se anula en fases de objeto. |
| CA12 | Cumplido | `SelectorAccionIA`, `EvaluadorSoporteIA` y `actorAdmiteSoporte()` sin cambios; el autoobjetivo solo se genera desde `selectMove()` del jugador. |
| CA13 | Cumplido | `SESSION_VERSION` sigue en 21; sin cambios en DTO ni serialización. |
| CA14 | Cumplido | `movimientoDisponible()` y el chequeo de postura se mantienen antes del código nuevo. |
| CA15 | Cumplido | `previewAliado()` emite “SOBRE SÍ MISMO” vs “SOBRE ALIADO”; Blade usa `{{ $move['apoyoEtiqueta'] ?? 'SOBRE ALIADO' }}` con fallback. |

## 5. Fortalezas

- Predicado único de dominio, lista explícita y subconjunto garantizado de `para()`; el diseño es *safe-by-default*.
- Defensa en profundidad real: presentación (flag), selección (`previewTarget`/`selectMove`) y resolución (`resolver`) revalidan con el estado del servidor; `currentMoves` manipulable solo afecta a la pintura.
- Orden de guardas correcto en `resolver()` (aliado inválido → autoataque inválido → enrutado), con rechazo que consume la acción para no bloquear el turno.
- Corrección arquitectónica respecto al análisis: `DESAFIO` se excluye por `$esAutoobjetivo` (objetivo=actor), no por la propiedad del movimiento.
- Cambio quirúrgico en Blade, sin duplicar el badge ni introducir lógica.

## 6. Riesgos residuales

- `resolver()` sigue sin reautorizar al actor del jugador (preexistente). La guarda nueva reduce el impacto de pendientes inconsistentes, pero no sustituye una reautorización explícita.
- Un `pendingAction` con `defenderId === actorId` y un movimiento autodirigido sigue la ruta normal; es el comportamiento preexistente e intencional. No existe ningún movimiento `target:self` con potencia (0/78), así que no hay autoataque explotable.
- `AUTOOBJETIVO` es una lista mantenida a mano: si se añade otro movimiento con `target normal`, hay que incorporarlo. Documentado en el docblock.
- La verificación funcional de CA2–CA5 (escudos, Acua Aro, refresco) es estática; requiere la prueba manual del plan §7.2.

## 7. Recomendación final

**APPROVED.** No hay correcciones obligatorias ni especialista requerido.

Seguimiento opcional (no bloqueante): documentar en el informe final el efecto colateral LOW de que los autodirigidos quedan usables desde la carta propia cuando el actor tiene uno de los tres movimientos, y actualizar los docblocks que aún hablan solo de “aliado vivo”. Si se desea estricto cumplimiento literal del plan §2.2, encargar a `backend`/`frontend` deshabilitar los autodirigidos cuando `target === actor`; no es necesario para los criterios de aceptación ni para la seguridad.
