# TASK-041 — Plan v1

- **ID:** TASK-041
- **Versión:** v1
- **Fecha:** 2026-11-09
- **Estado:** AWAITING_APPROVAL — no autoriza implementación
- **Base:** rama `task-011`
- **Análisis:** `analysis.md` (COMPLETED)
- **Diseño:** `architecture.md` (COMPLETED)

## 1. Objetivo

1. **IA:** que `declararIa()` evalúe las acciones con el mismo criterio que la IA normal y solo declare una prioridad cuando la mejor acción sea realmente prioritaria. Así Riolu usará Garra Metal contra Fantasma en lugar de Ataque Rápido por su prioridad.
2. **Debug:** añadir un tooltip hover-only sobre la carta rival que muestre movimientos, stats, tipos, habilidad y objeto, construido y enviado **solo** con `APP_DEBUG=true`.

## 2. Alcance

### 2.1. Incluido

- Nueva política de `GestorPrioridadesBatalla::declararIa()`:
  - limpiar prioridad, postura y objetivo cubierto de todos los rivales vivos;
  - pre-chequear si hay alguna prioridad disponible;
  - usar `$battle->elegirAccionPara($rival)`;
  - declarar solo si la mejor acción es un movimiento con `priority > 0` y no es apoyo a aliado;
  - no tocar `DESAFIO` (el selector ya aplica el forzado).
- Nuevo `DebugCombatientePresenter` en `app/Livewire/Presenters`.
- Gate server-side en `SincronizadorVistaBatalla`: `debug` solo para `team2` y solo con `config('app.debug')`.
- Tooltip CSS hover-only en `_pokemon-card.blade.php` y `combate.css`, superpuesto dentro de la carta con `pointer-events: none`.
- Contenido: tipos, habilidad/efectos activos, objeto, 6 stats actual/máx y movimientos con tipo, categoría, potencia, precisión, prioridad, usos y disponibilidad.

### 2.2. Fuera de alcance

- Filtrar o corregir `DESAFIO` en `GestorPrioridadesBatalla` (el selector ya lo aplica).
- Refactor del RNG global de `CalculadoraDanioIA::estimar()`.
- Caché de la decisión de IA por ronda (requeriría serialización y probablemente `SESSION_VERSION`).
- Añadir nombres legibles de habilidades (se muestran las claves de efectos activos).
- Mostrar la efectividad de cada movimiento contra el objetivo en el tooltip.
- Cambios en `MovimientoBatalla`, `PerfilMovimiento`, `CombatienteVista`, `SelectorAccionIA`, `AgregadoBatalla`, `SESSION_VERSION`, serialización, migraciones, dependencias, tests o JS.

## 3. Arquitectura y contratos

- **IA:** `declararIa()` usa `AgregadoBatalla::elegirAccionPara()`; el `AccionBatalla` devuelto se inspecciona: si `move->priority > 0` y no es apoyo a aliado, se declara; si no, se deja al flujo normal. Pre-chequeo de prioridad disponible para evitar evaluación innecesaria.
- **Debug:** array aditivo `debug` en cada elemento de `team2`, construido por `DebugCombatientePresenter::para(Combatiente)`. No se añade a `team1` ni a `CombatienteVista`.
- **Gate:** `(bool) config('app.debug')` en `SincronizadorVistaBatalla::proyectar()`. En producción no se construye ni viaja el array.
- **Tooltip:** overlay `position: absolute; inset: 0` dentro de la carta, `pointer-events: none`, visible solo en `@media (hover: hover) and (pointer: fine)`. Sin JS, sin foco, sin tap.
- **Compatibilidad:** sin cambios de serialización ni `SESSION_VERSION` (21).

## 4. Archivos y responsables

### 4.1. Backend (`backend`)

| Orden | Archivo | Cambio |
|---|---|---|
| B1 | `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php` | Nueva política; pre-chequeo `tienePrioridadDisponible()`; limpieza; filtro de apoyo; no tocar `DESAFIO`. |
| B2 | `app/Livewire/Presenters/DebugCombatientePresenter.php` | Nuevo presentador con el contrato `para(Combatiente): array`. |
| B3 | `app/Livewire/Combate/Soporte/SincronizadorVistaBatalla.php` | Gate `config('app.debug')`; `debug` solo en `team2`. |

