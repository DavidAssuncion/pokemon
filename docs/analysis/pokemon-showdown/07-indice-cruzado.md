# 07 — Índice Cruzado: Pokemon Showdown ↔ Nuestro Proyecto

> **Propósito:** Referencia rápida para navegar entre ambos repositorios. No reemplaza los documentos de análisis (01-06), sino que los complementa con indexación práctica.

---

## A. Concepto → Dónde está en cada repo

### Mecánicas de daño

| Concepto | Pokemon Showdown | Nuestro proyecto | Notas |
|----------|-----------------|------------------|-------|
| **Fórmula de daño base** | `sim/battle-actions.ts` → `getDamage()` (líneas ~1717) | `Chain/ManejadorDanioBase.php` → `process()` | PS: `⌊⌊⌊(2L/5+2)×A×P/D⌋/50⌋`. Nosotros: `(((2×50/5+2)×potencia×atk/max(def,1))/50)+2` |
| **Type effectiveness / TypeChart** | `data/typechart.ts` — `damageTaken` por tipo (0=inmune, 1=resiste, 2=neutro, 3=súper eficaz) | `src/Shared/Tipos/TypeChart.php` → `getEffectiveness()` + `TipoPokemon::effectivenessAgainst()` | PS codifica 0-3; nosotros usamos 0.0/0.5/1.0/2.0 directamente |
| **STAB** | `sim/battle-actions.ts` → evento `ModifySTAB` (×1.5 normal, ×2.0 Tera, ×2.25 Adaptability) | `Chain/ManejadorSTAB.php` → `process()` (×1.5 si coincide tipo) | No implementamos Tera/Adaptability |
| **Critical hits** | `sim/battle-actions.ts` → `randomChance(1, critMult[critRatio])` (líneas ~1623). `critMult = [0,24,8,2,1]` | `Chain/ManejadorCritico.php` → `process()` (6.25% = 1/16, ×1.5) | PS: 1/24 base. Nosotros: 1/16. Ambos ×1.5 |
| **Stat stages / boosts** | `sim/pokemon.ts` → `boosts` object + `boostTable = [1,1.5,2,2.5,3,3.5,4]` | `ValueObjects/EtapasStats.php` → `obtenerMultiplicador()` | Ambos rango -6 a +6. Fórmula: nosotros `(2+stage)/2` / `2/(2-stage)` — equivalente |
| **Weather damage (end of turn)** | `data/conditions.ts` → weather residual damage (sand: 1/16 a non-Rock/Ground/Steel) | `CalculadorDañoClima.php` → `calcular()` + `AgregadoBatalla::triggerRoundEndEffects()` | Nosotros: granizo 6.25% a no-HIELO; tormenta arena 6.25% a no-ROCA/TIERRA/ACERO |
| **Recoil damage** | `data/moves.ts` → `recoil: [num, den]` + `sim/battle-actions.ts` evento `Damage` | `Effects/EfectoOrbeVida.php` → `onDamageDealt()` (recoil 10% HP) | PS: genérico por movimiento. Nosotros: solo Life Orb |
| **Accuracy / Evasion** | `sim/battle-actions.ts` — `P(acierto) = accuracy_mod × move.accuracy × 100 / target.evasion` | No implementado (movimientos siempre aciertan) | Pendiente |
| **Critical hit ratio/stages** | `sim/battle-actions.ts` → `critRatio` (0-4), `critMult` table | `Chain/ManejadorCritico.php` — ratio fijo 6.25% | PS soporta moves con critRatio variable; nosotros fijo |

### Entidades de batalla

