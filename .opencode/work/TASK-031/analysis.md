# TASK-031 — Análisis

- **Estado:** COMPLETADO (análisis)
- **Fecha:** 2026-11-07
- **Base:** rama `task-011`, HEAD `c020b6c` (TASK-030)
- **Método:** dos análisis independientes de `analyst`, en paralelo:
  - **Frente A:** curas, buffs, curación de estados, efectos de equipo y `STATS_ALIADO`.
  - **Frente B:** IA y prioridades con objetivo aliado.
- Sin implementación, sin tests, sin modificaciones de código.
- El contenido siguiente es el resultado de los analistas, sin conclusiones añadidas por el orchestrator.

---

# FRENTE A — Curas, buffs, estados, equipo y `STATS_ALIADO`

## A.1 Resumen ejecutivo

- El alcance nominal de esta parte son **38 slugs**: 9 curas de PS + 27 buffs + 2 de crítico. Si se excluye Bálsamo Osado por ser compuesto, quedan 26 buffs + 2 crítico = **28**.
- Las **9 curas** son homogéneas: `heal_percent: 50`, `target: self`, `effect_key: null`. Reutilizan `TipoEfectoAliado::CURAR_PS` sin cambios de fórmula.
- **25 de los 27 buffs** reutilizan `TipoEfectoAliado::BUFF_STATS`; todos usan `self_effects` y la ruta de stats ya aplica al aliado en modo aliado.
- **Bálsamo Osado** y **Bastión Final** no son buffs puros: necesitan un tipo compuesto (buff + cura estado / buff + `SIN_RETIRADA`).
- **Foco Energía** y **Aguzar** no pueden reutilizar un `TipoEfectoAliado::CRITICO_MEJORADO` porque **ese caso no existe**; existe la condición `ClaveCondicion::CRITICO_MEJORADO`. Requieren un tipo nuevo que aplique esa condición al aliado.
- La palanca segura sigue siendo `CatalogoEfectosAliado`: no hace falta tocar `esAutodirigido()`, `Combate`, migraciones ni `SESSION_VERSION`.
- Los buffs se convierten en `selfStatChanges` en `MovimientoFactory.php:37,151-152,215-228` y se aplican con `GestorEfectosMovimiento.php:144-184`; el modo aliado los redirige con `EjecutorEfectoAliado.php:130-157`.
- Las curas de PS se resuelven en `EjecutorEfectoAliado.php:85-99`, con la constante `FRACCION_RECUPERACION = 0.5` en `:24`.
- Riesgo inmediato de implementación: los `match` exhaustivos de `EjecutorEfectoAliado.php:41-47` y `MovesPreviewPresenter.php:328-351` deben actualizarse a la vez que cualquier caso nuevo.
- Geocontrol reutiliza `BUFF_STATS`, pero arrastra `carga_maxima: 1`; su mecánica de carga se trata en A.5.

## A.2 Inventario de los 9 movimientos de cura

Todos verificados en `storage/data/moves.json` (entrada por slug; el JSON no tiene números de línea citables). La conversión a dominio está en `src/Battle/Infra/Factories/MovimientoFactory.php:61` (`curacionFraccion = heal_percent / 100`).

| Slug | Nombre | `heal_percent` | `target` | `effect_key` | Verificación |
|---|---|---:|---|---|---|
| `roost` | Respiro | 50 | `self` | `null` | `storage/data/moves.json` entrada `roost`; `MovimientoFactory.php:61` |
| `morning-sun` | Sol Matinal | 50 | `self` | `null` | `storage/data/moves.json` entrada `morning-sun`; `MovimientoFactory.php:61` |
| `synthesis` | Fotosíntesis | 50 | `self` | `null` | `storage/data/moves.json` entrada `synthesis`; `MovimientoFactory.php:61` |
| `moonlight` | Luz Lunar | 50 | `self` | `null` | `storage/data/moves.json` entrada `moonlight`; `MovimientoFactory.php:61` |
| `soft-boiled` | Ovocuración | 50 | `self` | `null` | `storage/data/moves.json` entrada `soft-boiled`; `MovimientoFactory.php:61` |
| `milk-drink` | Batido | 50 | `self` | `null` | `storage/data/moves.json` entrada `milk-drink`; `MovimientoFactory.php:61` |
| `slack-off` | Relajo | 50 | `self` | `null` | `storage/data/moves.json` entrada `slack-off`; `MovimientoFactory.php:61` |
| `heal-order` | Auxilio | 50 | `self` | `null` | `storage/data/moves.json` entrada `heal-order`; `MovimientoFactory.php:61` |
| `shore-up` | Recogearena | 50 | `self` | `null` | `storage/data/moves.json` entrada `shore-up`; `MovimientoFactory.php:61` |

Notas de verificación:

- En modo propio curan porque `AplicadorCuracion.php:15-29` exige `attacker === defender` y usa `perfil->curacionFraccion`.
- En modo aliado, `EjecutorEfectoAliado.php:85-99` aplica `hpMax * 0.5` al `defender`; la constante está en `:24`.
- `PerfilMovimiento.php:38` define `curacionFraccion`; el `heal_percent` se mapea en `MovimientoFactory.php:61`.

## A.3 Inventario de buffs y crítico

Fuente: `storage/data/moves.json` (campo `self_effects`). Conversión: `MovimientoFactory.php:37` (`self_effects` → `autoEfectos`), `:151-152` (`cambiosPropios` → `selfStatChanges`) y `:215-228` (solo efectos al 100% se vuelven incondicionales). Aplicación: `GestorEfectosMovimiento.php:144-184`; redirección al aliado: `EjecutorEfectoAliado.php:130-157`.

### A.3.1 Buffs agrupados por conjunto de stats (27)

| Grupo | Slugs | Efecto exacto |
|---|---|---|
| Solo Velocidad | `agility`, `rock-polish`, `autotomize` | spe +2 |
| Solo Defensa (+1) | `harden`, `withdraw`, `rizo_defensa` | def +1 |
| Solo Defensa (+2) | `barrier`, `acid-armor`, `iron-defense`, `shelter` | def +2 |
| Solo Defensa (+3) | `cotton-guard` | def +3 |
| Solo Defensa Especial (+2) | `amnesia` | spd +2 |
| Solo Defensa Especial (+1) | `charge` | spd +1 |
| Solo Ataque Especial | `tail-glow` | spa +3 |
| Solo Ataque | `sharpen` | atk +1 |
| Ataque + Precisión | `hone-claws` | atk +1, accuracy +1 |
| Defensa + Defensa Especial | `cosmic-power`, `defend-order`, `magnetic-flux` | def +1, spd +1 |
| Ataque + Ataque Especial | `work-up` | atk +1, spa +1 |
| Ataque + Defensa + Precisión | `coil` | atk +1, def +1, accuracy +1 |
| Ataque + Velocidad | `shift-gear` | atk +1; spe +2 (dos `self_effects`) |
| At.Esp + Def.Esp + Velocidad (+1) | `quiver-dance` | spa +1, spd +1, spe +1 |
| At.Esp + Def.Esp + Velocidad (+2) | `geomancy` | spa +2, spd +2, spe +2 (tres `self_effects`); `data.carga_maxima: 1` |
| Las cinco stats (+1) | `victory-dance`, `take-heart`, `no-retreat` | atk +1, def +1, spa +1, spd +1, spe +1 |

Casos con `effect_key` propio dentro de este grupo:

- `take-heart` → `tomar_aliento`: `EjecutorTomarAliento.php:22-30` cura estado y sube +1 las cinco stats al **attacker**.
- `no-retreat` → `sin_retirada`: `EjecutorSinRetirada.php:21-25` aplica `CondicionVolatil(SIN_RETIRADA, 0)` al **attacker**; el buff de stats viene de `self_effects`.
- `geomancy` → sin `effect_key`, pero con `carga_maxima: 1` (`MovimientoFactory.php:70`).

### A.3.2 Crítico (2)

| Slug | Nombre | `target` | `effect_key` | Efecto actual |
|---|---|---|---|---|
| `focus-energy` | Foco Energía | `self` | `foco_energia` | `EjecutorFocoEnergia.php:25-31` aplica `CondicionVolatil(CRITICO_MEJORADO, 5)` al **attacker** |
| `laser-focus` | Aguzar | `self` | `foco_energia` | Reutiliza el mismo ejecutor; mismo efecto |

La condición existe en `ClaveCondicion.php:65` (`CRITICO_MEJORADO = 'critico_mejorado'`), pero **no** existe un `TipoEfectoAliado::CRITICO_MEJORADO`.

## A.4 Clasificación exacta

### A.4.1 Reutilizan `CURAR_PS` (9)

`roost`, `morning-sun`, `synthesis`, `moonlight`, `soft-boiled`, `milk-drink`, `slack-off`, `heal-order`, `shore-up`.

- Tipo existente: `TipoEfectoAliado::CURAR_PS` (`TipoEfectoAliado.php:15`).
- Catálogo: añadir slugs en `CatalogoEfectosAliado.php:21-31`.
- Ejecutor: `EjecutorEfectoAliado.php:85-99`.
- Todos son 50%, por lo que la constante `:24` es exacta. Recomendación: usar `perfil->curacionFraccion` para no acoplarlo.

### A.4.2 Reutilizan `BUFF_STATS` (25)

`agility`, `rock-polish`, `autotomize`, `harden`, `withdraw`, `rizo_defensa`, `barrier`, `acid-armor`, `iron-defense`, `shelter`, `cotton-guard`, `amnesia`, `charge`, `tail-glow`, `cosmic-power`, `defend-order`, `magnetic-flux`, `sharpen`, `hone-claws`, `work-up`, `coil`, `quiver-dance`, `shift-gear`, `geomancy`, `victory-dance`.

- Tipo existente: `TipoEfectoAliado::BUFF_STATS` (`TipoEfectoAliado.php:16`).
- Ejecutor: `EjecutorEfectoAliado.php:130-157`; usa `selfStatChanges` y aplica al aliado.
- `geomancy` reutiliza el tipo, pero conserva la restricción de carga (`PerfilMovimiento.php:48`; `MovimientoFactory.php:70`).

### A.4.3 `CRITICO_MEJORADO`: no hay reutilización directa (2)

`focus-energy`, `laser-focus`.

- No existe `TipoEfectoAliado::CRITICO_MEJORADO` en `TipoEfectoAliado.php:10-17`.
- Existe la condición `ClaveCondicion::CRITICO_MEJORADO` (`ClaveCondicion.php:65`).
- Requieren **tipo nuevo** que aplique `CondicionVolatil(CRITICO_MEJORADO, turnos)` al `defender`.
- Lógica reutilizable: `EjecutorFocoEnergia.php:25-31` (pero cambiando `attacker` por el aliado).

### A.4.4 Requieren `TipoEfectoAliado` nuevo o compuesto (2)

