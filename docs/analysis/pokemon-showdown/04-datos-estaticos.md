# 04 — Datos Estáticos

## Tipos (Type Chart)

Fuente: `data/typechart.ts`.

### 18 Tipos

```
Normal, Fire, Water, Electric, Grass, Ice, Fighting, Poison,
Ground, Flying, Psychic, Bug, Rock, Ghost, Dragon, Dark, Steel, Fairy
```

### Matriz de Efectividad (18×18)

Fuente: `data/typechart.ts`, `damageTaken` por cada tipo.

| Atacante ↓ / Defensor → | NOR | FIR | WAT | ELE | GRS | ICE | FIG | PSN | GND | FLY | PSY | BUG | RCK | GHO | DRG | DRK | STL | FAR |
|--------------------------|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Normal**               |  1  |  1  |  1  |  1  |  1  |  1  |  1  |  1  |  1  |  1  |  1  |  1  |½|  0  |  1  |  1  |½|  1  |
| **Fire**                 |  1  | ½| ½|  1  |  2  |  2  |  1  |  1  |  1  |  1  |  1  |  2  |½|  1  |½|  1  |  2  |  1  |
| **Water**                |  1  |  2  | ½|  1  |½|  1  |  1  |  1  |  2  |  1  |  1  |  1  |  2  |  1  |½|  1  |  1  |  1  |
| **Electric**             |  1  |  1  |  2  | ½| ½|  1  |  1  |  1  |  0  |  2  |  1  |  1  |  1  |  1  |½|  1  |  1  |  1  |
| **Grass**                |  1  |½|  2  |  1  | ½|  1  |  1  |½|  2  |½|  1  |½|  2  |  1  |½|  1  |  1  |  1  |
| **Ice**                  |  1  |½|½|  1  |  2  |  1  |  1  |  1  |  2  |  2  |  1  |  1  |  1  |  1  |  2  |  1  |½|  1  |
| **Fighting**             |  2  |  1  |  1  |  1  |  1  |  2  |  1  |½|  1  |½|½|½|  2  |  0  |  1  |  2  |  2  |½|
| **Poison**               |  1  |  1  |  1  |  1  |  2  |  1  |  1  |½|½|  1  |  1  |  1  |½|  1  |  1  |  1  |  0  |  2  |
| **Ground**               |  1  |  2  |  1  |  2  |½|  1  |  1  |  2  |  1  |  0  |  1  |½|  2  |  1  |  1  |  1  |  2  |  1  |
| **Flying**               |  1  |  1  |  1  |½|  2  |  1  |  2  |  1  |  1  |  1  |  1  |  2  |½|  1  |  1  |  1  |½|  1  |
| **Psychic**              |  1  |  1  |  1  |  1  |  1  |  1  |  2  |  2  |  1  |  1  |½|  1  |  1  |  1  |  1  |  0  |½|  1  |
| **Bug**                  |  1  |½|  1  |  1  |  2  |  1  |½|½|  1  |½|  2  |  1  |  1  |  1  |  1  |  2  |½|½|
| **Rock**                 |  1  |  2  |  1  |  1  |  1  |  2  |½|  1  |½|  2  |  1  |  2  |  1  |  1  |  1  |  1  |½|  1  |
| **Ghost**                |  0  |  1  |  1  |  1  |  1  |  1  |  1  |  1  |  1  |  1  |  2  |  1  |  1  |  2  |  1  |½|  1  |  1  |
| **Dragon**               |  1  |  1  |  1  |  1  |  1  |  1  |  1  |  1  |  1  |  1  |  1  |  1  |  1  |  1  |  2  |  1  |½|  0  |
| **Dark**                 |  1  |  1  |  1  |  1  |  1  |  1  |½|  1  |  1  |  1  |  2  |  1  |  1  |  2  |  1  |½|  1  |½|
| **Steel**                |  1  |½|½|½|  1  |  2  |  1  |  1  |  1  |  1  |  1  |  1  |  2  |  1  |  1  |  1  |  1  |  2  |
| **Fairy**                |  1  |½|  1  |  1  |  1  |  1  |  2  |½|  1  |  1  |  1  |  1  |  1  |  1  |  2  |  2  |½|  1  |

> **Nota:** 0 = inmune, ½ = resistente, 1 = neutro, 2 = super efectivo.

