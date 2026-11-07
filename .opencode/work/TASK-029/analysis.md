# TASK-029 — Análisis: movimientos usables sobre aliados

> Resultado literal de `analyst`. Estado: COMPLETADO. Sin modificaciones al repositorio ni ejecución de tests.

## 1. Resumen ejecutivo

- El dominio **ya tiene un enum de objetivos** (`ObjetivoMovimiento`) con soporte declarativo para aliados (`ADJACENT_ALLY`, `ADJACENT_ALLY_OR_SELF`, `ALLIES`, `ALLY_SIDE`, `ALLY_TEAM`), pero **la ejecución real no permite elegir un aliado distinto del propio actor**: `MovimientoBatalla::esAutodirigido()` y cuatro llamadores de Livewire/IA fuerzan `defender = actor`.
- **No existe precedente funcional de curación/buff a un aliado concreto**. Sí existen movimientos de equipo (`allies`, `allySide`, `allyTeam`) que funcionan sin selección, pero los buffs de `Howl`/`Coaching`/`Piñón Auxiliar`/`Niebla Aromática` se guardan en `self_effects` y el motor los aplica **al actor**, no a los aliados. Además, `ClaveEfectoMovimiento::STATS_ALIADO` **no tiene ejecutor registrado**.
- **«Escudo físico» = `defensaHpActual`** y **«escudo especial» = `defensaEspHpActual`** (`VitalidadCombatiente` / `BarrerasVidaManager`). La curación de escudos existe (`curarBarreras` cura ambos) y hay un primitivo de regeneración **solo física** (`EfectoRegeneracionDefensa`), pero **ningún movimiento del catálogo** cura escudos.
- Los tres movimientos del usuario (`Bucle Arena`, `Giro Fuego`, `Torbellino`) son `normal` (rival), potencia 35 + estado volátil `partiallytrapped` → daño residual 1/8 del HP máximo. **El catálogo no puede expresar hoy un efecto alternativo por tipo de objetivo**: un movimiento tiene un `target` y un efecto.
- El catálogo real en runtime es **`storage/data/moves.json`** (673 entradas) cargado por `MoveSeeder`; `active/catalogo_final_movimientos.csv` (938 filas) **no es la fuente de runtime**.
- Recomendación: MVP acotado a los movimientos con efecto equivalente ya existente (curas y boosts + Acua Aro) más un ejecutor nuevo para escudo físico/especial; dejar fuera IA-aliada, movimientos con coste propio y rediseño de fase/UI.

## 2. Modelo actual de objetivos y efectos

**Objetivo declarativo** — `src/Battle/Domain/Enums/ObjetivoMovimiento.php:11-29`:
`NORMAL`, `SELF`, `ALL_ADJACENT_FOES`, `ALL_ADJACENT`, `FILA_VANGUARDIA`, `FILA_RETAGUARDIA`, `FILA_OBJETIVO`, `ALLY_SIDE`, `FOE_SIDE`, `ALL`, `SCRIPTED`, `RANDOM_NORMAL`, `ANY`, `ADJACENT_ALLY`, `ADJACENT_ALLY_OR_SELF`, `ALLIES`, `ALLY_TEAM`.
`esUnSoloObjetivo()` (`:58-69`) incluye `ADJACENT_ALLY` y `ADJACENT_ALLY_OR_SELF`.

**Propagación al motor**: `Movimiento::objetivo` (`src/Battle/Domain/Movimiento.php:38`) → `aMovimientoBatalla()` (`:63-81`) → `MovimientoBatalla::objetivo` (`src/Battle/Domain/MovimientoBatalla.php:30`), serializado en `__serialize` (`:113`) y restaurado con fallback a `NORMAL` (`:146-147`).

**La puerta central: `esAutodirigido()`** — `MovimientoBatalla.php:194-215`:
```php
if (in_array($this->objetivo, [SELF, ALLY_SIDE, ALLIES, ALLY_TEAM], true)) return true;
return $this->esEstado() && $this->tieneSelfStatChanges() && ! $this->tieneTargetStatChanges();
```
El comentario dice «apunta al propio usuario/aliados», pero **todos los llamadores lo traducen a `defender = actor`**:
- `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php:114-122`
- `app/Livewire/Combate/Soporte/GestorAccionBatalla.php:98-104` (animación) y `:169-172` (resolución)
- `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php:149-162`
- `src/Battle/Domain/AI/SelectorAccionIA.php:206-217`

**Cómo se aplican los efectos**:
- `GestorEfectosMovimiento::aplicarStatChangesAMuchos` (`src/Battle/Domain/Danio/GestorEfectosMovimiento.php:144-184`): los `selfStatChanges` se aplican **siempre al actor** (`:148-152`); los `targetStatChanges` a cada objetivo (`:154-183`).
- `AplicadorEstadoMovimiento::aplicarEstadoYStatChanges` (`src/Battle/Domain/Ejecucion/AplicadorEstadoMovimiento.php:80-108`) resuelve objetivos con `ResolutorObjetivosDanio` y aplica estado y stats.
- `ResolutorObjetivosDanio::resolver` (`src/Battle/Domain/Danio/ResolutorObjetivosDanio.php:20-89`) solo expande multiobjetivo para `ALL_ADJACENT_FOES`, `ALL_ADJACENT`, `ALL` y filas. Para `SELF`/aliados devuelve `null` → flujo de un solo objetivo (`ServicioEjecucionBatalla.php:71-97`).
- `ResolutorImpacto::ejecutarContra` (`src/Battle/Domain/Ejecucion/ResolutorImpacto.php:41-76`) trabaja siempre contra `$accion->defender`; si el movimiento es de estado rompe combo (`:50-52`) y dispara `dispararSerObjetivo` cuando `defender !== attacker` (`:61-63`).
- Constructores de la acción: `EjecutorAccion::construirAccion` (`src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php:68-84`).

**Escudos (respuesta a la pregunta 5)** — `src/Battle/Domain/Componentes/Combatiente/VitalidadCombatiente.php:16-31`:
- `defensaHpActual` = escudo/barrera **física**.
- `defensaEspHpActual` = escudo/barrera **especial**.
- `recibirDano($dano, $isSpecial, ...)` (`:102-137`) elige la barrera según `isSpecial`.
- `curarBarreras($porcentaje)` (`:245-260`) cura **ambos** escudos; `curarConExcedente` (`:190-224`) cura HP con desbordamiento a escudo especial por defecto.
- `BarrerasVidaManager::curarBarreras` (`src/Battle/Domain/BarrerasVidaManager.php:59-76`).
- Único primitivo «solo escudo físico»: `EfectoRegeneracionDefensa::onRoundEnd` (`src/Battle/Domain/Effects/EfectoRegeneracionDefensa.php:29-38`) — es efecto pasivo, no movimiento. **No existe primitivo «solo escudo especial»**.

**Daño porcentual de las trampas** — `src/Battle/Domain/Componentes/Agregado/ProcesadorCondicionesRonda.php:86-96`: `ATRAPADO` hace `hpMax * 0.125` y llama `recibirDaño($dano, ! $condicion->esFisico, 0.0)`. En `GestorEfectosMovimiento.php:301-313` se construye `CondicionVolatil` con `esFisico: $clave === ClaveCondicion::ATRAPADO` y `fraccionDano: 0.125`. **Observación a verificar**: con esa expresión literal, todo `ATRAPADO` saldría marcado `esFisico=true` independientemente de la categoría (Giro Fuego y Torbellino son especiales). No he leído `CondicionVolatil` para confirmar la semántica final.

## 3. Inventario de movimientos existentes relevantes

Fuente de verdad: `storage/data/moves.json` (673 entradas). Cargado por `database/seeders/MoveSeeder.php:20-37` (`storage_path('data/moves.json')`, upsert por `slug`); `:39-40` elimina `estallido_limite`.

Distribución de `target` (673):
`normal` 492 · `self` 78 · `allAdjacentFoes` 51 · `allAdjacent` 17 · `all` 8 · `allies` 7 · `allySide` 6 · `adjacentAlly` 4 · `foeSide` 3 · `allyTeam` 3 · `filaObjetivo` 2 · `scripted` 1 · `adjacentAllyOrSelf` 1.

**Los 9 movimientos del usuario + Acua Aro como referencia** (nombres exactos de catálogo):

| Nombre exacto | slug | target | categoría | efecto actual |
|---|---|---|---|---|
| Bucle Arena | `sand-tomb` | `normal` | físico, 35 | secundario `partiallytrapped` (100%) |
| Giro Fuego | `fire-spin` | `normal` | especial, 35 | secundario `partiallytrapped` (100%) |
| Torbellino | `whirlpool` | `normal` | especial, 35 | secundario `partiallytrapped` (100%) |
| Maquinación | `nasty-plot` | `self` | estado | `self_effects` spa +2 |
| Paz mental | `paz_mental` | `self` | estado | `self_effects` spa +1, spd +1 |
| Danza Espada | `swords-dance` | `self` | estado | `self_effects` atk +2 |
| Danza Dragón | `dragon-dance` | `self` | estado | `self_effects` atk +1, spe +1 |
| Recuperación | `recover` | `self` | estado | `heal_percent` 50 |
| Corpulencia | `bulk-up` | `self` | estado | `self_effects` atk +1, def +1 |
| *(referencia)* Acua Aro | `aqua-ring` | `self` | estado | `effect_key: aqua_ring` → condición `AQUA_RING`, 1/16 HP por ronda |

