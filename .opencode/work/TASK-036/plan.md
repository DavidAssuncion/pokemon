# TASK-036 — Plan v1

- **ID:** TASK-036
- **Versión:** v1
- **Fecha:** 2026-11-09
- **Estado:** AWAITING_APPROVAL — no autoriza implementación
- **Base:** rama `task-011`
- **Análisis:** `analysis.md` (COMPLETED)
- **Diseño:** `architecture.md` (COMPLETED)

## 1. Objetivo

Permitir que el jugador use **Bucle Arena** (`sand-tomb`), **Giro Fuego** (`fire-spin`) y **Torbellino** (`whirlpool`) sobre su propio Pokémon activo, obteniendo su efecto de apoyo (escudo físico, escudo especial o Acua Aro), sin que el autoobjetivo se ejecute como ataque normal contra uno mismo.

## 2. Alcance

### 2.1. Incluido

- Predicado de dominio único `CatalogoEfectosAliado::permiteAutoobjetivo()` con lista explícita de los tres slugs.
- Marcar la carta del actor como seleccionable en `player_target`/`player_move` cuando tenga alguno de esos movimientos, mediante un flag de presentación separado (`actorAutoobjetivoSeleccionable`).
- Previsualización de esos movimientos sobre uno mismo como apoyo (escudo / Acua Aro), nunca como daño.
- `selectMove()`: permitir el autoobjetivo solo con el predicado; rechazar el resto; no redirigir por `DESAFIO` cuando el objetivo es el actor.
- `GestorAccionBatalla::resolver()`: enrutar el autoobjetivo a `aplicarMovimientoAliado()` y rechazar en servidor cualquier autoataque no autodirigido/no catalogado.
- Etiqueta “SOBRE SÍ MISMO” en el panel de movimientos.
- Log “sobre sí mismo” al aplicar el efecto.

### 2.2. Fuera de alcance

- Los 43 movimientos del catálogo con `target: self` (`recover`, `swords-dance`, etc.): siguen funcionando por la ruta autodirigida; no se habilitan desde la propia carta.
- Los 21 movimientos aliados fuera del catálogo (`allySide`, `allies`, `adjacentAlly`, `allyTeam`, `adjacentAllyOrSelf`).
- Autoobjetivo de la IA: se mantiene intacta.
- Cambios en daño, estado `partiallytrapped`, fórmulas, economía o balance.
- Cambios en `DTOAccionBatalla`, serialización, `SESSION_VERSION`, `BattleSessionService`, migraciones, dependencias o tests.
- Cambio de animación/banner: el autoobjetivo se comporta como los movimientos autodirigidos (sin banner atacante → defensor).

## 3. Arquitectura y contratos

- **Dominio:** `CatalogoEfectosAliado::AUTOOBJETIVO = ['sand-tomb', 'fire-spin', 'whirlpool']` y `permiteAutoobjetivo(string $slug): bool`. Es el único predicado; lo usan presentación, selección y resolución.
- **Presentación:** `refsAliado()` no cambia (sigue siendo “aliados distintos del actor”). `actorAutoobjetivoSeleccionable` es un flag interno de los arrays de vista, no una propiedad pública de `Combate`.
- **Selección:** `$esAutoobjetivo` exige movimiento catalogado **y** objetivo = actor. `DESAFIO` solo se excluye en ese caso; contra un rival sigue redirigiendo.
- **Resolución:** `$autoobjetivo` exige `defender === actor`, movimiento no autodirigido y predicado verdadero. Con `$efectoAliado` se enruta a `aplicarMovimientoAliado()`; si no, se rechaza el autoataque.
- **Contrato Livewire:** sin propiedades ni métodos nuevos en `Combate` (34/18).
- **Persistencia:** sin cambios. El `DTOAccionBatalla` ya transporta `actorId` y `defenderId`.

## 4. Archivos y responsables

### 4.1. Backend (`backend`)