| Slug | Composición necesaria | Evidencia |
|---|---|---|
| `take-heart` (Bálsamo Osado) | `BUFF_STATS` + `CURAR_ESTADO` | `self_effects` +1 a las cinco; `EjecutorTomarAliento.php:22-30` cura estado |
| `no-retreat` (Bastión Final) | `BUFF_STATS` + `SIN_RETIRADA` | `self_effects` +1 a las cinco; `EjecutorSinRetirada.php:21-25` aplica la condición |

Matiz: el componente de buffs de ambos puede reutilizar la lógica de `BUFF_STATS`, pero como `CatalogoEfectosAliado::para()` devuelve un único `EfectoAliado`, hace falta un caso compuesto o extender el VO con sub-efectos.

### A.4.5 Resumen numérico

| Clasificación | Cantidad | Slugs |
|---|---:|---|
| `CURAR_PS` | 9 | las 9 curas |
| `BUFF_STATS` | 25 | los 25 buffs puros |
| Tipo nuevo crítico | 2 | `focus-energy`, `laser-focus` |
| Tipo compuesto | 2 | `take-heart`, `no-retreat` |
| **Total** | **38** | 9 + 27 + 2 |

## A.5 Casos especiales

### A.5.1 `take-heart` (Bálsamo Osado)

- Datos: `target: self`, `effect_key: tomar_aliento`, `self_effects` +1 a atk/def/spa/spd/spe (`storage/data/moves.json`, entrada `take-heart`).
- Ejecutor: `src/Battle/Domain/Effects/Movimiento/EjecutorTomarAliento.php:22-30` cura el estado del **attacker** y sube +1 las cinco stats al **attacker**.
- Ruta genérica: `app/Livewire/Combate/Soporte/ResolutorAccionBatalla.php:113-118` aplica `selfStatChanges` al actor si `resultado->golpes > 0`; en IA, `src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php:45` → `AplicadorEstadoMovimiento.php:80-108` → `GestorEfectosMovimiento.php:144-184`.
- **Riesgo**: si un movimiento de estado cuenta como un golpe (no verificado `CalculadorGolpes`), el +1 se aplicaría dos veces: una por `selfStatChanges` y otra por el ejecutor. Es una hipótesis fuerte, no confirmada en ejecución.
- Modo aliado: el camino dedicado de `ResolutorAccionBatalla.php:155-203` no pasa por `effect_key`; requiere tipo compuesto `BUFF_STATS + CURAR_ESTADO`.

### A.5.2 `no-retreat` (Bastión Final)

- Datos: `effect_key: sin_retirada`, `data: {"sin_retirada": true}`, `self_effects` +1 a las cinco stats.
- Ejecutor: `src/Battle/Domain/Effects/Movimiento/EjecutorSinRetirada.php:21-25` aplica `CondicionVolatil(SIN_RETIRADA, 0)` al **attacker**.
- `ClaveCondicion::SIN_RETIRADA` existe en `src/Battle/Domain/Enums/ClaveCondicion.php:70`; `turnos: 0` se interpreta como permanente (no hay decremento, coherente con el tratamiento de `AQUA_RING turnos=0` documentado en TASK-030).
- El buff de stats sí lo aplica la ruta genérica de `selfStatChanges`; el ejecutor solo añade la condición.
- Sobre aliado: decidir si se aplica `SIN_RETIRADA` al aliado (le impediría retirarse) y si debe ser permanente. Requiere tipo compuesto `BUFF_STATS + SIN_RETIRADA`.

### A.5.3 `geomancy` (Geocontrol) y mecánica de carga

- Datos: `target: self`, `flags: ["charge"]`, `data: {"carga_maxima": 1}`, `self_effects` spa+2, spd+2, spe+2 (tres entradas).
- Mapeo: `src/Battle/Infra/Factories/MovimientoFactory.php:70` → `cargaMaxima: 1`; `PerfilMovimiento.php:48`.
- Disponibilidad: `src/Battle/Domain/Componentes/Combatiente/MovimientosCombatiente.php:85-88` devuelve `cargando` mientras `carga < 1`.
- Avance: `src/Battle/Domain/GestorTurnos.php:67` llama a `avanzarCargasMovimiento()` al inicio de ronda; `EstadoMovimientos.php:21-33`.
- Consumo: `EstadoMovimientos.php:50-54` reinicia la carga al usar el movimiento.
- Modo aliado: `ResolutorAccionBatalla.php:197-199` consume turno y PP; la carga del actor se reinicia igual. No requiere lógica nueva, pero conviene un smoke test de dos rondas.
- El buff en sí reutiliza `BUFF_STATS`.

### A.5.4 `acupressure` (Acupresión)

- Datos: `target: adjacentAllyOrSelf`, `effect_key: acupresion`, sin `self_effects`.
- Ejecutor: `src/Battle/Domain/Effects/Movimiento/EjecutorAcupresion.php:21-28` elige una stat al azar (`:24`) y aplica +2 al `defender` salvo que `objetivo === 'self'` (`:25`).
- No es autodirigido: no está en la lista de `MovimientoBatalla::esAutodirigido()` (`:206-220`) y no tiene `self_effects`.
- UI actual: en `player_target` solo se marcan seleccionables los rivales (`app/Livewire/Presenters/BatallaVistaPresenter.php:193-203`); los aliados solo si están en `refsAliado` (`:115-147`), que excluye `actingRefId` (`:131-144`). Por tanto, hoy Acupresión se lanza sobre un rival y **lo buffea**.
- Añadirla al catálogo permitiría aliado, pero **no self**: la carta del actor no es seleccionable y `selectMove` exige un objetivo. Para self haría falta un cambio de selección/UI o un caso especial.
- La stat aleatoria no es previsualizable de forma determinista.

### A.5.5 Otros con efectos adicionales o datos simplificados

- `charge` (Carga): datos solo spd+1; sin boost eléctrico.
- `shelter` (Retracción): datos solo def+2; sin evasión.
- `autotomize` (Aligerar): datos solo spe+2; sin efecto de peso.
- `roost` (Respiro): datos solo cura 50%; sin pérdida temporal de tipo Volador.
- `morning-sun` / `synthesis` / `moonlight`: 50% fijo; sin variación por clima.
- `shore-up` (Recogearena): 50% fijo; sin variación por tormenta de arena.
- `laser-focus` (Aguzar): mapeado a `foco_energia` (5 turnos de crítico), no al “próximo movimiento crítico” oficial.
- `take-heart`: datos +1 a las cinco stats; oficial +1 Sp.Atk/Sp.Def + cura estado.
- `gear-up`: datos atk+1/spa+1 a aliados; sin restricción Plus/Minus.
- `coaching`: datos a `allies`; oficial a un aliado adyacente.
- `aromatic-mist`: datos a `adjacentAlly`; oficial a un aliado adyacente.
- `descanso` (`rest`): `target: self`, `effect_key: descanso`; `EjecutorDescanso.php:20-26` duerme 2 turnos y cura todo el HP al atacante. **Excluirlo** del modo aliado salvo decisión expresa: dormiría al aliado.

## A.6 Curación de estados

### A.6.1 Alivio (`refresh`)

- Datos: `target: self`, `effect_key: refresco`, sin `self_effects`.
- `src/Battle/Domain/Effects/Movimiento/EjecutorRefresh.php:20-26`: `setEstado(NONE)`, `setTurnosEstado(0)`, `setContadorVenenoGrave(0)` sobre el **attacker**.
- En modo propio funciona. Para aliado requiere `TipoEfectoAliado::CURAR_ESTADO` y aplicar el mismo bloque al `defender`.
- Decisión pendiente: el oficial de Refresh cura burn/poison/paralysis, no sueño/congelación; el código actual limpia cualquier estado mayor.

### A.6.2 Purificación (`purify`) — bug actual y ambigüedad

- Datos: `target: adjacentAlly`, `heal_percent: 50`, `data: {"curacion_fraccion": 0.5}`, `effect_key: purificar`.
- `src/Battle/Domain/Effects/Movimiento/EjecutorPurificar.php:22-26`: cura estado del `defender` y PS del **attacker** al 50%.
- No es autodirigido; en la UI actual solo se puede seleccionar rival, por lo que hoy se usa sobre un rival: le cura el estado y cura al usuario.
- El request dice “el ejecutor cura al atacante; corregir”, pero en el juego oficial Purify cura el estado del objetivo y **cura al usuario**. Hay que decidir si se mantiene el oficial o se cambia a “cura al aliado”. Es una decisión de producto, no solo un bug.

### A.6.3 Aromaterapia / Cascabel Cura y curas de equipo

- `aromatherapy` y `heal-bell`: `target: self`, `effect_key: aromaterapia`; `EjecutorAromaterapia.php:20-33` limpia el estado mayor de todos los vivos del equipo. `heal-bell` reutiliza el mismo ejecutor y añade `flags: sound`.
- `life-dew`: `target: allies`, 25%, `effect_key: curacion_equipo`; `ColeccionEjecutoresMovimiento.php:87-89` registra `EjecutorCuracion(equipo=true)`; `EjecutorCuracion.php:37-43` itera `equipoDe(attacker)->combatientesCollection()->vivos()`.
- `jungle-healing`: `target: allies`, 25%, `curacion_equipo_estado`; `EjecutorCuracion(equipo=true, curaEstado=true)` (`ColeccionEjecutoresMovimiento.php:88-89`); cura estado en `:52-56`.
- `lunar-blessing`: `target: allies`, `heal_percent: 0`, `data.curacion_fraccion: 0.25`, `curacion_equipo_estado`. **Bug**: `MovimientoFactory.php:61` solo mapea `heal_percent/100` (0); `EjecutorCuracion.php:29-35` cae al valor por defecto 0.5. Cura 50%, no 25%.
- `dragon-cheer`: `target: allies`, `effect_key: dragon_cheer`; `EjecutorDragonCheer.php:21-30` aplica `CRITICO_MEJORADO` 5 turnos a los aliados vivos excepto al actor.

### A.6.4 Problema transversal: confusión

- `EstadosCombatiente::setEstado()` (`src/Battle/Domain/Componentes/Combatiente/EstadosCombatiente.php:102-121`) limpia el estado mayor, pero **no elimina `ClaveCondicion::CONFUSION`** (`ClaveCondicion.php:16`).
- La confusión vive como condición volátil y solo se elimina en `EstadosCombatiente::resolverConfusion()` cuando el estado resultante es `NONE` (`:227-229`).
- `setTurnosEstado(0)` sí pone a 0 los turnos de la confusión si existe (`:128-138`), pero no la quita de la colección.
- Todas las curas actuales (`EjecutorRefresh`, `EjecutorAromaterapia`, `EjecutorPurificar`, `EjecutorCuracion`) repiten el mismo bloque y arrastran esta limitación. Conviene un helper único `curarEstado` que también haga `quitar(CONFUSION)` si se decide.
- `setEstado(NONE)` sí resetea `cargasVeneno` (`:116-118`).

