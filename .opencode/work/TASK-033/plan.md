# TASK-033 — Plan v1

- **ID:** TASK-033
- **Versión:** v1
- **Fecha:** 2026-11-09
- **Estado:** AWAITING_APPROVAL — no autoriza implementación
- **Base:** rama `task-011`
- **Análisis:** `analysis.md` (COMPLETED)
- **Diseño:** `architecture.md` (COMPLETED)

## 1. Objetivo

Corregir tres fallos de selección de objetivo en el combate:

1. Los movimientos de apoyo (Danza Espada y el resto del catálogo aliado) deben poder dirigirse a cualquier aliado vivo, incluido uno en retaguardia.
2. En la preparación de ronda debe poder elegirse el objetivo de un ataque de prioridad.
3. En la preparación de ronda debe poder elegirse a quién cubre la postura `Cubrir`.

## 2. Alcance

### 2.1. Incluido

- **Fallo 1:** eliminar el filtro de retaguardia aliada solo en el camino del jugador:
  - `BatallaVistaPresenter::refsAliado()`.
  - `GestorSeleccionBatalla::previewTarget()`.
  - `GestorSeleccionBatalla::selectMove()`.
- **Fallo 2:** objetivo de prioridad en la preparación:
  - Nueva propiedad `$prioridadesObjetivo` (`refId actor => refId rival`, `''` = Automático).
  - Ampliar `$prioridadesDisponibles` con `autodirigido`, `objetivoForzadoRefId`, `objetivoForzadoNombre` y `objetivos` legales.
  - Validar en `GestorPreparacionBatalla::aplicar()` y revalidar en `GestorPrioridadesBatalla::resolver()`.
  - `DESAFIO` prevalece sobre la elección del jugador, con aviso en UI.
  - Movimientos autodirigidos (`protect`, `detect`, `endure`) no piden objetivo.
- **Fallo 3:** objetivo de Cubrir en la preparación:
  - Nueva propiedad `$coberturas` (`refId cubridor => refId aliado cubierto`, `''` = Automático).
  - Selector visible solo con postura `Cubrir`.
  - Validación en `aplicar()` (mismo equipo, vivo, distinto de sí mismo) y fallback con `normalizarCubridores()`.
  - Una prioridad fuerza `ATACAR` y limpia la cobertura.
- **Soporte de dominio:** nuevo método de lectura `AgregadoBatalla::objetivosRivalesLegalesPara(Combatiente): CombatientesCollection` para no duplicar la regla de fila en Livewire.

### 2.2. Fuera de alcance

- Alinear la IA con el apoyo a retaguardia: la IA conserva su restricción actual. Tarea separada si se desea.
- Persistir la cobertura en la pantalla de Equipo (`teams.posturas`, migraciones o nuevo esquema).
- Reasignación inmediata de Cubrir al morir el objetivo cubierto: se mantiene la reasignación en la siguiente ronda.
- Prioridades de apoyo: hoy ningún movimiento aliado tiene `priority > 0`; no se diseña el enrutado a `aplicarMovimientoAliado()`.
- Cambios de fórmulas, economía, balance, IA, `SESSION_VERSION`, `BattleSessionService`, serialización de dominio, migraciones, dependencias o tests.
- Persistir el objetivo de prioridad en el dominio: la resolución es inmediata en la misma petición.
- Endurecer la validación de fila rival en servidor más allá de lo existente.

## 3. Arquitectura y contratos

- **Contrato Livewire:** se añaden 2 propiedades públicas (`$prioridadesObjetivo`, `$coberturas`). El contrato pasa de 32 propiedades/18 métodos a **34 propiedades/18 métodos**.
- **Sin métodos públicos nuevos.**
- **`$prioridadesDisponibles`:** se mantienen las claves existentes y se añaden las nuevas; los snapshots antiguos se toleran con `??` en Blade.
- **Dominio:** se añade una lectura pura `objetivosRivalesLegalesPara()`; no se modifica `actorAdmiteSoporte()`, `SelectorAccionIA`, `EquipoBatalla::normalizarCubridores()` ni `AplicadorPosturaDanio`.
- **Persistencia:** sin cambios. Los mapas viven en el componente Livewire.
- **Blade:** solo presentación; las listas legales se construyen en servidor.
- **`wire:model.live`:** se usa solo en el select de movimiento de prioridad para que el panel reaccione al cambio de movimiento; objetivo y cobertura usan `wire:model` diferido.

## 4. Archivos y responsables

### 4.1. Backend (`backend`)