| Concepto | Pokemon Showdown | Nuestro proyecto | Notas |
|----------|-----------------|------------------|-------|
| **Pokemon species / data** | `data/pokedex.ts` → `SpeciesDataTable` (~1100 entradas). `sim/dex-species.ts` interface | `src/Pokemon/Domain/PokemonEntity.php` + BD (`pokemon` table) | PS: archivo TS. Nosotros: Eloquent model + stats en `pokemon_stats` |
| **Pokemon in battle (active instance)** | `sim/pokemon.ts` → `class Pokemon` (hp, boosts, volatiles, moveSlots, position) | `src/Battle/Domain/Combatiente.php` (hpActual, barreras, etapas, estado, efectos, posicion) | Nosotros añadimos barreras duales (defensa física/especial) — mecánica propia |
| **Move definition (data)** | `data/moves.ts` → `MoveDataTable` (~900 movimientos, ~21k líneas). `sim/dex-moves.ts` interface | `src/Battle/Domain/MovimientoBatalla.php` (VO inmutable) + mock en `FabricaBatallaMock` | PS: 900+ moves. Nosotros: definidos inline en mock |
| **Move in battle (active)** | Movimientos como parte de `pokemon.moveSlots[]` con PP | `MovimientoBatalla.php` — VO con nombre, potencia, tipo, categoría, prioridad | No diferenciamos move definition vs active move |
| **Ability definition + effects** | `data/abilities.ts` → `AbilityDataTable` (~300). Event handlers inline (`onModifyAtk`, etc.) | `Effects/InterfazEfecto.php` + clases concretas (`EfectoPerforacionArmadura`, etc.) + `FabricaEfectos` | PS: handlers inline en data. Nosotros: clases Strategy separadas |
| **Item definition + effects** | `data/items.ts` → `ItemDataTable` (~200). Event handlers inline | `Effects/EfectoRestos.php`, `EfectoOrbeVida.php` + `Chain/ManejadorObjetosEquipados.php` (mapa multiplicadores) | PS: handlers inline. Nosotros: efectos + cadena de daño |
| **Team / Side** | `sim/side.ts` → `class Side` (pokemon[], active[], team, choice) | `src/Battle/Domain/EquipoBatalla.php` (3 combatientes) | PS soporta 1-6 pokemon. Nosotros: siempre 3 |
| **Field (weather, terrain)** | `sim/field.ts` → `class Field` (weather, terrain, pseudoWeather) | `src/Battle/Domain/AgregadoBatalla.php` → `$weather` (TipoClima enum) | PS: weather + terrain + pseudoWeather. Nosotros: solo weather (7 valores) |

### Sistema de turnos

| Concepto | Pokemon Showdown | Nuestro proyecto | Notas |
|----------|-----------------|------------------|-------|
| **Action queue / turn order** | `sim/battle-queue.ts` → sorting por order → priority → speed → subOrder + Fisher-Yates | `src/Battle/Domain/GestorTurnos.php` → velocidad acumulada por ronda | PS: 4 niveles de prioridad. Nosotros: velocidad acumulada (mecánica simplificada) |
| **Priority system** | `sim/battle-queue.ts` → `sortActions()`. Prioridad del movimiento (-7 a +5) | `MovimientoBatalla.php` → `$priority` (int). `GestorTurnos` usa velocidad acumulada | PS: prioridad separada de velocidad. Nosotros: prioridad en VO pero turnos por velocidad |
| **Speed calculation** | `sim/pokemon.ts` → `getStat('spe')` + `getActionSpeed()` (trick room: 10000-speed) | `Combatiente.php` → `obtenerStatEfectivo('speed')` + `GestorTurnos::agregarVelocidad()` | PS: speed directa. Nosotros: velocidad acumulada por ronda |
| **Turn/round lifecycle** | `sim/battle.ts` → decrementWeather → runEvent('UponTurn') → choices → execute → faint check → endOfTurn | `AgregadoBatalla.php` → `ejecutarBatalla()` loop: startNewRound → execute actions → triggerRoundEndEffects | PS: 5 fases. Nosotros: start → execute → endOfRound |
| **Switch mechanics** | `sim/battle.ts` → switch actions, runSwitch event, switchOut/switchIn | No implementado (equipo fijo de 3 en campo) | Pendiente para PvP |

### Estados y condiciones

