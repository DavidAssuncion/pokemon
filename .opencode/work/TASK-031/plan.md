# TASK-031 — Plan v1

- **Estado:** APROBADO v1 (2026-11-07) — ver `approval.md`.
- **ID:** TASK-031
- **Versión:** v1
- **Fecha:** 2026-11-07
- **Nota de corrección (2026-11-07):** tras la revisión se aclara que Alivio mantiene el comportamiento actual: deja la confusión con 0 turnos y se elimina en la siguiente acción. La IA no reutiliza `CRITICO_MEJORADO` si la condición ya está activa. Sin cambio de alcance.
- **Base:** rama `task-011`, HEAD `c020b6c`
- **Análisis:** `analysis.md`
- **Diseño:** `architecture.md`
- **Tests:** no se crean, no se modifican, no se ejecutan.

## 1. Objetivo

Ampliar el modo aliado con 9 curas de PS, 25 buffs puros, Alivio y Foco Energía/Aguzar, y añadir una IA capaz de usar soporte sobre aliados con una compuerta conservadora, sin romper el modo propio/rival, el contrato de `Combate` ni la sesión.

## 2. Alcance

### Incluido

**Frente A — dominio y presentación**

- Catálogo: 37 slugs nuevos.
  - 9 `CURAR_PS`: `roost`, `morning-sun`, `synthesis`, `moonlight`, `soft-boiled`, `milk-drink`, `slack-off`, `heal-order`, `shore-up`.
  - 25 `BUFF_STATS`: `agility`, `rock-polish`, `autotomize`, `harden`, `withdraw`, `rizo_defensa`, `barrier`, `acid-armor`, `iron-defense`, `shelter`, `cotton-guard`, `amnesia`, `charge`, `tail-glow`, `cosmic-power`, `defend-order`, `magnetic-flux`, `sharpen`, `hone-claws`, `work-up`, `coil`, `quiver-dance`, `shift-gear`, `geomancy`, `victory-dance`.
  - 1 `CURAR_ESTADO`: `refresh`.
  - 2 `CRITICO_MEJORADO`: `focus-energy`, `laser-focus`.
- `TipoEfectoAliado::CURAR_ESTADO` y `TipoEfectoAliado::CRITICO_MEJORADO`.
- `Combatiente::curarEstadoMayor()`.
- `EjecutorEfectoAliado`: ramas de cura de estado y crítico; fracción de cura desde `perfil->curacionFraccion`.
- `MovesPreviewPresenter`: textos de los dos tipos nuevos; cura desde el perfil.

**Frente B — IA**

- B0: decisión única para actores con soporte; eliminación del forzado `defenderId = actor`; APIs legacy con `incluirSoporte: false`.
- B1: generación de candidatas de soporte, `ClasificadorAccionIA`, `EvaluadorSoporteIA`, `supportValue`, pesos, compuerta conservadora, filtros anti-inútiles y lookahead desactivado para soporte.
- `EjecutorAccion`/Exploraciones: `incluirSoporte: false`; sin rama aliada en el motor headless.

### Fuera de alcance

- Purificar, Bálsamo Osado (`take-heart`), Bastión Final (`no-retreat`), Acupresión (`acupressure`).
- Aullido (`howl`), Piñón Auxiliar (`gear-up`), Motivación (`coaching`), Niebla Aromática (`aromatic-mist`), `STATS_ALIADO` y efectos de equipo.
- Prioridades con objetivo aliado y cambios en `Combate::$prioridades`.
- Cambios en `storage/data/moves.json`, migraciones, `SESSION_VERSION`, Blade, JS o `DepuradorMovimientos`.
- Tests.
- `git push`.

## 3. Arquitectura y contratos

Se implementa el diseño de `architecture.md`:

- Dominio puro; sin dependencias de Eloquent, HTTP ni Blade.
- `TipoEfectoAliado`/`EfectoAliado` se calculan en runtime; no se persisten.
- `EvaluacionAccion` y `PesosAmenaza` crecen al final con defaults.
- `SelectorAccionIA::elegirAccion()` añade `incluirSoporte = true`; las APIs legacy pasan `false`.
- `AgregadoBatalla` añade `actorAdmiteSoporte()` y `elegirAccionPara()`; no es el contrato Livewire.
- `app/Livewire/Combate.php` no cambia (32 propiedades/18 métodos).
- `SESSION_VERSION` sigue en 21; sin migraciones ni datos nuevos.
- `esAutodirigido()` no cambia.
- `ResolutorAccionBatalla` no cambia.

## 4. Fases, archivos y responsables

### F1 — Dominio base (backend)

| Archivo | Cambio |
|---|---|
| `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php` | Añadir 9 `CURAR_PS` + 25 `BUFF_STATS` |
| `src/Battle/Domain/Ejecucion/EjecutorEfectoAliado.php` | `aplicarCuracionPs()` usa `perfil->curacionFraccion` con fallback 0.5 |
| `app/Livewire/Presenters/MovesPreviewPresenter.php` | Preview de cura usa la fracción del perfil |