| Orden | Archivo | Cambio |
|---|---|---|
| B1 | `app/Livewire/Combate.php` | Añadir `$prioridadesObjetivo` y `$coberturas`; ampliar docblock de `$prioridadesDisponibles`. |
| B2 | `src/Battle/Domain/AgregadoBatalla.php` | Añadir `objetivosRivalesLegalesPara()` (lectura pura). |
| B3 | `app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php` | Inicializar/limpiar mapas; ampliar `prioridadesDisponibles`; sincronizar coberturas; validar prioridad y cobertura en `aplicar()`; helpers privados. |
| B4 | `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php` | Leer objetivo declarado; `objetivoPara()` con autodirigido, `DESAFIO`, declarado legal y fallback IA; revalidar vivo. |
| B5 | `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` | Eliminar los dos bloqueos de retaguardia aliada. |
| B6 | `app/Livewire/Presenters/BatallaVistaPresenter.php` | Eliminar el filtro de retaguardia en `refsAliado()`; retirar `tieneVanguardiaViva()` si queda sin uso. |

### 4.2. Frontend (`frontend`)

| Orden | Archivo | Cambio |
|---|---|---|
| F1 | `resources/views/livewire/partials/preparacion-panel.blade.php` | Selector de objetivo de prioridad (condicional a `autodirigido`/`DESAFIO`), selector de aliado cubierto (postura `Cubrir` y sin prioridad), avisos, `??` defensivos y `wire:key`. |

### 4.3. Sin cambios

- `resources/views/livewire/combate.blade.php` (lo usa TASK-034; TASK-033 no lo toca).
- `resources/views/livewire/partials/preparacion-modal.blade.php`.
- `src/Battle/Domain/EquipoBatalla.php`, `Combatiente.php`, `PosturaCombate.php`, `CatalogoEfectosAliado.php`, `AplicadorPosturaDanio.php`, `SelectorAccionIA.php`, `actorAdmiteSoporte()`.
- `src/Battle/Domain/ValueObjects/EstadoTurno.php`, `TurnoCombatiente.php`, `SerializadorCombatiente.php`.
- `app/Support/BattleSessionService.php`, `SESSION_VERSION`.
- `tests/`, dependencias, migraciones, `bugs.txt`, `TASK-032/`.

## 5. Orden de ejecución y dependencias

1. **B1 + B2** — contrato del componente y lectura de dominio.
2. **B3 + B4** — preparación y resolución (dependen de B1/B2).
3. **B5 + B6** — fallo 1 (independientes; pueden ir en paralelo con B3/B4).
4. **F1** — UI (depende de B1 y B3).
5. **Verificación estática** — ver §7.1.
6. **Revisión** — `reviewer`; `security` si aplica.
7. **Informe final** — `final-report.md`.

Los archivos de backend son disjuntos entre sí. F1 no toca backend. No hay escritura paralela sobre el mismo archivo. Si TASK-034 se aprueba e implementa a la vez, se secuenciará la escritura de archivos compartidos; en esta tarea el único archivo potencialmente compartido es `app/Livewire/Combate.php`, que TASK-034 no necesita modificar para su alcance previsible.

## 6. Criterios de aceptación

### Fallo 1

- **CA1:** con `swords-dance` y un aliado vivo en retaguardia, la carta del aliado es seleccionable.
- **CA2:** `previewTarget()` y `selectMove()` aceptan al aliado en retaguardia y crean la acción con `defenderId` = aliado.
- **CA3:** el buff se aplica al aliado elegido vía `aplicarMovimientoAliado()`.
- **CA4:** el targeting rival conserva la regla de fila (vanguardia rival viva bloquea su retaguardia).
- **CA5:** la IA conserva su restricción de apoyo a retaguardia.

### Fallo 2

- **CA6:** al elegir una prioridad no autodirigida aparece selector con “Automático” y rivales legales.
- **CA7:** el rival elegido recibe el ataque de prioridad.
- **CA8:** “Automático” reproduce el comportamiento actual.
- **CA9:** `protect`/`detect`/`endure` no piden objetivo.
- **CA10:** objetivo ilegal, muerto, aliado o manipulado se limpia y no se usa.
- **CA11:** si el objetivo muere por una prioridad anterior, se revalida y nunca se golpea a un muerto.
- **CA12:** con `DESAFIO` y objetivo forzado vivo, el selector se oculta y prevalece el forzado.

### Fallo 3

- **CA13:** con postura `Cubrir` aparece selector de aliado cubierto (vivos, distintos de sí mismo).
- **CA14:** el cubridor recibe el 40 % del daño dirigido al aliado elegido.
- **CA15:** autoobjetivo, aliado muerto o manipulación se rechazan y se aplica fallback.
- **CA16:** cambiar de `Cubrir` a `Guardia`/`Atacar` limpia el objetivo.
- **CA17:** una prioridad fuerza `ATACAR` y limpia la cobertura.
- **CA18:** “Saltar” y “autoSaltar” aplican posturas/coberturas y limpian los mapas.
- **CA19:** dos cubridores mantienen el reparto 40/40/20 y el límite existente.