Nota: el usuario enumera 9 movimientos objetivo + Acua Aro como modelo comparativo. `Paz mental` usa **minúscula y slug con guion bajo** (`paz_mental`), a diferencia de la convención kebab-case; cualquier mapeo debe hacerse por `slug`.

## 4. Listado adicional de candidatos

**Estado del catálogo**: no existe ningún movimiento con efecto «restaurar solo escudo físico» ni «restaurar solo escudo especial». Los candidatos de escudos son **propuesta de diseño**, no datos existentes. El resto son movimientos reales cuyo efecto puede redirigirse a un aliado.

### A. Curación de salud (`heal_percent` 50, salvo indicación)
| Nombre exacto | slug | Efecto actual | Efecto propuesto sobre aliado | ¿Existe? |
|---|---|---|---|---|
| Recuperación ★ | `recover` | cura 50% PS propio | cura 50% PS del aliado | Sí (ejemplo usuario) |
| Respiro | `roost` | cura 50% PS propio | cura 50% PS del aliado | Sí |
| Sol Matinal | `morning-sun` | cura 50% PS propio | cura 50% PS del aliado | Sí |
| Fotosíntesis | `synthesis` | cura 50% PS propio | cura 50% PS del aliado | Sí |
| Luz Lunar | `moonlight` | cura 50% PS propio | cura 50% PS del aliado | Sí |
| Ovocuración | `soft-boiled` | cura 50% PS propio | cura 50% PS del aliado | Sí |
| Batido | `milk-drink` | cura 50% PS propio | cura 50% PS del aliado | Sí |
| Relajo | `slack-off` | cura 50% PS propio | cura 50% PS del aliado | Sí |
| Auxilio | `heal-order` | cura 50% PS propio | cura 50% PS del aliado | Sí |
| Recogearena | `shore-up` | cura 50% PS propio | cura 50% PS del aliado | Sí |
| Descanso | `rest` | `effect_key: descanso` | cura total + sueño (requiere decisión) | Sí (excluir del MVP) |
| Acua Aro ★ | `aqua-ring` | condición `AQUA_RING` (1/16/ronda) en el usuario | condición en el aliado | Sí (modelo del usuario) |

### B. Escudo físico — **no existe en catálogo** (propuesta)
| Nombre exacto | slug | Efecto actual | Efecto propuesto sobre aliado | ¿Existe? |
|---|---|---|---|---|
| Bucle Arena ★ | `sand-tomb` | daño 35 + atrapado 1/8/ronda | restaura `defensaHpActual` del aliado | Sí (ejemplo usuario) |
| *(otros movimientos de trampa)* | — | — | mismo reskin de escudo físico | **No enumerado exhaustivamente**; solo confirmé `sand-tomb`, `fire-spin`, `whirlpool` con `partiallytrapped` |

### C. Escudo especial — **no existe en catálogo** (propuesta)
| Nombre exacto | slug | Efecto actual | Efecto propuesto sobre aliado | ¿Existe? |
|---|---|---|---|---|
| Giro Fuego ★ | `fire-spin` | daño 35 + atrapado 1/8/ronda | restaura `defensaEspHpActual` del aliado | Sí (ejemplo usuario) |
| Torbellino ★ | `whirlpool` | daño 35 + atrapado 1/8/ronda | **el usuario pide salud, no escudo**: restaurar PS tipo Acua Aro | Sí (ejemplo usuario) |

> **Ambigüedad detectada**: el usuario asigna a Torbellino «restaura la salud, como Acua Aro», no escudo especial. Por tanto el reparto es: Bucle Arena → escudo físico; Giro Fuego → escudo especial; Torbellino → salud/regen. Lo dejo así, aunque rompe el paralelismo con los tipos de daño rival.

### D. Boosts de stats (`self_effects`)
| Nombre exacto | slug | Boost actual | Propuesto sobre aliado | ¿Existe? |
|---|---|---|---|---|
| Maquinación ★ | `nasty-plot` | spa +2 | spa +2 al aliado | Sí (usuario) |
| Paz mental ★ | `paz_mental` | spa +1, spd +1 | ídem al aliado | Sí (usuario) |
| Danza Espada ★ | `swords-dance` | atk +2 | ídem al aliado | Sí (usuario) |
| Danza Dragón ★ | `dragon-dance` | atk +1, spe +1 | ídem al aliado | Sí (usuario) |
| Corpulencia ★ | `bulk-up` | atk +1, def +1 | ídem al aliado | Sí (usuario) |
| Desarrollo | `growth` | atk +1, spa +1 | ídem al aliado | Sí |
| Meditación | `meditate` | atk +1 | ídem | Sí |
| Agilidad | `agility` | spe +2 | ídem | Sí |
| Pulimento | `rock-polish` | spe +2 | ídem | Sí |
| Aligerar | `autotomize` | spe +2 | ídem | Sí |
| Fortaleza | `harden` | def +1 | ídem | Sí |
| Refugio | `withdraw` | def +1 | ídem | Sí |
| Rizo defensa | `rizo_defensa` | def +1 | ídem | Sí |
| Barrera | `barrier` | def +2 | ídem | Sí |
| Armadura Ácida | `acid-armor` | def +2 | ídem | Sí |
| Defensa Férrea | `iron-defense` | def +2 | ídem | Sí |
| Retracción | `shelter` | def +2 | ídem | Sí |
| Rizo Algodón | `cotton-guard` | def +3 | ídem | Sí |
| Amnesia | `amnesia` | spd +2 | ídem | Sí |
| Carga | `charge` | spd +1 | ídem | Sí |
| Luminicola | `tail-glow` | spa +3 | ídem | Sí |
| Masa Cósmica | `cosmic-power` | def +1, spd +1 | ídem | Sí |
| A Defender | `defend-order` | def +1, spd +1 | ídem | Sí |
| Aura Magnética | `magnetic-flux` | def +1, spd +1 | ídem | Sí |
| Afilar | `sharpen` | atk +1 | ídem | Sí |
| Afilagarras | `hone-claws` | atk +1, precision +1 | ídem | Sí |
| Avivar | `work-up` | atk +1, spa +1 | ídem | Sí |
| Enrosque | `coil` | atk +1, def +1, precision +1 | ídem | Sí |
| Danza Aleteo | `quiver-dance` | spa +1, spd +1, spe +1 | ídem | Sí |
| Cambio de Marcha | `shift-gear` | atk +1, spe +2 | ídem | Sí |
| Geocontrol | `geomancy` | spa +2, spd +2, spe +2 | ídem | Sí |
| Danza Triunfal | `victory-dance` | +1 a todo | ídem | Sí |
| Bálsamo Osado | `take-heart` | +1 a todo + cura estado (`tomar_aliento`) | ídem + cura estado al aliado | Sí |
| Bastión Final | `no-retreat` | +1 a todo (`sin_retirada`) | ídem | Sí |
| Foco Energía | `focus-energy` | `effect_key: foco_energia` | crítico mejorado al aliado | Sí (propuesta) |
| Aguzar | `laser-focus` | `effect_key: foco_energia` | ídem | Sí (propuesta) |

### E. Estados / curación de estado
| Nombre exacto | slug | Efecto actual | Propuesto | ¿Existe? |
|---|---|---|---|---|
| Aromaterapia | `aromatherapy` | cura estado del equipo (`aromaterapia`) | ya es de equipo | Sí |
| Cascabel Cura | `heal-bell` | `effect_key: aromaterapia` | ya es de equipo | Sí |
| Alivio | `refresh` | cura estado propio (`refresco`) | cura estado del aliado | Sí (propuesta) |
| Purificación | `purify` | `adjacentAlly`; ejecutor cura al **atacante** (`EjecutorPurificar.php:26`) | corregir a aliado | Sí (ya declarado aliado) |
| Pulso Cura | `heal-pulse` | `adjacentAlly`, `curacion_aliado`, 50% | ya declarado aliado | Sí |
| Cura Floral | `floral-healing` | `adjacentAlly`, `curacion_aliado`, 50% | ya declarado aliado | Sí |
| Niebla Aromática | `aromatic-mist` | `adjacentAlly`, `stats_aliado`, spd +1 | ya declarado aliado (pero sin ejecutor) | Sí |
| Piñón Auxiliar | `gear-up` | `allies`, `stats_aliado`, atk +1/spa +1 | ya declarado (sin ejecutor) | Sí |
| Motivación | `coaching` | `allies`, `stats_aliado`, atk +1/def +1 | ya declarado (sin ejecutor) | Sí |
| Aullido | `howl` | `allies`, boost atk +1 en `self_effects` | aplicar al equipo/aliado | Sí |
| Gota Vital | `life-dew` | `allies`, `curacion_equipo`, 25% | equipo | Sí |
| Cura Selvática | `jungle-healing` | `allies`, `curacion_equipo_estado`, 25% | equipo | Sí |
| Plegaria Lunar | `lunar-blessing` | `allies`, `curacion_equipo_estado` | equipo | Sí |
| Bramido Dragón | `dragon-cheer` | `allies`, `dragon_cheer` | equipo | Sí |
| Acupresión | `acupressure` | `adjacentAllyOrSelf`, `acupresion` | aliado o self | Sí |

### F. Excluir explícitamente
- Con coste/daño propio: `Tambor` (`belly-drum`), `Deslome` (`fillet-away`), `Estruendo Escama` (`clangorous-soul`, `heal_percent: -33`), `Rompecoraza` (`shell-smash`, def/spd −1).
- Evasión (personal): `Doble Equipo` (`double-team`), `Reducción` (`minimize`).
- Autoprotección/sustituto: `Protección`, `Detección`, `Aguante`, `Sustituto`, `Levitón`.
- Campo/clima/terreno/trampas/objetos: `Pantalla de Luz`, `Reflejo`, `Tailwind`, `Neblina`, `Velo Sagrado`, `Conjuro`, `Aromaterapia` (ya equipo), climas y terrenos, `Mofa`, `Desafío`, etc.
- Todos los ataques con `potencia > 0` salvo los tres del usuario en su modo aliado.