### A.6.5 Duplicidad existente

El mismo bloque `setEstado(NONE) + setTurnosEstado(0) + setContadorVenenoGrave(0)` aparece en:

- `EjecutorRefresh.php:22-24`
- `EjecutorAromaterapia.php:28-30`
- `EjecutorPurificar.php:22-24`
- `EjecutorTomarAliento.php:24-26`
- `EjecutorCuracion.php:53-55`
- `EfectoCuracionEstado.php:43`, `EfectoCuraEquipo.php:39`, `EfectoCurarEstado.php:65`, `EfectoRestaurarTodo.php:43`

No hay un método de dominio compartido; es deuda técnica relevante.

## A.7 Efectos de equipo y `STATS_ALIADO`

### A.7.1 Estado de `STATS_ALIADO`

- `ClaveEfectoMovimiento::STATS_ALIADO` existe en `src/Battle/Domain/Enums/ClaveEfectoMovimiento.php:97`.
- **No está registrado** en `ColeccionEjecutoresMovimiento::conEjecutoresPorDefecto()` (`src/Battle/Domain/Effects/Movimiento/ColeccionEjecutoresMovimiento.php:21-105`).
- `ejecutar()` con una clave ausente es no-op: `$ejecutor = ... ?? null; $ejecutor?->ejecutar(...)` (`:112-116`).
- `tiene(STATS_ALIADO)` devuelve `false` (`:128-131`).
- Consecuencia: `gear-up`, `coaching` y `aromatic-mist` no aplican el efecto de aliado. **Matiz**: sus `self_effects` sí se convierten en `selfStatChanges` (`MovimientoFactory.php:37,151-152,215-228`) y la ruta genérica los aplica al **usuario**, no a los aliados.

### A.7.2 `allies` / `allySide` / `allyTeam`

- `ObjetivoMovimiento` define `ALLY_SIDE` (`:20`), `ADJACENT_ALLY` (`:26`), `ADJACENT_ALLY_OR_SELF` (`:27`), `ALLIES` (`:28`), `ALLY_TEAM` (`:29`); etiquetas en `:41-50`; `esUnSoloObjetivo()` incluye `ADJACENT_ALLY` y `ADJACENT_ALLY_OR_SELF` (`:58-69`).
- `MovimientoBatalla::esAutodirigido()` (`:206-220`) considera autodirigidos `SELF`, `ALLY_SIDE`, `ALLIES` y `ALLY_TEAM`.
- `ResolutorObjetivosDanio::resolver()` (`:28-51`) solo resuelve multiobjetivo para `ALL_ADJACENT_FOES`, `ALL_ADJACENT` y `ALL`; para `ALLIES`/`ALLY_TEAM`/`ADJACENT_ALLY` devuelve `null`, por lo que el objetivo efectivo es el `defender` único (normalmente el actor en autodirigidos).
- Hay 21 movimientos con target de aliado: `adjacentAlly` 4 (`aromatic-mist`, `floral-healing`, `heal-pulse`, `purify`), `adjacentAllyOrSelf` 1 (`acupressure`), `allies` 7 (`coaching`, `dragon-cheer`, `gear-up`, `howl`, `jungle-healing`, `life-dew`, `lunar-blessing`), `allySide` 6 (`lucky-chant`, `mist`, `pantalla_de_luz`, `reflejo`, `safeguard`, `tailwind`), `allyTeam` 3 (`bendicion_revivir`, `revival-blessing`, `vinculo_dano`).
- Los `allySide`/`allyTeam` ya tienen ejecutores registrados: pantallas (`ColeccionEjecutoresMovimiento.php:36-37`), protección de equipo (`:84-86`), tailwind (`:40`), revivir (`:43`), vínculo de daño (`:42`). Funcionan en modo propio y quedan fuera del alcance de Frente A.

### A.7.3 Qué funciona y qué falta

| Movimiento | Target | `effect_key` | Estado actual | Qué falta |
|---|---|---|---|---|
| `howl` (Aullido) | `allies` | **null** | `self_effects: atk+1`; al ser autodirigido, la ruta genérica buffea solo al usuario. No hay ejecutor que se active. | Ruta de efecto de equipo: (a) añadir `effect_key: stats_aliado` en datos, (b) catálogo de efectos de equipo por slug, o (c) catálogo aliado como `BUFF_STATS` a un aliado. |
| `gear-up` (Piñón Auxiliar) | `allies` | `stats_aliado` | Sin ejecutor; `self_effects: atk+1/spa+1` buffean solo al usuario. | Registrar `EjecutorStatsAliado` para `STATS_ALIADO`; decidir si incluye al actor y si respeta Plus/Minus. |
| `coaching` (Motivación) | `allies` | `stats_aliado` | Igual que Gear Up; buffea solo al usuario. | Mismo ejecutor; decidir equipo vs aliado único. |
| `aromatic-mist` (Niebla Aromática) | `adjacentAlly` | `stats_aliado` | Sin ejecutor. Además es autodirigido por `self_effects` (`MovimientoBatalla.php:217-219`), así que `defender = actor`; un ejecutor que use `defender` buffearía al usuario. | Añadir al catálogo aliado con `BUFF_STATS` para el aliado elegido; el modo propio queda como buff al usuario o sin efecto de aliado. |
| `life-dew` (Gota Vital) | `allies` | `curacion_equipo` | **Funciona**: cura 25% a todos los vivos del equipo (`EjecutorCuracion.php:37-43`). | Nada funcional. Opcional: unificar `curarConExcedente` vs `curarHpCantidad`. |
| `jungle-healing` (Cura Selvática) | `allies` | `curacion_equipo_estado` | **Funciona**: cura 25% + estado mayor (`EjecutorCuracion.php:52-56`). | Nada funcional; misma deuda de confusión. |
| `lunar-blessing` (Plegaria Lunar) | `allies` | `curacion_equipo_estado` | **Funciona con bug**: cura 50% en vez de 25% (`MovimientoFactory.php:61` + `EjecutorCuracion.php:29-35`). | Corregir fracción o mapear `data.curacion_fraccion`. |
| `dragon-cheer` (Bramido Dragón) | `allies` | `dragon_cheer` | **Funciona**: `CRITICO_MEJORADO` 5 turnos a aliados vivos excepto el actor (`EjecutorDragonCheer.php:21-30`). | Nada funcional. |

### A.7.4 Diseño mínimo de `EjecutorStatsAliado`

- Implementar `InterfazEjecutorMovimiento` y registrar `clave() = STATS_ALIADO` en `ColeccionEjecutoresMovimiento.php:21-105`.
- Iterar `equipoDe($accion->attacker)?->combatientesCollection()->vivos()`.
- Aplicar `self_effects`/`selfStatChanges` a cada aliado. Reutilizar `GestorEfectosMovimiento::aplicarStatChangesAMuchos($aliado, [], $movimiento)` como hace `EjecutorEfectoAliado.php:135`.
- **Evitar doble buff al actor**: la ruta genérica ya aplica `selfStatChanges` al actor (`ResolutorAccionBatalla.php:113-118`, `AplicadorEstadoMovimiento.php:80-108`). Excluir al actor del bucle o coordinar la ejecución.
- Para `aromatic-mist`, no usar `defender` en el ejecutor: es `actor` por ser autodirigido. Resolverlo por catálogo aliado.
- Para `howl`, el ejecutor no se activará porque no tiene `effect_key`; requiere decisión aparte.

### A.7.5 IA y prioridades (contexto)

- `SelectorAccionIA` construye `aliados` (`:157-159`) pero no los usa como objetivo; los autodirigidos van al actor y el resto a enemigos (`:200-210`).
- `GestorPrioridadesBatalla::objetivoPara()` (`:149-162`) fuerza actor para autodirigidos y rival para el resto.
- Implicación: los movimientos de soporte no autodirigidos (`acupressure`, `purify`, `heal-pulse`, `floral-healing`) son tratados como ataques por la IA y pueden usarse sobre rivales. Es un bug preexistente que la ampliación del catálogo no corrige por sí sola.

## A.8 Impacto técnico

### A.8.1 Dominio

- `src/Battle/Domain/Enums/TipoEfectoAliado.php:10-17`: añadir casos (`CURAR_ESTADO`, `CRITICO_MEJORADO`, compuestos, stat aleatoria).
- `src/Battle/Domain/Catalog/CatalogoEfectosAliado.php:21-31`: añadir slugs. Es la única palanca de modo aliado; no toca `esAutodirigido()` ni el `target` de BD.
- `src/Battle/Domain/ValueObjects/EfectoAliado.php:15-22`: hoy solo `tipo` + `turnos`. Para compuestos/condiciones hay que extenderlo (campos opcionales o lista de sub-efectos) o añadir casos de enum.
- `src/Battle/Domain/Ejecucion/EjecutorEfectoAliado.php:41-47`: `match` exhaustivo; cualquier caso nuevo sin rama lanza `UnhandledMatchError`.
- `src/Battle/Domain/Ejecucion/EjecutorEfectoAliado.php:85-99` (cura) y `:130-157` (buffs): reutilizables.
- `src/Battle/Domain/Effects/Movimiento/ColeccionEjecutoresMovimiento.php:21-105`: registrar `EjecutorStatsAliado`.
- `Ejecutores existentes`: `EjecutorRefresh.php:20-26`, `EjecutorPurificar.php:22-26`, `EjecutorTomarAliento.php:22-30`, `EjecutorAromaterapia.php:20-33`, `EjecutorCuracion.php:27-58`. Posible refactor a helper de cura de estado; no obligatorio para el MVP.
- `MovimientoBatalla::esAutodirigido()` (`:206-220`): **no tocar**.
- `ClaveCondicion` reutilizable: `CRITICO_MEJORADO` (`:65`), `AQUA_RING` (`:68`), `SIN_RETIRADA` (`:70`). No hace falta una condición nueva.

### A.8.2 Catálogo y datos

- `MovimientoFactory.php:61`: `curacionFraccion = heal_percent / 100`; **no lee `data.curacion_fraccion`**. Origen del bug de Plegaria Lunar.
- `MovimientoFactory.php:70`: `cargaMaxima` desde `data.carga_maxima` o flags; Geocontrol = 1.
- `MovimientoFactory.php:37,151-152,215-228`: `self_effects` al 100% → `autoEfectos` + `selfStatChanges`.
- `PerfilMovimiento.php:38` (`curacionFraccion`) y `:48` (`cargaMaxima`).
- Cambios de datos solo si se decide: `howl` (`effect_key`), `lunar-blessing` (`curacion_fraccion`). Sería `storage/data/moves.json` + reseed; no esquema.

### A.8.3 Livewire