### Compatibilidad

- **CA20:** una batalla/snapshot antiguo carga sin errores y usa defaults.
- **CA21:** `SESSION_VERSION` sigue en 21; sin cambios en `BattleSessionService`.
- **CA22:** sin cambios en tests, dependencias, migraciones, `bugs.txt` ni `TASK-032`.

## 7. Verificación requerida

### 7.1. Estática

Desde `/home/david/pokemon`:

```bash
php -l app/Livewire/Combate.php
php -l src/Battle/Domain/AgregadoBatalla.php
php -l app/Livewire/Combate/Soporte/GestorPreparacionBatalla.php
php -l app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php
php -l app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php
php -l app/Livewire/Presenters/BatallaVistaPresenter.php

vendor/bin/phpstan analyse --no-progress
vendor/bin/pint --dirty --format agent
php artisan view:cache
```

Greps de control:

```bash
grep -R "tieneVanguardiaViva" app/Livewire/Presenters/BatallaVistaPresenter.php
grep -R "no está en la vanguardia" app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php
grep -R "prioridadesObjetivo" app/Livewire
grep -R "coberturas" app/Livewire
grep -n "SESSION_VERSION" app/Support/BattleSessionService.php
git status --short
git diff --stat
git diff -- tests composer.json composer.lock database/migrations
```

Resultados esperados: sin coincidencias de los bloques eliminados; mapas presentes en los archivos previstos; `SESSION_VERSION` en 21; sin diffs en tests, dependencias ni migraciones.

### 7.2. Manual

1. Danza Espada y otros apoyos sobre aliado en retaguardia.
2. Targeting rival sigue bloqueado por fila.
3. Prioridad con objetivo elegido y con “Automático”.
4. Prioridad autodirigida (`protect`/`detect`/`endure`).
5. Objetivo de prioridad muerto por una prioridad anterior.
6. `DESAFIO` prevalece y muestra aviso.
7. Cubrir con aliado elegido; reparto del 40 %.
8. Cubrir inválido y cambio de postura.
9. Prioridad + Cubrir.
10. Saltar/omitir preparación.
11. Sesión antigua.
12. Dos cubridores sobre el mismo objetivo.
13. Selects dentro del modal y `wire:model.live`.

**Tests:** no se crean, no se modifican y no se ejecutan.

## 8. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Ampliación del contrato de `Combate` a 34 propiedades. | Aprobación explícita; defaults seguros; no renombrar ni eliminar propiedades. |
| `wire:model.live` es un patrón nuevo. | Verificación manual; fallback con `$wire.set(..., true)`. |
| Snapshots antiguos sin claves nuevas. | Defaults `[]`; `??` en Blade; fallback en `aplicar()`/`resolver()`. |
| `DESAFIO` prevalece sobre la elección. | Aprobación explícita; aviso en UI; coherente con el flujo normal. |
| Muerte del objetivo cubierto a mitad de ronda. | Se mantiene el comportamiento actual; documentado como fuera de alcance. |
| Prioridades de apoyo futuras. | No se enrutan; el selector solo lista rivales; nota de extensión. |
| Manipulación de propiedades Livewire. | Validación en `aplicar()` y revalidación en `resolver()`. |
| Regla de fila duplicada. | Nuevo método de dominio para el camino nuevo; deuda preexistente documentada. |

## 9. Rollback

- Revertir el commit de implementación de TASK-033.
- No hay migraciones, cambios de esquema, `SESSION_VERSION` ni datos persistidos que deshacer.
- Los mapas del componente se descartan con el código; las sesiones antiguas siguen siendo compatibles.

## 10. Decisiones que autoriza la aprobación del plan v1

Al aprobar el plan v1 se autorizan expresamente:

1. Añadir `$prioridadesObjetivo` y `$coberturas` al contrato de `Combate` (34 propiedades/18 métodos).
2. Que `DESAFIO` prevalezca sobre la elección de prioridad, con aviso en UI.
3. Que solo el jugador pueda apoyar a aliados en retaguardia; la IA queda intacta.
4. Que la cobertura se elija solo en la preparación de ronda y no se persista en Equipo.
5. Mantener la reasignación de Cubrir al morir el objetivo en la siguiente ronda.
6. Añadir `AgregadoBatalla::objetivosRivalesLegalesPara()` como lectura pura.
7. Usar `wire:model.live` en el select de movimiento de prioridad.
8. No tocar `SESSION_VERSION`, `BattleSessionService`, serialización de dominio, migraciones, tests ni dependencias.