## 5. Precedentes y movimientos que ya targetean aliados

- **Enum y datos**: 21 entradas con target de aliado (`allySide` 6, `allyTeam` 3, `allies` 7, `adjacentAlly` 4, `adjacentAllyOrSelf` 1).
- **Efectos de equipo reales**: `EjecutorCuracion` con `equipo=true` (`ColeccionEjecutoresMovimiento.php:87-89`; `EjecutorCuracion.php:37-43`) y `EfectoEquipo*` (`src/Battle/Domain/Effects/Equipo/`). Funcionan porque iteran `$battle->equipoDe($accion->attacker)`, no dependen de selección.
- **Selección de aliado**: el único precedente es el **uso de objetos** con `ObjetivosItem::ALIADO_VIVO` — `app/Livewire/Presenters/BatallaVistaPresenter.php:120-125`. La fase `player_target` normal solo marca seleccionables a `teamIdx === 1` (rival) (`:127-133`).
- **No hay precedente de buff/cura dirigido a un aliado concreto**:
  - `EfectoRegeneracionDefensa` cura escudo físico, pero solo como efecto pasivo de ronda.
  - `EjecutorAquaRing` aplica `AQUA_RING` hardcodeado a `$accion->attacker` (`EjecutorAquaRing.php:21-25`).
  - `STATS_ALIADO` existe en el enum (`ClaveEfectoMovimiento.php:97`) pero **no está registrado** en `ColeccionEjecutoresMovimiento` (`:21-105`); `Howl`, `Coaching`, `Gear Up` y `Aromatic Mist` no tienen ejecutor.
- **Riesgo de comportamiento actual**: movimientos `adjacentAlly` no autodirigidos (p. ej. `Pulso Cura`) al no estar en la lista de `esAutodirigido` toman el `defender` seleccionado, que en la UI **solo puede ser rival**, y `EjecutorCuracion` cura a ese `defender`. Hay que confirmarlo en ejecución (no hay tests), pero el camino de código lo permite. Lo marco como **hallazgo a verificar**.

## 6. Impacto técnico

### Dominio (`src/Battle/Domain/`)
- `MovimientoBatalla::esAutodirigido` (`:194-215`) es el punto que hoy impide apuntar a un aliado distinto del actor. Cualquier cambio afecta a los 4 llamadores y a movimientos de estado existentes.
- `GestorEfectosMovimiento::aplicarStatChangesAMuchos` (`:144-184`) aplica `selfStatChanges` **siempre al actor**; para buffear aliados hay que cambiar la semántica (nuevo parámetro o nuevo ejecutor), con riesgo de regresión en todos los buffs propios.
- `ResolutorObjetivosDanio` (`:20-89`) no contempla aliados; habría que añadir resolución de `ADJACENT_ALLY`/`ALLIES` cuando proceda.
- Para Bucle Arena/Giro Fuego/Torbellino en modo aliado: hoy no hay forma de que un movimiento con `potencia > 0` se convierta en «potencia 0 + cura de escudo» según objetivo. Opciones en §9.
- `ResolutorImpacto` (`:41-76`): el modo aliado debe evitar cadena de daño, precisión, inmunidad de tipo, crítico y `dispararSerObjetivo` (que dispara `ReaccionaAlSerObjetivo` cuando `defender !== attacker`).
- Escudos: reutilizar `VitalidadCombatiente::setDefensaHpActual/setDefensaEspHpActual` (`Combatiente.php:573-580`) o crear `curarBarreraFisica/Especial`; `BarrerasVidaManager::curarBarreras` cura ambos, no sirve tal cual para curas separadas.
- `AQUA_RING` reutilizable para Torbellino, pero `EjecutorAquaRing` debe dejar de hardcodear `attacker`.
- `ProcesadorCondicionesRonda` (`:86-96`, `:138-142`) define el daño residual y el regen: base para el reskin, no requiere cambios si se reutilizan claves.

### Livewire (`app/Livewire/`)
- `GestorSeleccionBatalla.php:114-122`, `GestorAccionBatalla.php:98-104/169-172`, `GestorPrioridadesBatalla.php:149-162`: fuerzan `defender = actor` para autodirigidos. Cambiarlos toca el contrato congelado.
- `ResolutorActorBatalla::objetivoPorSeleccion` (`:49-58`) ya soporta `teamIdx 0` (equipo propio) y `1` (rival); el backend puede resolver un aliado, falta permitirlo.
- `BatallaVistaPresenter::marcarInteraccion` (`:106-133`): solo hace seleccionables a `teamIdx === 1`. Requiere distinguir por movimiento (rival vs aliado).
- Contrato público de `Combate` congelado: evitar nuevas propiedades/métodos; reutilizar `selectedTargetTeam/Idx/RefId`.

### UI/JS
- `resources/views/livewire/_pokemon-card.blade.php:4`: `wire:click="previewTarget(team, idx)"` condicionado a `selectable`.
- `resources/views/livewire/partials/moves-panel.blade.php:22-32`: `selectMove(idx)` sin elección de bando.
- `resources/views/livewire/combate.blade.php` solo incluye parciales; el JS de combate vive en `resources/views/livewire/partials/combate-scripts.blade.php` y `resources/js/combate.js`.
- Cambio mínimo: marcar aliados seleccionables cuando el movimiento actual admita modo aliado, y mostrar en `moves-panel`/`MovesPreviewPresenter` el efecto aliado en lugar de daño.

### Sesión (`app/Support/BattleSessionService.php`)
- **No leído directamente en esta ejecución** (límite de pasos). `SESSION_VERSION = 21` proviene del contexto del orquestador.
- Evidencia indirecta de forma serializada: `MovimientoBatalla::__serialize` (`:101-125`) incluye `objetivo` y `__unserialize` (`:133-159`) tolera claves desconocidas; `VitalidadCombatiente::serializar` (`:45-55`) ya guarda `defensaHpActual` y `defensaEspHpActual`.
- **Conclusión condicional**: si el MVP reutiliza `AQUA_RING`, boosts existentes y los campos actuales de escudos, **no debería requerir subir `SESSION_VERSION`**. Si se añade una nueva `ClaveCondicion` (p. ej. regeneración de escudo persistente) o se cambia la semántica de una clave existente en sesiones guardadas, habría que evaluar bump. No puedo afirmarlo sin leer `BattleSessionService` y `PerfilMovimiento`.

### IA
- `SelectorAccionIA::generarAccionesCandidatas` (`:197-243`) genera acciones solo contra enemigos; el contexto sí expone `aliados` (`:157-159`) pero no se usan como objetivo.
- `EjecutorAccion::construirAccion` (`:68-84`) fuerza `defender` de la acción de IA.
- `EvaluadorAccionIA`/`CalculadoraDanioIA` no leídos: no puedo evaluar cómo puntuaría una acción de soporte a aliado. Riesgo: la IA elegiría curar/buffear aliados solo si se le añade heurística; si no, nunca los usará.

### Datos/migraciones
- `MovimientoFactory.php:138` hace `ObjetivoMovimiento::from($modelo->target)`: **añadir un target nuevo en JSON sin añadirlo al enum rompe la factoría**. Cualquier nuevo campo de catálogo exige migración (`database/migrations/2026_10_22_000001_create_moves_table.php`, no leída) y autorización explícita.
- `storage/data/moves.json` es la fuente real; `active/catalogo_final_movimientos.csv` (938 filas) no se carga en runtime.

## 7. Riesgos y ambigüedades

- **[alto] `esAutodirigido` es un interruptor global**: añadir `ADJACENT_ALLY` o cambiar su semántica afecta a `SELF`, `ALLY_SIDE`, `ALLIES`, `ALLY_TEAM` y a todo movimiento de estado con `self_effects`. Impacto: regresión en buffs propios, curas y pantallas de equipo. Mitigación: introducir un predicado separado (`admiteObjetivoAliado()`) y no tocar `esAutodirigido` salvo para los movimientos autorizados.
- **[alto] Los `selfStatChanges` siempre se aplican al actor** (`GestorEfectosMovimiento.php:148-152`). Para buffear al aliado hay que cambiar el destino sin romper el auto-buff. Mitigación: ejecutor dedicado que aplique el boost al `defender` y no tocar la ruta genérica.
- **[alto] El catálogo no puede expresar dos efectos según objetivo** (Bucle Arena rival vs aliado). Sin campo/migración, obliga a mapa en dominio o duplicar movimientos. Impacto: deuda y riesgo de divergencia.
- **[medio] `STATS_ALIADO` sin ejecutor** (`ColeccionEjecutoresMovimiento.php:21-105`): `Howl`, `Coaching`, `Gear Up`, `Niebla Aromática` no aplican el efecto al aliado. Si se toma como precedente, se propaga el defecto. Mitigación: implementar/registrar el ejecutor en el MVP.
- **[medio] `EjecutorAquaRing` hardcodea `attacker`** (`:21-25`): Torbellino sobre aliado requeriría modificarlo. Mitigación: parametrizar el objetivo.
- **[medio] `Purificación` cura al atacante** (`EjecutorPurificar.php:26`): inconsistencia con su target `adjacentAlly`.
- **[medio] Movimientos `adjacentAlly` no autodirigidos pueden apuntar a un rival en la UI actual** (`BatallaVistaPresenter:127-133` solo permite team 1). Verificar Pulso Cura/Cura Floral.
- **[medio] `dispararSerObjetivo` y reacciones** (`ResolutorImpacto.php:61-63`): un aliado objetivo podría disparar habilidades de «al ser objetivo» y redirecciones. Verificar `ReaccionaAlSerObjetivo`, `RedirigeObjetivo`.
- **[medio] Ambigüedad funcional de Torbellino**: el usuario pide salud (Acua Aro), no escudo especial. Debe confirmarse; rompe el paralelismo con los otros dos.
- **[bajo/medio] `ATRAPADO` y `esFisico`**: la expresión `esFisico: $clave === ClaveCondicion::ATRAPADO` (`GestorEfectosMovimiento.php:312`) parece forzar barrera física para todas las trampas; verificar con `CondicionVolatil` y con Giro Fuego/Torbellino (especiales).
- **[bajo] `Paz mental` slug/name irregulares** (`paz_mental`): mapear por slug, no por nombre.
- **[bajo] Claves duplicadas en `ClaveCondicion::desdePs`** (`taunt` en `:117` y `:131`; `match` devuelve la primera): deuda preexistente, no bloquea.
- **[bajo] `EjecutorCuracion.php:29-32`** accede a `perfil->toArray()['curacionFraccion']` y luego a `$accion->move->perfil->curacionFraccion`: redundante, indica zona frágil.
- **Sin tests**: la validación de estos caminos es estática únicamente por restricción del proyecto.