### 4.2. Frontend (`frontend`)

| Orden | Archivo | Cambio |
|---|---|---|
| F1 | `resources/views/livewire/_pokemon-card.blade.php` | Clase `pokemon-card--debug-rival` y bloque `.debug-tooltip` solo para `team === 1` con `debug`. |
| F2 | `resources/css/combate.css` | Estilos hover-only, overlay interno, `pointer-events: none`. |

### 4.3. Sin cambios

- `src/Battle/Domain/MovimientoBatalla.php`, `PerfilMovimiento.php`, `CombatienteVista.php`, `SelectorAccionIA.php`, `AgregadoBatalla.php`.
- `resources/js/combate.js`, `resources/views/livewire/combate.blade.php` (salvo que F1 lo requiera; no se prevé).
- `app/Support/BattleSessionService.php`, `SESSION_VERSION`.
- Migraciones, dependencias, tests, `bugs.txt`, `TASK-032/` a `TASK-040/`, cambios de TASK-035.

## 5. Orden de ejecución y dependencias

1. **B1** — política de IA (independiente).
2. **B2** — presentador de debug.
3. **B3** — gate en el sincronizador (depende de B2).
4. **F1 + F2** — tooltip (dependen del contrato de B3).
5. Verificación estática y manual.
6. Revisión independiente y seguridad.
7. Informe final.

B1 y B2 pueden ir en paralelo (archivos distintos). B3 depende de B2. F1/F2 dependen de B3.

## 6. Criterios de aceptación

### IA

- **CA-IA-1:** Riolu contra Fantasma no declara Ataque Rápido si Garra Metal es la mejor acción; el log no muestra `RIVAL PRIORIDAD: Ataque Rápido` y sí una acción normal con Garra Metal.
- **CA-IA-2:** si la mejor acción tiene prioridad > 0, se declara y resuelve antes.
- **CA-IA-3:** si la mejor acción tiene prioridad 0, no se declara y el flujo normal la ejecuta.
- **CA-IA-4:** el filtro de Protección/Aguante de TASK-040 sigue vigente.
- **CA-IA-5:** una prioridad de apoyo a aliado no se declara; el flujo normal la resuelve.
- **CA-IA-6:** una prioridad autodirigida se declara y se aplica al actor.
- **CA-IA-7:** un rival con `DESAFIO` que declara prioridad golpea al objetivo forzado.
- **CA-IA-8:** `SESSION_VERSION` sigue en 21; sesión antigua carga.
- **CA-IA-9:** un rival sin prioridad disponible no provoca `elegirAccionPara()`.
- **CA-IA-10:** no se declaran dos prioridades para el mismo rival en la misma ronda.

### Tooltip

- **CA-DBG-1:** con `APP_DEBUG=false`, `team2` no contiene `debug` y el DOM no contiene `.debug-tooltip`; no viajan moveset ni stats ofensivas nuevas.
- **CA-DBG-2:** con `APP_DEBUG=true`, cada rival contiene `debug` y el hover muestra tipos, habilidad/efectos, objeto, 6 stats actual/máx y todos los movimientos con tipo, categoría, potencia, precisión, prioridad y usos.
- **CA-DBG-3:** `team1` no contiene `debug` ni pinta tooltip.
- **CA-DBG-4:** solo hover; sin `tabindex`, `:focus`, `:focus-within` ni listeners táctiles.
- **CA-DBG-5:** el tooltip no se recorta ni cambia el layout fuera del hover.
- **CA-DBG-6:** con el tooltip visible, `wire:click="previewTarget(...)"` sigue seleccionando al rival.
- **CA-DBG-7:** todo con `{{ }}`; sin `{!! !!}`.
- **CA-DBG-8:** no se modifican `CombatienteVista`, `MovimientoBatalla`, `PerfilMovimiento` ni `SESSION_VERSION`.
- **CA-DBG-9:** sin dependencias; solo Blade y CSS.

## 7. Verificación requerida

