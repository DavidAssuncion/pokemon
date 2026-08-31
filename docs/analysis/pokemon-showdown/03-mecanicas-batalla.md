# 03 — Mecánicas de Batalla

## Flujo General de un Turno

Fuente: `sim/battle.ts`, `sim/battle-queue.ts`.

```
┌─────────────────────────────────────────┐
│  1. Inicio del turno                     │
│     - decrementWeather()                │
│     - decrementTerrain()                │
│     - runEvent('UponTurn')              │
├─────────────────────────────────────────┤
│  2. Elección de jugadores               │
│     - Cada jugador elige acciones        │
│     - Se capturan en Side.choice        │
├─────────────────────────────────────────┤
│  3. Ejecución de acciones               │
│     - BattleQueue ordena por prioridad  │
│     - Se ejecutan en orden              │
│     - Cada acción puede generar más     │
│       acciones (encadenamiento)         │
├─────────────────────────────────────────┤
│  4. Faint check                         │
│     - Verificar Pokemon derrotados      │
│     - Forzar switch si necesario        │
├─────────────────────────────────────────┤
│  5. Fin del turno                       │
│     - Daño pasivo (weather, poison)     │
│     - Restauración (Leftovers, etc.)    │
│     - runEvent('AfterTurn')             │
│     - Verificar si la batalla termina   │
└─────────────────────────────────────────┘
```

## Cola de Acciones (BattleQueue)

Fuente: `sim/battle-queue.ts`.

### Tipos de Acción

```typescript
type Action = {
  choice: 'move' | 'switch' | 'runswitch' | 'runprimal' | 'faint' |
          'team' | 'start' | 'runSwitch' | 'event' | 'habitat' | 'debug' |
          'moves' | 'mega' | 'tera' | 'shift' | 'beforeTurn' | 'beforeTurnMove' |
          'reswitch' | 'postdirty' | 'resolve' | 'update';
  priority: number;      // prioridad de acción
  order?: number;        // orden de aplicación
  subOrder?: number;     // sub-orden
  speedOrder?: number;   // orden por velocidad
  pokemon?: Pokemon;
  move?: string;
  target?: Pokemon | 'self';
};
```

### Sorting (Prioridad de Acciones)

```typescript
function sortActions(a: Action, b: Action): number {
  // 1. Order: bajo = primero
  if ((a.order || 0) !== (b.order || 0)) return (a.order || 0) - (b.order || 0);
  // 2. Priority: alto = primero
  if ((b.priority || 0) !== (a.priority || 0)) return (b.priority || 0) - (a.priority || 0);
  // 3. Speed: alto = primero
  if ((b.speedOrder || 0) !== (a.speedOrder || 0)) return (b.speedOrder || 0) - (a.speedOrder || 0);
  // 4. SubOrder: bajo = primero
  if ((a.subOrder || 0) !== (b.subOrder || 0)) return (a.subOrder || 0) - (b.subOrder || 0);
  // 5. Fisher-Yates shuffle para desempate
  return 0; // se mantiene el orden del shuffle previo
}
```

### Velocidad como tiebreaker

```typescript
// En Battle.initSpeed():
this.speedOrder = Utils.shuffle(
  Array(this.activePerHalf * 2).fill(0).map((_, i) => i),
  this.prng
);
```

Los `activePerHalf` Pokemon son:
- Singles: 1 por lado → 2 total
- Doubles: 2 por lado → 4 total
- Triples: 3 por lado → 6 total

## Targeting (Selección de Objetivos)

Fuente: `sim/dex-moves.ts`.

### Target Types

```typescript
type MoveTarget =
  | 'allAdjacentFoes'    // todos los enemigos adyacentes (spread)
  | 'allAdjacent'        // todos los adyacentes (aliados y enemigos)
  | 'allAdjacentAllies'  // solo aliados adyacentes
  | 'allySide'           // todo el lado aliado
  | 'allyTeam'           // todo el equipo (banco incluido)
  | 'all'                // todos los Pokemon activos
  | 'foeSide'            // todo el lado enemigo
  | 'normal'             // 1 objetivo
  | 'randomNormal'       // 1 objetivo random
  | 'self'               // usuario
  | 'scripted'           // objetivo pre-determinado
  | 'any'                // cualquier Pokemon activo
  | 'allies'             // aliados
  | 'ally'               // 1 aliado
  | 'xzMove'             // Z-Move
  | 'max'                // Max Move
  | 'scripted'           // objetivo determinado por script
```