- `GestorSeleccionBatalla.php:142-176`: rama aliado; el catálogo decide si el aliado es objetivo válido.
- `GestorSeleccionBatalla.php:293-303`: `actorAdmiteAliado()` consulta el catálogo.
- `GestorAccionBatalla.php:170-222`: resolución normal/aliada; defensa en profundidad en `:176-187`.
- `ResolutorAccionBatalla.php:155-203`: camino aliado dedicado; consume turno/PP en `:197-199`.
- `ResolutorAccionBatalla.php:113-118`: aplicación genérica de `selfStatChanges` al actor (relevante para el doble buff y para el diseño de `STATS_ALIADO`).
- No se necesitan nuevas propiedades/métodos públicos en `Combate` para las curas/buffs; el contrato puede quedar intacto.

### A.8.4 Presenters

- `MovesPreviewPresenter.php:127-136`: deriva a preview aliado si el target es aliado.
- `MovesPreviewPresenter.php:318-352`: `match` exhaustivo sobre `TipoEfectoAliado`; actualizar a la vez que el enum.
- `MovesPreviewPresenter.php:354-365`: texto de buffs basado en `selfStatChanges`; reutilizable.
- `PresentadorVistaCombate.php:53-62`: `actorAdmiteAliado` se amplía automáticamente.
- `BatallaVistaPresenter.php:115-147` y `:193-203`: aliados seleccionables; para Acupresión self habría que tocar esta lógica o el flujo de selección.

### A.8.5 Blade / JS

- `resources/views/livewire/partials/moves-panel.blade.php:58-63`: badge `SOBRE ALIADO` + texto del efecto.
- `:66-93`: oculta la fila de daño en modo aliado.
- No hay tipos de evento nuevos: se reutiliza `effect` en `ResolutorAccionBatalla.php:190-195`.
- No se toca `resources/js/combate.js` ni `combate-scripts.blade.php`.

### A.8.6 Sesión, campos y migración

- **Campos nuevos**: ninguno obligatorio. `MovimientoBatalla` ya tiene `slug`, `selfStatChanges`, `perfil` y `objetivo`. `TipoEfectoAliado`/`EfectoAliado` no se persisten: se calculan desde el catálogo en runtime.
- **Migración**: no, salvo que se decida cambiar datos (`moves.json` + reseed), que no es esquema.
- **`SESSION_VERSION`**: no se toca. Sesiones antiguas siguen funcionando: un slug desconocido devuelve `null` y mantiene el modo propio/rival.
- Si se añadiera una `ClaveCondicion` nueva habría que evaluar serialización, pero se pueden reutilizar las existentes.

## A.9 Riesgos

| Riesgo | Severidad | Detalle | Mitigación |
|---|---|---|---|
| `match` exhaustivo en `EjecutorEfectoAliado` | **Alto** | `:41-47`. Caso nuevo sin rama → `UnhandledMatchError` al ejecutar. | Actualizar el `match` en el mismo cambio. |
| `match` exhaustivo en `MovesPreviewPresenter` | **Alto** | `:328-351`. Caso nuevo sin rama → error al previsualizar un aliado. | Actualizar el `match` en el mismo cambio. |
| Doble buff de Bálsamo Osado | **Alto** | `selfStatChanges` en `ResolutorAccionBatalla.php:113-118` + `EjecutorTomarAliento.php:27-29`. Hipótesis si `golpes > 0` en estado. | Verificar en ejecución; si se confirma, quitar el buff del ejecutor o del `self_effects`. |
| `STATS_ALIADO` sin semántica | **Alto** | `ColeccionEjecutoresMovimiento.php:21-105` no lo registra. `howl` no tiene `effect_key`; `aromatic-mist` es autodirigido por `self_effects`. | Definir equipo vs aliado único, inclusión del actor y vía de activación de Howl. |
| Purificación: destino de la cura | **Medio** | `EjecutorPurificar.php:22-26` cura estado al `defender` y PS al `attacker`. El request pide “corregir”. | Decisión de producto; documentar y ajustar ejecutor/preview. |
| Soportes `adjacentAlly` usables sobre rival | **Medio** | `purify`, `heal-pulse`, `floral-healing`, `acupressure` no son autodirigidos; la UI solo permite rival. | Validación de objetivo o cambio de selección. |
| Curación de estado incompleta (confusión) | **Medio** | `EstadosCombatiente.php:102-121` no quita `CONFUSION`; solo se elimina en `:227-229`. | Helper único `curarEstado` con `quitar(CONFUSION)` si se decide. |
| Plegaria Lunar cura 50% | **Medio** | `MovimientoFactory.php:61` no mapea `data.curacion_fraccion`; `EjecutorCuracion.php:29-35` cae a 0.5. | Decidir 25% vs 50%; corregir mapeo o ejecutor. |
| `curarConExcedente` vs `curarHpCantidad` | **Medio** | `EjecutorCuracion.php:51` desborda a escudo; `EjecutorEfectoAliado.php:90` no. | Decidir política de equipo y unificar. |
| Bastión Final sobre aliado | **Medio** | `EjecutorSinRetirada.php:23` aplica `SIN_RETIRADA` con `turnos: 0` (permanente). | Decisión de producto; no aplicar o hacer temporal. |
| Acupresión self inaccesible | **Medio** | `BatallaVistaPresenter.php:131-144` excluye `actingRefId`; `selectMove` exige objetivo. | Decisión UX o caso especial. |
| Acupresión aleatoria no previsualizable | **Bajo** | `EjecutorAcupresion.php:24`. | Preview genérico. |
| UX de aliados seleccionables | **Medio** | Al ampliar el catálogo, casi cualquier actor tendrá aliados clicables. | Badge/aviso y preview que deshabilita no usables; “Cambiar objetivo” ya existe. |
| Regresión en modo propio/rival | **Medio** | El catálogo solo se consulta si `defender` es aliado; los cambios en ejecutores de equipo sí afectan al modo propio. | Fases; no tocar ejecutores de equipo hasta decidir semántica. |
| Inmunidad a sonido en Cascabel Cura | **Bajo** | `EvaluadorPrecondicionesMovimiento.php:75-86` comprueba flags del `defender`; en self-target el defensor es el actor. | Revisar si Soundproof bloquea el propio Heal Bell. |
| `SIN_RETIRADA` consumidores no verificados | **Bajo** | No se leyeron todos los usos de la condición. | Revisar antes de aplicarla a aliados. |
| `heal_percent` hardcodeado | **Bajo** | `EjecutorEfectoAliado.php:24` fija 0.5. Los 9 son 50%, pero es frágil. | Usar `perfil->curacionFraccion`. |

## A.10 Fases propuestas

### Fase 0 — Bajo riesgo, alto valor

1. Añadir al `CatalogoEfectosAliado` los 9 `CURAR_PS` y los 25 `BUFF_STATS` puros.
2. Usar `perfil->curacionFraccion` en `EjecutorEfectoAliado.php:85-99` (opcional, recomendado).
3. No añadir casos nuevos a `TipoEfectoAliado`.
4. No tocar datos, sesión, IA ni `esAutodirigido`.
5. Verificar que el preview existente cubre los tipos ya presentes.

Riesgo: bajo. Solo aparecen aliados seleccionables y previews de apoyo.

### Fase 1 — Medio riesgo: estados y crítico

1. `TipoEfectoAliado::CURAR_ESTADO` para `refresh`.
2. `TipoEfectoAliado::CRITICO_MEJORADO` para `focus-energy` y `laser-focus`.
3. Actualizar simultáneamente `EjecutorEfectoAliado.php:41-47` y `MovesPreviewPresenter.php:328-351`.
4. Crear helper de cura de estado y decidir si elimina `CONFUSION`.
5. Decidir el conjunto de estados que cura Alivio.

Riesgo: medio por los `match` exhaustivos y la semántica de estados.

### Fase 2 — Medio-alto: compuestos

1. `take-heart`: buff + cura estado; verificar el doble buff.
2. `no-retreat`: buff + `SIN_RETIRADA`; decidir permanencia y destino.
3. `acupressure`: tipo de stat aleatoria; decidir self.
4. `purify`: decidir si cura PS al aliado o al lanzador; implementar tipo compuesto o corregir ejecutor.
5. Actualizar previews y matches.

Riesgo: alto por decisiones de producto y por el posible doble buff.

### Fase 3 — Alto: `STATS_ALIADO` y equipo

1. Implementar y registrar `EjecutorStatsAliado`.
2. Definir `target: allies` vs `adjacentAlly` y si incluye al actor.
3. Resolver Howl: datos (`effect_key`), catálogo de efectos de equipo o catálogo aliado.
4. Resolver Aromatic Mist vía catálogo aliado.
5. Resolver Gear Up / Coaching con el ejecutor.
6. Revisar Pulso Cura / Cura Floral (misma familia, no incluidas en el alcance).

Riesgo: alto por semántica, datos y posible doble aplicación al actor.

### Fase 4 — Medio: consistencia de equipo (opcional)

1. Corregir Plegaria Lunar (25% vs 50%).
2. Unificar `curarConExcedente` vs `curarHpCantidad`.
3. Corregir logs de `EjecutorCuracion` (registra cura aunque el objetivo esté lleno).
4. Revisar `aromatherapy`/`heal-bell` con inmunidad a sonido.

Riesgo: medio; afecta a movimientos ya existentes.

### Fuera de alcance de Frente A

- IA y prioridades con objetivo aliado (Frente B): `SelectorAccionIA`, `GestorPrioridadesBatalla`, contrato de `prioridades`.

## A.11 Preguntas abiertas

1. **Purificación**: ¿cura PS al aliado o al lanzador? ¿Se mantiene el comportamiento oficial?
2. **Bálsamo Osado**: ¿el +1 a las cinco stats actual es intencional? ¿Se confirma o corrige el posible doble buff?
3. **Bastión Final**: ¿se aplica `SIN_RETIRADA` al aliado? ¿Permanente o temporal?
4. **Aullido / Piñón Auxiliar / Motivación**: ¿equipo completo o aliado único? ¿Incluyen al lanzador? ¿Se autoriza cambiar `effect_key` de `howl` en `moves.json` o se prefiere un catálogo de efectos de equipo?
5. **Niebla Aromática**: ¿aliado único adyacente? ¿Debe seguir funcionando en modo propio?
6. **Acupresión**: ¿debe poder usarse sobre el propio actor? ¿Stat aleatoria o determinista?
7. **Alivio**: ¿cura todos los estados mayores (incluido sueño/congelación) o solo burn/poison/paralysis? ¿Debe curar confusión?
8. **Plegaria Lunar**: ¿25% (dato `curacion_fraccion`) o 50% (comportamiento actual)? ¿Se corrige en este frente?
9. **Curas de equipo**: ¿deben desbordar a escudo (`curarConExcedente`) o solo PS (`curarHpCantidad`)?
10. **Soportes `adjacentAlly`**: ¿se debe impedir su uso sobre rival (Purificación, Pulso Cura, Cura Floral, Acupresión)?
11. **Alcance de Fase 0**: ¿se autoriza ampliar el catálogo con 9 + 25 sin tocar ejecutores de equipo, o se prefiere un único lote?
12. **Datos**: ¿se autoriza tocar `storage/data/moves.json` y reseed si hace falta (`howl`, `lunar-blessing`)? Sin migración de esquema.
13. **Descanso**: confirmar que queda excluido del modo aliado.

