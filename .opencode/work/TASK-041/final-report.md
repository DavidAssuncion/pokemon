# TASK-041 — Informe final

- **ID:** TASK-041
- **Fecha:** 2026-11-09
- **Estado final:** `COMPLETED` — implementación, verificación estática, revisión y seguridad completadas. Smoke manual pendiente por el desarrollador.
- **Commit local:** `fe7af82` (`feat(task-041): evaluar prioridades de IA y anadir tooltip debug del rival`), sin `git push`.
- **Plan:** v1 aprobado («Aprobar plan v1»).
- **Tests:** no ejecutados, no creados, no modificados.

## 1. Resumen

### IA

`GestorPrioridadesBatalla::declararIa()` ya no elige el movimiento con mayor prioridad sin evaluar. Ahora:

- limpia prioridad, postura y objetivo cubierto de todos los rivales vivos;
- pre-chequea si hay alguna prioridad disponible;
- decide con `AgregadoBatalla::elegirAccionPara()` (el mismo evaluador que la IA normal: tipo, daño, KO, amenaza, supervivencia, riesgo);
- declara prioridad solo si la mejor acción es un movimiento con `priority > 0`;
- no declara apoyo a aliado (no soportado en la ruta de prioridades);
- mantiene el filtro de Aguante/Protección de TASK-040 y no toca `DESAFIO`.

Contra Fantasma, Garra Metal (Acero) ya no queda desplazada por Ataque Rápido (Normal) solo por prioridad; el evaluador decide y, si Garra Metal es mejor, el flujo normal la ejecuta.

### Tooltip de debug

- Nuevo `DebugCombatientePresenter` con tipos, habilidad base/efectos activos, objeto, 6 stats actual/máx y todos los movimientos (tipo, categoría, potencia, precisión, prioridad, usos/máx, disponibilidad y motivo).
- Gate server-side en `SincronizadorVistaBatalla`: `debug` solo para `team2` y solo con `config('app.debug')`. En producción no se construye ni viaja en el payload de Livewire.
- Tooltip hover-only en la carta rival, panel lateral, `pointer-events: none`, sin JS, sin foco, sin tap y sin bloquear `wire:click`.

## 2. Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php` | Nueva política de prioridades con `elegirAccionPara()`, pre-chequeo y filtro de apoyo. |
| `app/Livewire/Presenters/DebugCombatientePresenter.php` | Nuevo presentador del payload de debug. |
| `app/Livewire/Combate/Soporte/SincronizadorVistaBatalla.php` | Gate `config('app.debug')` y `debug` solo en `team2`. |
| `resources/views/livewire/_pokemon-card.blade.php` | Tooltip de debug hover-only en la carta rival. |
| `resources/css/combate.css` | Estilos del panel lateral, hover-only y `pointer-events: none`. |

## 3. Decisiones

- La IA evalúa con el mismo criterio que el turno normal; no se añade un término de prioridad a la puntuación.
- Se acepta el cambio de balance y la perturbación del RNG global por la evaluación extra de `CalculadoraDanioIA`.
- Se acepta la doble evaluación con pre-chequeo; no hay caché por ronda.
- `DESAFIO` se mantiene sin cambios: el selector ya aplica el objetivo forzado.
- Tooltip superpuesto lateral (variante sobre el `inset: 0` del plan) para evitar el recorte de `.pokemon-card`; se documenta como decisión de implementación.
- Solo hover real; sin foco ni tap. Habilidades mostradas como claves de efectos activos.
- Debug solo con `APP_DEBUG=true`; sin cambios de `SESSION_VERSION` (21), serialización, migraciones, dependencias, JS ni tests.

## 4. Verificación ejecutada

- `php -l` sobre los 3 PHP: sin errores.
- `vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=512M`: 34 errores preexistentes; ninguno en archivos de TASK-041.
- `vendor/bin/pint` sobre los archivos: pass.
- `php artisan view:cache`: correcto.
- `npm run build`: correcto; CSS del tooltip incluido en el bundle.
- `git diff --check`: limpio.
- Greps: único `config('app.debug')` en el sincronizador; `debug` solo en presentador/sincronizador/carta; `SESSION_VERSION` 21; sin `{!! !!}` nuevo.
- Re-revisión del filtro de apoyo: `APPROVED`.

**Tests:** no se ejecutaron ni se modificaron.

## 5. Revisión y seguridad

- **Reviewer:** `APPROVED`. CA-IA-2..10 y CA-DBG-1..9 cumplidos estáticamente; CA-IA-1/7 requieren partida manual. LOW/NIT: variante lateral del tooltip, filtro de apoyo latente (corregido), scroll del panel si excediera `70vh`, comentarios menores.
- **Security:** `PASS_WITH_FINDINGS`, sin CRITICAL/HIGH.
  - **MEDIUM condicional:** con `APP_DEBUG=true` en un entorno accesible a jugadores se envía la build completa del rival (moveset y stats ofensivas) y se habilita la superficie de debug de Laravel. El gate del código es correcto; el riesgo es operativo. El `docker-compose.yml` usa `${APP_DEBUG:-true}` por defecto.
  - LOW: advisories de dependencias preexistentes.
  - INFO: doble evaluación/RNG, exposición parcial preexistente del rival, `team1`/`team2` públicos sin `#[Locked]`.

## 6. Riesgos residuales

- **`APP_DEBUG=true` en producción/compartido** expone la build rival y la página de debug. Forzar `APP_DEBUG=false` y corregir el default del compose. Es la condición operativa principal.
- Cambio de balance de la IA: menos prioridades inefectivas; validar en partida.
- Perturbación del RNG global por `CalculadoraDanioIA::estimar()`: puede alterar críticos/estados; aceptado.
- Doble evaluación de `SelectorAccionIA`; mitigada con pre-chequeo.
- Objetivo de la IA reevaluado en `resolver()`; preexistente.
- Tooltip lateral sin scroll usable si el contenido excediera `70vh` (`pointer-events: none`); no se alcanza con 6 stats + 6 movimientos en viewports normales.
- Exposición parcial preexistente de datos del rival en `CombatienteVista` aun con debug apagado.
- Deuda de dependencias (17 advisories).

## 7. Próximos pasos

1. Smoke manual: Riolu vs Fantasma; comprobar que usa Garra Metal y no Ataque Rápido por prioridad.
2. Con `APP_DEBUG=true`, hover sobre la carta rival: comprobar contenido, panel lateral, sin recorte y clic de selección.
3. Con `APP_DEBUG=false`, inspeccionar payload/DOM: sin `debug` ni `.debug-tooltip`.
4. Probar 2v2/3v3, `DESAFIO`, apoyo y prioridades autodirigidas.
5. Forzar `APP_DEBUG=false` en entornos con jugadores y corregir el default de `docker-compose.yml`; considerar un flag dedicado `battle.debug_tooltip`.
6. Programar actualización de dependencias y hardening de sesiones/propiedades Livewire.
7. No se ha hecho `git push`; el commit `fe7af82` queda local.
8. Los artefactos de `.opencode/work/TASK-041/` quedan sin seguimiento en Git.
9. Los cambios de TASK-035 y otros trabajos en el árbol son ajenos y no se han tocado.
