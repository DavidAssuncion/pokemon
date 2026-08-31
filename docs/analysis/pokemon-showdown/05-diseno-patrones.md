# 05 — Patrones de Diseño

## Patrones Identificados en Pokemon Showdown

### 1. Event System (Chain of Responsibility)

**Implementación en Showdown:** `Battle.runEvent()`

Es el patrón más fundamental de todo el motor. Cada evento es una cadena de callbacks que pueden modificar el resultado.

```typescript
// Ejemplo: ModifyDamage
runEvent('ModifyDamage', source, target, move, baseDamage)
  → ability.onDamage()       // ej: Multiscale ×0.5
  → item.onDamage()          // ej: Life Orb ×1.3
  → weather.onDamage()       // ej: Rain ×0.5 para fuego
  → terrain.onDamage()       // ej: Electric Terrain
  → move.onDamage()          // efecto del movimiento
  → ... más listeners
  → return finalDamage
```

**Características:**
- Cada listener recibe los mismos parámetros y puede modificarlos.
- Orden de ejecución por `priority` numérico.
- Un listener puede retornar `false` para cancelar el evento.
- Soporta eventos con retorno (modifican valor) y eventos sin retorno (side effects).

**En nuestro sistema:** Equivalente a `src/Battle/Domain/Damage/` con Chain of Responsibility.

---

### 2. Data-Driven Design

**Implementación en Showdown:** `data/` + interfaces en `sim/dex-*.ts`

Toda la información estática (movimientos, habilidades, objetos, Pokemon) está separada de la lógica de batalla.

```typescript
// data/moves.ts - solo datos
{
  earthquake: {
    num: 89,
    accuracy: 100,
    basePower: 100,
    category: 'Physical',
    target: 'allAdjacent',
    type: 'Ground',
    flags: { protect: 1, mirror: 1 },
  }
}

// sim/battle-actions.ts - solo lógica
getDamage(source, target, move) {
  // usa move.basePower, move.category, etc.
}
```

**Ventaja:** Los datos se pueden cambiar sin tocar la lógica. Los mods solo reescriben datos.

**En nuestro sistema:** Modelos Eloquent + migraciones. Los movimientos/habilidades/objetos son registros en BD con campos que alimentan la lógica.

---

### 3. State Machine (Máquina de Estados)

**Implementación en Showdown:** Ciclo de vida de la batalla.

```
[Not Started] → start() → [Turn N]
  → choices() → [Waiting for Choices]
  → makeActions() → [Executing]
  → faintQueue → [Faint Check]
  → [Turn N+1] o [Battle Over]
```

**Cada Pokemon también es una máquina de estados:**
```
[Active] → move/switch → [Executing]
  → [Active] (siguiente turno)
  → [Fainted] (si HP ≤ 0)
  → [Switched Out] (si cambia)
```

**En nuestro sistema:** Los estados de batalla se representan como campos en el modelo `Battle` (turno, fase, estado).

---

### 4. Observer Pattern (Observador)

**Implementación en Showdown:** Habilidades y objetos que reaccionan a eventos.

```typescript
// ability: Speed Boost
{
  onResidual(pokemon) {
    pokemon.boosts.spe = Math.min(6, pokemon.boosts.spe + 1);
  }
}

// item: Leftovers
{
  onResidual(pokemon) {
    this.heal(pokemon.baseMaxhp / 16);
  }
}
```

Cada habilidad/objeto se "suscribe" a eventos específicos y reacciona cuando se disparan.

**En nuestro sistema:** Listeners de Laravel o métodos en el Chain of Responsibility.

---

### 5. Strategy Pattern (Estrategia)

**Implementación en Showdown:** `BattleActions.getDamage()`.

El cálculo de daño es una estrategia que cambia según:
- Tipo de movimiento (Physical/Special/Status)
- Generación (Gen 1-9)
- Modificadores activos (clima, terreno, items)

```typescript
// La estrategia se determina en tiempo de ejecución:
const category = this.battle.getCategory(move);
const attackStat = move.overrideOffensiveStat || (isPhysical ? 'atk' : 'spa');
const defenseStat = move.overrideDefensiveStat || (isPhysical ? 'def' : 'spd');
```

**En nuestro sistema:** Cada paso de la cadena de daño puede ser una estrategia intercambiable.

---

### 6. Decorator Pattern (Decorador)

**Implementación en Showdown:** Modificadores de daño encadenados.

Cada modificador "decora" el daño base sin cambiar la fórmula original:

```
baseDamage
  → +spreadModifier (decora)
  → +weatherModifier (decora)
  → +critModifier (decora)
  → +randomModifier (decora)
  → +stab (decora)
  → +typeEffectiveness (decora)
  → +burnModifier (decora)
  → +finalModifier (decora)
```

Cada paso es una transformación pura: `f(damage) → damage`.

**En nuestro sistema:** Los modificadores en `src/Battle/Domain/Damage/` ya siguen este patrón.

---

### 7. Template Method (Método Plantilla)

**Implementación en Showdown:** `BattleActions.runMove()`.

```typescript
runMove(moveName, pokemon, targetLoc, sourceEffect, ...) {
  // 1. Pre-checks (template)
  // 2. Accuracy check (template)
  // 3. Execute hit steps (template)
  // 4. Apply damage (template)
  // 5. Apply secondary effects (template)
  // 6. Post-hit effects (template)
}
```

Los pasos son fijos, pero cada paso puede tener implementaciones variables.

---

### 8. Flyweight Pattern (Pesos Mosca)

**Implementación en Showdown:** Datos compartidos via `Dex`.

Los datos de movimientos/habilidades/objetos se cargan una vez y se reutilizan entre batallas.

```typescript
// Dex cachea los datos:
dex.moves.get('tackle') // carga y cachea
dex.moves.get('tackle') // reutiliza cache
```

**En nuestro sistema:** Usar cache de Laravel (`Cache::remember()`) para datos estáticos de BD.

---

### 9. Null Object Pattern (Objeto Nulo)

**Implementación en Showdown:** Estados vacíos representados como objetos con `id: ''`.

```typescript
this.effect = { id: '' } as Effect;
this.event = { id: '' };
```

En vez de `null`, se usa un objeto "vacío" que no hace nada.

**En nuestro sistema:** Evitar `null` donde sea posible. Usar objetos vacíos o Optional patterns.

---

### 10. Serialization Pattern (Serialización)

**Implementación en Showdown:** `State.serialize()` / `State.deserialize()`.

```typescript
// serialización recursiva
static serializeBattle(battle) {
  return {
    turn: battle.turn,
    field: serializeField(battle.field),
    sides: battle.sides.map(serializeSide),
    // ...
  };
}
```

**En nuestro sistema:** Laravel Eloquent maneja esto automáticamente via `$casts` y relaciones.

---

## Patrones Arquitectónicos

### Separación de Responsabilidades en Showdown

```
Battle          → orquestador (facade)
BattleActions   → cálculos y ejecución
BattleQueue     → ordenamiento
Pokemon         → estado individual
Side            → estado de jugador
Field           → estado compartido
Dex             → acceso a datos
PRNG            → generación aleatoria
State           → serialización
```

**Contraste con nuestro DDD:**
```
Battle              → Aggregate Root
BattleActions       → Domain Services
BattleQueue         → no tenemos equivalente directo
Pokemon             → Entity
Side                → Value Object (parcial)
Field               → Value Object
Dex                 → Repository + Cache
PRNG                → Domain Service
State               → Eloquent (persistence)
```

---

## Comparación con nuestro sistema

| Patrón | Pokemon Showdown | Nuestro Sistema |
|--------|-----------------|-----------------|
| **Chain of Responsibility** | `runEvent()` genérico | `DamageChain` con pasos explícitos |
| **Data-Driven** | Archivos `.ts` estáticos | BD + Eloquent Models |
| **State Machine** | Estados en battle properties | Fases en modelo Battle |
| **Observer** | Habilidades como listeners | Laravel Listeners |
| **Strategy** | Modificadores condicionales | Steps de la cadena |
| **Decorator** | Modificadores encadenados | Pipeline de modificadores |
| **Template Method** | `runMove()` con pasos fijos | Métodos de Domain Service |
| **Flyweight** | Dex con cache | Cache de Laravel |
| **Null Object** | `{ id: '' }` | Optional patterns |

### Patrones que deberíamos adoptar

1. **Flyweight para datos estáticos** — Los tipos, naturalezas y constantes deben cachearse.
2. **Más Data-Driven** — Los movimientos/habilidades deberían tener campos que alimenten la lógica genérica.
3. **State Machine explícita** — Definir fases de batalla como Enum con transiciones.

### Patrones que ya tenemos bien implementados

1. **Chain of Responsibility** — Ya existe en `src/Battle/Domain/Damage/`.
2. **DDD Separation** — Dominio vs Infraestructura bien separado.
3. **Domain Events** — Laravel events para comunicación entre bounded contexts.