## A.12 Limitaciones del análisis

- Solo lectura: no se ejecutaron tests, no se levantó la aplicación y no se modificaron archivos.
- El límite de pasos impidió leer completos `CalculadorGolpes`, `CondicionVolatil` y `BattleSessionService`; el doble buff de Bálsamo Osado y la semántica de `golpes > 0` en movimientos de estado son **hipótesis verificables**, no hechos confirmados.
- No se verificaron todos los consumidores de `SIN_RETIRADA` ni el comportamiento real de retirada/cambio.
- `storage/data/moves.json` no tiene números de línea citables; las referencias son por slug y por el código que los mapea.
- No se analizó en profundidad IA/prioridades (Frente B) ni el contrato de `prioridades`.
- No se llenaron los artefactos de `.opencode/work/TASK-031/`; el análisis es solo texto.

---

# FRENTE B — IA y prioridades con objetivo aliado

## B.1 Resumen ejecutivo

- El modo aliado de TASK-030 vive solo en la ruta del jugador; la IA no genera, puntúa ni resuelve acciones sobre aliados.
- `SelectorAccionIA` calcula `aliados` en el contexto (`SelectorAccionIA.php:157-159`) pero **nunca los usa como objetivo**; solo itera enemigos (`:233-242`).
- La única acción “no enemiga” que genera es la autodirigida, con `defender = actor` (`SelectorAccionIA.php:206-217`).
- `EvaluadorAccionIAImpl` no tiene señal de soporte: su score es `KO + daño + amenaza + supervivencia - riesgo + lookahead` (`:85-86`) y `EvaluacionAccion` no expone `supportValue`.
- Los 3 movimientos de ataque del catálogo (`sand-tomb`, `fire-spin`, `whirlpool`) tienen potencia 35 y **no son `esEstado()`**; si se generan contra un aliado, `CalculadoraDanioIA::estimar()` calcula daño contra el aliado y puede dar `koValue = 100` (`EvaluadorAccionIAImpl.php:42-55`).
- `recover` y los 5 buffs son `esAutodirigido()` (`MovimientoBatalla.php:194-215`), y `prepararAnimacionIa()` fuerza `defenderId = actor` (`GestorAccionBatalla.php:98-104`), por lo que la IA se autoaplicaría el soporte.
- `prepararAnimacionIa()` toma **dos decisiones independientes** (`elegirObjetivoPara` y `elegirMejorMovimiento`), ambas llamando a `elegirAccion()`; el objetivo y el movimiento pueden no corresponderse (`SelectorAccionIA.php:91-104`, `109-131`; `AgregadoBatalla.php:395-403`).
- El lookahead tampoco entiende soporte: simula con `ServicioEjecucionBatalla` y puede dañar al aliado o aplicarle buffs al actor (`SimuladorAccionIA.php`, `EvaluadorAccionIAImpl.php:111-155`).
- En empate, el desempate favorece daño y potencia (`SelectorAccionIA.php:283-303`), de modo que un soporte con score neutro pierde siempre frente a un ataque débil.
- No hay tests ni verificación en runtime; todo el análisis es estático.

## B.2 Flujo actual de `SelectorAccionIA`

1. **Entrada:** `elegirAccion($battle, $actor, $dificultad)` (`SelectorAccionIA.php:60-86`).
2. **Contexto:** `construirContexto()` (`:148-173`) identifica el equipo del actor (`:153-155`), construye `aliados` vivos excluyendo al actor (`:157-159`) y `enemigos` vivos (`:161`). Los aliados quedan disponibles en `ContextoDecisionIA` pero no se consultan después.
3. **Amenazas:** `analizadorAmenazas->analizar($contexto)` (`:67`), que evalúa a los enemigos contra los aliados (`AnalizadorAmenazasImpl.php:66-88`).
4. **Generación de candidatas:** `generarAccionesCandidatas()` (`:182-246`):
   - Determina el equipo enemigo (`:188-190`) y si tiene vanguardia viva (`:192`).
   - Filtra enemigos legales según la posición del actor (`:195`, `filtrarEnemigosPorPosicion` en `:254-265`): vanguardia ataca solo vanguardia enemiga viva; si no queda, o si el actor está en retaguardia, ataca a cualquiera.
   - Recorre los movimientos del actor (`:197`), descarta los que no son `MovimientoBatalla` (`:198-200`) y los no disponibles (`:202-204`).
   - Si `esAutodirigido()`: crea **una sola** acción `attacker = actor`, `defender = actor` (`:206-217`) y continúa.
   - Si no: `objetivos = enemigosLegales` (`:219`); los movimientos de fila se reducen al primero (`:220-222`); `DESAFIO` sobrescribe con el objetivo forzado (`:224-231`).
   - Crea una `AccionBatalla` por cada enemigo objetivo (`:233-242`).
5. **Evaluación:** `map` sobre las acciones con `evaluadorAccion->evaluar()` (`:71-73`).
6. **Selección por dificultad:** `seleccionarSegunDificultad()` (`:75`, `:277-314`):
   - Ordena por `score` descendente (`:283-290`).
   - Desempata por `damageValue` (`:295-299`) y luego por `potencia` (`:301`).
   - Toma el top 1/2/3 según `PERFECTA`/`DIFÍCIL`/`NORMAL` (`:305-309`) y elige aleatoriamente dentro del top (`:311-313`).
7. **Fallback:** si no hay evaluaciones, `accionFallback()` (`:77-79`, `:326-357`) ataca al primer enemigo con “Espera” o “Placaje”.
8. **Retorno:** `ResultadoDecision` con la acción, amenazas y evaluaciones (`:81-85`).

**Elección del `defender`:** no hay resolvedor separado. El `defender` nace en la generación: `actor` si el movimiento es autodirigido (`:207-213`), o el enemigo legal de turno (`:233-241`). Los envoltorios legacy `elegirObjetivoPara()` (`:91-104`) y `elegirMejorMovimiento()` (`:109-131`) devuelven campos de una `elegirAccion()` completa; `AgregadoBatalla::elegirObjetivoPara/elegirMejorMovimiento` (`:395-403`) también delegan en `elegirAccion()`.

**Filtros existentes:** disponibilidad de movimiento (`:202-204`), posición del actor frente a enemigos (`:254-265`), fila (`:220-222`) y `DESAFIO` (`:224-231`). No hay filtro de aliados, de retaguardia aliada, de PS llenos ni de buffs activos.

## B.3 Scoring actual de `EvaluadorAccionIA` / `CalculadoraDanioIA`

**Cálculo de daño (`CalculadoraDanioIA`):**

- `estimar()` (`:35-73`) construye una `AccionBatalla` con el defensor real (`:41-49`), ejecuta `CadenaDanio::calculate()` con semilla fija (`mt_srand(42)`, `:52-55`), aplica multi-golpe (`:59`) y calcula `hpDamage` descontando barreras (`:62`, `calcularHpDamage` en `:128-149`).
- `probabilidadKO` es binaria: `hpDamage >= defensor->hpActual()` (`:64`).
- `mejorEstimacionContra()` (`:79-100`) recorre todos los movimientos y devuelve la estimación de mayor daño esperado, **sin comprobar PP ni disponibilidad**.

**Evaluación (`EvaluadorAccionIAImpl::evaluar`, `:34-97`):**

| Señal | Cálculo | Peso / efecto |
|---|---|---|
| `koValue` | Si el movimiento **no es estado**, estima daño; si `probabilidadKO > 0`, asigna `puntosKO` | `100` (`PesosAmenaza`) |
| `damageValue` | `hpDamage / hpActualObjetivo * multiplicadorDanio` | `40` (`PesosAmenaza`) |
| `threatReduction` | Solo si hay KO: busca el enemigo objetivo en las amenazas y suma su `score` | variable (`:160-174`) |
| `survivalValue` | `+50` si ningún enemigo restante puede matar al actor; `-50` si alguno puede | `50` (`PesosAmenaza`) |
| `risk` | `-30` si algún enemigo puede matar al actor | `30` (`PesosAmenaza`) |
| `lookaheadScore` | Solo `PERFECTA`/`DIFÍCIL` (`:99-106`): simula la acción, genera hasta 3 respuestas rivales y toma la peor posición × `0.3` (`:111-155`) | variable |

**Fórmula final:** `score = koValue + damageValue + threatReduction + survivalValue - risk + lookaheadScore` (`:85-86`).

**Cómo compiten las acciones:**

- No hay normalización ni comparación relativa: los valores son absolutos y se suman.
- Un KO vale 100; el daño aporta hasta ~40; la supervivencia y el riesgo son **constantes por contexto**, no por acción, así que se cancelan entre acciones similares.
- Un soporte de estado (`recover`, buffs) obtiene `koValue=0`, `damageValue=0`, `threatReduction=0` y solo suma `survivalValue - risk` (+/-), igual que un ataque que no mata; pero el ataque conserva `damageValue > 0`.
- El desempate prefiere `damageValue` y luego `potencia` (`SelectorAccionIA.php:283-303`), por lo que el soporte solo puede ganar si su score supera estrictamente al del ataque.
- `EvaluacionAccion` (`src/Battle/Domain/AI/ValueObjects/EvaluacionAccion.php`) no tiene campo de soporte; `PesosAmenaza` tampoco tiene pesos de curación, escudo ni buff.

## B.4 Riesgos críticos

### B.4.1 `esAutodirigido()` + `prepararAnimacionIa()`: la IA se autoaplica el soporte

**Evidencia:**

- `MovimientoBatalla::esAutodirigido()` devuelve `true` si el objetivo es `SELF`, `ALLY_SIDE`, `ALLIES` o `ALLY_TEAM`, y también si es estado con `selfStatChanges` y sin `targetStatChanges` (`src/Battle/Domain/MovimientoBatalla.php:194-215`).
- En `storage/data/moves.json`, `recover` tiene `target: "self"`; `nasty-plot`, `paz_mental`, `swords-dance`, `dragon-dance` y `bulk-up` tienen `target: "self"` y `self_effects`. Todos son, por tanto, `esAutodirigido() === true`.
- `GestorAccionBatalla::prepararAnimacionIa()` comprueba `$movimiento->esAutodirigido()` y sobrescribe `$defenderId = $actor->id()` y `$targetForAnim = $actor` (`app/Livewire/Combate/Soporte/GestorAccionBatalla.php:98-104`).
- En `resolver()`, el bloque de modo aliado exige `objetivo->id() !== actor->id()`; como el `pendingAction` ya apunta al actor, `$esAliado` es `false`, `$efectoAliado` es `null`, y después se fuerza `$objetivo = $actor` (`:169-172`).
- Resultado: aunque el `SelectorAccionIA` eligiera un aliado, `prepararAnimacionIa()` destruye esa elección y la resolución aplica la cura/buff al propio actor de la IA.