### Formato de datos internos

```typescript
// damageTaken contiene el multiplicador como:
// 0 = inmune, 1 = resistente (½), 2 = neutro, 3 = super efectivo (×2)
// En código se convierte: 1 → -1 (resist), 3 → +1 (super)
```

## Naturalezas (Natures)

Fuente: `data/natures.ts` (completo).

### 25 Naturalezas

| Naturaleza | +Stat | -Stat |
|:----------:|:-----:|:-----:|
| Hardy | — | — |
| Lonely | Atk | Def |
| Brave | Atk | Spe |
| Adamant | Atk | SpA |
| Naughty | Atk | SpD |
| Bold | Def | Atk |
| Docile | — | — |
| Impish | Def | SpA |
| Lax | Def | SpD |
| Relaxed | Def | Spe |
| Timid | Spe | Atk |
| Hasty | Spe | Def |
| Serious | — | — |
| Jolly | Spe | SpA |
| Naive | Spe | SpD |
| Modest | SpA | Atk |
| Mild | SpA | Def |
| Quiet | SpA | Spe |
| Bashful | — | — |
| Rash | SpA | SpD |
| Calm | SpD | Atk |
| Gentle | SpD | Def |
| Sassy | SpD | Spe |
| Careful | SpD | SpA |
| Quirky | — | — |

### Formato de datos

```typescript
interface NatureData {
  name: string;
  num: number;
  gen?: number;
  plus?: StatIDExceptHP;   // stat boost (+10%)
  minus?: StatIDExceptHP;  // stat reduce (-10%)
}
```

> **Mecánica:** Las naturalezas solo afectan stats que **no** sean HP. +10% en la stat positiva, -10% en la negativa. Las 5 "neutrales" no tienen efecto.

## Movimientos (Moves)

Fuente: `data/moves.ts` (~900 movimientos, ~21,000 líneas).

### Estructura de un Movimiento

```typescript
interface MoveData {
  num: number;                    // número del Pokédex
  accuracy: true | number;        // true = guaranteed hit
  basePower: number;              // poder base
  category: 'Physical' | 'Special' | 'Status';
  name: string;
  pp: number;                     // power points
  priority: number;               // -7 a +5
  flags: MoveFlags;               // banderas de comportamiento
  target: MoveTarget;             // tipo de targeting
  type: TypeName;                 // tipo del movimiento
  contestType?: string;           // contest type

  // Propiedades opcionales
  secondary?: SecondaryEffect;    // efecto secundario
  secondaryCondition?: Condition; // condición para secundario
  boosts?: Partial<BoostsTable>;  // boosts directos (Status moves)
  volatileStatus?: string;        // volatile a aplicar
  status?: string;                // status a aplicar
  weather?: string;               // clima a cambiar
  terrain?: string;               // terreno a cambiar
  ohko?: boolean | string;        // OHKO move
  recoil?: [number, number];      // recoil [numerator, denominator]
  drain?: [number, number];       // drain [numerator, denominator]
  heal?: [number, number];        // heal [numerator, denominator]
  multihit?: number | [number, number]; // golpes múltiples
  accuracyBonus?: number;         // bonus de precisión
  basePowerCallback?: Function;   // poder variable
  damage?: number | 'level' | Function; // daño fijo
  selfdestruct?: 'ifHit' | 'always'; // autodestrucción
  forceSTAB?: boolean;            // fuerza STAB aunque no sea tipo
  noSketch?: boolean;             // no copiable por Sketch
  stallingMove?: boolean;         // move que "stalla" (Protect)
  isZ?: boolean;                  // Z-Move
  isMax?: boolean;                // Max Move
  critRatio?: number;             // ratio de crítico modificado
  overrideOffensivePokemon?: 'target' | 'source';
  overrideDefensivePokemon?: 'source' | 'target';
  overrideOffensiveStat?: StatIDExceptHP;
  overrideDefensiveStat?: StatIDExceptHP;
  ignoreOffensive?: boolean;      // ignora boosts ofensivos
  ignoreDefensive?: boolean;      // ignora boosts defensivos
  ignoreNegativeOffensive?: boolean;
  ignorePositiveDefensive?: boolean;
  breakProtect?: boolean;         // rompe Protect
  noCopy?: boolean;               // no copiable por Transform
  disruptMove?: boolean;          // interrumpe movimientos de carga
}
```