## 8. Preguntas para el desarrollador

1. ¿El combate es 3v3 con vanguardia/retaguardia como muestra el campo (`battle-field.blade.php:19-64`)? ¿El aliado objetivo puede ser cualquiera vivo del equipo propio, incluido uno en retaguardia?
2. Para **Bucle Arena/Giro Fuego/Torbellino** en modo aliado: ¿deben **no hacer daño** y **no aplicar atrapado**, consumiendo turno/PP igual? ¿O se mantiene algo del efecto ofensivo?
3. **Torbellino**: ¿salud (Acua Aro, 1/16 por ronda) o curación instantánea? El texto dice «como Acua Aro», lo que implica condición persistente, no cura inmediata.
4. ¿Los porcentajes de curación de escudo son fijos (p. ej. 50% del máximo) o se reutilizan los del rival? ¿Afectan a un escudo vacío o solo al dañado?
5. ¿Los buffs (`Maquinación`, `Paz mental`, `Danza Espada`, `Danza Dragón`, `Corpulencia`) aplican al aliado **y también al usuario**, o solo al aliado?
6. ¿Se permite usar el modo aliado sobre el **propio usuario** (equivalente a `SELF`) o solo sobre otro miembro del equipo?
7. ¿Debe la IA usar estos movimientos sobre aliados? ¿Con qué prioridad frente a atacar?
8. ¿El MVP incluye solo los 9 movimientos del usuario o también candidatos de §4?
9. ¿Se autoriza un campo nuevo en catálogo (`ally_effect_key` o similar) con su migración, o se prefiere un mapeo en dominio sin tocar el esquema?
10. ¿Se autoriza modificar `SESSION_VERSION` si el diseño lo exige?

## 9. Alcance MVP recomendado

**MVP (fase 1)** — sin migraciones, sin cambiar el contrato público de `Combate`, sin SESSION_VERSION:
1. **Curas instantáneas**: `Recuperación` (`recover`) → cura 50% PS al aliado seleccionado. Reutiliza `EjecutorCuracion`/`curacionFraccion`; requiere que el objetivo aliado llegue como `defender` (no forzar `actor`).
2. **Buffs de stats**: `Maquinación`, `Paz mental`, `Danza Espada`, `Danza Dragón`, `Corpulencia` → aplicar `self_effects` al aliado. Necesita ejecutor/modo aliado que aplique los boosts al `defender`.
3. **Torbellino → Acua Aro**: parametrizar `EjecutorAquaRing` para aplicar `AQUA_RING` al `defender` en modo aliado y `potencia=0`.
4. **Bucle Arena → escudo físico** y **Giro Fuego → escudo especial**: nuevos ejecutores de cura de un solo escudo (usar `setDefensaHpActual`/`setDefensaEspHpActual`), activados solo en modo aliado. No hay primitivo existente para «solo especial»; crearlo.
5. **UI mínima**: en fase de selección, marcar como seleccionables los aliados vivos **solo si el movimiento en curso admite modo aliado**; reutilizar `selectedTargetTeam/Idx`. No añadir fase nueva.
6. **Mapeo**: preferible un mapa explícito `slug → comportamiento aliado` en dominio (sin migración), con los 6 slugs afectados. Alternativa más limpia pero con migración: nuevo campo en `moves`.

**Fase 2 (posterior)**:
- Alinear los movimientos `allies`/`adjacentAlly` existentes: registrar `STATS_ALIADO`, corregir `AQUA_RING`/`Purificar`, decidir `Pulso Cura`.
- Extender el modo aliado a los candidatos de §4D (Agilidad, Barrera, Defensa Férrea, A Defender, Danza Aleteo, etc.) y §4A (Respiro, Batido, etc.).
- Curación de estado a aliado (`Alivio`, `Aromaterapia` ya de equipo).
- Extender a más trampas para escudos, si el desarrollador lo desea.

**Fase 3 (deferida)**:
- IA con soporte a aliados (`SelectorAccionIA` + `EvaluadorAccionIA`).
- `Bramido Dragón`/`Danza Revelación` y efectos de equipo con selección.
- Revisión de `SESSION_VERSION` si se añaden claves persistentes nuevas.

## 10. Evidencia y limitaciones

**Evidencia principal**
- `src/Battle/Domain/Enums/ObjetivoMovimiento.php:11-29`, `:58-69`
- `src/Battle/Domain/Enums/ObjetivoEfecto.php:10-17`
- `src/Battle/Domain/Movimiento.php:27-57`, `:63-81`, `:86-123`
- `src/Battle/Domain/MovimientoBatalla.php:30`, `:101-125`, `:133-159`, `:194-215`
- `src/Battle/Domain/AgregadoBatalla.php:287-307`, `:395-403`
- `src/Battle/Domain/Ejecucion/ResolutorImpacto.php:41-76`
- `src/Battle/Domain/ServicioEjecucionBatalla.php:71-97`, `:114-143`
- `src/Battle/Domain/Danio/ResolutorObjetivosDanio.php:20-89`
- `src/Battle/Domain/Danio/GestorEfectosMovimiento.php:144-184`, `:301-313`
- `src/Battle/Domain/Ejecucion/AplicadorEstadoMovimiento.php:80-108`
- `src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php:68-84`
- `src/Battle/Domain/AI/SelectorAccionIA.php:157-159`, `:197-243`
- `src/Battle/Domain/Effects/Movimiento/ColeccionEjecutoresMovimiento.php:21-105`
- `src/Battle/Domain/Effects/Movimiento/EjecutorCuracion.php:27-58`
- `src/Battle/Domain/Effects/Movimiento/EjecutorAquaRing.php:21-25`
- `src/Battle/Domain/Effects/Movimiento/EjecutorPurificar.php:26`
- `src/Battle/Domain/Effects/EfectoRegeneracionDefensa.php:29-38`
- `src/Battle/Domain/Effects/EfectoCuracionFinTurno.php:62-71`
- `src/Battle/Domain/Enums/ClaveEfectoMovimiento.php:83-100`
- `src/Battle/Domain/Enums/ClaveCondicion.php:14-71`, `:108-140`
- `src/Battle/Domain/Componentes/Agregado/ProcesadorCondicionesRonda.php:48-98`, `:138-142`
- `src/Battle/Domain/Componentes/Combatiente/VitalidadCombatiente.php:16-31`, `:102-137`, `:190-224`, `:245-260`
- `src/Battle/Domain/BarrerasVidaManager.php:30-76`
- `src/Battle/Domain/Combatiente.php:573-580`, `:742-769`
- `src/Battle/Infra/Factories/MovimientoFactory.php:138`
- `app/Livewire/Combate/Soporte/GestorSeleccionBatalla.php:114-122`
- `app/Livewire/Combate/Soporte/GestorAccionBatalla.php:98-104`, `:169-172`
- `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php:149-162`
- `app/Livewire/Combate/Soporte/ResolutorActorBatalla.php:49-58`
- `app/Livewire/Presenters/BatallaVistaPresenter.php:106-133`
- `resources/views/livewire/_pokemon-card.blade.php:4`, `:72-94`
- `resources/views/livewire/partials/moves-panel.blade.php:22-32`
- `resources/views/livewire/partials/battle-field.blade.php:19-64`
- `database/seeders/MoveSeeder.php:11-41`
- `storage/data/moves.json` (673 entradas, inventario completo de `self` y targets de aliado)
- `active/catalogo_final_movimientos.csv` (938 filas; no es fuente de runtime)

**Limitaciones de esta ejecución (máximo de pasos alcanzado)**
- No leí `app/Support/BattleSessionService.php` ni `PerfilMovimiento`: la conclusión sobre `SESSION_VERSION` es condicional.
- No leí `app/Livewire/Presenters/MovesPreviewPresenter.php`, `EvaluadorAccionIA`, `CalculadoraDanioIA`, `AplicadorCuracion`, `CondicionVolatil` ni `EjecutorDragonCheer`/`EjecutorDanzaRevelacion`.
- No verifiqué en ejecución los hallazgos sobre `ATRAPADO`/`esFisico`, Pulso Cura/Cura Floral ni la ausencia de efecto de `STATS_ALIADO` (restricción: sin tests).
- No enumeré exhaustivamente todos los movimientos con `partiallytrapped` ni los 673 del catálogo; confirmé los 9 del usuario + Acua Aro, los 78 `self` y los 21 con target de aliado.
- No revisé `database/migrations/2026_10_22_000001_create_moves_table.php` ni el esquema real de `moves`.

