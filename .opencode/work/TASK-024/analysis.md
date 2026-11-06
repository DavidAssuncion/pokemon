# TASK-024 — Análisis

- **Estado:** COMPLETADO
- **Autor:** `analyst` (`ses_e4e33e5caffejDHZf65pC39N13`)
- **Referencia previa:** `.opencode/work/TASK-023/analysis.md` §Fase 6B.
- **Alcance:** `Livewire/Combate.php`, presentadores, `combate_page.blade.php`, `livewire/combate.blade.php` y parciales, `equipos/index`, `habitats/show`, JS.

## 1. Contrato Livewire de `Combate.php` (1.919 líneas)

### Propiedades públicas

- Usadas por Blade/JS: `battleId`, `currentMoves`, `phase`, `round`, `processing`, `anim*`, `habitatId`, `eventos`, `prioridades`, `posturas`, `preparacionPendiente`, `prioridadesDisponibles`, `objetosCombate`, `objetoSeleccionado`, `objetivosItem`.
- Solo internas/presentador: `team1`, `team2`, `turnQueue`, `selectedMoveIdx`, `log`, `actingRefId`, `weather`, `rewards`, `eventoTick`, `selectedTarget*`.

### Métodos públicos

- Ciclo Livewire: `boot`, `mount`, `render`.
- Usados por Blade/JS: `confirmarPreparacion`, `saltarPreparacion`, `cambiarPostura`, `commitAction`, `previewTarget`, `selectMove`, `seleccionarObjeto`, `cancelarObjeto`, `swapTurno`, `retrasarTurno`, `pasarTurno`.
- Solo internos: `nuevaBatalla`, `nextActor`, `cancelTarget`, `confirmarObjetivoItem` (candidatos a `private`, condicionados a tests).

### Ciclo de vida

- `boot()` resuelve `FabricaBatallaInterface` y `BattleSessionService` con `??=`.
- `mount()` lee `battle_id`, hidrata el agregado, sincroniza y llama `nextActor()`.
- `render()` delega en `BatallaVistaPresenter` y `MovesPreviewPresenter`.

## 2. Bloques de responsabilidad

| Rango | Bloque |
|---|---|
| 35-118 | Estado y dependencias |
| 119-201 | Lifecycle |
| 203-339 | `nextActor` y avance de turno |
| 341-388 | `advanceRound` / fin de batalla |
| 390-466 | Preparación visible |
| 468-609 | Preparación e IA de prioridades |
| 611-773 | Resolución de prioridades y objetivo |
| 775-800 | `endBattle` |
| 802-841 | IA: preparación de animación |
| 843-1024 | Ejecución de acción |
| 1026-1148 | Selección de objetivo/movimiento |
| 1150-1400 | Objetos |
| 1402-1523 | Turno del jugador |
| 1525-1615 | Helpers |
| 1617-1778 | Grabación de eventos de animación |
| 1780-1815 | `buildTurnQueue` |
| 1817-1862 | Estado de animación |
| 1864-1918 | `syncViewData` / objetos |

**Acoplamiento central:** cada método recarga el agregado desde sesión, muta, sincroniza, guarda y encadena `nextActor`. La grabación de eventos está entretejida en `nextActor`, `resolverAccion`, `resolverAccionObjeto` y `endBattle`.

## 3. JS y Alpine

### Funciones globales de `combate_page.blade.php` (`@push('scripts')`, 9-615)

22 funciones: `instantaneaVitales`, `restaurarVitales`, `aplicarEstadoVitales`, `actualizarNumero`, `buscarCarta`, `maximoVital`, `animarVitales`, `reproducirEventos`, `reproducirEvento`, `reiniciarAnimacion`, `animarDanio`, `animarCura`, `animarEstado`, `animarBarreraRota`, `animarKO`, `limpiarPildoras`, `mostrarPildora`, `pulsoRonda`, `mostrarModalVictoria`, `golpeStop`, `duracionEvento`, `esperar`; más un listener `shown.bs.modal`.

### `x-init` de `livewire/combate.blade.php` (4-101)

- `DURACION_ACCION = 700`; comprueba globales; bloquea interfaz con `inert`; manipula `.pokemon-card.anim-ataque`; espera `$wire.commitAction()`; lee `$wire.get('eventos')`; llama `reproducirEventos`; restaura log; desbloquea; muestra modal de victoria; reprograma.
- `programarAccion` depende de `$wire.get('animAttackerId')` y `$wire.$watch('animTick', ...)`.