| Concepto | Pokemon Showdown | Nuestro proyecto | Notas |
|----------|-----------------|------------------|-------|
| **Status conditions** | `data/conditions.ts` → psn, tox, brn, par, frz, slp | `Enums/EstadoPokemon.php` (8 valores: NONE, BURN, POISON, BAD_POISON, PARALYSIS, SLEEP, FREEZE, CONFUSION) | PS: confusión es volatile. Nosotros: enum unificado |
| **Volatile conditions** | `sim/pokemon.ts` → `volatiles` object (~50+ volátiles) | `Combatiente.php` → `puedeActuar()` (confusión inline como volatile) + `Effects/InterfazEfecto.php` hooks | PS: sistema genérico de volátiles. Nosotros: confusión integrada en Combatiente |
| **Side conditions** | `sim/side.ts` → side conditions (Stealth Rock, Spikes, etc.) | No implementado | Pendiente |
| **Weather / Terrain** | `data/conditions.ts` → weather definitions + `sim/field.ts` | `Enums/TipoClima.php` (7 valores) + `Effects/EfectoInvocadorClima.php` + `Chain/ManejadorClima.php` | PS: weather + terrain + pseudoWeather. Nosotros: solo weather |
| **Stat stages** | `sim/pokemon.ts` → `boosts` object + `boostTable` | `ValueObjects/EtapasStats.php` + `Combatiente::aplicarCambioEtapa()` | Equivalente: -6 a +6 |

### Estructuras de datos

| Concepto | Pokemon Showdown | Nuestro proyecto | Notas |
|----------|-----------------|------------------|-------|
| **Type chart (18×18)** | `data/typechart.ts` | `src/Shared/Tipos/TypeChart.php` + `src/Shared/Tipos/TipoPokemon.php` | PS: 324 entradas en TS. Nosotros: array sparse en PHP (solo no-neutrales) |
| **Natures table** | `data/natures.ts` (25 naturalezas) | No implementado | Pendiente — Enum propuesto en `06-recomendaciones-migracion.md` |
| **Move data structure** | `sim/dex-moves.ts` → `MoveData` interface | `src/Battle/Domain/MovimientoBatalla.php` | PS: ~25 propiedades. Nosotros: 8 propiedades (simplificado) |
| **Ability data structure** | `sim/dex-abilities.ts` → `AbilityData` interface | `Effects/InterfazEfecto.php` + clases concretas | PS: data + event handlers. Nosotros: clases Strategy |
| **Item data structure** | `sim/dex-items.ts` → `ItemData` interface | `Effects/InterfazEfecto.php` + `Chain/ManejadorObjetosEquipados.php` (mapa) | PS: data + handlers. Nosotros: mapa + efectos |
| **Species/Pokedex data structure** | `sim/dex-species.ts` → `SpeciesData` interface | `src/Pokemon/Domain/PokemonEntity.php` + BD (`pokemon`, `pokemon_stats`) | PS: TS interface. Nosotros: Eloquent + StatsValue |

### Patrones de diseño

| Concepto | Pokemon Showdown | Nuestro proyecto | Notas |
|----------|-----------------|------------------|-------|
| **Event system (runEvent)** | `sim/battle.ts` → `runEvent()` — cadena de callbacks con prioridad | `Chain/CadenaDanio.php` (7 manejadores) + `Effects/InterfazEfecto.php` (11 hooks) | PS: sistema genérico. Nosotros: cadena explícita + Strategy |
| **Chain of responsibility** | Implícito en `runEvent()` — listeners en cadena | `Chain/` — 7 manejadores: Base → Tipo → STAB → Crit → Posición → Clima → Objetos | Nosotros: patrón explícito. PS: patrón implícito en evento |
| **Strategy pattern (effects)** | Habilidades/objetos con event handlers inline | `Effects/` — `InterfazEfecto` + `ComportamientosPorDefecto` trait + clases concretas | PS: monolítico. Nosotros: Strategy separada |
| **Observer pattern** | Implícito en `runEvent()` — habilidades/objetos escuchan eventos | `Observer/SujetoBatalla.php` + `ObservadorBatalla.php` (3 eventos: endTurn, damaged, fainted) | PS: implícito. Nosotros: Observer explícito |
| **State serialization** | `sim/state.ts` → `State.serialize()` / `State.deserialize()` (JSON recursivo) | `app/Livewire/Combate.php` → `saveBattle()`/`getBattle()` + `Combatiente::__serialize()` (sesión con versionado v4) | PS: JSON. Nosotros: PHP serialize + versionado |
| **Mod/Dex lazy loading** | `sim/dex.ts` — carga archivos `.ts` bajo demanda, cache, mods | `src/Shared/Tipos/TypeChart.php` → cache estática (`self::$chart`). BD + Cache::remember() para datos futuros | PS: lazy loading por archivo. Nosotros: cache estática o Laravel Cache |

---

## B. "Quiero implementar X → por dónde empezar"