---

# TASK-029 — Addendum: duración de 5 rondas

> Segundo resultado literal de `analyst`. Estado: COMPLETADO. Sin modificaciones al repositorio ni ejecución de tests.

## 1. Resumen ejecutivo

- **La duración de 5 rondas ya existe y ya se aplica a los boosts de stats**: `MultiplicadoresStats::TURNOS_TEMPORALES = 5` (`src/Battle/Domain/ValueObjects/MultiplicadoresStats.php:25`), con descuento por ronda en `MotorRondas::aplicarEfectosPorCombatiente` → `Combatiente::avanzarMultiplicadoresTemporales()` (`src/Battle/Domain/Componentes/Agregado/MotorRondas.php:195`; `src/Battle/Domain/Combatiente.php:612-615`). Por tanto, el «modo propio actual» de Maquinación, Paz mental, Danza Espada, Danza Dragón y Corpulencia **ya dura 5 rondas**; el requisito no exige cambios en ellos.
- **También existen precedentes de condiciones de 5 rondas**: `CRITICO_MEJORADO` con `turnos=5` en `EjecutorFocoEnergia` (`:16`, `:27-30`) y en `EjecutorDragonCheer` (`:26`), descontado en `ProcesadorCondicionesRonda::aplicarContadores` (`:153-170`). `LEVITACION` también usa 5 (`EjecutorLevitacion.php:23`).
- **El único efecto persistente que hoy NO caduca es `AQUA_RING`** (`CondicionVolatil` con `turnos=0` en `EjecutorAquaRing.php:23`, sin caso en `ProcesadorCondicionesRonda`): es permanente hasta KO/revivir. Es el cambio real que exige el requisito en modo propio.
- **Curas de PS, curación de estados y restauración puntual de escudos son instantáneas por naturaleza** (no hay concepto de «buff con caducidad» sobre esos recursos). Recomiendo dejarlas fuera de la regla de 5 rondas y decirlo explícitamente.
- **No hace falta migración ni subir `SESSION_VERSION`**: `turnos` ya está en `CondicionVolatil` y en `MultiplicadoresStats`, y ambos se serializan. Matiz importante: subir `SESSION_VERSION` **por sí solo no invalida** sesiones antiguas, porque `cargar()` solo rechaza versiones `< MINIMUM_COMPATIBLE_SESSION_VERSION` (9).
- **Conflicto detectado**: los efectos de equipo ya tienen duraciones propias (Pantalla/Reflejo 10, Tailwind 4, Velo Sagrado y Neblina 5). Forzar 5 rondas a *todos* los efectos beneficiosos chocaría con esas cifras. Recomiendo excluirlos del alcance y documentarlo.

## 2. Modelo actual de duración (rutas/líneas)

### 2.1 Condiciones volátiles (`CondicionVolatil`)
- Estructura: `src/Battle/Domain/ValueObjects/CondicionVolatil.php:17-37`. Campos relevantes: `ClaveCondicion $clave`, `int $turnos = 0`, `origenId`, `fraccionDano`, `vaDirectoAHp`, `esFisico`, `multiplicadorDanoRecibido`, `fraccionDanioCompartido`, `hpSustituto`/`hpMaxSustituto`, `tipoOverride`.
- Serialización: `toArray()` (`:42-59`) y `desdeArray()` (`:64-83`). En sesión se usa `serialize()` nativo (§6).
- Semántica de `turnos = 0`: **no es universal**. Para claves incluidas en los bucles de descuento, 0 implica retirada en la primera ronda; para claves no incluidas (`AQUA_RING`, `SIN_RETIRADA`) implica permanencia (`ProcesadorCondicionesRonda`).

### 2.2 Procesador de condiciones por ronda
`src/Battle/Domain/Componentes/Agregado/ProcesadorCondicionesRonda.php`:
- Recorre todas las condiciones del portador (`:18-30`); si el portador muere, registra, notifica y **rompe el bucle** (`:23-28`), por lo que el resto de condiciones no se procesa esa ronda.
- Condiciones de daño: `DRENADORAS`, `MALDICION`, `ATRAPADO`, `CANTO_MORTAL`, `MARIONETA_INERTE` (`:49-58`) con daño residual y decremento propio (`:60-98`).
- `AQUA_RING`: cura 1/16 del HP máximo **sin decrementar** (`:138-142`).
- `PROTEGIDO`/`AGUANTE`: se quitan al final de la ronda (`:132-136`).
- Contadores que descuentan y quitan: `LEVITACION`, `DESAFIO`, `SILENCIO`, `MARCADO`, `VINCULO_DANO`, `EMBARGO`, `IGNORA_HABILIDAD`, `VULNERABLE`, `MOFA`, `CRITICO_MEJORADO` (`:153-170`).
- `TIPO_ALTERADO`, `HABILIDAD_ANULADA`, `SUSTITUTO`: duración especial (`:172-197`).
- **No hay log de expiración**: `reducirYQuitar` (`:199-205`) quita en silencio.

### 2.3 Boosts de stats (capa temporal)
`src/Battle/Domain/ValueObjects/MultiplicadoresStats.php`:
- Dos capas: `base` y `temporales` (`:10-18`), con límite 0.25–4.0 (`:21-23`).
- `TURNOS_TEMPORALES = 5` (`:25`).
- `aplicarTemporal()` multiplica el stack y **reinicia la duración compartida a 5** (`:163-179`).
- `refrescarTemporal()` fija el valor exacto y reinicia a 5; lo usan objetos (`:186-203`; p. ej. `EfectoModificarStat.php:53`).
- `avanzarTemporales()` descuenta 1 y limpia todos los temporales al llegar a 0 (`:205-223`).
- Un **único contador** para todos los temporales del combatiente (`:33`, `:315-318`).
- Serialización con `turnos` (`:303-313`); restauración con compatibilidad legacy (`:96-126`).
- Aplicación desde movimientos: `CambioStat::aplicadoTemporalEn()` → `aplicarTemporal()` (`src/Battle/Domain/ValueObjects/CambioStat.php:76-79`), usado en `GestorEfectosMovimiento::aplicarCambioStat` (`src/Battle/Domain/Danio/GestorEfectosMovimiento.php:318-335`).
- Descuento por ronda: `MotorRondas::aplicarEfectosPorCombatiente` (`:184-197`, línea `:195`) solo para `combatientesVivos()` (`:186`).
- Reseteos que borran temporales: `EjecutorHaze` (`:23`), `EjecutorNieblaClara` (`:22`), `EfectoCuriousMedicine` (`:31`), objeto `EfectoRestaurarTodo` (`:47`), y `Combatiente::revivir` (`:234`).

### 2.4 Efectos de equipo (`EfectoEquipo*`)
- `EfectoEquipoSafeguard`: `turnos = 5` por defecto, decremento en `onRoundEnd` (`src/Battle/Domain/Effects/Equipo/EfectoEquipoSafeguard.php:15`, `:26-29`).
- `EfectoEquipoMist`: `turnos = 5` (`EfectoEquipoMist.php:15`, `:26-29`).
- `EfectoEquipoTailwind`: `turnosRestantes = 4` (`EfectoEquipoTailwind.php:19`, `:32-35`).
- `EfectoEquipoPantalla`: `turnosRestantes` y `capacidadRestante`; el ejecutor fija `turnos = 10` (`EjecutorPantalla.php:42`, `:49-54`), ×2 con Pantallista (`:44-47`).
- `EfectoEquipoAuroraVeil`: `turnosRestantes = 5` (`:19`).
- Reaplicación: `EquipoBatalla::agregarEfecto` solo añade (`src/Battle/Domain/EquipoBatalla.php:48-51`); `ColeccionEfectosEquipo` multiplica los efectos (`:37-45`, `:58-66`) → **reaplicar Tailwind suma otra instancia (×4 velocidad)**; **reaplicar Reflejo/Pantalla multiplica la reducción (0.25)**. Es comportamiento preexistente.
- Descuento: `ColeccionEfectosEquipo::onRoundEnd` (`:76-86`), invocado desde `MotorRondas` (`:77-78`).

### 2.5 Duraciones por movimiento (`PerfilMovimiento`)
`src/Battle/Domain/ValueObjects/PerfilMovimiento.php:39-85`: existen duraciones específicas por movimiento (`turnosDanoDiferido`, `turnosMaximosPotencia`, `turnosMarcado`, `turnosVinculoDano`, `turnosInvocacion`, `turnosObjeto`, `turnosHabilidad`, `turnosSustituto`, `turnosTipo`). Son datos del catálogo (`data`), independientes de la regla genérica de 5 rondas.

### 2.6 Curación y estados
- Curación de movimiento: `AplicadorCuracion` solo cura si `attacker === defender` (`src/Battle/Domain/Ejecucion/AplicadorCuracion.php:15-29`) → **instantánea, sin duración** y sin soporte actual de cura a aliado por esta vía.
- Curación a aliado/equipo: `EjecutorCuracion` (`:27-58`), instantánea.
- Estados mayores: `turnosEstado` en `EstadosCombatiente` (`:23`, `:61`, `:128-137`); sueño/confusión 2–4 en `GestorEfectosMovimiento::aplicarEstado` (`:77-81`).
- `AQUA_RING` en `ProcesadorCondicionesRonda:138-142` cura con desbordamiento a escudo (`curarConExcedente`), 1/16.

