# TASK-030 — Plan v2 — Modo aliado para 9 movimientos

- **ID:** TASK-030
- **Versión:** v3
- **Estado:** PENDIENTE DE APROBACIÓN — corrige Recuperación al 50%
- **Fecha:** 2026-11-07
- **Base:** HEAD `073c0d4`
- **Análisis:** `.opencode/work/TASK-029/analysis.md` y addendum
- **Diseño:** `.opencode/work/TASK-030/architecture.md` + addendum v2 + nota v3

> v3 sustituye al plan v2: Recuperación vuelve a curar el **50% del PS máximo del receptor**. La fórmula `1/32 + 5%` queda solo para Aqua Ring.

---

## 1. Objetivo

Permitir que los 9 movimientos del primer alcance se usen sobre un aliado vivo de vanguardia, aplicando el efecto de apoyo correspondiente, sin alterar el modo propio ni el modo rival.

**Movimientos:** Bucle Arena (`sand-tomb`), Giro Fuego (`fire-spin`), Torbellino (`whirlpool`), Maquinación (`nasty-plot`), Paz Mental (`paz_mental`), Danza Espada (`swords-dance`), Danza Dragón (`dragon-dance`), Recuperación (`recover`) y Corpulencia (`bulk-up`).

---

## 2. Alcance

- Catálogo de dominio `slug → EfectoAliado` para los 9 movimientos.
- Calculadora única de fórmulas de escudo y curación.
- Ejecutor dedicado que aplica escudo físico/especial, `AQUA_RING`, cura de PS y buffs de stats al aliado.
- Rama de resolución `aplicarMovimientoAliado()` en `ResolutorAccionBatalla`, sin pasar por daño, precisión, crítico, inmunidad, reacciones ni `partiallytrapped`.
- Selección de aliado en Livewire reutilizando `selectedTargetTeam/Idx/RefId` y `previewTarget`.
- Presentación: aliados seleccionables solo cuando el actor tiene movimientos del catálogo; preview con efecto aliado; movimientos no usables deshabilitados; botón “Cambiar objetivo”.
- Duración de 5 rondas para buffs y `AQUA_RING`; escudos instantáneos.
- Identidad por `slug` en `MovimientoBatalla` y `DTOMovimientoBatalla`, con compatibilidad hacia atrás.
- Endurecimiento de autorización en `previewTarget` y `selectMove` con `jugadorAutorizado()`.

---

## 3. Fuera de alcance

- Curas de salud adicionales (Respiro, Sol Matinal, etc.).
- Boosts de stats adicionales (Agilidad, Barrera, Defensa Férrea, etc.).
- Curación de estados a aliados.
- IA que use movimientos sobre aliados.
- Prioridades que seleccionen aliados.
- Escudo temporal de 5 rondas; el escudo restaurado es instantáneo.
- Cambios en `storage/data/moves.json`, seed, tabla `moves` o `ObjetivoMovimiento`.
- Migraciones y cambios de `SESSION_VERSION`.
- Cambios en el contrato público de `Combate` (32 propiedades / 18 métodos).
- Tests: no se crean, modifican ni ejecutan.

---

## 4. Decisiones adoptadas para v2