### Cálculo de Targets

Fuente: `sim/pokemon.ts`, `getMoveTargets()`.

```typescript
getMoveTargets(move, target): { targets: Pokemon[], pressureTargets: Pokemon[] } {
  switch (move.target) {
    case 'normal':
      targets = [target];
      break;
    case 'allAdjacentFoes':
      targets = adjacentFoes();
      break;
    case 'allAdjacent':
      targets = adjacentFoes() + adjacentAllies();
      break;
    case 'allySide':
      targets = alliesAndSelf();
      break;
    // ...etc
  }
}
```

## Posiciones y Adyacencia

Fuente: `sim/pokemon.ts`, líneas 780-789.

```typescript
// Posiciones relativas: positivo = foe, negativo = ally
getLocOf(target) {
  const positionOffset = floor(target.side.n / 2) * target.side.active.length;
  const position = target.position + positionOffset + 1;
  const sameHalf = (this.side.n % 2) === (target.side.n % 2);
  return sameHalf ? -position : position;
}

// Adyacencia: adyacentes si están en posiciones contiguas
isAdjacent(pokemon2) {
  if (this.fainted || pokemon2.fainted) return false;
  if (this.side.active.length <= 2) return this !== pokemon2;
  // Triples: adyacentes si |pos1 - pos2| === 1
  if (this.side === pokemon2.side) return Math.abs(this.position - pokemon2.position) === 1;
  return Math.abs(this.position + pokemon2.position + 1 - this.side.active.length) <= 1;
}
```

### Diagrama de posiciones (Triples)

```
Enemigo:  [3] [2] [1]
                ↕
Aliado:   [1] [2] [3]
```

- Posición 1 aliado adyacente a posición 2 aliado y posición 1 enemigo.
- Posición 2 aliado adyacente a 1 y 3 aliados y ambos enemigos centrales.
- Las esquinas solo adyacenten con un lado.

## Stats Boosts y Modificadores

### Boosts

Fuente: `sim/pokemon.ts`, línea 616-628.

- Rango: -6 a +6 (clamp).
- Tabla de multiplicadores idéntica para todos los stats.
- `ModifyBoost` evento permite interceptar (Contrary invierte).

```typescript
// Stat con boost:
const boostTable = [1, 1.5, 2, 2.5, 3, 3.5, 4];
if (boost >= 0) stat = floor(stat * boostTable[boost]);
else stat = floor(stat / boostTable[-boost]);
```

### Wonder Room

Intercambia `def` y `spd` al calcular stats. Se aplica DESPUÉS de boosts pero ANTES de ModifyStat events.

## Estados Alterno (Status)

### Statuts Support

| Estado | Efecto en daño | Efecto pasivo | Duración |
|--------|---------------|---------------|----------|
| **psn** (poison) | — | `⌊maxhp / 16⌋` por turno | Hasta cura |
| **tox** (badly poison) | — | `⌊maxhp / 16⌋ × turnos` por turno | Hasta cura |
| **brn** (burn) | ×0.5 Físico (sin Guts) | `⌊maxhp / 16⌋` por turno | Hasta cura |
| **par** (paralysis) | — | Velocidad ×0.5 (Gen 7+: ×0.25) | Hasta cura |
| **frz** (freeze) | — | No puede actuar (20% descongela por turno) | Hasta cura |
| **slp** (sleep) | — | No puede actuar (1-3 turnos) | 1-3 turnos |
| **awn** (afteryou) | — | — | Solo 1 turno |

### Probabilidad de aplicar status

Depende del movimiento y habilidad. Cada status tiene su propio mecanismo:

- **Parálisis:** `chance = 30%` base, modificado por Thunder Wave (100%), Static (30% contacto), etc.
- **Quemadura:** `chance = 10-30%` dependiendo del movimiento (Will-O-Wisp = 100% pero Status).
- **Veneno:** `chance = 30%` (Toxic Spikes al entrar, Poison Point, etc.)
- **Congelación:** `chance = 10%` (Ice Beam, Blizzard, etc.)
- **Sueño:** `chance =}` depende del movimiento (Sleep Powder = 75%, Spore = 100%)