| Orden | Archivo | Cambio |
|---|---|---|
| B1 | `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php` | `AUTOOBJETIVO` + `permiteAutoobjetivo()`. |
| B2 | `app/Livewire/Presenters/MovesPreviewPresenter.php` | Rama de autoobjetivo, `apoyoEtiqueta`, motivo “No usable sobre sí mismo”; conservar autodirigidos. |
| B3 | `app/Livewire/Presenters/BatallaVistaPresenter.php` | Parámetro `actorAutoobjetivoSeleccionable`, paso a `marcarInteraccion()` y condición de la carta del actor. |
| B4 | `app/Livewire/Presenters/PresentadorVistaCombate.php` | Calcular el flag desde `currentMoves` y pasarlo a `BatallaVistaPresenter::toView()`. |
| B5 | `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` | Guarda en `previewTarget()`, `actorAdmiteAutoobjetivo()`, `$esAutoobjetivo` en `selectMove()` y `DESAFIO` corregido. |
| B6 | `app/Livewire/Combate/Soporte/GestorAccionBatalla.php` | `$autoobjetivo`, enrutado a `aplicarMovimientoAliado()` y rechazo de autoataque. |
| B7 | `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php` | Log “sobre sí mismo” en `aplicarMovimientoAliado()`. |

### 4.2. Frontend (`frontend`)

| Orden | Archivo | Cambio |
|---|---|---|
| F1 | `resources/views/livewire/partials/moves-panel.blade.php` | Texto del badge desde `{{ $move['apoyoEtiqueta'] ?? 'SOBRE ALIADO' }}`. |

### 4.3. Sin cambios

- `resources/views/livewire/_pokemon-card.blade.php`, `items-panel.blade.php` (solo verificación).
- `EstadoAnimacionBatalla.php`, `EjecutorEfectoAliado.php`, `EfectoAliado`, `TipoEfectoAliado`, `MovimientoBatalla.php`, `AgregadoBatalla.php`.
- `SelectorAccionIA.php`, `EvaluadorSoporteIA.php`.
- `DTOAccionBatalla.php`, `BattleSessionService.php`, `SESSION_VERSION`, `Combate.php`, `storage/data/moves.json`.
- Migraciones, tests, dependencias, `bugs.txt`, `TASK-032/`, `TASK-033/`, `TASK-034/`, `TASK-035/`.

## 5. Orden de ejecución y dependencias

1. **B1** — predicado de dominio.
2. **B2** — previsualización.
3. **B3 + B4** — flag de presentación y carta seleccionable (coordinados).
4. **B5** — selección y `DESAFIO`.
5. **B6** — resolución y defensa en profundidad.
6. **B7** — log.
7. **F1** — etiqueta.
8. Verificación estática y manual.
9. Revisión independiente y seguridad.
10. Informe final.

B5 y B6 dependen de B1. B3 y B4 comparten firma. B2 es independiente de B3/B4. F1 depende de B2. No hay escritura paralela sobre el mismo archivo.

## 6. Criterios de aceptación

- **CA1:** la carta del actor es seleccionable solo si tiene `sand-tomb`, `fire-spin` o `whirlpool`; no con `recover`/`swords-dance` únicamente.
- **CA2:** al seleccionar al actor, los tres movimientos muestran apoyo (`modoAliado`, `SOBRE SÍ MISMO`, daño 0) y los normales aparecen no usables con “No usable sobre sí mismo”.
- **CA3:** `sand-tomb` sobre el actor sube DEF hasta el máximo, sin daño ni `partiallytrapped`, consumiendo PP/turno.
- **CA4:** `fire-spin` sobre el actor sube DEF.ESP hasta el máximo, sin daño ni `partiallytrapped`.
- **CA5:** `whirlpool` sobre el actor aplica/refresca Acua Aro 5 turnos con curación del 5 % del HP máximo por ronda.
- **CA6:** un movimiento normal con `defenderId === actorId` se rechaza en `selectMove()` con “Objetivo no válido.”.
- **CA7:** una `pendingAction` manipulada/antigua con `defenderId === actorId` y movimiento normal se rechaza en `resolver()` sin daño.
- **CA8:** con `DESAFIO`, el autoobjetivo no se redirige; el mismo movimiento contra un rival sí.
- **CA9:** el soporte sobre otro aliado y sobre retaguardia no cambia.
- **CA10:** los tres movimientos contra rivales siguen aplicando daño y `partiallytrapped`.
- **CA11:** el panel de objetos no cambia (`aliadosSeleccionables` falso en solitario).
- **CA12:** la IA no genera autoobjetivos.
- **CA13:** `SESSION_VERSION` sigue en 21; sesiones antiguas cargan.
- **CA14:** posturas, silencio, PP, carga y objeto de elección siguen bloqueando igual.
- **CA15:** etiqueta “SOBRE ALIADO” para aliado y “SOBRE SÍ MISMO” para autoobjetivo.

## 7. Verificación requerida

### 7.1. Estática

Desde `/home/david/pokemon`:

```bash
php -l src/Battle/Domain/Catalog/CatalogoEfectosAliado.php
php -l app/Livewire/Presenters/MovesPreviewPresenter.php
php -l app/Livewire/Presenters/BatallaVistaPresenter.php
php -l app/Livewire/Presenters/PresentadorVistaCombate.php
php -l app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php
php -l app/Livewire/Combate/Soporte/GestorAccionBatalla.php
php -l app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php

vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=512M
vendor/bin/phpstan analyse -c phpstan-hardener.neon --no-progress --memory-limit=512M
vendor/bin/deptrac analyse --no-progress
vendor/bin/pint --dirty --format agent
php artisan view:cache
```

Greps de control:

```bash
grep -R "permiteAutoobjetivo" src app
grep -R "actorAutoobjetivoSeleccionable" app
grep -R "apoyoEtiqueta" app resources
grep -n "SESSION_VERSION" app/Support/BattleSessionService.php
git diff -- tests composer.json composer.lock database/migrations
git status --short
```

Resultados esperados: predicado único en el catálogo y sus cinco consumidores; `SESSION_VERSION` en 21; sin diffs en tests, dependencias ni migraciones; solo los archivos autorizados.

### 7.2. Manual

1. `sand-tomb` sobre el propio Pokémon: carta seleccionable, preview de escudo, aplicación sin daño, PP/turno consumidos.
2. `fire-spin` sobre el propio Pokémon: DEF.ESP.
3. `whirlpool` sobre el propio Pokémon: Acua Aro 5 turnos, curación por ronda, refresco al reutilizar.
4. Movimiento normal sobre uno mismo: “Objetivo no válido.”, sin daño.
5. `pendingAction` manipulada con `defenderId === actorId`: rechazo sin daño.
6. `DESAFIO`: autoobjetivo no redirigido; contra rival sí.
7. Soporte sobre aliado y retaguardia: sin cambios.
8. IA: sin autoobjetivos.
9. Sesión antigua: carga correctamente.
10. `Cubrir`/`Guardia`, silencio, sin PP y objeto de elección: bloqueos actuales.
11. Panel de objetos: sin aviso de aliado en solitario.
12. Etiqueta “SOBRE SÍ MISMO” en el panel de movimientos.

**Tests:** no se crean, no se modifican y no se ejecutan.

## 8. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Relajar solo la presentación y autoinfligir daño/`partiallytrapped`. | Validación en `selectMove()` y defensa en profundidad en `resolver()`. |
| Cliente manipula `selectedTargetTeam/Idx`. | El servidor revalida con el estado del agregado; no confía en `currentMoves`. |
| `pendingAction` inconsistente con `defenderId === actorId`. | Rechazo explícito antes de `aplicarMovimiento()`; consume la acción para no bloquear. |
| `DESAFIO` redirige el autoobjetivo. | `$esAutoobjetivo` se excluye de la redirección; contra rival se mantiene. |
| `MovesPreviewPresenter` muestra daño contra uno mismo. | Rama de autoobjetivo y bloqueo explícito de movimientos no usables. |
| `AUTOOBJETIVO` diverge del catálogo futuro. | Lista explícita documentada; se revisa al añadir movimientos con `target normal`. |
| Regresión en panel de objetos o targeting rival. | No se cambia `refsAliado` ni `aliadosSeleccionables`; verificación manual. |
| Log “usa X sobre X”. | Condicional “sobre sí mismo”. |
| IA cambia de comportamiento. | No se toca el dominio de IA; se verifica manualmente. |

## 9. Rollback

- Revertir el commit de implementación de TASK-036.
- No hay migraciones, cambios de esquema, `SESSION_VERSION` ni datos persistidos que deshacer.
- Las sesiones y acciones pendientes anteriores siguen funcionando; la guarda nueva solo rechaza autoataques inválidos.

## 10. Decisiones que autoriza la aprobación del plan v1

1. Alcance limitado a `sand-tomb`, `fire-spin` y `whirlpool`.
2. Permitir el autoobjetivo bajo `DESAFIO` sin redirección.
3. Etiqueta “SOBRE SÍ MISMO” en el panel de movimientos.
4. Permitir gastar PP/turno aunque el escudo esté al máximo o Acua Aro ya esté activo.
5. Usar el flag separado `actorAutoobjetivoSeleccionable` (no incluir al actor en `refsAliado`).
6. Cambiar el log a “sobre sí mismo” en `ResolutorAccionBatalla`.
7. Mantener la IA intacta, sin autoobjetivo.
8. Sin cambios en `SESSION_VERSION`, `DTOAccionBatalla`, serialización, migraciones, tests ni dependencias.