| # | Decisión | Valor v2 |
|---|---|---|
| 1 | **Escudo físico** (`sand-tomb`) | `incremento = max(1, floor(round(maxEscudo/32 + DefLanzador*0.10, 9)))`; `nuevo = min(maxEscudo, actual + incremento)` |
| 2 | **Escudo especial** (`fire-spin`) | Ídem con `maxEscudoEspecial` y `DefEspLanzador` |
| 3 | **Recuperación** (`recover`) | **50% del PS máximo del receptor**, con tope; sin fórmula `1/32 + 5%` |
| 4 | **Aqua Ring** (`whirlpool`) | 5 rondas; guarda `curacionFija = PSMaxLanzador*0.05`; cada ronda cura `PSMaxReceptor/32 + curacionFija` |
| 5 | **Aqua Ring propio** | 1/16 durante 5 rondas; sin fórmula; `curacionFija=0` |
| 6 | **Redondeo** | `floor` de la suma redondeada a 9 decimales; mínimo 1 por aplicación |
| 7 | **Stat del lanzador** | Stat **efectiva** (`obtenerStatEfectivo`) |
| 8 | Aliado objetivo | Solo **vanguardia viva**; retaguardia no seleccionable |
| 9 | Flujo UI | **Target-first** (objetivo → movimiento), sin fase nueva; “Cambiar objetivo” reutiliza `cancelTarget()` |
| 10 | `AQUA_RING` legado | Sesiones antiguas (`turnos=0`, `curacionFija=0`) siguen 1/16 permanentes |
| 11 | Buffs sobre aliado | **Solo el aliado** recibe el buff; el actor no; 5 rondas |
| 12 | Combo | La acción de apoyo **rompe combo** |
| 13 | Prioridades | **No** permiten seleccionar aliado en v2 |
| 14 | Badge de duración | **Incluido**: pintar `buffTurns` en `_pokemon-card.blade.php` |
| 15 | Autorización | `jugadorAutorizado()` en `previewTarget` y `selectMove`; `retrasarTurno`/`pasarTurno` fuera |
| 16 | Identidad | **`slug`** en `MovimientoBatalla`/`DTOMovimientoBatalla`, con `unset` en `DepuradorMovimientos` |

---

## 5. Arquitectura y contratos

### 5.1 Dominio

- `TipoEfectoAliado` (enum): `ESCUDO_FISICO`, `ESCUDO_ESPECIAL`, `AQUA_RING`, `CURAR_PS`, `BUFF_STATS`.
- `EfectoAliado` (VO readonly): `tipo` y `turnos`; **sin `fraccion`**.
- `CatalogoEfectosAliado`: mapa estático de los 9 slugs; `para()` y `admite()`.
- `CalculadoraCuracionAliada` (nuevo): única fuente de fórmulas y redondeo.
  - `incrementoEscudo(float $maxEscudo, float $statLanzador): int`
  - `curacionConAporteFijo(float $psMaxReceptor, float $aporteFijo): int`
- `EjecutorEfectoAliado`: aplica el efecto al aliado.
  - Escudos: `setDefensaHpActual` / `setDefensaEspHpActual` con tope.
  - `AQUA_RING`: `reemplazarCondicion(new CondicionVolatil(ClaveCondicion::AQUA_RING, turnos: 5, origenId: ..., curacionFija: PSMaxLanzador*0.05))`.
  - Cura (`recover`): `curarHpCantidad(0.5 * PSMaxReceptor)`, sin desbordamiento a escudos.
  - Aqua Ring: `curarHpCantidad(PSMaxReceptor/32 + curacionFija)`.
  - Buffs: `GestorEfectosMovimiento::aplicarStatChangesAMuchos($aliado, [], $movimiento)`.

### 5.2 `CondicionVolatil`

- Nuevo campo aditivo `float $curacionFija = 0.0`.
- `toArray()` y `desdeArray()` con default `0.0`.
- Semántica: guarda el **5% de los PS máximos del lanzador**; el `1/32` del receptor se recalcula cada ronda.

### 5.3 Identidad por slug

- `MovimientoBatalla`: `public readonly string $slug = ''` al final del constructor; `__serialize`/`__unserialize` con `?? ''`; propagación en `conPotencia()` y `conTipo()`.
- `Movimiento::aMovimientoBatalla()`: propaga `slug`.
- `DTOMovimientoBatalla`: `slug` en `desdeDominio`, `toLivewire`, `fromLivewire` y `toDomain`.
- `DepuradorMovimientos::firma()`: `unset($datos['slug'])` para no romper la deduplicación de loadouts.

### 5.4 Resolución

- `ResolutorAccionBatalla::aplicarMovimientoAliado()`:
  - dispara inicio/fin de turno, rompe combo, registra log, ejecuta el efecto, emite un evento `effect`, consume acción y PP.
  - no llama a `ResolutorImpacto`, `ServicioEjecucionBatalla`, `dispararSerObjetivo`, `dispararDanioRecibido`, `contraatacar` ni `aplicarSecundarios`.