## 3. Clasificación persistente vs instantáneo

| Categoría | Persistente | Instantáneo | Evidencia |
|---|---|---|---|
| Escudos (`defensaHpActual`, `defensaEspHpActual`) | No como buff; son **recursos mutables** que bajan con daño (`VitalidadCombatiente.php:102-137`) | Restaurarlos es un cambio de recurso puntual | `BarrerasVidaManager.php:51-76`; `VitalidadCombatiente.php:245-260` |
| `AQUA_RING` | **Sí**, actualmente permanente | — | `ProcesadorCondicionesRonda.php:138-142`; `EjecutorAquaRing.php:21-25` |
| Boosts de stats | **Sí**, 5 rondas ya | — | `MultiplicadoresStats.php:25`, `:163-179`, `:205-223` |
| Crítico mejorado | **Sí**, 5 rondas | — | `EjecutorFocoEnergia.php:16-30`; `EjecutorDragonCheer.php:26`; `ProcesadorCondicionesRonda.php:165` |
| Curación de PS | No | **Sí** | `AplicadorCuracion.php:15-29`; `EjecutorCuracion.php:45-58` |
| Curación de estados | No | **Sí** | `EjecutorCuracion.php:52-56`; `EjecutorAromaterapia` (no leído en detalle) |
| Pantallas/Tailwind/Safeguard/Neblina | **Sí**, con duración propia (10/4/5/5) | — | `EjecutorPantalla.php:42`; `EfectoEquipoTailwind.php:19`; `EfectoEquipoSafeguard.php:15` |

**Conclusión de la clasificación**: solo los efectos que viven en el tiempo (condiciones y multiplicadores temporales) pueden «durar 5 rondas». Los recursos (HP, escudos) y las curas de estado no tienen duración; asignarles una exigiría inventar un modelo nuevo (buff temporal sobre el recurso).

## 4. Propuesta de efectos con 5 rondas

**Reciben 5 rondas** (persistentes):
1. **Boosts/debuffs de stats** (`self_effects`, `target_effects` con `chance=100`): ya vigente por `MultiplicadoresStats::TURNOS_TEMPORALES = 5`. No cambiar nada; solo verificarlo y documentarlo.
2. **`AQUA_RING`** (Torbellino/Ally y Acua Aro/Propio): cambiar de permanente a 5 rondas. Uso el campo existente `CondicionVolatil::turnos` y añado el caso al descuento por ronda. Es el único cambio de comportamiento en modo propio.
3. **`CRITICO_MEJORADO`** (Foco Energía/Aguzar, y Bramido Dragón): ya 5 rondas; mantener.
4. **Cualquier regeneración recurrente de escudo** que se decida implementar (ver §4 y §8): si se modela como condición con `turnos=5`, encaja sin cambios estructurales.

**Quedan fuera de la regla de 5 rondas** (instantáneos):
1. **Curación de PS** (Recuperación, Respiro, Batido…): un pico de HP no tiene duración; el HP recuperado permanece hasta que se dañe.
2. **Curación de estados** (Aromaterapia, Cascabel Cura, Alivio, Purificación): efecto puntual.
3. **Restauración puntual de escudo** (Bucle Arena → físico; Giro Fuego → especial): si se implementa como «rellenar el escudo una vez», el valor restaurado es un recurso, no un buff con caducidad. **Decisión abierta** (ver §8): si el desarrollador quiere que el escudo extra caduque a las 5 rondas, hay que modelarlo como condición de regeneración o como «escudo bonus temporal», que hoy no existe.
4. **Pantallas/Tailwind/Reflejo/Neblina/Velo Sagrado**: ya tienen duraciones deliberadas (10/4/5/5). Forzar 5 rompería el diseño existente y el equilibrio. Recomiendo excluirlos explícitamente.
5. **`SIN_RETIRADA`** (Bastión Final): hoy `turnos=0` y no se descuenta (`EjecutorSinRetirada.php:23`; no está en `aplicarContadores`). Si se quiere 5 rondas, es un cambio aparte que afecta a un movimiento no solicitado.

**Justificación**: la regla de 5 rondas tiene sentido para efectos cuyo valor es «estar activo durante N rondas». Aplicarla a recursos o curas puntuales no aporta nada y complica el modelo sin necesidad.

## 5. Modo propio vs modo aliado

### Modo propio actual
- **Stats**: ya 5 rondas. `aplicarTemporal` reinicia el contador a 5 y acumula el stack; `avanzarTemporales` descuenta al final de cada ronda. **No hay que cambiar nada** para Maquinación, Paz mental, Danza Espada, Danza Dragón ni Corpulencia.
- **Acua Aro**: hoy permanente (`turnos=0`, sin decremento). Pasa a 5 rondas. Afecta también al modo propio, tal como pide el desarrollador.
- **Foco Energía/Aguzar**: ya 5 rondas (`EjecutorFocoEnergia`).
- **Curación (Recuperación)**: instantánea, sin cambio.
- **Escudos**: no aplica hoy (no hay movimiento que los cure en modo propio).

### Modo aliado (cuando se implemente)
- Los boosts deben aplicarse al **aliado** con la misma capa temporal de 5 rondas (`aplicarTemporal`), sin tocar el contador del lanzador.
- `AQUA_RING` debe aplicarse al **aliado** con `turnos=5` (hoy `EjecutorAquaRing` lo fija al `attacker`).
- Curas y restauración de escudo: instantáneas, igual que en modo propio.

### Reaplicación (pregunta 4)
- **Stats**: reaplicar **acumula** (multiplicativo, con clamp 0.25–4.0) y **refresca** el contador **compartido** de todos los temporales del combatiente (`aplicarTemporal`, `MultiplicadoresStats.php:166-179`). No hay contador por stat ni por efecto.
- **`AQUA_RING`**: `EjecutorAquaRing` usa `reemplazarCondicion` (`:23`) → reaplicar **reinicia a 5** (si se fija `turnos=5`).
- **`CRITICO_MEJORADO`**: `EjecutorFocoEnergia`/`EjecutorDragonCheer` usan `agregarCondicion` (`:27`, `:26`), y `CondicionesCombatiente::agregar` **no reemplaza si ya existe** (`CondicionesCombatiente.php:61-66`) → **reaplicar no refresca**. Inconsistencia a tener en cuenta.
- **Efectos de equipo**: reaplicar **añade otra instancia** (`EquipoBatalla::agregarEfecto`), no refresca; los multiplicadores se multiplican (Tailwind ×4, pantallas al 25%). Riesgo preexistente.

### Caducidad
- **Cambio de Pokémon**: no existe mecánica de cambio en el código (no encontré `cambiarPokemon`/swap). Solo hay vanguardia/retaguardia fijas y invocaciones temporales (`GestorInvocaciones::retirarExpiradas`).
- **KO**: los temporales **no se descuentan** a combatientes muertos (`MotorRondas.php:186` filtra vivos). Al revivir, `Combatiente::revivir` reinicia estados, condiciones y stats (`Combatiente.php:232-235`), borrando buffs y `AQUA_RING`.
- **Fin de batalla**: la sesión se descarta; irrelevante.

## 6. Serialización y `SESSION_VERSION`

- **Forma de sesión**: `BattleSessionService::guardar` escribe `SESSION_VERSION.'|'.serialize($battle)` (`app/Support/BattleSessionService.php:71-74`); `cargar` hace `unserialize($payload)` (`:106-122`).
- **`CondicionesVolatil`**: se serializa con `serialize($condiciones)` (`SerializadorCombatiente.php:99-102`), invocado desde `CondicionesCombatiente::serializar` (`:36-39`). `turnos` viaja dentro del objeto.
- **`MultiplicadoresStats`**: `StatsCombatiente::serializar` (`:56-62`) → `toArray()` con `turnos` (`MultiplicadoresStats.php:303-313`).
- **Conclusión**: implementar 5 rondas con los campos existentes **no requiere campos nuevos, ni migración, ni subir `SESSION_VERSION`**.
  - Caso AQUA_RING: reutiliza `CondicionVolatil::turnos`.
  - Caso stats: reutiliza `MultiplicadoresStats::turnosTemporales`.
  - Caso regeneración de escudo como condición: reutiliza `turnos` (nueva `ClaveCondicion` en código, sin esquema).
- **Compatibilidad con sesiones guardadas**:
  - Las sesiones con `AQUA_RING` y `turnos=0` seguirían siendo permanentes hasta que se reaplique el movimiento. Si eso no es aceptable, hay que **invalidarlas**.
  - Matiz crítico: subir `SESSION_VERSION` de 21 a 22 **no invalida** nada por sí solo; `cargar` solo rechaza `< MINIMUM_COMPATIBLE_SESSION_VERSION` (9) (`BattleSessionService.php:100`). Para forzar reset habría que subir ese mínimo (rompe partidas en curso).
  - Si en el futuro se añaden propiedades nuevas a `CondicionVolatil`/`VitalidadCombatiente`, deben tener **valor por defecto** (los `desdeArray` usan `?? default`) para no romper payloads antiguos; `VitalidadCombatiente::desdeArray` ya lo hace (`:36-43`).
- **Recomendación**: no tocar `SESSION_VERSION` en el MVP. Solo plantearlo si se decide invalidar sesiones activas por el cambio semántico de `AQUA_RING`.

## 7. UI/JS e IA

