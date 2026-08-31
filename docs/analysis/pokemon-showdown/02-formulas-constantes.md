# 02 — Fórmulas y Constantes

## Fórmula de Daño Base

Fuente: `sim/battle-actions.ts`, líneas 1717-1718.

```
baseDamage = ⌊⌊⌊⌊(2 × L / 5 + 2) × A × P / D⌋ / 50⌋
```

Donde:
- `L` = nivel del atacante (siempre 50 en competición)
- `A` = stat de ataque del atacante (con boosts y modificadores)
- `P` = base power del movimiento (con modificadores)
- `D` = stat de defensa del defensor (con boosts y modificadores)
- `⌊⌋` = truncamiento a entero en cada paso (`this.battle.trunc`)

### Fórmula completa (con todos los modificadores)

Después del `baseDamage`, se aplican en orden:

```
daño_final = ⌊⌊baseDamage + 2⌋
             × spread_modifier
             × weather_modifier
             × crit_modifier
             × random_modifier
             × STAB
             × type_effectiveness
             × burn_modifier
             × final_modifier⌋₁₆
```

Detalles de cada modificador:

| Modificador | Fórmula | Notas |
|-------------|---------|-------|
| **+2 fijo** | `baseDamage + 2` | Siempre se añade |
| **Spread** | `× 0.75` (doubles/triples) o `× 0.5` (free-for-all) | Solo para multi-target |
| **Parental Bond** | `× 0.25` (Gen 7+) o `× 0.5` (Gen 6-) | Solo 2do golpe |
| **Weather** | Ver tabla abajo | Dependiendo del clima y tipo |
| **Critical Hit** | `× 1.5` (Gen 6+) o `× 2` (Gen 5-) | Si es crítico |
| **Random** | `× [0.85, 1.00]` uniforme | `randomizer(baseDamage)` |
| **STAB** | `× 1.5` normal, `× 2.0` Tera, `× 2.25` Adaptability | Si el tipo coincide |
| **Type Effectiveness** | `× 2^n` o `× (1/2)^n` | n = número de debilidades/resistencias, clamp [-6, 6] |
| **Burn** | `× 0.5` | Solo Físico, sin Guts |
| **Life Orb** | `× 1.3` | Modificador final, aplica daño al usuario |
| **Bypass Protect (Z-Break)** | `× 0.25` | Si bypass es deflectable |
| **Trunc 16-bit** | `trunc(damage, 16)` | Truncamiento final |

### Damage Minimum

```typescript
// Gen 5: baseDamage mínimo = 1 (antes de modificadores finales)
// Gen 6+: damage mínimo = 1 (después de modificadores finales)
// Siempre retornar al menos 1
```

## Fórmula de Stat

Fuente: `sim/pokemon.ts`, líneas 600-639.

### Stat Base (desde IVs, EVs, nivel)

```
stat = ⌊((2 × base + iv + ⌊ev/4⌋) × level / 100 + 5) × nature⌋
```

> **Nota:** Showdown pre-calcula las stats base y las almacena en `storedStats`. Solo aplica boosts y modificadores en tiempo de batalla.

### Aplicación de Boosts

```typescript
const boostTable = [1, 1.5, 2, 2.5, 3, 3.5, 4];
// boost >= 0: stat = floor(stat × boostTable[boost])
// boost < 0:  stat = floor(stat / boostTable[-boost])
```

| Boost Level | Multiplicador | Fracción |
|:-----------:|:-------------:|:--------:|
| -6 | ÷ 4 | 2/8 |
| -5 | ÷ 3.5 | 4/7 |
| -4 | ÷ 3 | 2/6 |
| -3 | ÷ 2.5 | 4/5 |
| -2 | ÷ 2 | 4/8 |
| -1 | ÷ 1.5 | 4/6 |
| 0 | × 1 | — |
| +1 | × 1.5 | 6/4 |
| +2 | × 2 | 8/4 |
| +3 | × 2.5 | 10/4 |
| +4 | × 3 | 12/4 |
| +5 | × 3.5 | 14/4 |
| +6 | × 4 | 16/4 |

### Modificadores de Stat post-boost

Después de boosts, se ejecuta `runEvent('Modify[Stat]')` que permite:
- Habilidades (Huge Power: ×2 Atk, Fur Coat: ×2 Def)
- Items (Choice Scarf: ×1.5 Spe, Evolite: ×1.5 Def/SpD)
- Clima (sun: ×1.5 SpA fuego, rain: ×1.5 SpA agua)
- Terreno (Electric Terrain: ×1.3 Spe con Quark Drive)

## Probabilidad de Crítico

Fuente: `sim/battle-actions.ts`, líneas 1623-1643.

### Gen 9

| critRatio | Probabilidad | Valor |
|:---------:|:------------:|:-----:|
| 0 | 0/24 = 0% | no crit |
| 1 | 1/24 ≈ 4.17% | base |
| 2 | 1/8 = 12.5% | high crit |
| 3 | 1/2 = 50% | very high crit |
| 4 | 1/1 = 100% | always crit |

```typescript
critMult = [0, 24, 8, 2, 1]; // Gen 7+
moveHit.crit = randomChance(1, critMult[critRatio]);
```

### Efectos del crítico (Gen 6+)