- `GestorAccionBatalla`: rama aliado; no fuerza `actor` cuando hay `EfectoAliado`; defensa en profundidad si llega un aliado con movimiento no aliado.
- `GestorSeleccionBatalla`: autentica con `jugadorAutorizado()`, valida aliado vivo y vanguardia, recalcula preview y crea la acción.
- `ResolutorActorBatalla` y `GestorPrioridadesBatalla`: sin cambios.

### 5.5 Presentación

- `BatallaVistaPresenter`: `refsAliado` y `aliadosSeleccionables`; marca aliados seleccionables solo si `actorAdmiteAliado`.
- `PresentadorVistaCombate`: calcula `actorAdmiteAliado` desde `currentMoves`.
- `MovesPreviewPresenter`: campos `slug`, `modoAliado`, `efectoAliado`; preview aliado sin daño; textos con cantidades calculadas.
- `moves-panel.blade.php`: badge `SOBRE ALIADO`, texto de efecto, deshabilitar no usables, botón “Cambiar objetivo”.
- `_pokemon-card.blade.php`: badge `buffTurns`.
- JS: sin cambios; se reutilizan los eventos y animaciones existentes.

### 5.6 Persistencia

- Sin migraciones.
- Sin cambio de `SESSION_VERSION` (21).
- `slug` con `?? ''` para compatibilidad.
- `curacionFija` aditivo con `0.0`; sesiones antiguas conservan 1/16.
- Rollback: código antiguo leyendo `curacionFija` puede emitir `Deprecated: Creation of dynamic property` en PHP 8.2+, no fatal.

---

## 6. Archivos y responsables

### Crear

| Archivo | Responsable | Fase |
|---|---|---|
| `src/Battle/Domain/Enums/TipoEfectoAliado.php` | backend | 0 |
| `src/Battle/Domain/ValueObjects/EfectoAliado.php` | backend | 0 |
| `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php` | backend | 0 |
| `src/Battle/Domain/Ejecucion/CalculadoraCuracionAliada.php` | backend | 0 |
| `src/Battle/Domain/Ejecucion/EjecutorEfectoAliado.php` | backend | 0 |

### Modificar

| Archivo | Responsable | Fase |
|---|---|---|
| `src/Battle/Domain/ValueObjects/CondicionVolatil.php` | backend | 0 |
| `src/Battle/Domain/MovimientoBatalla.php` | backend | 0 |
| `src/Battle/Domain/Movimiento.php` | backend | 0 |
| `src/Battle/Presentation/DTOMovimientoBatalla.php` | backend | 0 |
| `src/Battle/Domain/Catalog/DepuradorMovimientos.php` | backend | 0 |
| `src/Battle/Domain/Effects/Movimiento/EjecutorAquaRing.php` | backend | 0 |
| `src/Battle/Domain/Componentes/Agregado/ProcesadorCondicionesRonda.php` | backend | 0 |
| `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php` | backend | 1 |
| `app/Livewire/Combate/Soporte/GestorAccionBatalla.php` | backend | 1 |
| `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php` | backend | 1 |
| `app/Livewire/Presenters/MovesPreviewPresenter.php` | backend | 2 |
| `app/Livewire/Presenters/BatallaVistaPresenter.php` | backend | 2 |
| `app/Livewire/Presenters/PresentadorVistaCombate.php` | backend | 2 |
| `resources/views/livewire/partials/moves-panel.blade.php` | frontend | 2 |
| `resources/views/livewire/_pokemon-card.blade.php` | frontend | 2 |
| `resources/views/livewire/partials/items-panel.blade.php` | frontend | 2 |

### No tocar

`app/Livewire/Combate.php`, `MovimientoBatalla::esAutodirigido()`, `ResolutorImpacto`, `ServicioEjecucionBatalla`, `SelectorAccionIA`, `GestorPrioridadesBatalla`, `ResolutorActorBatalla`, `BattleSessionService`, `database/`, `storage/data/moves.json`, `ObjetivoMovimiento`, `resources/js/combate.js`, `combate-scripts.blade.php` y `tests/`.

---

## 7. Orden de ejecución y dependencias

### Fase 0 — Núcleo de dominio (backend)