### UI/JS
- **Dato ya disponible**: `CombatienteVista` calcula `buffTurns` (`src/Battle/Domain/CombatienteVista.php:84`) y `condicionesDetalle`/`condicionesBadge` con `turnos` (`:89-107`).
- **Render actual**: `_pokemon-card.blade.php` solo pinta `condicionesBadge` (`:60-64`) con `title="{{ $condicion['turnos'] }} turnos"`. **`buffTurns` no se pinta en ninguna vista** (grep sin resultados en `resources/views`).
- **Consecuencia**:
  - `AQUA_RING` con `turnos=5` aparecerá automáticamente en el badge con «5 turnos» → «4 turnos»… **sin tocar Blade**, porque ya se renderiza `condicionesBadge`.
  - Los **buffs de stats** no muestran rondas restantes; para ello habría que añadir un badge que use `buffTurns` en `_pokemon-card.blade.php` (zona de badges, `:52-69`). Es aditivo sobre `team1`/`team2` (arrays públicos), no cambia la firma de `Combate`.
  - Los **efectos de equipo** (Pantalla, Tailwind, Velo Sagrado, Neblina) **no se exponen** en la vista; no encontré campo en `BatallaVistaPresenter` ni en `CombatienteVista`. Exponerlos exigiría añadir datos al array de vista.
- **Expiración visible**: `ProcesadorCondicionesRonda` no registra log al quitar (`reducirYQuitar:199-205`), y `MultiplicadoresStats::avanzarTemporales` tampoco. No hay evento JS de expiración (`GrabadorEventosBatalla` genera `tipo: 'fin'`, `'battle_over'`, etc.; no hay tipo de caducidad).
  - Para mostrar «efecto terminado» haría falta log y/o evento nuevo. Es opcional y fuera del mínimo.
- **Animaciones**: el JS de `resources/views/livewire/combate.blade.php` y `resources/js/combate.js` reproduce `eventos`; no hay evento de expiración, así que no hay animación asociada.
- **Contrato congelado**: evitar nuevas propiedades/métodos públicos en `Combate`; usar los arrays `team1`/`team2`/`currentMoves` y el `battleField` del presentador.

### IA
- **Buffs propios**: la IA ya genera acciones autodirigidas (`SelectorAccionIA.php:206-217`) y los boosts ya duran 5 rondas; el requisito no altera el comportamiento actual, solo su valoración si el evaluador mira duración.
- **Buffs a aliados**: la IA **no genera acciones contra aliados**; solo enemigos (`SelectorAccionIA.php:197-243`, filtro de posición `:254-265`). Mientras no se añada ese objetivo (fuera del alcance de esta tarea), la duración no cambia nada en IA.
- **Evaluación**: no leí `EvaluadorAccionIAImpl` ni `CalculadoraDanioIA` en esta ejecución. **Conclusión condicional**: si el evaluador puntúa acciones solo por daño/supervivencia, una duración mayor o menor de un buff no alterará la elección; si se añade soporte a aliados, habría que incorporar el valor esperado de 5 rondas al score.

## 8. Riesgos y casos borde

| Riesgo | Detalle | Evidencia | Severidad |
|---|---|---|---|
| Contador compartido | Un solo `turnosTemporales` para todos los stats; reaplicar cualquier buff refresca todos los temporales | `MultiplicadoresStats.php:33`, `:163-179` | Medio |
| Acumulación al reaplicar | `aplicarTemporal` multiplica stack; Danza Espada repetida acumula hasta clamp 4.0 | `:166-179`, `:21-23` | Medio (diseño existente) |
| `agregarCondicion` no refresca | Foco Energía/Bramido Dragón no renuevan duración si ya está activa | `CondicionesCombatiente.php:61-66`; `EjecutorFocoEnergia.php:27`; `EjecutorDragonCheer.php:26` | Bajo |
| `turnos=0` ambiguo | Permanente solo si la clave no está en los bucles de descuento (`AQUA_RING`, `SIN_RETIRADA`) | `ProcesadorCondicionesRonda.php:126-197` | Medio |
| Orden cura/decremento | Si `AQUA_RING` se añade a `aplicarContadores`, cura antes de descontar → 5 curas; si se pone antes, 4 | `ProcesadorCondicionesRonda.php:44-46`, `:138-142`, `:153-170` | Alto (define el número real de curas) |
| «5 rondas completas» | El descuento ocurre al final de la ronda de aplicación; si actúa el último, el efecto solo cubre 4 rondas posteriores | `MotorRondas.php:165-197` | Medio (definir 5 vs 6) |
| KO | Los temporales no se descuentan a muertos; al revivir se borran todos | `MotorRondas.php:186`; `Combatiente.php:232-235` | Bajo |
| Corte del bucle por KO | Al morir el portador se dejan de procesar el resto de condiciones de esa ronda | `ProcesadorCondicionesRonda.php:23-28` | Bajo |
| Borrado temprano de buffs | Haze, Niebla Clara, Curious Medicine, objeto Restaurar Todo borran temporales | `EjecutorHaze.php:23`; `EjecutorNieblaClara.php:22`; `EfectoCuriousMedicine.php:31`; `EfectoRestaurarTodo.php:47` | Medio (intencional, documentar) |
| Duplicación de efectos de equipo | Reaplicar añade instancia y multiplica (Tailwind ×4) | `EquipoBatalla.php:48-51`; `ColeccionEfectosEquipo.php:37-45` | Medio (preexistente) |
| Pantallas y 5 rondas | Pantalla/Reflejo duran 10 (20 con Pantallista), Tailwind 4 | `EjecutorPantalla.php:42-47`; `EfectoEquipoTailwind.php:19` | Alto (conflicto de requisito) |
| Escudo «temporal» | No existe buff de escudo; solo recurso mutable | `VitalidadCombatiente.php:16-31`, `:245-260` | Alto (decisión de diseño) |
| Sesiones antiguas | AQUA_RING `turnos=0` seguiría permanente hasta reaplicar; subir versión no invalida | `BattleSessionService.php:100` | Bajo/Medio |

## 9. Tabla actualizada de candidatos con duración

Leyenda: **5r** = 5 rondas; **Inst** = instantáneo; **N/A** = no aplica / excluido.

### A. Curación de PS — todas **Inst**
| Nombre exacto | slug | Duración | Nota |
|---|---|---|---|
| Recuperación ★ | `recover` | Inst | ya `heal_percent: 50` |
| Respiro | `roost` | Inst | |
| Sol Matinal | `morning-sun` | Inst | |
| Fotosíntesis | `synthesis` | Inst | |
| Luz Lunar | `moonlight` | Inst | |
| Ovocuración | `soft-boiled` | Inst | |
| Batido | `milk-drink` | Inst | |
| Relajo | `slack-off` | Inst | |
| Auxilio | `heal-order` | Inst | |
| Recogearena | `shore-up` | Inst | |
| Descanso | `rest` | Inst + N/A | cura total + sueño 2–4; excluido del MVP |

### B. Escudos — **Inst** (o 5r si se modela como regeneración; decisión pendiente)
| Nombre exacto | slug | Duración propuesta | Nota |
|---|---|---|---|
| Bucle Arena ★ | `sand-tomb` | Inst | restauración puntual de `defensaHpActual`; sin buff temporal en el modelo |
| Giro Fuego ★ | `fire-spin` | Inst | restauración puntual de `defensaEspHpActual` |
| *(otras trampas)* | — | — | no enumeradas exhaustivamente; mismo criterio |

> Si el desarrollador quiere que el escudo restaurado «dure 5 rondas y luego se pierda», no se puede con el modelo actual: habría que crear una condición de escudo bonus/regeneración con `turnos=5`. Lo marco como **decisión bloqueante** para el diseño posterior.

### C. Persistente de salud
| Nombre exacto | slug | Duración | Nota |
|---|---|---|---|
| Acua Aro ★ | `aqua-ring` | **5r (cambio)** | hoy permanente (`turnos=0`); Torbellino sobre aliado usa esta misma condición |

### D. Boosts de stats — **5r (ya vigente)**
| Nombre exacto | slug |
|---|---|
| Maquinación ★ | `nasty-plot` |
| Paz mental ★ | `paz_mental` |
| Danza Espada ★ | `swords-dance` |
| Danza Dragón ★ | `dragon-dance` |
| Corpulencia ★ | `bulk-up` |
| Desarrollo | `growth` |
| Meditación | `meditate` |
| Agilidad | `agility` |
| Pulimento | `rock-polish` |
| Aligerar | `autotomize` |
| Fortaleza | `harden` |
| Refugio | `withdraw` |
| Rizo defensa | `rizo_defensa` |
| Barrera | `barrier` |
| Armadura Ácida | `acid-armor` |
| Defensa Férrea | `iron-defense` |
| Retracción | `shelter` |
| Rizo Algodón | `cotton-guard` |
| Amnesia | `amnesia` |
| Carga | `charge` |
| Luminicola | `tail-glow` |
| Masa Cósmica | `cosmic-power` |
| A Defender | `defend-order` |
| Aura Magnética | `magnetic-flux` |
| Afilar | `sharpen` |
| Afilagarras | `hone-claws` |
| Avivar | `work-up` |
| Enrosque | `coil` |
| Danza Aleteo | `quiver-dance` |
| Cambio de Marcha | `shift-gear` |
| Geocontrol | `geomancy` |
| Danza Triunfal | `victory-dance` |
| Bálsamo Osado | `take-heart` | boosts **5r**; `SIN_RETIRADA` interno hoy permanente (**N/A** a la regla) |
| Bastión Final | `no-retreat` | boosts **5r**; `SIN_RETIRADA` hoy permanente (**N/A**) |