- Multiplicador: `× 1.5`
- Ignora boosts negativos de ataque del atacante.
- Ignora boosts positivos de defensa del defensor.
- Ignora Ability Multiscale, Battery, etc.

## Efecto de Clima en Daño

Fuente: `sim/battle-actions.ts`, evento `WeatherModifyDamage`.

| Clima | Tipo afectado | Multiplicador | Tipo opuesto | Multiplicador |
|-------|---------------|:-------------:|--------------|:-------------:|
| Sun | Fire | ×1.5 | Water | ×0.5 |
| Rain | Water | ×1.5 | Fire | ×0.5 |
| Strong Sun | Fire | ×1.3 | Water | ×0.5 |
| Strong Rain | Water | ×1.3 | Fire | ×0.5 |
| Sand | (ninguno en daño) | — | — | — |
| Snow | (ninguno en daño) | — | — | — |

> **Nota:** Sand daña a todos menos Rock, Ground, Steel. Snow protege a Ice (Gen 9).

## Probabilidad de Estado Alterno

Fuente: `sim/battle-actions.ts` (efectos secundarios).

El cálculo de probabilidad de un efecto secundario es directo:

```
P(efecto) = chance / 100
```

Donde `chance` es el porcentaje definido en el movimiento (ej: 10 = 10%).

**Multihit:** Cada golpe tiene su propia probabilidad independiente.

**Secundarios encadenados:** Si un movimiento tiene múltiples `secondary effects`, se ejecutan todos independientemente.

## Precisión y Evasión

Fuente: `sim/battle-actions.ts`.

```
P(acierto) = accuracy_mod × move.accuracy × 100 / target.evasion
```

Donde:
- `move.accuracy` puede ser `true` (100% guaranteed) o un número (1-100).
- `accuracy_mod` incluye boosts de precisión/evasión y habilidades.
- Clima Weather Rock: precisión ×1.3 en Sandstorm.

### Tabla de boosts de precisión/evasión

Misma tabla que stats: -6 a +6, mismos multiplicadores (1 a 4).

## Daño por Confusión

Fuente: `sim/battle-actions.ts`, líneas 1850-1862.

```
confusionDamage = trunc(trunc(trunc(trunc(2 × L / 5 + 2) × basePower × Atk) / Def) / 50) + 2
```

- `basePower = 40` (fijo para confusión)
- Sin boosts, sin habilidades, sin items, sin STAB, sin críticos.
- Solo se aplica randomizer y minimum 1.

## Velocidad y Orden de Turno

Fuente: `sim/pokemon.ts`, líneas 641-649.

```typescript
getActionSpeed() {
  let speed = this.getStat('spe', false, false);
  if (trickRoom) speed = 10000 - speed;
  return trunc(speed, 13);
}
```

**Trick Room:** Invierte la velocidad (`10000 - speed`).

**Orden de turno (de mayor a menor):**
1. **Priority** del movimiento (alta = primero)
2. **Speed** del Pokemon (alta = primero)
3. **Empate aleatorio** (Fisher-Yates shuffle)

## Recuperación HP (Drain / Heal)

### Drain (Robar HP)

```typescript
drain: [numerator, denominator]
// healing = floor(damage × numerator / denominator)
// ej: drain [1,2] → cura 50% del daño causado
```

### Restorative Berries (Bayas restaurativas)

```typescript
const RESTORATIVE_BERRIES: {[id: string]: string} = {
  liechi: 'atk', ganlon: 'def', salac: 'spe',
  petaya: 'spa', apicot: 'spd', enigma: 'hp',
  micle: 'acc', custap: 'spe',
};
// Se activan cuando HP < 25% (¼)
```

---

## Comparación con nuestro sistema

| Fórmula | Pokemon Showdown | Nuestro Sistema |
|---------|-----------------|-----------------|
| **Daño base** | `⌊(2L/5+2) × A × P / D / 50⌋` | Chain of Responsibility — definir fórmula exacta |
| **Boosts stats** | Tabla predefinida ×1 a ×4 | Por definir (mismo rango -6 a +6 recomendado) |
| **Crítico** | 1/24 base, ×1.5 daño | Por definir — 1.5x recomendado |
| **STAB** | ×1.5, ×2 Tera, ×2.25 Adaptability | Por definir — ×1.5 recomendado como base |
| **Random factor** | Uniforme [0.85, 1.0] | Por definir — mismo rango recomendado |
| **Weather damage mod** | ×1.5 / ×0.5 | Por definir — mismo esquema |
| **Daño confusión** | basePower 40, sin modificadores | Por definir |
| **Mínimo daño** | Siempre ≥ 1 | Implementar: `max(1, damage)` |

### Recomendaciones

1. **Mantener la fórmula base idéntica** — Es el estándar de la franquicia.
2. **Simplificar el chain de modificadores** — Showdown apila 10+ modificadores. Nosotros podemos consolidar.
3. **No implementar Gen-specific branches** — Nosotros solo hacemos Gen 9+, eliminar los checks `this.battle.gen <= 5`.
4. **RNG uniforme [0.85, 1.0]** — Mismo rango, implementar con `random_int(85, 100) / 100`.
5. **Boosts** — Mantener la tabla de multiplicadores, pero considerar si nuestro sistema tiene más o menos niveles.