| Quiero... | En PS busca | En nuestro proyecto busca | Paso sugerido |
|-----------|-------------|---------------------------|---------------|
| **Agregar un nuevo tipo de daño** | `sim/battle-actions.ts` → `getDamage()` + eventos `ModifyDamage` | `Chain/ManejadorDanioXxx.php` + registrar en `CadenaDanio::__construct()` | Crear nuevo manejador extendiendo `ManejadorDanioAbstracto`, añadir al final de la cadena |
| **Agregar una nueva habilidad** | `data/abilities.ts` + hooks en `onXxx` | `Effects/EfectoXxx.php` + registrar en `FabricaEfectos` + `BattleEffectServiceProvider` | Crear clase que implemente `InterfazEfecto`, usar `ComportamientosPorDefecto`, sobrescribir hooks necesarios |
| **Agregar un nuevo estado alterado** | `data/conditions.ts` | `Enums/EstadoPokemon.php` + lógica en `Combatiente::puedeActuar()` y `aplicarDañoStatus()` | Añadir case al enum + implementar lógica de bloqueo/daño en Combatiente |
| **Modificar la fórmula de daño** | `sim/battle-actions.ts` → `getDamage()` | `Chain/ManejadorDanioBase.php` → `process()` | Modificar la fórmula en el manejador base |
| **Agregar un nuevo clima** | `data/conditions.ts` → weather | `Enums/TipoClima.php` + `EfectoInvocadorClima` + `ManejadorClima` + `CalculadorDañoClima` | Añadir case al enum + actualizar multiplicadores en manejadores |
| **Agregar un nuevo movimiento** | `data/moves.ts` | `MovimientoBatalla.php` (VO) + definición en `FabricaBatallaMock` / BD futura | Definir VO con nombre, potencia, tipo, categoría, prioridad |
| **Agregar un objeto equipado** | `data/items.ts` | `Effects/EfectoXxx.php` + clave en `ManejadorObjetosEquipados` mapa | Crear efecto (si tiene hooks) + añadir clave al mapa `['objeto' => multiplicador]` |
| **Modificar prioridades** | `sim/battle-queue.ts` → `resolveAction()` | `GestorTurnos.php` + `MovimientoBatalla::$priority` | Ajustar lógica de orden en GestorTurnos (actualmente usa velocidad acumulada) |
| **Agregar targeting 3v3** | `sim/dex-moves.ts` → `MoveTarget` | `SelectorAccionIA.php` → `elegirObjetivoPara()` | Simplificar a vanguardia/retaguardia. PS soporta 15+ target types; nosotros ~4 |
| **Serializar batalla** | `sim/state.ts` | `Combate.php` → `saveBattle()`/`getBattle()` + `Combatiente::__serialize()` | Verificar `SESSION_VERSION` (actual: 4) |
| **Agregar un volátil** | `sim/pokemon.ts` → `volatiles[id]` + `data/conditions.ts` | `Effects/InterfazEfecto.php` hooks + lógica en `Combatiente::puedeActuar()` | Crear efecto o integrar en Combatiente según complejidad |
| **Agregar efecto secundario de movimiento** | `data/moves.ts` → `secondary` object | `MovimientoBatalla.php` → `statusEffect`, `selfStatChanges`, `targetStatChanges` | Definir en VO del movimiento; aplicar en `ServicioEjecucionBatalla::aplicarEstado()` / `aplicarStatChanges()` |
| **Agregar daño de confusión** | `sim/battle-actions.ts` → líneas ~1850 (basePower 40, sin modificadores) | `Combatiente.php` → `puedeActuar()` caso CONFUSION | Ya implementado: fórmula nivel 50, poder 40, 33% probabilidad |

---

## C. Tabla de tipos completa