## Estados Volátiles (Volatiles)

Fuente: `sim/pokemon.ts`, `volatiles` object.

Los volatiles son estados temporales que se almacenan en `pokemon.volatiles[id]`.

```typescript
interface VolatileState {
  target: Pokemon;
  duration: number;
  startTime: number;
  [key: string]: any;
}
```

### Ejemplos de volátiles importantes

| Volatile | Efecto | Duración |
|----------|--------|----------|
| `confusion` | 33% de dañarse a sí mismo (Gen 7+: 33%) | 1-4 turnos |
| `leechseed` | Cura al oponente, daña al afectado | Indefinido hasta cura |
| `taunt` | Solo puede usar movimientos ofensivos | 3 turnos |
| `torment` | No puede usar el mismo movimiento dos veces seguidas | Indefinido |
| `substitute` | Bloquea 25% maxHP, absorbe hits | Hasta romperse |
| `protect` | Bloquea todos los ataques | Probabilidad decreciente |
| `confused` | Puede atacarse a sí mismo | Variable |
| `confusion` | 33% de dañarse a sí mismo | 1-4 turnos |

## Velocidad y Trick Room

### Cálculo de velocidad de turn

```typescript
getActionSpeed() {
  let speed = getStat('spe', false, false);
  if (trickRoom) speed = 10000 - speed;
  return trunc(speed, 13); // 13-bit truncation
}
```

- **Sin Trick Room:** Velocidad alta = primero.
- **Con Trick Room:** Velocidad baja = primero (`10000 - speed`).
- **Trunc 13-bit:** Limita el rango de velocidad a `[0, 8191]`.

## Cambios de Forma (Forme Change)

Fuente: `sim/pokemon.ts`.

```typescript
formeChange(speciesId, source, isPermanent) {
  // 1. Actualizar species
  // 2. Actualizar stats base si cambian
  // 3. Actualizar tipo si cambia
  // 4. Emitir evento '-formechange'
  // 5. Si isPermanent: actualizar species original
}
```

### Cambios de forma que afectan stats
- Mega Evolution: stats base cambian completamente.
- Terastallization: solo cambia tipo, no stats base.
- Rotom forms: cambian typing.

## Mecánicas de Exactitud

```typescript
// Precisión efectiva = accuracy × 100 / evasion
// Donde accuracy y evasion son los boosts (-6 a +6)
// Con multiplicadores de habilidades/items/clima
```

### Precisión garantizada
- Movimientos con `accuracy: true` → siempre aciertan.
- OHKO moves: accuracy = `userLevel - targetLevel + 30` (máx 30, mínimo 1).

---

## Comparación con nuestro sistema

| Mecánica | Pokemon Showdown | Nuestro Sistema |
|----------|-----------------|-----------------|
| **Turno** | 3 fases: elección → ejecución → fin | Por definir — recomendar misma estructura |
| **Cola de acciones** | 4 niveles de prioridad + Fisher-Yates | Chain of Responsibility con prioridades |
| **Targeting** | 15+ target types | Solo 3v3 — simplificar a ~5 target types |
| **Posiciones** | Lineales con adyacencia por distancia | Vanguardia/Retaguardia — 2 posiciones |
| **Boosts** | -6 a +6, tabla multiplicadora | Misma tabla recomendada |
| **Status** | 6 estados + volátiles | Por definir — recomendar implementar los 6 base |
| **Volatiles** | ~50+ volátiles diferentes | Priorizar: confusión, substitute, protect, leech |
| **Trick Room** | Invierte velocidad | Implementar como pseudoWeather |
| **Forme Change** | Cambio de specie en runtime | Solo Tera (cambio de tipo) |

### Decisiones clave

1. **Simplificar targeting** — Nuestro 3v3 no necesita `allAdjacent`, solo `normal`, `allAdjacentFoes`, `allySide`, `self`.
2. **2 posiciones por lado** — Vanguardia (frente) y Retaguardia (fondo). No 3 como en triples.
3. **Chain of Responsibility > Event System** — Usar patrón ya implementado en vez del sistema de eventos de Showdown.
4. **Priorizar volatiles** — Implementar solo los más usados: confusión, protect, substitute, leech seed.
5. **Mantener la tabla de boosts** — Es estándar y balanceada.