Dependencia: ninguna. Riesgo: bajo.

### F2 — Dominio estados y crítico (backend)

| Archivo | Cambio |
|---|---|
| `src/Battle/Domain/Enums/TipoEfectoAliado.php` | `CURAR_ESTADO`, `CRITICO_MEJORADO` |
| `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php` | `refresh`, `focus-energy`, `laser-focus` |
| `src/Battle/Domain/Combatiente.php` | `curarEstadoMayor()` |
| `src/Battle/Domain/Ejecucion/EjecutorEfectoAliado.php` | `aplicarCuracionEstado()`, `aplicarCriticoMejorado()` |
| `app/Livewire/Presenters/MovesPreviewPresenter.php` | Textos de los tipos nuevos |
| `src/Battle/Domain/Effects/Movimiento/EjecutorRefresh.php` | Delegación opcional en `curarEstadoMayor()` |

Dependencia: F1. Riesgo: medio por los `match` exhaustivos. **F2 debe hacerse en un solo cambio atómico** para no dejar el enum sin ramas.

### F3 — IA B0 (backend)

| Archivo | Cambio |
|---|---|
| `src/Battle/Domain/AgregadoBatalla.php` | `actorAdmiteSoporte()`, `elegirAccionPara()`, legacy `incluirSoporte:false` |
| `app/Livewire/Combate/Soporte/GestorAccionBatalla.php` | Ruta condicionada en `prepararAnimacionIa()`; eliminar el forzado `defenderId = actor` |
| `src/Battle/Domain/AI/SelectorAccionIA.php` | `incluirSoporte` en `elegirAccion()` y `elegirObjetivoPara()` |
| `src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php` | `incluirSoporte: false` en `elegirMovimiento()` |

Dependencia: ninguna; puede ir en paralelo a F1/F2. Riesgo: medio.

### F4 — IA B1 (backend)

| Archivo | Cambio |
|---|---|
| `src/Battle/Domain/AI/ClasificadorAccionIA.php` | **Nuevo**: `esApoyo()` |
| `src/Battle/Domain/AI/EvaluadorSoporteIA.php` | **Nuevo**: valoración por tipo y compuerta |
| `src/Battle/Domain/AI/SelectorAccionIA.php` | Generación de soporte, filtros, compuerta KO |
| `src/Battle/Domain/AI/EvaluadorAccionIAImpl.php` | Rama de soporte; lookahead con acción |
| `src/Battle/Domain/AI/ValueObjects/EvaluacionAccion.php` | `supportValue = 0.0` |
| `src/Battle/Domain/AI/PesosAmenaza.php` | Pesos de soporte |

Dependencia: F1, F2, F3. Riesgo: medio-alto. **No desplegar F4 sin F3.**

### F5 — Verificación estática y smoke (orchestrator/backend)

- `php -l` de todos los PHP tocados.
- `vendor/bin/pint --dirty --format agent`.
- `composer analyse`.
- `composer arch`.
- `php artisan view:cache` / `php artisan view:clear`.
- `git diff --check`, `git diff --stat`.
- Smoke manual de `/combate` (ver §6).
- Sin tests.

### F6 — Revisión y seguridad (reviewer/security)

- `reviewer`: diff, capas, contratos, duplicidades, regresiones.
- `security`: selección de objetivos, autorización de acciones, entradas y superficie de ataque.
- Si el veredicto es `CHANGES_REQUESTED`, correcciones acotadas y nueva revisión.
- Si `BLOCKED`, se escala al desarrollador.

### F7 — Cierre (orchestrator)

- `review.md`, `security.md`, `final-report.md`.
- Commit de la tarea tras la revisión. Sin `git push`.
- `documenter`: no aplica salvo petición expresa; la arquitectura queda en `architecture.md`.

## 5. Criterios de aceptación

### Dominio

1. `CatalogoEfectosAliado::para()` devuelve el tipo esperado para los 37 slugs nuevos y `null` para el resto; los 9 slugs de TASK-030 conservan su tipo.
2. Los 25 buffs aplican `selfStatChanges` al aliado, no al actor, y no hay doble aplicación al actor en modo aliado.
3. Las 9 curas aplican `hpMax * perfil->curacionFraccion` (50 %) al aliado, sin desbordar a escudo.
4. `refresh` sobre aliado limpia el estado mayor y el contador de veneno grave; si hay confusión, deja sus turnos a 0 y se elimina en la siguiente acción (comportamiento actual de Alivio).
5. `focus-energy` y `laser-focus` aplican `CRITICO_MEJORADO` 5 turnos al aliado; si ya existía, se refresca a 5.
6. El preview muestra textos para `CURAR_ESTADO` y `CRITICO_MEJORADO`, sin fila de daño, con badge `SOBRE ALIADO`.

### IA