| Tipo (EN) | Tipo (ES) | Nuestro Enum | VS Normal | VS Fire | VS Water | VS Electric | VS Grass | VS Ice | VS Fighting | VS Poison | VS Ground | VS Flying | VS Psychic | VS Bug | VS Rock | VS Ghost | VS Dragon | VS Dark | VS Steel | VS Fairy |
|:---------:|:---------:|:------------:|:---------:|:-------:|:--------:|:-----------:|:--------:|:------:|:-----------:|:---------:|:---------:|:---------:|:----------:|:------:|:-------:|:--------:|:---------:|:-------:|:--------:|:--------:|
| **Normal** | Normal | `TipoPokemon::NORMAL` | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | ½ | 0 | 1 | 1 | ½ | 1 |
| **Fire** | Fuego | `TipoPokemon::FUEGO` | 1 | ½ | ½ | 1 | 2 | 2 | 1 | 1 | 1 | 1 | 1 | 2 | ½ | 1 | ½ | 1 | 2 | 1 |
| **Water** | Agua | `TipoPokemon::AGUA` | 1 | 2 | ½ | 1 | ½ | 1 | 1 | 1 | 2 | 1 | 1 | 1 | 2 | 1 | ½ | 1 | 1 | 1 |
| **Electric** | Eléctrico | `TipoPokemon::ELECTRICO` | 1 | 1 | 2 | ½ | ½ | 1 | 1 | 1 | 0 | 2 | 1 | 1 | 1 | 1 | ½ | 1 | 1 | 1 |
| **Grass** | Planta | `TipoPokemon::PLANTA` | 1 | ½ | 2 | 1 | ½ | 1 | 1 | ½ | 2 | ½ | 1 | ½ | 2 | 1 | ½ | 1 | 1 | 1 |
| **Ice** | Hielo | `TipoPokemon::HIELO` | 1 | ½ | ½ | 1 | 2 | 1 | 1 | 1 | 2 | 2 | 1 | 1 | 1 | 1 | 2 | 1 | ½ | 1 |
| **Fighting** | Lucha | `TipoPokemon::LUCHA` | 2 | 1 | 1 | 1 | 1 | 2 | 1 | ½ | 1 | ½ | ½ | ½ | 2 | 0 | 1 | 2 | 2 | ½ |
| **Poison** | Veneno | `TipoPokemon::VENENO` | 1 | 1 | 1 | 1 | 2 | 1 | 1 | ½ | ½ | 1 | 1 | 1 | ½ | 1 | 1 | 1 | 0 | 2 |
| **Ground** | Tierra | `TipoPokemon::TIERRA` | 1 | 2 | 1 | 2 | ½ | 1 | 1 | 2 | 1 | 0 | 1 | ½ | 2 | 1 | 1 | 1 | 1 | 2 | 1 |
| **Flying** | Volador | `TipoPokemon::VOLADOR` | 1 | 1 | 1 | ½ | 2 | 1 | 2 | 1 | 1 | 1 | 1 | 2 | ½ | 1 | 1 | 1 | 1 | ½ | 1 |
| **Psychic** | Psíquico | `TipoPokemon::PSIQUICO` | 1 | 1 | 1 | 1 | 1 | 1 | 2 | 2 | 1 | 1 | ½ | 1 | 1 | 1 | 1 | 1 | 0 | ½ | 1 |
| **Bug** | Bicho | `TipoPokemon::BICHO` | 1 | ½ | 1 | 1 | 2 | 1 | ½ | ½ | 1 | ½ | 2 | 1 | 1 | 1 | 1 | 1 | 2 | ½ | ½ |
| **Rock** | Roca | `TipoPokemon::ROCA` | 1 | 2 | 1 | 1 | 1 | 2 | ½ | 1 | ½ | 2 | 1 | 2 | 1 | 1 | 1 | 1 | 1 | ½ | 1 |
| **Ghost** | Fantasma | `TipoPokemon::FANTASMA` | 0 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 2 | 1 | 1 | 2 | 1 | ½ | 1 | 1 | 1 |
| **Dragon** | Dragón | `TipoPokemon::DRAGON` | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 1 | 2 | 1 | ½ | 0 |
| **Dark** | Siniestro | `TipoPokemon::SINIESTRO` | 1 | 1 | 1 | 1 | 1 | 1 | ½ | 1 | 1 | 1 | 2 | 1 | 1 | 2 | 1 | ½ | 1 | 1 | ½ |
| **Steel** | Acero | `TipoPokemon::ACERO` | 1 | ½ | ½ | ½ | 1 | 2 | 1 | 1 | 1 | 1 | 1 | 1 | 2 | 1 | 1 | 1 | 1 | 1 | 2 |
| **Fairy** | Hada | `TipoPokemon::HADA` | 1 | ½ | 1 | 1 | 1 | 1 | 2 | ½ | 1 | 1 | 1 | 1 | 1 | 1 | 2 | 2 | ½ | 1 | 1 |