**Agravante:** `prepararAnimacionIa()` obtiene el objetivo con `elegirObjetivoPara()` y el movimiento con `elegirMejorMovimiento()`; ambas llaman a `elegirAccion()` de forma independiente (`SelectorAccionIA.php:91-104`, `109-131`; `AgregadoBatalla.php:395-403`). Incluso sin el problema de `esAutodirigido`, el movimiento y el objetivo podrían no pertenecer a la misma decisión.

### B.4.2 Ataques de soporte puntuados como daño contra el aliado

**Evidencia:**

- `EvaluadorAccionIAImpl::evaluar()` solo evita el cálculo de daño cuando `$movimiento->esEstado()` (`:45`). `sand-tomb`, `fire-spin` y `whirlpool` son físico/especial con `base_power: 35` en `storage/data/moves.json`, por lo que `esEstado()` es `false`.
- El evaluador usa `$objetivo = $accion->defender` (`:40`) y llama `estimar($accion->attacker, $objetivo, ...)` (`:46-51`). Si el `defender` es un aliado, **la estimación se calcula contra el aliado**.
- `CalculadoraDanioIA::estimar()` marca `probabilidadKO = hpDamage >= defensor->hpActual()` (`:64`) y devuelve `hpDamage` real (`:62`, `:66-72`).
- El evaluador convierte eso en `koValue = 100` si hay KO (`:52-54`) y en `damageValue > 0` (`:62-68`).
- `calcularReduccionAmenaza()` busca el id del objetivo entre las amenazas enemigas (`:160-174`); un aliado no aparece, devuelve `0` y no hay ninguna guarda que detecte el caso.
- `calcularSupervivencia()` puede incluso excluir al aliado de la lista de enemigos restantes si `koValue > 0` (`:186-192`), lo que carece de sentido cuando el objetivo es aliado.
- El lookahead agrava el problema: `SimuladorAccionIA::simular()` aplica `ServicioEjecucionBatalla::calcularYAplicarDano()` sobre el clon; si el objetivo aliado “muere” en la simulación, `EvaluadorAccionIAImpl.php:118-121` suma `puntosSupervivencia * 0.5`.

**Consecuencia:** sin una rama explícita de soporte, la IA consideraría óptimo “atacar” a su aliado más débil, especialmente si el daño estimado produce KO. El riesgo no es teórico: los 9 slugs del catálogo ya están admitidos por `CatalogoEfectosAliado::para()`, y basta con que la generación de candidatas incluya aliados para activar este camino.

## B.5 Cambios mínimos para generar soporte sin romper lo actual

### B.5.1 Generación aditiva en `SelectorAccionIA`

No tocar la generación enemiga existente (`SelectorAccionIA.php:182-246`). Añadir un bloque previo o posterior:

- Solo si `! $contexto->aliados->isEmpty()` (`:157-159`).
- Para cada movimiento del actor (`:197`) que cumpla:
  - `$movimiento instanceof MovimientoBatalla`;
  - `$actor->movimientoDisponible($movimiento)` (`Combatiente.php:257-273`);
  - `CatalogoEfectosAliado::admite($movimiento->slug)` (`src/Battle/Domain/Catalog/CatalogoEfectosAliado.php`, match de los 9 slugs).
- Para cada aliado alcanzable de `$contexto->aliados`:
  - excluir al actor (`:157-159` ya lo hace; revalidar `id()`);
  - excluir retaguardia si el equipo del actor tiene vanguardia viva, replicando `BatallaVistaPresenter::refsAliado` (`:115-153`) y `GestorSeleccionBatalla.php:59-67`;
  - crear `AccionBatalla(attacker: actor, defender: aliado, move: movimiento, defenderTeamHasVanguard: $equipoActor->tieneVanguardiaViva(), equipoDefensor: $equipoActor)`.

Los movimientos autodirigidos (`recover`, 5 buffs) ya generan su acción propia en `:206-217`; el bloque de soporte añade la variante “sobre aliado” sin eliminar la variante propia. El evaluador decidirá. `sand-tomb`, `fire-spin` y `whirlpool` no son autodirigidos y hoy solo generan acciones enemigas; el bloque de soporte les añade la variante aliada.

**Helper propuesto:** `esAccionApoyo(AccionBatalla): bool` en el selector o en un VO compartido:

```text
defender->id() !== attacker->id()
&& battle->equipoDe(defender) === battle->equipoDe(attacker)
&& CatalogoEfectosAliado::admite(move->slug)
```

Debe usarse en generación, evaluación, lookahead y `prepararAnimacionIa`.

### B.5.2 Evaluación con rama de soporte

En `EvaluadorAccionIAImpl::evaluar()` (`:34-97`), antes del cálculo de KO/daño:

- Si `esAccionApoyo($accion)`, **no llamar** a `CalculadoraDanioIA::estimar()` ni a `mejorEstimacionContra()` sobre el aliado.
- Calcular `supportValue` según el efecto (`CatalogoEfectosAliado::para($slug)`):
  - `CURAR_PS`: `min(hpMax*0.5, hpMax-hpActual)` acotado por `EjecutorEfectoAliado::aplicarCuracionPs()`.
  - `ESCUDO_FISICO`/`ESPECIAL`: incremento real de `CalculadoraCuracionAliada::incrementoEscudo($maxEscudo, $statLanzador)` (`src/Battle/Domain/Ejecucion/CalculadoraCuracionAliada.php`), limitado por el déficit.
  - `BUFF_STATS`: valor esperado de las etapas que aporta (`MovimientoBatalla::$selfStatChanges`), descontando saturación (`MultiplicadoresStats::obtenerMultiplicador`, `:264-270`; `turnosTemporales`, `:315`).
  - `AQUA_RING`: curación esperada por ronda (`CalculadoraCuracionAliada::curacionConAporteFijo`) × turnos restantes, sin contar rondas ya cubiertas.
- Devolver `EvaluacionAccion` con `supportValue` y `score = supportValue + survivalValue - risk` (sin KO/daño/amenaza).
- Añadir `supportValue` a `EvaluacionAccion` como último parámetro con default `0.0` para no romper construcciones existentes (`src/Battle/Domain/AI/ValueObjects/EvaluacionAccion.php`).
- Añadir pesos de soporte a `PesosAmenaza` al final del constructor con defaults (curación, escudo, buff, Aqua Ring). No cambiar los pesos existentes (`puntosKO=100`, `multiplicadorDanio=40`, `puntosSupervivencia=50`, `puntosRiesgo=30`).
- En `lookahead` (`:79-83`, `:111-155`), desactivar para soporte en la primera fase: `debeAplicarLookahead()` (`:99-106`) no debe devolver `true` para una acción de apoyo, porque `SimuladorAccionIA` aplica daño/estado con `ServicioEjecucionBatalla` y no reproduce cura/buff aliado.

### B.5.3 Arreglar `prepararAnimacionIa` y la coherencia objetivo/movimiento

Hoy `prepararAnimacionIa()` obtiene el objetivo con `elegirObjetivoPara()` y el movimiento con `elegirMejorMovimiento()`; ambos llaman a `elegirAccion()` por separado (`SelectorAccionIA.php:91-104`, `109-131`; `AgregadoBatalla.php:395-403`). Después fuerza `defenderId = actor` si `esAutodirigido()` (`GestorAccionBatalla.php:98-104`).

Cambio mínimo:

1. Añadir a `AgregadoBatalla` un método aditivo, por ejemplo `elegirAccionPara(Combatiente $actor): ?AccionBatalla`, que llame una sola vez a `getSelectorAccion()->elegirAccion($this, $actor, $this->dificultad)` y devuelva `accion`.
2. En `prepararAnimacionIa()` usar esa única acción:
   - `$movimiento = $accion->move`;
   - `$target = $accion->defender`;
   - `$defenderId = $target->id()`;
   - `$targetForAnim = $target`.
3. Eliminar el forzado por `esAutodirigido()`: el selector ya devuelve `defender = actor` para las acciones propias (`SelectorAccionIA.php:206-217`). Si se mantiene, debe condicionarse a que el objetivo no sea aliado y el slug no esté en el catálogo.
4. En `resolver()`, la rama aliada ya existe: `esAliado` + `CatalogoEfectosAliado::para()` enruta a `aplicarMovimientoAliado()` (`ResolutorAccionBatalla.php:155-203`). Con el `defenderId` correcto, no hace falta tocar el resolutor.
5. Mantener la defensa en profundidad de `GestorAccionBatalla` para movimientos no aliados sobre aliado: log y consumo de acción, nunca daño.

### B.5.4 `EjecutorAccion` / motor de dominio

`EjecutorAccion::ejecutar()` (`src/Battle/Domain/Componentes/Agregado/EjecutorAccion.php:24-46`) asume que `defender` es enemigo: `construirAccion()` (`:68-84`) y `calcularYAplicarDano()` (`:40-43`). Si el selector generara soporte en un combate con aliados, `EjecutorAccion` dañaría al aliado o no aplicaría cura/buff.

Opciones mínimas:

- **Guarda:** no generar soporte si el contexto no tiene aliados (Exploraciones es 1v1: `CombateExploracion.php:124` usa `ejecutarBatalla()`, con `aliados` vacío). Es lo más seguro para el alcance actual.
- **Rama explícita:** en `EjecutorAccion`, si `esAccionApoyo`, invocar `EjecutorEfectoAliado` (ya vive en dominio) y consumir turno/PP, sin pasar por daño. Requiere replicar log/observadores y no es mínimo.

Recomendación: aplicar la guarda y documentar que el motor de dominio no soporta aliados en esta fase.

## B.6 Heurísticas anti-acciones inútiles

