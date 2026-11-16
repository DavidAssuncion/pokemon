# TASK-036 — Informe final

- **ID:** TASK-036
- **Fecha:** 2026-11-09
- **Estado final:** `COMPLETED` — implementación, verificación estática, revisión y seguridad completadas. Smoke manual en navegador pendiente por el desarrollador.
- **Commit local:** `e5ff46f` (`feat(task-036): permitir autoobjetivo de Bucle Arena y derivados`), sin `git push`.
- **Plan:** v1 aprobado («Aprobar plan v1»).
- **Tests:** no ejecutados, no creados, no modificados.

## 1. Resumen

El jugador ya puede pulsar su propio Pokémon en `player_target` y usar **Bucle Arena** (`sand-tomb`), **Giro Fuego** (`fire-spin`) y **Torbellino** (`whirlpool`) sobre sí mismo para obtener su efecto:

- `sand-tomb`: escudo físico (DEF).
- `fire-spin`: escudo especial (DEF.ESP).
- `whirlpool`: Acua Aro 5 turnos con curación por ronda.

La previsualización muestra el efecto de apoyo con la etiqueta “SOBRE SÍ MISMO”, nunca daño. El servidor rechaza cualquier autoataque con un movimiento normal, tanto al confirmar el movimiento como al resolver una acción pendiente manipulada o antigua. `DESAFIO` no redirige el autoobjetivo, pero sigue redirigiendo el mismo movimiento contra un rival. La IA no cambia.

## 2. Archivos modificados

| Archivo | Cambio |
|---|---|
| `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php` | Lista `AUTOOBJETIVO` y `permiteAutoobjetivo()`. |
| `app/Livewire/Presenters/MovesPreviewPresenter.php` | Rama de autoobjetivo, `apoyoEtiqueta` y motivo “No usable sobre sí mismo”. |
| `app/Livewire/Presenters/BatallaVistaPresenter.php` | Flag `actorAutoobjetivoSeleccionable` y carta del actor seleccionable. |
| `app/Livewire/Presenters/PresentadorVistaCombate.php` | Cálculo del flag desde `currentMoves`. |
| `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` | Guarda en `previewTarget()`, `$esAutoobjetivo` y exclusión correcta de `DESAFIO`. |
| `app/Livewire/Combate/Soporte/GestorAccionBatalla.php` | Enrutado a `aplicarMovimientoAliado()` y rechazo de autoataque normal. |
| `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php` | Log “sobre sí mismo”. |
| `resources/views/livewire/partials/moves-panel.blade.php` | Badge desde `apoyoEtiqueta` con fallback. |

## 3. Decisiones

- Alcance congelado a los tres movimientos del catálogo con `target: normal`; los 43 `target: self` no se habilitan por sí solos desde la propia carta.
- Un único predicado de dominio usado por presentación, selección y resolución; el fallo es seguro si la lista y `para()` divergieran.
- Flag de presentación separado; `refsAliado()` y `aliadosSeleccionables` no cambian, por lo que el panel de objetos no se ve afectado.
- `DESAFIO` se excluye solo cuando el objetivo es el actor (`$esAutoobjetivo`), no por la propiedad del movimiento.
- Se permite gastar PP/turno aunque el escudo esté al máximo o Acua Aro ya esté activo, por paridad con el uso sobre aliados.
- Sin cambios en IA, `DTOAccionBatalla`, serialización, `SESSION_VERSION` (21), `BattleSessionService`, migraciones, dependencias ni `Combate.php`.

## 4. Verificación ejecutada

- `php -l` sobre los 7 PHP: sin errores.
- `vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=512M`: 34 errores preexistentes; ninguno en archivos de TASK-036.
- `vendor/bin/phpstan analyse -c phpstan-hardener.neon`: 140 errores de baseline; sin errores nuevos atribuibles a TASK-036.
- `vendor/bin/deptrac analyse --no-progress`: 100 violaciones preexistentes; ninguna en archivos de TASK-036.
- `vendor/bin/pint` sobre los 7 archivos: pass.
- `php artisan view:cache`: correcto.
- `git diff --check`: limpio en los 8 archivos.
- Greps: `permiteAutoobjetivo` en catálogo + 5 consumidores; `actorAutoobjetivoSeleccionable` coherente; `apoyoEtiqueta` en productor y Blade; `SESSION_VERSION` en 21.
- Sin diffs en tests, `composer.json`, `composer.lock` ni migraciones.

**Tests:** no se ejecutaron ni se modificaron.

## 5. Revisión y seguridad

- **Reviewer:** `APPROVED`. CA1–CA15 cumplidos. Hallazgos LOW/NIT no bloqueantes:
  - LOW: los movimientos autodirigidos quedan usables desde la carta propia cuando el actor además tiene uno de los tres movimientos. Se acepta como corolario del flag aprobado; no hay movimientos `target:self` con potencia y el enrutado es el autodirigido existente.
  - NIT: duplicación trivial del bucle de detección, docblocks que aún dicen “aliado vivo”, categoría “Estado” en el preview no usable y parámetro inerte para el equipo rival.
- **Security:** `PASS_WITH_FINDINGS`, sin CRITICAL/HIGH. Hallazgos:
  - MEDIUM preexistente: advisories de dependencias (Livewire, Laravel, Symfony, CommonMark), no introducidos por TASK-036.
  - LOW: la rama de ataque de `resolver()` no reautoriza al actor del pendiente (preexistente, `pendingAction` server-side).
  - LOW: un DTO pendiente forjado con `objetivo=self` podría eludir la guarda; no alcanzable desde el cliente.
  - INFO: el flag de presentación es manipulable pero solo afecta a la UI; `selectMove()` no comprueba fase/estado (preexistente).

## 6. Riesgos residuales

- **Smoke manual pendiente:** pulsar la carta propia con `sand-tomb`, `fire-spin` y `whirlpool`; comprobar DEF/DEF.ESP, Acua Aro 5 turnos y curación por ronda.
- Comprobar que un ataque normal sobre uno mismo se rechaza y que `DESAFIO` no redirige el autoobjetivo.
- Los movimientos autodirigidos también se pueden usar desde la carta propia cuando el actor tiene alguno de los tres; comportamiento aceptado y documentado.
- `AUTOOBJETIVO` es una lista manual: si el catálogo gana movimientos con `target: normal`, hay que añadirlos.
- Deuda preexistente de reautorización del actor en la rama de ataque y de validación del DTO pendiente; no alcanzable desde el cliente.
- Advisories de dependencias; fuera de alcance.

## 7. Próximos pasos

1. Smoke manual del desarrollador en `/combate` con los tres movimientos sobre el propio Pokémon.
2. Verificar visualmente “SOBRE SÍ MISMO”, el rechazo de ataques normales y el comportamiento con `DESAFIO`.
3. Programar en tareas separadas la actualización de dependencias y el endurecimiento de `pendingAction`.
4. No se ha hecho `git push`; el commit `e5ff46f` queda local.
5. Los artefactos de `.opencode/work/TASK-036/` quedan sin seguimiento en Git.
6. Los cambios de TASK-035 en el árbol (`routes/*`, `src/Combate*`, `resources/views/habitats/*`, etc.) son ajenos y no se han tocado.