7. La IA no genera soporte si no hay aliados vivos o movimientos del catálogo.
8. `prepararAnimacionIa()` no fuerza `defenderId = actor` cuando la acción es soporte; el `pendingAction` apunta al aliado y `resolver()` llama `aplicarMovimientoAliado()`.
9. `sand-tomb`/`fire-spin`/`whirlpool` de la IA contra un aliado no pasan por `CalculadoraDanioIA::estimar()` ni producen `koValue`/`damageValue`.
10. Si hay un KO disponible, ninguna acción de soporte es seleccionada.
11. No se selecciona soporte con `supportValue <= 0` (PS llenos, escudo lleno, buff saturado, estado ausente, crítico ya activo, Aqua Ring activo).
12. La cura solo se valora si el aliado está ≤ 50 % o en riesgo de KO; el escudo solo si el déficit ≥ 25 % o hay riesgo de KO; el buff solo si el delta neto ≥ 0.3; el crítico solo si la condición no está activa.
13. El lookahead no se ejecuta para soporte; los ataques conservan `PERFECTA`/`DIFÍCIL` con lookahead y `NORMAL` sin él.
14. `EjecutorAccion`/`ejecutarBatalla()` no genera soporte sobre aliados; Exploraciones 1v1 no cambia.
15. Actores sin aliados o sin movimientos del catálogo conservan el camino legacy de `prepararAnimacionIa()`.

### Regresión y contratos

16. Los 9 movimientos de TASK-030 y los 5 buffs previos conservan su resolución en modo propio y aliado.
17. `app/Livewire/Combate.php` no cambia; el contrato sigue en 32 propiedades/18 métodos.
18. `SESSION_VERSION` sigue en 21; no hay migraciones, datos nuevos ni cambios de serialización.
19. `storage/data/moves.json`, Blade, JS y `DepuradorMovimientos` no cambian.
20. `esAutodirigido()` no cambia.

## 6. Verificación requerida

### Estática

```bash
php -l <cada PHP tocado>
vendor/bin/pint --dirty --format agent
composer analyse
composer arch
php artisan view:cache
php artisan view:clear
git diff --check
git diff --stat
```

Criterio: 0 errores nuevos en PHPStan y deptrac respecto a la línea base de TASK-030 (34 y 100 preexistentes). `php -l`, Pint, `view:cache` y `git diff --check` deben pasar.

### Smoke manual (sin tests)

En `/combate`:

1. Cura sobre aliado con PS dañados; comprobar badge, texto, log y que no hay daño.
2. Cura sobre aliado con PS llenos; comprobar que el preview lo indica y que la acción no se ofrece o no tiene efecto.
3. Buff sobre aliado; comprobar que el actor no recibe el buff y que el badge muestra la duración.
4. Alivio sobre aliado con estado y sin estado.
5. Foco Energía dos veces sobre el mismo aliado; el jugador debe poder refrescar a 5 rondas, pero la IA no debe reutilizarlo si ya está activo.
6. Geocontrol en modo aliado: comprobar la carga (`carga_maxima`) y la aplicación posterior.
7. Modo propio y modo rival con los 9 movimientos de TASK-030.
8. IA con aliados: no ataca al aliado, cura bajo umbral, no usa soporte si tiene KO.
9. Recarga de sesión y responsive.

## 7. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| `UnhandledMatchError` por enum nuevo | F2 atómica; PHPStan y `php -l` |
| IA se autoaplica el soporte | F3 elimina el forzado; `elegirAccionPara` devuelve el aliado |
| IA daña a un aliado | `esApoyo()` antes de `estimar()`; legacy `false`; defensa en profundidad |
| Cambio de comportamiento de la IA | Ruta condicionada; legacy intacto sin soporte |
| Bucles/pasividad | Compuerta HP/KO, delta de buff, mínimo 5, no-KO |
| Coste de `mejorEstimacionContra` | Listas acotadas; cachear después si hace falta |
| Pesos mal calibrados | Valores conservadores; smoke y ajuste en `PesosAmenaza` |
| Alivio no cura confusión | Comportamiento actual preservado; decisión de producto pendiente |
| `geomancy` con carga | Smoke de dos rondas |
| Divergencia de `CRITICO_MEJORADO` propio/aliado | Documentada; modo propio intacto |

## 8. Rollback

- Todo es código; sin migraciones, datos, `SESSION_VERSION` ni estado persistido.
- Revertir F4 si B1 produce pasividad o selección incorrecta, conservando F3.
- Revertir F2 completo si hay `UnhandledMatchError`.
- Revertir F3 si la IA cambia demasiado.
- Usar `git revert`; no `reset --hard`, `clean` ni `checkout --` sin aprobación. Sin `git push`.

## 9. Aprobación

Este plan no autoriza implementación hasta que el desarrollador responda explícitamente con una fórmula que mencione la versión, por ejemplo:

```text
Aprobar plan v1
```

La aprobación se registrará en `approval.md` con el texto literal, la fecha y el alcance autorizado.