> **Leyenda:** 0 = inmune, ½ = resistente (×0.5), 1 = neutro, 2 = super efectivo (×2).

---

## D. Mapeo de archivos PS → archivos nuestro proyecto

| Propósito | Pokemon Showdown | Nuestro proyecto |
|-----------|-----------------|------------------|
| **Battle simulation core** | `sim/battle.ts` | `src/Battle/Domain/AgregadoBatalla.php` |
| **Damage calculation** | `sim/battle-actions.ts` → `getDamage()` | `src/Battle/Domain/Chain/CadenaDanio.php` + 7 manejadores |
| **Pokemon in battle** | `sim/pokemon.ts` | `src/Battle/Domain/Combatiente.php` |
| **Player/Side** | `sim/side.ts` | `src/Battle/Domain/EquipoBatalla.php` |
| **Turn management** | (inline en `battle.ts`) | `src/Battle/Domain/GestorTurnos.php` |
| **Action queue** | `sim/battle-queue.ts` | `src/Battle/Domain/GestorTurnos.php` + `AccionBatalla.php` |
| **Type chart** | `data/typechart.ts` | `src/Shared/Tipos/TypeChart.php` + `TipoPokemon.php` |
| **Natures** | `data/natures.ts` | (no implementado — propuesta en `06-recomendaciones-migracion.md`) |
| **Move data** | `data/moves.ts` | `src/Battle/Domain/MovimientoBatalla.php` (VO) + mock en `FabricaBatallaMock` |
| **Ability effects** | `data/abilities.ts` | `src/Battle/Domain/Effects/EfectoXxx.php` (Strategy Pattern) |
| **Item effects** | `data/items.ts` | `src/Battle/Domain/Effects/EfectoXxx.php` + `Chain/ManejadorObjetosEquipados.php` |
| **Species data** | `data/pokedex.ts` | `src/Pokemon/Domain/PokemonEntity.php` + BD (`pokemon`, `pokemon_stats`) |
| **Status/conditions** | `data/conditions.ts` | `src/Battle/Domain/Enums/EstadoPokemon.php` + lógica en `Combatiente` |
| **Weather/Terrain** | `sim/field.ts` + `data/conditions.ts` | `src/Battle/Domain/Enums/TipoClima.php` + `ManejadorClima` + `CalculadorDañoClima` |
| **State serialization** | `sim/state.ts` | `app/Livewire/Combate.php` → `saveBattle()`/`getBattle()` + `Combatiente::__serialize()` |
| **PRNG** | `sim/prng.ts` (determinista con semilla) | `mt_rand()` / `mt_srand()` PHP nativo (no determinista) |
| **Battle protocol** | `PROTOCOL.md` (WebSocket messages) | Livewire wire protocol (implícito) |
| **Team validation** | `sim/team-validator.ts` | (no implementado) |
| **Dex/API** | `sim/dex.ts` (lazy loading + mods) | Eloquent models + `TypeChart` cache estática |
| **Targeting** | `sim/dex-moves.ts` → `MoveTarget` + `sim/pokemon.ts` → `getMoveTargets()` | `SelectorAccionIA.php` → `elegirObjetivoPara()` |
| **Observer/Events** | `sim/battle.ts` → `runEvent()` | `src/Battle/Domain/Observer/SujetoBatalla.php` + `ObservadorBatalla.php` |
| **Effect factory** | `sim/dex.ts` → `dex.abilities.get()` / `dex.items.get()` | `src/Battle/Domain/Effects/FabricaEfectos.php` + `BattleEffectServiceProvider` |
| **Damage execution** | `sim/battle-actions.ts` → `runMove()` | `src/Battle/Domain/ServicioEjecucionBatalla.php` → `calcularYAplicarDano()` |
| **AI selection** | `sim/tools/random-player-ai.ts` | `src/Battle/Domain/SelectorAccionIA.php` |
| **Data interfaces** | `sim/dex-moves.ts`, `sim/dex-species.ts`, `sim/dex-abilities.ts`, `sim/dex-items.ts` | `MovimientoBatalla.php`, `PokemonEntity.php`, `InterfazEfecto.php` |