### E. Crítico — **5r (ya vigente)**
| Nombre exacto | slug | Duración |
|---|---|---|
| Foco Energía | `focus-energy` | 5r (`EjecutorFocoEnergia:16`) |
| Aguzar | `laser-focus` | 5r |
| Bramido Dragón | `dragon-cheer` | 5r a aliados (`EjecutorDragonCheer:26`) |

### F. Curación de estados / soporte — **Inst**
| Nombre exacto | slug | Duración |
|---|---|---|
| Aromaterapia | `aromatherapy` | Inst |
| Cascabel Cura | `heal-bell` | Inst |
| Alivio | `refresh` | Inst |
| Purificación | `purify` | Inst |
| Pulso Cura | `heal-pulse` | Inst |
| Cura Floral | `floral-healing` | Inst |
| Gota Vital | `life-dew` | Inst |
| Cura Selvática | `jungle-healing` | Inst |
| Plegaria Lunar | `lunar-blessing` | Inst |

### G. Otros con target de aliado
| Nombre exacto | slug | Duración |
|---|---|---|
| Niebla Aromática | `aromatic-mist` | 5r (stats) |
| Piñón Auxiliar | `gear-up` | 5r (stats) |
| Motivación | `coaching` | 5r (stats) |
| Aullido | `howl` | 5r (stats) |
| Acupresión | `acupressure` | 5r (stats) |

### H. Efectos de equipo — **N/A a la regla de 5** (duración propia)
| Nombre exacto | slug | Duración actual |
|---|---|---|
| Pantalla de Luz | `pantalla_de_luz` | 10 (20 con Pantallista) |
| Reflejo | `reflejo` | 10 (20 con Pantallista) |
| Tailwind | `tailwind` | 4 |
| Velo Sagrado | `safeguard` | 5 |
| Neblina | `mist` | 5 |
| Velo Aurora | `aurora-veil` | 5 |
| Conjuro | `lucky-chant` | 5 (por defecto) |

### I. Excluidos (N/A)
`Tambor` (`belly-drum`), `Deslome` (`fillet-away`), `Estruendo Escama` (`clangorous-soul`), `Rompecoraza` (`shell-smash`), `Doble Equipo` (`double-team`), `Reducción` (`minimize`), `Protección`, `Detección`, `Aguante`, `Sustituto`, `Levitón`.

## 10. Conclusión, evidencia y limitaciones

### Conclusión
**Sí, el modelo actual permite 5 rondas sin migración.**
- Los **boosts de stats ya duran 5 rondas** (`MultiplicadoresStats::TURNOS_TEMPORALES = 5` + descuento en `MotorRondas:195`). No hay que cambiar nada para el modo propio.
- **`AQUA_RING`** puede pasar a 5 rondas usando `CondicionVolatil::turnos` y añadiendo su caso a `ProcesadorCondicionesRonda::aplicarContadores` (después de la cura, para obtener 5 curas). Sin campos nuevos.
- **Curas y restauración puntual de escudos** son instantáneas; no tiene sentido darles duración con el modelo actual.
- **Serialización**: no requiere campos nuevos, ni migración, ni `SESSION_VERSION`. Solo habría que decidir si se invalidan sesiones con `AQUA_RING` antiguo (subiendo `MINIMUM_COMPATIBLE_SESSION_VERSION`, no `SESSION_VERSION`).
- **UI**: `AQUA_RING` mostrará rondas automáticamente vía `condicionesBadge`; los buffs de stats requieren pintar `buffTurns` (dato ya calculado); los efectos de equipo no están expuestos.

**Cambios mínimos si se implementara** (enumeración, sin diseñar):
1. `EjecutorAquaRing`: fijar `turnos: 5` y aplicar al objetivo correcto (hoy `attacker`).
2. `ProcesadorCondicionesRonda::aplicarContadores`: añadir `AQUA_RING` al descuento (manteniendo la cura previa).
3. Reutilizar `MultiplicadoresStats` para boosts propios y de aliado; no cambiar su semántica.
4. (Opcional UI) pintar `buffTurns` en `_pokemon-card.blade.php`.
5. (Opcional) log/evento de expiración; hoy inexistente.
6. (Decisión) semántica de escudos: instantáneo vs regeneración de 5 rondas.
7. (Decisión) qué hacer con pantallas/Tailwind: excluirlos explícitamente de la regla.

### Evidencia principal
- `src/Battle/Domain/ValueObjects/CondicionVolatil.php:17-37`, `:42-59`, `:64-83`
- `src/Battle/Domain/Componentes/Agregado/ProcesadorCondicionesRonda.php:18-30`, `:44-46`, `:49-58`, `:86-96`, `:126-150`, `:153-170`, `:172-205`
- `src/Battle/Domain/ValueObjects/MultiplicadoresStats.php:21-25`, `:43-52`, `:96-126`, `:163-179`, `:186-203`, `:205-223`, `:303-318`
- `src/Battle/Domain/ValueObjects/CambioStat.php:76-79`
- `src/Battle/Domain/Danio/GestorEfectosMovimiento.php:144-184`, `:318-335`
- `src/Battle/Domain/Componentes/Agregado/MotorRondas.php:165-197` (descuento en `:195`), `:184-197`
- `src/Battle/Domain/Combatiente.php:232-235`, `:305-333`, `:612-615`
- `src/Battle/Domain/Componentes/Combatiente/StatsCombatiente.php:56-62`, `:70-84`, `:159-168`
- `src/Battle/Domain/Componentes/Combatiente/CondicionesCombatiente.php:36-39`, `:61-72`, `:196-199`
- `src/Battle/Domain/Componentes/Combatiente/SerializacionCombatiente.php:30-52`, `:54-74`
- `src/Battle/Domain/Componentes/Combatiente/SerializadorCombatiente.php:23-78`, `:99-102`, `:134-141`
- `src/Battle/Domain/Componentes/Combatiente/VitalidadCombatiente.php:16-31`, `:45-55`, `:102-137`, `:190-224`, `:245-260`
- `src/Battle/Domain/ValueObjects/ResistenciasTemporales.php:15-17`, `:70-99` (patrón análogo de 5)
- `src/Battle/Domain/Effects/Movimiento/EjecutorAquaRing.php:21-25`
- `src/Battle/Domain/Effects/Movimiento/EjecutorFocoEnergia.php:16-30`
- `src/Battle/Domain/Effects/Movimiento/EjecutorDragonCheer.php:21-29`
- `src/Battle/Domain/Effects/Movimiento/EjecutorLevitacion.php:23`
- `src/Battle/Domain/Effects/Movimiento/EjecutorSinRetirada.php:23`
- `src/Battle/Domain/Effects/Movimiento/EjecutorPantalla.php:42-54`
- `src/Battle/Domain/Effects/Movimiento/EjecutorTailwind.php:29`
- `src/Battle/Domain/Effects/Equipo/EfectoEquipoSafeguard.php:15`, `:26-29`
- `src/Battle/Domain/Effects/Equipo/EfectoEquipoMist.php:15`, `:26-29`
- `src/Battle/Domain/Effects/Equipo/EfectoEquipoTailwind.php:19`, `:32-35`
- `src/Battle/Domain/Effects/Equipo/EfectoEquipoAuroraVeil.php:19`, `:35-37`
- `src/Battle/Domain/Effects/Equipo/ColeccionEfectosEquipo.php:37-45`, `:76-86`
- `src/Battle/Domain/EquipoBatalla.php:48-51`
- `src/Battle/Domain/Effects/EfectoRegeneracionDefensa.php:29-38`
- `src/Battle/Domain/Effects/EfectoDanioFinTurno.php:28-74` (mecanismo de daño a escudos, no de buff)
- `src/Battle/Domain/Ejecucion/AplicadorCuracion.php:15-29`
- `src/Battle/Domain/Effects/Movimiento/EjecutorCuracion.php:27-58`
- `src/Battle/Domain/CombatienteVista.php:56-96` (`buffTurns` en `:84`, condiciones con `turnos` en `:89-107`)
- `resources/views/livewire/_pokemon-card.blade.php:52-69` (`condicionesBadge` renderizado en `:60-64`; `buffTurns` no usado)
- `app/Support/BattleSessionService.php:50`, `:64`, `:71-74`, `:100-122`
- `app/Livewire/Combate/Soporte/GestorPrioridadesBatalla.php:149-162`
- `src/Battle/Domain/AI/SelectorAccionIA.php:197-243`
- `src/Battle/Domain/ValueObjects/PerfilMovimiento.php:39-85`
- Catálogo: `storage/data/moves.json` (673 entradas; inventario de §9)

### Limitaciones
- No leí `EvaluadorAccionIAImpl`, `CalculadoraDanioIA`, `GrabadorEventosBatalla` completo ni `resources/js/combate.js`: las conclusiones sobre scoring de IA y sobre eventos de expiración son **condicionales**.
- No verifiqué en ejecución nada (sin tests); todos los hallazgos son estáticos.
- No enumeré exhaustivamente todos los movimientos con `partiallytrapped` ni los 673 del catálogo; confirmé los 9 del usuario + Acua Aro, los 78 `self` y los 21 con target de aliado del análisis previo.
- No modifiqué `.opencode/work/TASK-029/analysis.md`; la tabla con duración de §9 queda solo en esta respuesta.
- La decisión sobre **escudos con caducidad** y sobre **pantallas/Tailwind** es bloqueante para el diseño posterior: el modelo actual no soporta «escudo temporal» y las pantallas ya tienen duraciones propias distintas de 5.