1. `CalculadoraCuracionAliada`.
2. `TipoEfectoAliado`, `EfectoAliado` y `CatalogoEfectosAliado`.
3. `CondicionVolatil::curacionFija`.
4. `MovimientoBatalla` (`slug`), `Movimiento`, `DTOMovimientoBatalla` y `DepuradorMovimientos`.
5. `EjecutorEfectoAliado`.
6. `EjecutorAquaRing` y `ProcesadorCondicionesRonda`.

**Paralelización:** el bloque de `slug` y el bloque de `AQUA_RING` pueden ir en paralelo si no comparten archivos; `CondicionVolatil` y `CalculadoraCuracionAliada` deben ir primero.

### Fase 1 — Resolución y selección (backend)

7. `ResolutorAccionBatalla::aplicarMovimientoAliado`.
8. `GestorAccionBatalla`.
9. `GestorSeleccionBatalla`.

**Salida:** una acción aliada se resuelve sin daño y consume turno/PP.

### Fase 2 — Presentación (backend + frontend)

10. `MovesPreviewPresenter`.
11. `BatallaVistaPresenter` y `PresentadorVistaCombate`.
12. `moves-panel.blade.php`.
13. `_pokemon-card.blade.php` e `items-panel.blade.php`.

### Fase 3 — Verificación y revisión

14. Pint, PHPStan, deptrac, `php -l`, `view:cache`, `git diff --check`.
15. Smoke manual de navegador.
16. Revisión independiente (`reviewer`).
17. Auditoría de seguridad (`security`).
18. Informe final.

---

## 8. Criterios de aceptación

### Funcionales

1. Los 9 slugs están en `CatalogoEfectosAliado`; cualquier otro devuelve `null`.
2. Los aliados vivos de vanguardia son seleccionables solo si el actor tiene al menos un movimiento del catálogo.
3. `sand-tomb` sobre aliado: `incremento = max(1, floor(round(max/32 + Def*0.10, 9)))`, `nuevo = min(max, actual + incremento)`. Ejemplo max 320, Def 120 → **+22**. Sin daño ni `partiallytrapped`.
4. `fire-spin` sobre aliado: ídem con `maxEscudoEspecial` y `DefEsp`. Ejemplo max 320, SpDef 100 → **+20**. Sin daño ni `partiallytrapped`.
5. `recover` sobre aliado: cura **50% del PS máximo del receptor**, con tope. Ejemplo PS máx. 320, actual 100 → **+160 PS (260/320)**. Sin desbordamiento a escudos. `recover` propio sigue 50%.
6. `whirlpool` sobre aliado: `AQUA_RING` 5 rondas con `curacionFija = PSMaxLanzador*0.05`; cada ronda cura `PSMaxReceptor/32 + curacionFija`; ejemplo 320/300 → **25 PS/ronda** durante 5 rondas.
7. `aqua-ring` propio dura 5 rondas con 1/16; `recover` propio sigue 50%; buffs propios sin cambios.
8. Los 5 buffs se aplican al aliado, no al actor, y duran 5 rondas.
9. Modo rival intacto: `sand-tomb`, `fire-spin` y `whirlpool` contra rival siguen con daño + atrapado; buffs tras seleccionar rival siguen buffeando al actor.
10. Redondeo `floor` + mínimo 1; recurso lleno → 0 y consume turno/PP.
11. La acción aliada consume turno y PP; no dispara precisión, crítico, inmunidad, reacciones ni contraataque.

### Contrato y persistencia

12. `app/Livewire/Combate.php` sin cambios; 32 propiedades y 18 métodos públicos.
13. Sin migraciones; `SESSION_VERSION` sigue en 21.
14. Sesiones antiguas cargan sin error; `slug=''` no habilita modo aliado; `AQUA_RING` antiguo sigue 1/16 permanente.
15. `curacionFija` default `0.0`; sesiones nuevas conservan el 5% fijado.
16. `DepuradorMovimientos` sigue deduplicando loadouts.

### UI