### 7.1. Estática

```bash
php -l app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php
php -l app/Livewire/Presenters/DebugCombatientePresenter.php
php -l app/Livewire/Combate/Soporte/SincronizadorVistaBatalla.php

vendor/bin/phpstan analyse -c phpstan.neon --no-progress --memory-limit=512M
vendor/bin/pint --dirty --format agent
php artisan view:cache
npm run build
```

Greps:

```bash
grep -n "config('app.debug')" app/Livewire/Combate/Soporte/SincronizadorVistaBatalla.php
grep -rn "debug" app/Livewire/Presenters/DebugCombatientePresenter.php app/Livewire/Combate/Soporte/SincronizadorVistaBatalla.php resources/views/livewire/_pokemon-card.blade.php
grep -n "elegirAccionPara\|ClasificadorAccionIA" app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php
grep -n "SESSION_VERSION" app/Support/BattleSessionService.php
git diff --check
git status --short
```

Resultados esperados: gate solo en el sincronizador; `debug` en presentador/sincronizador/carta; `elegirAccionPara` y `ClasificadorAccionIA` en `GestorPrioridadesBatalla`; `SESSION_VERSION` 21; solo los 5 archivos autorizados.

### 7.2. Manual

1. `APP_DEBUG=false`: batalla Riolu vs Fantasma; inspeccionar payload de Livewire y DOM; sin `debug` ni `.debug-tooltip`.
2. `APP_DEBUG=true`: hover sobre carta rival; comprobar tipos, habilidad/efectos, objeto, 6 stats y todos los movimientos (tipo, categoría, potencia, precisión, prioridad, usos).
3. Riolu vs Fantasma: comprobar que usa Garra Metal y no Ataque Rápido por prioridad.
4. 2v2/3v3: prioridades legítimas, apoyo, autodirigidas, `DESAFIO`.
5. Sesión antigua: carga sin error.
6. Tooltip visible: `wire:click` sigue seleccionando al rival.
7. Sin recorte del tooltip y sin cambios de layout fuera del hover.

**Tests:** no se crean, no se modifican y no se ejecutan.

## 8. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Cambio de balance de la IA. | Aprobación explícita; verificación manual contra Fantasma y otros tipos. |
| Perturbación del RNG global por `CalculadoraDanioIA::estimar()`. | Documentado; no se refactoriza en esta tarea. Se acepta el desplazamiento de la secuencia. |
| Doble evaluación de `SelectorAccionIA`. | Pre-chequeo de prioridad disponible; medir en 3v3. |
| Objetivo de la IA re-evaluado en `resolver()`. | Preexistente; documentado. |
| Fuga de debug en producción. | Gate server-side con `config('app.debug')`; no se construye el array sin debug. |
| Habilidad base `null`. | Se muestran efectos activos; la base solo si existe. |
| Tooltip superpuesto a la carta. | Debug-only; `pointer-events: none`; hover-only. |
| Precisión redondeada. | Se muestra como porcentaje entero. |

## 9. Rollback

- Revertir el commit de implementación de TASK-041.
- No hay migraciones ni cambios de esquema ni de sesión.
- El tooltip es una proyección efímera; al revertir desaparece.
- La política de IA vuelve a la selección por prioridad anterior.

## 10. Decisiones que autoriza la aprobación del plan v1

1. Cambiar la política de IA para evaluar acciones y declarar prioridad solo si la mejor acción es prioritaria.
2. Aceptar el cambio de balance que implica.
3. Aceptar la perturbación del RNG global por la evaluación extra, sin refactorizar `CalculadoraDanioIA` en esta tarea.
4. Aceptar la doble evaluación con pre-chequeo, sin caché por ronda.
5. Mantener `DESAFIO` sin cambios (el selector ya aplica el forzado).
6. Tooltip superpuesto dentro de la carta, hover-only, sin foco ni tap.
7. Mostrar habilidades como claves de efectos activos; sin nombres legibles.
8. Debug solo con `APP_DEBUG=true`; gate server-side y solo `team2`.
9. Sin cambios en `SESSION_VERSION`, serialización, migraciones, dependencias, tests ni JS.