### Orden de carga

- `layouts/app.blade.php:8` carga `app.js` como módulo (diferido); `app.js` inicia Livewire.
- `@stack('scripts')` en `layouts/app.blade.php:146`.
- `combate_page` empuja `@vite('combate.js')` (módulo) y un `<script>` clásico con las funciones.
- **Riesgo alto:** mover el bloque a un módulo Vite rompe la garantía de que las globales existan antes de que Alpine evalúe `x-init`. Mantener script clásico en `@push('scripts')`.

## 4. Vistas gigantes

### `equipos/index.blade.php` (3.284)

- 1-5 layout; 6 `x-data="favoritosApp()"`.
- 13-244 panel de equipos.
- 247-603 panel derecho (tabs, Pokémon, Huevos).
- 606-1179 modales: exploración, eliminar equipo, reemplazo, detalle de Pokémon.
- 1183-3284 `@push('scripts')`: `favoritosApp()`, datos `@json`, getters y métodos; 23 `fetch` con CSRF manual; sin `<form>` ni consultas Eloquent.

### `habitats/show.blade.php` (3.007)

- 1-5 layout; 6 `x-data="habitatShow()" x-init="init()"`.
- 8-127 columna izquierda.
- 130-475 columna derecha.
- 479-1518 modales: exploración, favoritos, formación, gestión, mazmorra, granja, bayas.
- 1521-3007 `@push('scripts')`: `habitatShow()`, datos `@json`, getters y métodos; 24 `fetch`; sin `<form>`.
- `@php` de presentación (bloqueos, fallbacks, `collect()->pluck()`), sin SQL.

### Duplicidades

- Shell de modal repetido 4 veces en Equipos y 7 en Hábitats.
- Modal de exploración casi equivalente.
- `onSpriteError`, `riesgoClass`, utilidades de tipos, helpers de nivel/CP.
- Patrones de fetch + CSRF + feedback repetidos.

## 5. Riesgos

| Riesgo | Nivel | Mitigación |
|---|---|---|
| Cambiar orden de scripts y romper animación/`commitAction` | Alto | Mantener script clásico en `@push('scripts')` |
| Hacer `private` métodos usados por tests/JS | Alto | Grep previo en tests; mantener públicos en la primera ola |
| Alterar grabación de eventos (`eventos`, `eventoTick`, `fin`) | Alto | Extraer helpers puros primero; comparar payload |
| Doble layout de `/combate` | Medio | No cambiar `render()` en esta tarea |
| Perder `@json` al extraer Alpine | Medio | Bootstrap `window.__datos = @json(...)` |
| CSRF repetido/desincronizado | Medio | Helper único `fetchJson` |
| Cambiar ids/estructura DOM | Bajo | Copia literal; conservar ids y clases |

## 6. Preguntas bloqueantes y resolución

1. **¿JS a `resources/js` o script clásico?** → Script clásico en parcial Blade dentro de `@push('scripts')`. Sin build obligatorio y sin romper el orden.
2. **¿Cambiar el layout de `/combate`?** → No. Se conserva `combate_page` + `@livewire('combate')`; no se toca `render()`.
3. **¿Métodos públicos a `private`?** → No en esta tarea. Se mantienen públicos; el grep de tests se usará solo para documentar.
4. **¿Partiales con cambio de DOM?** → No. Copia literal, mismos ids/clases/`wire:*`.
5. **¿Equipos y Hábitats en paralelo?** → Sí, son archivos disjuntos.

## 7. Recomendación de olas

1. **Ola 1 — Vistas gigantes (bajo riesgo):** extraer modales de `equipos/index` (611-1179) y `habitats/show` (479-1518) a parciales literales. Dos frentes paralelos.
2. **Ola 2 — JS de combate:** mover las 22 funciones de `combate_page` a `livewire/partials/combate-scripts.blade.php`, incluyéndolo con `@include` en el mismo `@push('scripts')`. Sin tocar el `x-init`.
3. **Ola 3 — `Combate.php`:** extraer colaboradores sin estado para eventos y sincronización de vista, manteniendo propiedades y métodos `wire` públicos.
4. **Ola 4 — Componentes compartidos:** unificar shell de modal y modal de exploración entre Equipos y Hábitats.

Cada ola requiere plan y aprobación propios.