| Caso | Detección | Acción |
|---|---|---|
| Aliado muerto | `! $aliado->estaVivo()`; revalidar en generación y antes de commit | Descartar. `EjecutorEfectoAliado::ejecutar()` ya comprueba `estaVivo()`, pero consume turno/PP; no llegar ahí. |
| PS llenos (`recover`) | `$aliado->hpActual() >= $aliado->pokemon()->battleStats()->hp` | Descartar. |
| PS llenos (Aqua Ring) | Ya tiene `AQUA_RING` con turnos suficientes (`Combatiente::condicion(ClaveCondicion::AQUA_RING)`, `:315`) y PS lleno | Descartar salvo que se quiera valor futuro; reaplicar solo reinicia a 5 (`EjecutorEfectoAliado::aplicarAquaRing` usa `reemplazarCondicion`). |
| Escudo físico lleno | `$aliado->defensaHpActual() >= battleStats()->defenseHp` (`Combatiente.php:97-104`) | Descartar `sand-tomb`. |
| Escudo especial lleno | `$aliado->defensaEspHpActual() >= battleStats()->spDefenseHp` | Descartar `fire-spin`. |
| Buff ya activo | `multiplicadores()->obtenerMultiplicador($stat) >= umbral` (`MultiplicadoresStats.php:264-270`) o `turnosTemporales() >= 4` (`:315`) | Evitar repetir el mismo stat; decidir umbral con el desarrollador. |
| Doble buff | Varias acciones del mismo movimiento/stat sobre el mismo aliado | Una acción por (movimiento, aliado); el sistema acumula y refresca un contador compartido de 5 rondas (`MultiplicadoresStats::TURNOS_TEMPORALES = 5`, `:25`; `aplicarTemporal`, `:166-179`), así que “no buffear dos veces” es una decisión de producto, no una invalidación técnica. |
| Retaguardia | `$aliado->estaEnRetaguardia()` y el equipo del actor tiene vanguardia viva | Descartar, replicando `BatallaVistaPresenter::refsAliado` (`:115-153`) y `GestorSeleccionBatalla.php:59-67`. |
| Autoobjetivo | `$aliado->id() === $actor->id()` | Descartar; `contexto->aliados` ya lo excluye (`SelectorAccionIA.php:157-159`). |
| Cura desperdiciada | `faltante = hpMax - hpActual`; valorar `min(hpMax*0.5, faltante)` | Descartar si `faltante <= 0`; no valorar overheal. |
| Turno sin efecto | Delta esperado = 0 (cura 0, escudo al máximo, buff en clamp 4.0, Aqua Ring ya activo) | Descartar salvo valor estratégico explícito (p. ej. refrescar duración antes de una ronda clave). |
| `DESAFIO` | `$actor->condicion(ClaveCondicion::DESAFIO)` con `objetivoForzadoId` (`SelectorAccionIA.php:224-231`; `GestorSeleccionBatalla.php:178-183`) | Decidir si el desafío bloquea el soporte; hoy la UI lo permite (`! $esAliado`), la IA debería replicar esa regla o bloquearlo explícitamente. |
| `CUBRIR` | El aliado puede estar cubriendo a otro | No bloquea el soporte en el código actual; `aplicarMovimientoAliado` no dispara reacciones ni redirecciones. |

## B.7 ¿Solo si no puede atacar, o scoring competitivo?

**Propuesta: scoring competitivo con compuertas conservadoras.**

- “Solo si no puede atacar” produce pasividad: la IA ignoraría curas que evitan un KO o buffs rentables, y alternaría entre atacar y no hacer nada.
- “Solo scoring” sin filtros puede generar bucles de cura/buff y acciones sin efecto.

**Fórmula concreta:**

1. Calcular primero el mejor ataque no soporte (`koValue`, `damageValue`, `survivalValue`, `risk`, `lookahead`).
2. Para cada candidata de soporte, calcular `supportValue`:
   - **Cura:** si el aliado moriría con el mejor ataque enemigo (`CalculadoraDanioIA::mejorEstimacionContra($enemigo, $aliado)` >= `hpActual + curacion`), `supportValue ≈ puntosKO` (100); si no, `min(0.5*hpMax, faltante)/hpMax * puntosCuracion` (p. ej. 60).
   - **Escudo:** `incrementoEscudo / maxEscudo * puntosEscudo` (p. ej. 40), solo si el incremento evita un KO o reduce daño relevante.
   - **Buff:** valor esperado del aumento de daño durante 5 rondas, o `puntosBuff` por etapa neta (p. ej. 20-30), con penalización si el stat ya está saturado.
   - **Aqua Ring:** `curacionPorRonda * rondasRestantes / hpMax * puntosCuracion`, descontando rondas ya cubiertas.
3. `score = survivalValue - risk + supportValue` (sin KO/daño/amenaza para soporte).
4. **Compuerta fase 1:** permitir soporte solo si no hay un KO garantizado disponible y el aliado está por debajo de un umbral (HP ≤ 50% o escudo con déficit relevante o buff no activo). Así el soporte compite, pero no desplaza un KO.
5. **Dificultad:** `NORMAL` puede usar la compuerta estricta; `DIFÍCIL`/`PERFECTA` pueden comparar scores completos. El desempate actual (`SelectorAccionIA.php:283-303`) sigue favoreciendo daño; si se quiere que el soporte gane empates, comparar `supportValue` antes de `damageValue`.

## B.8 `GestorPrioridadesBatalla`: funcionamiento y contrato

### B.8.1 Funcionamiento actual

- `declararIa()` (`GestorPrioridadesBatalla.php:27-50`) recorre `team2`, elige el movimiento con `priority > 0` de mayor prioridad y, en empate, mayor potencia (`:33-41`), pone postura `ATACAR`, limpia `objetivoCubiertoId` y declara prioridad (`:45-47`).
- `resolver()` (`:52-147`) recoge prioridades declaradas, ordena por prioridad y velocidad (con Trick Room) (`:72-82`), y para cada actor llama `objetivoPara()` (`:149-162`):
  - autodirigido o `ObjetivoMovimiento::ALL` → `actor`;
  - resto → `$battle->elegirObjetivoPara($actor)` (enemigo).
- Siempre ejecuta `aplicarMovimiento()` (`:130-141`), nunca `aplicarMovimientoAliado()`.
- `GestorPreparacionBatalla::abrir()` (`:29-68`) construye `prioridadesDisponibles` solo con `priority > 0` (`:41-51`); `aplicar()` (`:158-207`) valida la prioridad del jugador (`:174-192`) y declara las de la IA (`:199-204`).

### B.8.2 Qué impide seleccionar aliado

1. **Filtro de prioridad:** los 9 movimientos de soporte tienen `priority: 0` en `storage/data/moves.json`, así que no aparecen en `prioridadesDisponibles` ni en `declararIa`.
2. **Sin campo de objetivo:** `Combate::$prioridades` es `refId => índice` (`Combate.php:39-40`) y `$prioridadesDisponibles` es `refId => list<{index,nombre,prioridad}>` (`:45-46`); no hay aliado.
3. **Resolución forzada:** `objetivoPara()` (`:149-162`) fuerza `actor` o enemigo; no consulta el catálogo aliado.
4. **Ruta de daño:** `resolver()` usa `aplicarMovimiento()` (`:130-141`), no la rama aliada.
5. **Persistencia sin objetivo:** `EstadoTurno::$prioridad` es solo `MovimientoBatalla` (`declararPrioridad`, `:171-178`); `TurnoCombatiente::serializar()` guarda `prioridadDeclarada` (`:47-63`) y `SerializadorCombatiente.php:63`; no hay `objetivoId`.

### B.8.3 Cambios que exigiría el contrato

Para permitir prioridades sobre aliados haría falta:

1. Incluir soporte en `prioridadesDisponibles` (cambia la semántica de “movimiento de prioridad”).
2. Añadir objetivo: nueva propiedad en `Combate` (rompe el contrato 32/18), o codificar `índice|refId` en el string existente (compatible pero frágil), o nuevo `EstadoTurno::$objetivoPrioridadId` con default `''` y serialización aditiva en `TurnoCombatiente`/`SerializadorCombatiente`.
3. En `resolver()`, detectar aliado + catálogo y llamar `aplicarMovimientoAliado()`.
4. En `objetivoPara()`, usar el objetivo declarado para soporte y validar vivo/alcanzable.
5. UI: selector de aliado en `preparacion-panel.blade.php:24-40`.
6. IA: `declararIa()` no usa `SelectorAccionIA`; habría que añadir una heurística o reutilizar el evaluador. Duplica la decisión normal de la IA.

**Recomendación:** no usar `prioridades` para soporte en este frente. La IA debe decidir en su acción normal; el jugador ya puede seleccionar aliado. Documentar como limitación.

## B.9 Impacto en sesión, UI, animaciones, eventos y log

### B.9.1 Sesión

- No hay migraciones ni cambio de `SESSION_VERSION` si solo se añaden `supportValue`/pesos y la generación/evaluación.
- `MovimientoBatalla` ya tiene `slug` y lo serializa; `DTOMovimientoBatalla` lo hidrata con `?? ''` (TASK-030). `CondicionVolatil::curacionFija` ya es aditivo.
- La acción pendiente de la IA se guarda como `DTOAccionBatalla` con `defenderId`; no hace falta campo nuevo.
- Si se amplía `prioridades` con objetivo, `EstadoTurno`/`TurnoCombatiente` necesitan un campo aditivo con default; `BattleSessionService` seguiría cargando sesiones antiguas. No sería necesario subir `SESSION_VERSION`, pero sí verificar el `restaurar()` de `EstadoTurno`.

### B.9.2 UI

- La IA no usa `moves-panel` ni `currentMoves`; el panel del jugador no cambia.
- `prepararAnimacionIa()` fija `animAttacker*`/`animDefender*` y llama `gestorAnimacion->movimiento($componente, $actor, $targetForAnim, $movimiento)`. Con el `target` aliado correcto, la animación muestra “X usa Y” sobre el aliado.
- `BatallaVistaPresenter::refsAliado`/`actorAdmiteAliado` son solo para el jugador (`:115-153`, `PresentadorVistaCombate.php:53-62`); no afectan a la IA.
- No se requieren cambios en Blade para la IA. Si se quiere mostrar el objetivo aliado en el turn queue, ya viene del `gestorAnimacion`.

### B.9.3 Animaciones y eventos

- `ResolutorAccionBatalla::aplicarMovimientoAliado()` (`:155-203`) emite **un único evento `effect`** con `actorId`, `objetivoId = aliado`, `texto` y `forzar: true` (`:190-195`), consume turno/PP (`:197-198`) y no dispara daño, precisión, crítico, inmunidad ni reacciones.
- `acumularEvento()` usa `GrabadorEventosBatalla::registrar()` (`GestorEventosBatalla.php:57`); el evento lleva `cambios` calculados por `SnapshotBatalla`.
- JS existente: `reproducirEvento` procesa `cambios` (`combate-scripts.blade.php:341-395`), `animarCura` (`:452-469`) y la duración de `effect` (`:563-600`). No hay tipos nuevos.
- Para cura/escudo, los deltas de HP/DEF/SPDEF animan barras; para buff/Aqua Ring, el evento sin cambios vitales muestra píldora sobre el aliado y el morph de Livewire actualiza badges.

### B.9.4 Log

- `aplicarMovimientoAliado()` añade `"{$actor->nombre()} usa {$movimiento->nombre} sobre {$aliado->nombre()}"` (`:186`) y `EjecutorEfectoAliado` añade la línea concreta (“recupera X PS”, “recupera X de DEF”, “queda rodeado por Acua Aro…”).
- El camino aliado **no recibe prefijo “RIVAL”** (la firma no tiene prefijo, a diferencia de `aplicarMovimiento()`); el log de la IA puede quedar ambiguo. Opción: añadir un prefijo opcional o dejar el log genérico.