### MoveFlags

```typescript
interface MoveFlags {
  contact?: number;      // contacto físico
  protect?: number;      // bloqueado por Protect
  mirror?: number;       // copiable por Mirror Move
  kingsrock?: number;    // puede flinche por King's Rock
  punch?: number;        // boosting por Iron Fist
  sound?: number;        // move de sonido (blocked by Soundproof)
  powder?: number;       // move de polvo (immune Grass)
  authentic?: number;    // ignora Substitute, Detect, Protect
  gravity?: number;      // bloqueado por Gravity
  defrost?: number;      // descongela al usuario
  dance?: number;        // move de baile (Boosted by Dancer)
  wind?: number;         // move de viento
  pulse?: number;        // move de pulso (boosted by Mega Launcher)
  bullet?: number;       // move de bala (blocked by Bulletproof)
  slicing?: number;      // move de corte (boosted by Sharpness)
  bite?: number;         // move de mordisco (boosted by Strong Jaw)
  trample?: number;      // move de aplastamiento (passes through Substitute)
  winding?: number;      // move de rizo (blocked by Dragon Tail)
  psychic?: number;      // move psíquico (boosted by Psychic Terrain)
  metronome?: number;    // usable por Metronome
  heal?: number;         // move de curación
  ohko?: number;         // OHKO move
  mustrecharge?: number; // requiere recharge
  noassist?: number;     // no usable por Assist
  failsIn Gravity?: number;
}
```

### SecondaryEffect

```typescript
interface SecondaryEffect {
  chance?: number;        // porcentaje (0-100), default 100
  boosts?: Partial<BoostsTable>;  // boosts
  volatileStatus?: string;        // volatile
  status?: string;                // status
  self?: {                         // efecto en el usuario
    boosts?: Partial<BoostsTable>;
    volatileStatus?: string;
    status?: string;
  };
}
```

### Datos clave de movimientos comunes

| Movimiento | BP | Cat | Acc | Pri | Tipo | Target | Flags |
|------------|:--:|:---:|:---:|:---:|------|--------|-------|
| Tackle | 40 | Phy | 100 | 0 | Normal | normal | contact, protect, mirror |
| Flamethrower | 90 | Spe | 100 | 0 | Fire | normal | protect, mirror |
| Thunderbolt | 90 | Spe | 100 | 0 | Electric | normal | protect, mirror |
| Earthquake | 100 | Phy | 100 | 0 | Ground | allAdjacent | protect, mirror |
| Protect | 0 | Status | — | +4 | Normal | self | — |
| Stealth Rock | 0 | Status | — | 2 | Rock | foeSide | — |
| Swords Dance | 0 | Status | — | 0 | Normal | self | snatch, dance |
| Recover | 0 | Status | — | 0 | Normal | self | snatch, heal |

## Habilidades (Abilities)

Fuente: `data/abilities.ts` (~300 habilidades, ~5,700 líneas).

### Estructura

```typescript
interface AbilityData {
  name: string;
  num: number;
  rating: number;          // -1 a 5
  flags: AbilityFlags;
  desc?: string;           // descripción Gen 9
  shortDesc?: string;      // descripción corta

  // Event handlers (onModifySTAB, onModifyAtk, etc.)
  onModifySTAB?: Function;
  onModifyAtk?: Function;
  onModifyDef?: Function;
  onModifySpA?: Function;
  onModifySpD?: Function;
  onModifySpe?: Function;
  onDamage?: Function;
  onBeforeMove?: Function;
  onAfterMove?: Function;
  onSwitchIn?: Function;
  onFaint?: Function;
  // ... ~20+ event handlers posibles
}
```

### Habilidades destacadas

| Habilidad | Efecto | Rating |
|-----------|--------|:------:|
| Adaptability | STAB ×2 → ×2.25 | 4 |
| Huge Power | Atk ×2 | 4 |
| Levitate | Inmunidad Ground | 4 |
| Multiscale | Daño ×0.5 si HP = 100% | 4 |
| Protean | Cambia tipo al usar movimiento | 4 |
| Speed Boost | Spe +1 por turno | 4 |
| Sturdy | OHKO inmune si HP = 100% | 3 |
| Guts | Atk ×1.5 con status | 3 |
| Iron Barbs | Daña atacantes de contacto | 2 |