---

## E. Glosario de términos cruzado

| Término | Definición | Pokemon Showdown | Nuestro proyecto |
|---------|------------|-----------------|------------------|
| **Ability** | Habilidad pasiva o activa de un Pokémon que modifica comportamiento en batalla | `data/abilities.ts` (~300). Event handlers (`onModifyAtk`, etc.) | `Effects/InterfazEfecto.php` + clases concretas. Registradas en `FabricaEfectos` |
| **Action Queue** | Cola que ordena y ejecuta las acciones de un turno | `sim/battle-queue.ts` — sorting por order→priority→speed→subOrder | `GestorTurnos.php` — velocidad acumulada por ronda |
| **Accuracy** | Probabilidad de que un movimiento acierte. Fórmula: `accuracy_mod × move.acc × 100 / evasion` | `sim/battle-actions.ts` | No implementado (ataques siempre aciertan) |
| **Boost** | Modificador temporal de stat (-6 a +6). Tabla: ×0.25 a ×4 | `sim/pokemon.ts` → `boosts` object | `ValueObjects/EtapasStats.php` + `Combatiente::aplicarCambioEtapa()` |
| **Critical Hit** | Golpe crítico que ignora boosts defensivos y aplica ×1.5 de daño | `sim/battle-actions.ts` → `randomChance(1, critMult[ratio])` | `Chain/ManejadorCritico.php` — 6.25% ×1.5 |
| **Dex** | Biblioteca de acceso a datos estáticos (pokemon, movimientos, habilidades, etc.) | `sim/dex.ts` — lazy loading + cache + mods | `TypeChart.php` (cache estática). Futuro: BD + `Cache::remember()` |
| **Effectiveness** | Multiplicador de daño por tipo (0, 0.5, 1, 2) | `data/typechart.ts` — `damageTaken` (codificado 0-3) | `TypeChart::getEffectiveness()` (0.0/0.5/1.0/2.0) |
| **EV** | Effort Values — puntos ganados al derrotar pokemon, afectan stats finales | Cálculo en stat formula | `StatsValue.php` (propiedad `evs`) + `BattleStats.php` |
| **Field** | Estado compartido del campo de batalla (clima, terreno, pseudoWeather) | `sim/field.ts` → `class Field` | `AgregadoBatalla.php` → `$weather` (TipoClima) |
| **Fling** | Mecánica para lanzar el objeto equipado como ataque | `data/items.ts` → `fling` property | No implementado |
| **Gen** | Generación del juego (Gen 1-9). Afecta fórmulas y mecánicas | Branches por gen en `sim/battle-actions.ts`, `sim/pokemon.ts` | Solo Gen 9+ (sin branches) |
| **IV** | Individual Values — valores individuales que afectan stats | Cálculo en stat formula | No implementado directamente |
| **Learnset** | Lista de movimientos que un pokemon puede aprender | `data/learnsets.ts` | No implementado (movimientos definidos en mock) |
| **Matchup** | Ventaja/desventaja de tipos entre dos pokemon | TypeChart + effectiveness calculations | `TipoPokemon::effectiveness()` + `TypeChart::getEffectiveness()` |
| **Mega Evolution** | Transformación temporal que cambia stats base y tipo | `data/pokedex.ts` → `isMega`, `megaEvolves`. Eventos en `battle-actions.ts` | No implementado |
| **Modifier** | Multiplicador aplicado al daño (weather, STAB, crit, etc.) | Cada paso de `getDamage()` + eventos `Modify*` | Cada manejador en `CadenaDanio` (7 pasos) |
| **Move** | Movimiento que un Pokémon puede usar en batalla (potencia, tipo, categoría, etc.) | `data/moves.ts` (~900). `MoveData` interface en `sim/dex-moves.ts` | `MovimientoBatalla.php` (VO inmutable, 8 propiedades) |
| **Nature** | Naturaleza que modifica ±10% en dos stats | `data/natures.ts` (25 naturalezas) | No implementado — propuesta en `06-recomendaciones-migracion.md` |
| **OHKO** | One Hit Knock Out — movimiento que debilita de un golpe | `sim/dex-moves.ts` → `ohko` property. Accuracy = userLv - targetLv + 30 | No implementado |
| **PID** | Pokémon ID / identificador único | `data/pokedex.ts` → `num` property | `PokemonEntity` + BD `pokemon.id` |
| **PP** | Power Points — usos restantes de un movimiento | `sim/pokemon.ts` → `moveSlots[].pp` | No implementado (movimientos ilimitados) |
| **Priority** | Modificador de orden de acción del movimiento (-7 a +5) | `sim/dex-moves.ts` → `priority`. Aplicado en `battle-queue.ts` | `MovimientoBatalla.php` → `$priority`. Turnos por velocidad acumulada |
| **PRNG** | Pseudo-Random Number Generator determinista con semilla | `sim/prng.ts` — permite replays exactos | `mt_rand()` / `mt_srand()` (no determinista, sin replay) |
| **Quadruple Resists** | Resistencia x4 (dos resistencias ×0.5 apiladas) | TypeChart × multiplicative stacking | `TipoPokemon::effectiveness()` × por cada tipo defensor |
| **Random** | Factor aleatorio de daño: `×[0.85, 1.0]` uniforme | `sim/battle-actions.ts` → `randomizer()` | No implementado en cadena actual (pendiente) |
| **Recoil** | Daño que el atacante recibe al usar ciertos movimientos | `data/moves.ts` → `recoil: [num, den]`. Aplicado en `battle-actions.ts` | `EfectoOrbeVida.php` → `onDamageDealt()` (solo Life Orb, 10%) |
| **Resists** | Resistencia ×0.5 a un tipo | TypeChart | `TypeChart::getEffectiveness()` → 0.5 |
| **Roll** | Tirada aleatoria para determinar crítico, status, etc. | `sim/prng.ts` → `randomChance()`, `randomInt()` | `mt_rand()` / `mt_getrandmax()` |
| **Side** | Representación de un jugador en la batalla (su equipo, choice) | `sim/side.ts` → `class Side` | `src/Battle/Domain/EquipoBatalla.php` (3 combatientes) |
| **Spread** | Movimiento que afecta a múltiples objetivos (×0.75 en doubles/triples) | `sim/battle-actions.ts` → spread modifier | No aplicable (nuestro 3v3 es always spread por diseño) |
| **STAB** | Same Type Attack Bonus — ×1.5 si tipo del movimiento = tipo del atacante | `sim/battle-actions.ts` → evento `ModifySTAB` | `Chain/ManejadorSTAB.php` — ×1.5 |
| **Status** | Estado alterado persistente (burn, poison, paralysis, etc.) | `data/conditions.ts` + `sim/pokemon.ts` → `status` property | `Enums/EstadoPokemon.php` (8 valores) + lógica en `Combatiente` |
| **Switch** | Cambiar el Pokémon activo por otro del banco | `sim/battle.ts` → switch actions | No implementado (equipo fijo de 3) |
| **Target** | Objetivo de un movimiento (1 pokemon, lados, todos, etc.) | `sim/dex-moves.ts` → `MoveTarget` (15+ tipos) | `SelectorAccionIA.php` → vanguardia/retaguardia enemiga (~4 tipos) |
| **Terrain** | Terreno activo que modifica efectos (Electric Terrain, etc.) | `sim/field.ts` → `terrain` property | No implementado (solo weather) |
| **Tera** | Terastallization — cambia tipo del pokemon, STAB ×2.0 | `sim/pokemon.ts` → `terastallized`, `teraType` | No implementado |
| **Type** | Tipo elemental del pokemon o movimiento (18 tipos) | `sim/global-types.ts` → `TypeName` | `src/Shared/Tipos/TipoPokemon.php` (enum int, 18 valores) |
| **Volatile** | Estado temporal que se almacena en el pokemon (confusión, substitute, etc.) | `sim/pokemon.ts` → `volatiles` object (~50+) | Confusión en `Combatiente::puedeActuar()`. Efectos en `Effects/InterfazEfecto.php` |
| **Weather** | Clima activo que modifica daño y efectos de tipos | `sim/field.ts` → `weather` + `data/conditions.ts` | `Enums/TipoClima.php` (7 valores) + `ManejadorClima` + `CalculadorDañoClima` |
| **Z-Move** | Movimiento Z — movimiento potenciado una vez por batalla | `sim/dex-moves.ts` → `isZ` property | No implementado |
