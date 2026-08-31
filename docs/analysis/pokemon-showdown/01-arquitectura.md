# 01 — Arquitectura de Pokemon Showdown

## Visión General

Pokemon Showdown se divide en **3 capas principales** según su propio `ARCHITECTURE.md`:

1. **Data Layer** (`data/`) — Datos estáticos: Pokemon, movimientos, habilidades, objetos, tipos, naturalezas.
2. **Simulator Layer** (`sim/`) — Motor de batalla: lógica de combate, cola de acciones, cálculo de daño.
3. **Server Layer** (`server/`) — Servidor web, matchmaking, conexión con clientes.

```
┌─────────────────────────────────┐
│         Server Layer            │  matchmaking, websocket, rooms
├─────────────────────────────────┤
│       Simulator Layer           │  Battle, BattleActions, BattleQueue, Pokemon, Side, Field
├─────────────────────────────────┤
│         Data Layer              │  Moves, Abilities, Items, Species, Types, Natures
└─────────────────────────────────┘
```

## Simulator Layer — Clases Principales

### Battle (`sim/battle.ts`)

La clase central. Una instancia por combate.

**Responsabilidades:**
- Gestión del estado global de la batalla (turno, clima, terreno).
- Sistema de eventos (`runEvent()`).
- RNG determinista (`PRNG`).
- Serialización/deserialización (`State`).
- Coordinación entre `BattleQueue`, `BattleActions`, `Field`, `Side`.

**Propiedades clave:**
```typescript
class Battle {
  field: Field;           // clima, terreno, pseudoWeather
  sides: Side[];          // 1-4 jugadores
  queue: BattleQueue;     // cola de acciones
  actions: BattleActions; // cálculo de daño, ejecución de movimientos
  turn: number;
  gameType: 'singles' | 'doubles' | 'triples' | 'freeforall';
  activePerHalf: number;  // 1 (singles), 2 (doubles), 3 (triples)
  prng: PRNG;             // RNG determinista con semilla
}
```

### BattleActions (`sim/battle-actions.ts`)

Motor de cálculo. Contiene toda la lógica de daño y ejecución de movimientos.

**Responsabilidades:**
- Cálculo de daño (`getDamage`, `modifyDamage`).
- Ejecución de movimientos (`runMove`, `hitSteps`).
- Efectos secundarios, STAB, tipificación.
- Mega Evolución, Terastallization, Z-Moves, Max Moves.

### BattleQueue (`sim/battle-queue.ts`)

Cola de acciones con sorting por prioridad.

**Orden de prioridad (de mayor a menor):**
1. Order (explícito, bajo = primero)
2. Priority (alto = primero)
3. Speed (alto = primero)
4. SubOrder (bajo = primero)

Usa **Fischer-Yates shuffle** para desempate aleatorio.

### Pokemon (`sim/pokemon.ts`)

Representa un Pokemon activo en batalla.

**Estado que mantiene:**
- `hp`, `maxhp`, `status` — estado vital.
- `boosts` — modificadores de stats (-6 a +6).
- `volatiles` — estados temporales (confusión, idea, etc.).
- `moveSlots` — movimientos con PP.
- `position`, `side` — ubicación.
- `terastallized`, `teraType` — estado Tera.

### Field (`sim/field.ts`)

Estado compartido entre todos los Pokemon de un turno.

```typescript
class Field {
  weather: string;              // clima activo
  weatherState: EffectState;
  terrain: string;              // terreno activo
  terrainState: EffectState;
  pseudoWeather: AnyObject;     // efectos como Trick Room
}
```

### Side (`sim/side.ts`)

Representa a un jugador.

```typescript
class Side {
  pokemon: Pokemon[];     // todos los Pokemon del equipo (hasta 6)
  active: Pokemon[];      // Pokemon activos en campo (1-3)
  team: ServerTeams.PokemonSet[];
  choice: ChosenAction;   // acciones elegidas por el jugador
}
```

## Data Layer — Estructura

Los datos están en `data/` como objetos TypeScript estáticos tipados por interfaces en `sim/dex-*.ts`.

```
data/
├── moves.ts       → MoveDataTable (~900 movimientos, ~21k líneas)
├── abilities.ts   → AbilityDataTable (~300 habilidades)
├── items.ts       → ItemDataTable (~200 objetos)
├── pokedex.ts     → SpeciesDataTable (~1100 especies)
├── typechart.ts   → TypeChartDataTable (18×18 = 324 entradas)
├── natures.ts     → NatureDataTable (25 naturalezas)
└── ...
```

Cada entrada es un objeto con propiedades tipadas. Las interfaces de definición están en `sim/dex-moves.ts`, `sim/dex-species.ts`, etc.

### Dex (`sim/dex.ts`)

Biblioteca de acceso a datos con **lazy loading**. Carga archivos `.ts` bajo demanda.

```typescript
dex.moves.get('tackle')  // carga data/moves.ts si no estaba cargado
dex.species.get('pikachu')
dex.abilities.get('static')
```

Soporta **mods** para formatos personalizados que sobreescriben datos base.

## Patrón de Eventos

El sistema de eventos de Showdown es su característica arquitectónica más importante.

### `runEvent()`

Método central de `Battle`. Permite interceptar y modificar comportamiento.

```typescript
battle.runEvent('ModifyDamage', source, target, move, baseDamage);
```

**Flujo:**
1. Recopila todas las funciones `on[Evento]` de habilidades, objetos, movimientos,天气, terreno, pseudoWeather.
2. Las ejecuta en orden de prioridad.
3. Cada listener puede modificar los parámetros pasados.
4. Retorna el valor final (posiblemente modificado).

**Prioridades de ejecución:**
- Order numérico (bajo = primero)
- Dentro del mismo order: orden de aplicación

**Ejemplo concreto — cálculo de daño:**
```
baseDamage → WeatherModifyDamage → CriticalHit → BasePower → ModifyAtk/Def → ModifySTAB → TypeMod → BurnModifier → ModifyDamage (final)
```

Este patrón es equivalente a nuestro **Chain of Responsibility** en `src/Battle/Domain/Damage/`.

---

## Comparación con nuestro sistema

| Aspecto | Pokemon Showdown | Nuestro Sistema |
|---------|-----------------|-----------------|
| **Arquitectura** | 3 capas (Data/Sim/Server) | DDD + Laravel (`src/Battle/Domain/`) |
| **Motor de batalla** | Clase monolítica `Battle` | Cadena de responsabilidad + Services |
| **Sistema de eventos** | `runEvent()` con prioridades | Eventos Laravel + Listeners |
| **Datos estáticos** | Archivos `.ts` con objetos | Migraciones + Seeders / Enums |
| **RNG** | PRNG determinista con semilla | PHP `random_int()` / `mt_rand()` |
| **Serialización** | `State.serialize()` personalizado | Eloquent (persistencia en BD) |
| **Formatos** | 1v1, 2v2, 3v3, multi | Solo 3v3 |
| **Mods** | Sistema de mods sobre datos base | Configuración por environment |

### Decisiones clave para nosotros

1. **No copiar `runEvent()`** — Usar eventos de Laravel o el patrón Chain of Responsibility ya implementado.
2. **Datos estáticos como Enums/Constants** — Los tipos, naturalezas y constantes van como PHP Enums (ya tenemos `src/Shared/Domain/Enum/`).
3. **RNG no determinista** — Nuestro sistema no requiere replay, así que `random_int()` es suficiente.
4. **Serialización vía Eloquent** — El estado de batalla se persiste en BD, no se serializa a JSON.