17. Al seleccionar aliado, el panel muestra `SOBRE ALIADO`, la cantidad calculada y deshabilita movimientos no usables.
18. “Cambiar objetivo” vuelve a `player_target`.
19. El badge de `AQUA_RING` muestra 5→4→… y desaparece; `buffTurns` muestra las rondas restantes.
20. Sin cambios visuales inesperados en el modo rival.

### Verificación estática

21. Pint, PHPStan, deptrac, `php -l`, `view:cache`, `view:clear`, `git diff --check` sin errores.
22. Smoke manual de los 12 pasos del addendum v2.

---

## 9. Verificación requerida

- **Análisis estático:** Pint, PHPStan, deptrac, `php -l` de todos los archivos tocados, `php artisan view:cache`/`view:clear`, `git diff --check`.
- **Comprobaciones de contrato:** `git diff app/Livewire/Combate.php` vacío; `esAutodirigido()` sin cambios; `database/migrations` sin cambios.
- **Smoke manual de navegador:** valores controlados del addendum v2:
  - Lanzador: PS 300, Def 120, Def. Esp. 100.
  - Aliado: PS 320 (actual 100), escudo físico 50/320, escudo especial 20/320.
  - Bucle Arena → +22 DEF; Giro Fuego → +20 DEF.ESP; Recuperación → +160 PS (100→260); Torbellino → 25 PS/ronda durante 5 rondas.
  - Escudo al máximo, PS llenos, valores bajos, lanzador debilitado, modo propio, modo rival, recarga de sesión.
- **Tests:** no se ejecutan, no se crean, no se modifican.
- **Sin `git push`.**

---

## 10. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Divergencia preview/real por redondeo | Única fuente `CalculadoraCuracionAliada` para preview y ejecución |
| `DepuradorMovimientos` con `slug` rompe deduplicación | `unset($datos['slug'])`; verificar firma |
| Serialización antigua sin `slug` | `?? ''` en `__unserialize` y `fromLivewire` |
| `AQUA_RING` legado permanente | Decrementar solo si `turnos > 0`; `curacionFija=0` mantiene 1/16 |
| `curacionFija` en rollback | Deprecation no fatal en PHP 8.2+; documentar |
| Orden cura/decremento | Curar antes de decrementar; smoke de 5 curas |
| Defensa efectiva vs base | Se adopta efectiva; documentar |
| Escudo al máximo / PS llenos | 0 y consume turno/PP |
| Caster ausente | `curacionFija` guarda el 5%; no depende del lanzador |
| Cambio de auth | `jugadorAutorizado()` en `previewTarget`/`selectMove`; smoke de turnos |
| Aliados clicables confunden | Badge, preview y “Cambiar objetivo” |
| Regresión en modo rival | Rama aliada exige mismo equipo + catálogo; defensa en profundidad |

---

## 11. Rollback

- Revertir los archivos modificados/creados.
- No hay migraciones que deshacer.
- Las sesiones con `slug` son legibles por el código antiguo (clave desconocida ignorada).
- Las sesiones con `curacionFija` pueden emitir `Deprecated: Creation of dynamic property` con el código antiguo, sin fatal.
- Las sesiones con `AQUA_RING turnos=5` vuelven a tratarse como permanentes con el procesador antiguo, sin excepción.
- No se pierden datos ni partidas.

---

## 12. Aprobación solicitada

Aprobar la ejecución del plan v2 con las decisiones de la sección 4. En particular:

- Escudos: `max/32 + 10%` de la stat efectiva del lanzador, `floor` + mínimo 1, tope en el máximo.
- Recuperación: 50% del PS máximo del receptor.
- Aqua Ring: `PSMaxReceptor/32 + 5%` de los PS máximos del lanzador, `floor` + mínimo 1.
- Aqua Ring aliado: 5 rondas, 5% fijado; Aqua Ring propio: 1/16 durante 5 rondas.
- Buffs solo al aliado; acción de apoyo rompe combo.
- Solo vanguardia viva; flujo target-first.
- Badge `buffTurns`; `jugadorAutorizado()` en `previewTarget`/`selectMove`.
- Identidad por `slug`; sin migración; `SESSION_VERSION` 21.

Si alguna decisión debe cambiar, indica cuál antes de aprobar.