## B.10 Riesgos

| Riesgo | Detalle | Mitigación |
|---|---|---|
| **[alto] Daño al aliado en scoring** | `sand-tomb`/`fire-spin`/`whirlpool` no son estado; `estimar()` usa `defender` aliado y da `koValue=100` (`EvaluadorAccionIAImpl.php:42-68`; `CalculadoraDanioIA.php:64`). | Rama `esAccionApoyo` que no llame a `estimar()` sobre aliado. |
| **[alto] Autoaplicación del soporte** | `esAutodirigido()` true en `recover`/buffs (`MovimientoBatalla.php:194-215`); `prepararAnimacionIa` fuerza `defenderId=actor` (`GestorAccionBatalla.php:98-104`) y `resolver` fuerza `objetivo=actor` (`:169-172`). | Decisión única + no forzar actor cuando el objetivo es aliado. |
| **[alto] Incoherencia objetivo/movimiento** | `elegirObjetivoPara` y `elegirMejorMovimiento` hacen dos `elegirAccion()` (`SelectorAccionIA.php:91-104`, `109-131`; `AgregadoBatalla.php:395-403`). | `elegirAccionPara()` y consumir `move` + `defender` de la misma decisión. |
| **[alto] Exploraciones / `EjecutorAccion`** | `CombateExploracion.php:124` usa `ejecutarBatalla()`; `EjecutorAccion` daña al `defender` (`:40-43`, `:68-84`). Hoy es 1v1 (`contexto->aliados` vacío), pero si se generaliza, dañaría aliados. | Guarda: no generar soporte sin aliados; o rama `EjecutorEfectoAliado` en `EjecutorAccion`. |
| **[alto] Lookahead inválido** | `SimuladorAccionIA` aplica daño/estado con `ServicioEjecucionBatalla`; para soporte no cura al aliado, aplica buffs al actor y puede marcar `objetivoDerrotado` al aliado (`EvaluadorAccionIAImpl.php:111-155`). | Desactivar lookahead para soporte en fase 1; extenderlo después. |
| **[medio] Bucles / pasividad** | Cura/buff repetidos pueden alargar la batalla si ningún lado mata; el turno se consume, no hay bucle infinito de ejecución. | Compuerta de umbral, no curar/buffear si no hay efecto, favorecer daño cuando no hay riesgo. |
| **[medio] Doble buff / saturación** | El sistema acumula hasta clamp 4.0 y refresca un contador compartido de 5 rondas (`MultiplicadoresStats.php:25`, `:166-179`). | Umbral de multiplicador/`turnosTemporales`; decidir si se permite stacking. |
| **[medio] Retaguardia** | La IA podría curar a un aliado no alcanzable por el jugador. | Replicar la regla de `BatallaVistaPresenter::refsAliado` (`:115-153`). |
| **[medio] `DESAFIO`** | La IA podría ignorar el objetivo forzado con soporte; la UI lo permite para aliados (`GestorSeleccionBatalla.php:178-183`). | Decidir y documentar; si se bloquea, filtrar en generación. |
| **[medio] Estados inconsistentes** | Entre `prepararAnimacionIa()` y `commitAction()` el aliado podría morir por efectos de inicio de turno; `EjecutorEfectoAliado` comprueba `estaVivo()` pero consume turno. | Revalidar en generación y en resolución; no seleccionar aliados con HP crítico si el riesgo es alto. |
| **[bajo] Log ambiguo** | `aplicarMovimientoAliado` no lleva prefijo “RIVAL”. | Añadir prefijo opcional o aceptar log genérico. |
| **[bajo] Rendimiento** | Candidatas = movimientos × aliados; el lookahead es costoso. | Acotar aliados alcanzables y desactivar lookahead para soporte. |

## B.11 Fases propuestas y orden

1. **Fase 0 — decisión única y corrección de autoobjetivo.**
   - `AgregadoBatalla::elegirAccionPara()`.
   - `prepararAnimacionIa()` usa una sola acción y no fuerza `actor` si el `defender` es aliado.
   - Sin generar aún acciones de soporte: el comportamiento actual no cambia.
   - Verificación estática.

2. **Fase 1 — generación y evaluación mínima de soporte.**
   - Bloque aditivo en `SelectorAccionIA`.
   - Rama `esAccionApoyo` en `EvaluadorAccionIAImpl`, `supportValue` en `EvaluacionAccion`, pesos en `PesosAmenaza`.
   - Filtros anti-inútiles (B.6).
   - Lookahead desactivado para soporte.
   - Compuerta conservadora: sin KO disponible y aliado bajo umbral.
   - Guarda para `EjecutorAccion`/Exploraciones.

3. **Fase 2 — scoring completo y simulación.**
   - Extender `SimuladorAccionIA` para aplicar cura/escudo/buff al aliado en el clon.
   - Valorar buffs por daño esperado a 5 rondas y Aqua Ring por rondas restantes.
   - Ajustar pesos y desempate.
   - Smoke manual de IA en `/combate`.

4. **Fase 3 — prioridades (tarea separada, opcional).**
   - Requiere objetivo en `prioridades`, cambio de `EstadoTurno`/serialización, UI y resolución aliada.
   - No hacerlo antes de Fase 0-1: sin decisión única y sin rama aliada, el resultado sería incorrecto.

**Orden recomendado:** Fase 0 → Fase 1 → Fase 2 → Fase 3. Hacer prioridades primero rompe el contrato de `Combate`, la sesión y la UI antes de que la IA sepa elegir soporte.

## B.12 Preguntas abiertas

1. ¿Qué significa “prioridades” en este frente: el sistema existente de movimientos `priority > 0` o un nuevo sistema de preferencias estratégicas de la IA?
2. ¿La IA puede curar/buffear a un aliado en retaguardia, o se replica la regla de UI (solo vanguardia si hay vanguardia viva)?
3. ¿Se permite apilar buffs hasta el clamp actual (4.0) o la IA debe evitar repetir el mismo stat? ¿Qué umbral?
4. ¿La cura debe usarse solo cuando evita un KO o también preventivamente por debajo de un umbral (50%, 25%)?
5. ¿El soporte compite por score con el daño o solo se activa cuando no hay ataque rentable?
6. ¿Qué hacer con `recover`/Aqua Ring sobre aliado con PS llenos: descartar o permitir el consumo de turno?
7. ¿El `DESAFIO` debe bloquear el soporte sobre aliados?
8. ¿Se autoriza añadir `supportValue` a `EvaluacionAccion` y pesos nuevos a `PesosAmenaza`?
9. ¿Se autoriza añadir `AgregadoBatalla::elegirAccionPara()` (método aditivo) para eliminar la doble decisión?
10. ¿Se debe soportar aliados en `EjecutorAccion`/Exploraciones en el futuro o se deja fuera y se documenta?
11. ¿Se amplía `CatalogoEfectosAliado` con más movimientos de TASK-029 §4 o se mantienen los 9?
12. ¿La dificultad `NORMAL` debe usar soporte con la misma lógica que `PERFECTA`, o solo cuando no pueda atacar?

## B.13 Limitaciones del análisis

- No se ejecutaron herramientas, tests ni smoke manual en esta entrega; todo se basa en las lecturas previas y en TASK-029/TASK-030.
- Las líneas citadas corresponden al HEAD `c020b6c`; TASK-030 pudo desplazar algunas líneas de `MovimientoBatalla` al añadir `slug`. Se citan por método cuando hay duda.
- No se leyó `PesosAmenaza` completo con numeración, ni todos los ejecutores de efectos, ni `BattleSessionService` directamente en esta sesión; la conclusión sobre sesión es condicional.
- No se confirmó con conteo automatizado el contrato 32 propiedades/18 métodos de `Combate`; se toma de TASK-030.
- No se verificó en runtime el comportamiento de `esAutodirigido()` con `recover`/buffs ni la animación del aliado; la evidencia es estática.
- No se revisaron todos los movimientos de `storage/data/moves.json`, solo los slugs del alcance y `aqua-ring`.
- No se evaluó el impacto en tests existentes (no se ejecutan por restricción).

---

# Preguntas abiertas consolidadas

Las siguientes decisiones de producto/técnica provienen literalmente de los dos análisis y condicionan el diseño y el plan:

## Producto / semántica

1. Purificación: ¿cura PS al aliado o al lanzador? (A.11.1)
2. Bálsamo Osado: ¿+1 a las cinco stats es intencional? ¿Doble buff? (A.11.2, riesgo alto)
3. Bastión Final: ¿`SIN_RETIRADA` al aliado? ¿Permanente o temporal? (A.11.3)
4. Aullido / Piñón Auxiliar / Motivación: ¿equipo completo o aliado único? ¿Incluyen al lanzador? (A.11.4)
5. Niebla Aromática: ¿aliado único adyacente? ¿Modo propio? (A.11.5)
6. Acupresión: ¿self? ¿Stat aleatoria o determinista? (A.11.6)
7. Alivio: ¿qué estados cura? ¿Confusión? (A.11.7)
8. Plegaria Lunar: ¿25% o 50%? (A.11.8)
9. Curas de equipo: ¿desbordan a escudo? (A.11.9)
10. Soportes `adjacentAlly`: ¿impedir uso sobre rival? (A.11.10)
11. Descanso: confirmar exclusión del modo aliado. (A.11.13)

## Alcance / datos

12. ¿Se autoriza ampliar el catálogo con los 9 curas + 25 buffs en una sola fase? (A.11.11)
13. ¿Se autoriza tocar `storage/data/moves.json` y reseed (`howl`, `lunar-blessing`)? Sin migración. (A.11.12)
14. ¿Se implementan los compuestos (`take-heart`, `no-retreat`) y `acupressure` en esta ola? (A.10 Fase 2)
15. ¿Se implementa `STATS_ALIADO`/equipo en esta ola? (A.10 Fase 3)

## IA

16. ¿Prioridades = sistema `priority > 0` o preferencias estratégicas? (B.12.1)
17. ¿IA puede actuar sobre retaguardia aliada? (B.12.2)
18. ¿Apilar buffs hasta clamp o evitar repetir stat? ¿Umbral? (B.12.3)
19. ¿Cura solo si evita KO o también preventiva por umbral? (B.12.4)
20. ¿Soporte compite por score o solo si no hay ataque rentable? (B.12.5)
21. ¿`DESAFIO` bloquea soporte? (B.12.7)
22. ¿Se autoriza `supportValue` en `EvaluacionAccion` y pesos nuevos? (B.12.8)
23. ¿Se autoriza `AgregadoBatalla::elegirAccionPara()`? (B.12.9)
24. ¿Exploraciones/`EjecutorAccion` fuera de alcance con guarda? (B.12.10)
25. ¿Dificultad `NORMAL` con compuerta estricta? (B.12.12)