## Objetos (Items)

Fuente: `data/items.ts` (~200 objetos).

### Estructura

```typescript
interface ItemData {
  name: string;
  num: number;
  rating: number;
  flags: ItemFlags;
  desc?: string;

  // Efectos en batalla
  onModifyAtk?: Function;
  onModifyDef?: Function;
  onModifySpA?: Function;
  onModifySpD?: Function;
  onModifySpe?: Function;
  onBeforeMove?: Function;
  onAfterMove?: Function;
  onDamage?: Function;
  onSwitchIn?: Function;

  // Mega stones
  megaStone?: string;
  megaEvolves?: string;

  // Fling data
  fling?: {
    basePower: number;
    status?: string;
    volatileStatus?: string;
  };

  // Weights
  forced?: boolean;
}
```

### Objetos de batalla clave

| Objeto | Efecto |
|--------|--------|
| Leftovers | Restaura 1/16 maxHP por turno |
| Choice Band | Atk ×1.5, solo puede usar 1 movimiento |
| Choice Scarf | Spe ×1.5, solo puede usar 1 movimiento |
| Life Orb | Daño ×1.3, consume 1/10 maxHP |
| Focus Sash | Sobrevive OHKO con 1 HP (1 uso) |
| Assault Vest | SpD ×1.5, no puede usar Status |
| Eviolite | Def/SpD ×1.5 si pre-evolución |
| Heavy-Duty Boots | Ignora hazards al entrar |
| Rocky Helmet | Daña atacantes de contacto (1/6 maxHP) |
| Safety Goggles | Inmunidad a weather/Spore |

## Datos de Pokemon (Pokedex)

Fuente: `data/pokedex.ts` (~1100 entradas).

### Estructura

```typescript
interface SpeciesData {
  num: number;
  name: string;
  types: TypeName[];
  baseStats: {
    hp: number;
    atk: number;
    def: number;
    spa: number;
    spd: number;
    spe: number;
  };
  abilities: SpeciesAbility;
  heightm: number;        // en metros × 10
  weightkg: number;       // en kg
  color: string;
  eggGroups: string[];
  genderRatio?: { M: number; F: number };
  catchRate?: number;
  baseExp?: number;
  baseHappiness?: number;
  evYields?: Partial<StatsTable>;
  inheritOnly?: boolean;
  changesFrom?: string;
  otherFormes?: string[];
  formeOrder?: string[];
  cosmeticFormes?: string[];
  requiredItems?: string[];
  isMega?: boolean;
  canGigantamax?: boolean;
  gmaxUnreleased?: boolean;
  battleOnly?: string;
  inheritsFrom?: string;
  gen?: number;
  isNonstandard?: string;
  tier?: string;
  nfe?: boolean;
}
```

---

## Comparación con nuestro sistema

| Datos | Pokemon Showdown | Nuestro Sistema |
|-------|-----------------|-----------------|
| **Tipos** | 18 tipos, matriz 18×18 en archivo TS | PHP Enums + relación en BD |
| **Naturalezas** | 25, archivo TS con `plus`/`minus` | PHP Enum con +10%/-10% |
| **Movimientos** | ~900, objeto TS gigante | BD + Model Eloquent |
| **Habilidades** | ~300, con event handlers inline | BD + Listeners Laravel |
| **Objetos** | ~200, con event handlers inline | BD + Listeners Laravel |
| **Pokemon** | ~1100, stats base + tipo + abilities | BD + Model con relationships |

### Recomendaciones

1. **Tipos como PHP Enum** — Ya tenemos `src/Shared/Domain/Enum/`. Crear `BattleType` enum con las 18 opciones y la matriz de efectividad como método estático.
2. **Naturalezas como PHP Enum** — Simple: 25 valores con `plus` y `minus` como propiedades.
3. **Movimientos, habilidades, objetos en BD** — Demasiados para Enums. Usar migraciones + seeders. Las habilidades con event handlers se traducen a Listeners.
4. **Stats base como relación** — `species_id → base_stats` en tabla separada o campos directos.
5. **Matriz de efectividad como tabla pivote** — `type_effectiveness(attacker_type, defender_type) → multiplier`.
