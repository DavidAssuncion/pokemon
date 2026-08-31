# 06 — Recomendaciones de Migración

## Resumen Ejecutivo

Este documento consolida todas las recomendaciones derivadas del análisis de Pokemon Showdown para nuestro sistema de batalla 3v3 en Laravel/Livewire.

---

## 1. Fórmula de Daño

### Recomendación: Mantener la fórmula base oficial

```php
// src/Battle/Domain/Damage/Steps/BaseDamageStep.php

$baseDamage = intdiv(
    intdiv(
        intdiv(
            intdiv(2 * $level / 5 + 2, 1) * $basePower * $attack,
            $defense, 
            1
        ),
        50,
        1
    ),
    1,
    PHP_INT_SIZE === 4 ? 'PHP_INT_SIZE' : null
);
```

**Cambios vs Showdown:**
- Eliminar branches de generaciones anteriores (solo Gen 9+).
- Simplificar el truncamiento a `intdiv()` de PHP.
- Mantener los modificadores en el pipeline de Chain of Responsibility.

### Modificadores a implementar (en orden)

| # | Modificador | Obligatorio | Complejidad |
|---|-------------|:-----------:|:-----------:|
| 1 | +2 fijo | ✅ | Baja |
| 2 | Spread modifier (0.75) | ⚠️ Solo si multi-target | Baja |
| 3 | Weather modifier | ✅ | Media |
| 4 | Critical hit (×1.5) | ✅ | Baja |
| 5 | Random [0.85, 1.0] | ✅ | Baja |
| 6 | STAB (×1.5) | ✅ | Baja |
| 7 | Type effectiveness | ✅ | Media |
| 8 | Burn (×0.5 Físico) | ✅ | Baja |
| 9 | Final modifiers (Life Orb, etc.) | ✅ | Media |
| 10 | Minimum 1 | ✅ | Baja |

---

## 2. Sistema de Stats y Boosts

### Recomendación: Tabla estándar -6 a +6

```php
// src/Battle/Domain/Shared/Constants.php

final class Constants
{
    /** @var array<int, float> */
    public const BOOST_TABLE = [
        -6 => 2/8,
        -5 => 4/7,
        -4 => 2/6,
        -3 => 4/5,
        -2 => 1/2,
        -1 => 2/3,
         0 => 1,
         1 => 3/2,
         2 => 2,
         3 => 5/2,
         4 => 3,
         5 => 7/2,
         6 => 4,
    ];
    
    public const MAX_BOOST = 6;
    public const MIN_BOOST = -6;
}
```

### Recomendación: Stats base como relación Eloquent

```php
// Migration
Schema::create('pokemon_stats', function (Blueprint $table) {
    $table->id();
    $table->foreignId('species_id');
    $table->unsignedTinyInteger('hp');
    $table->unsignedTinyInteger('attack');
    $table->unsignedTinyInteger('defense');
    $table->unsignedTinyInteger('special_attack');
    $table->unsignedTinyInteger('special_defense');
    $table->unsignedTinyInteger('speed');
    $table->timestamps();
});
```

---

## 3. Tipo Chart

### Recomendación: PHP Enum + Tabla de efectividad

```php
// src/Shared/Domain/Enum/PokemonType.php

enum PokemonType: string
{
    case Normal = 'Normal';
    case Fire = 'Fire';
    case Water = 'Water';
    case Electric = 'Electric';
    // ... 18 tipos
    
    /**
     * Retorna el multiplicador de efectividad.
     * 0 = inmune, 0.5 = resistente, 1 = neutro, 2 = super efectivo
     */
    public function effectivenessAgainst(self $defender): float
    {
        return self::CHART[$this->value][$defender->value] ?? 1.0;
    }
    
    private const CHART = [
        'Normal' => [
            'Rock' => 0.5,
            'Ghost' => 0.0,
            'Steel' => 0.5,
        ],
        'Fire' => [
            'Fire' => 0.5,
            'Water' => 0.5,
            'Grass' => 2.0,
            'Ice' => 2.0,
            'Bug' => 2.0,
            'Rock' => 0.5,
            'Dragon' => 0.5,
            'Steel' => 2.0,
            'Fairy' => 2.0,
        ],
        // ... completa la matriz
    ];
}
```

### Recomendación alternativa: Tabla en BD

```php
Schema::create('type_effectiveness', function (Blueprint $table) {
    $table->id();
    $table->string('attacker_type', 20);
    $table->string('defender_type', 20);
    $table->decimal('multiplier', 3, 1); // 0.0, 0.5, 1.0, 2.0
    $table->unique(['attacker_type', 'defender_type']);
});
```

> **Decisión:** Para 18×18 = 324 entradas, un Enum con array constante es más eficiente que BD. Usar BD solo si el chart se modifica frecuentemente.

---

## 4. Naturalezas

### Recomendación: PHP Enum simple

```php
// src/Shared/Domain/Enum/Nature.php

enum Nature: string
{
    case Hardy = 'Hardy';
    case Lonely = 'Lonely';
    // ... 25 naturalezas
    
    public function increasedStat(): ?StatType
    {
        return match ($this) {
            self::Lonely, self::Brave, self::Adamant, self::Naughty 
                => StatType::Attack,
            self::Bold, self::Impish, self::Lax, self::Relaxed 
                => StatType::Defense,
            // ...
            default => null,
        };
    }
    
    public function decreasedStat(): ?StatType
    {
        return match ($this) {
            self::Lonely, self::Bold, self::Timid, self::Modest, self::Calm 
                => StatType::Attack,
            // ...
            default => null,
        };
    }
    
    public function applyToStat(float $baseStat, StatType $stat): float
    {
        if ($this->increasedStat() === $stat) {
            return $baseStat * 1.1;
        }
        if ($this->decreasedStat() === $stat) {
            return $baseStat * 0.9;
        }
        return $baseStat;
    }
}
```

---

## 5. Movimientos, Habilidades y Objetos

### Recomendación: Modelos Eloquent + Listeners

**Estructura de tablas:**

```php
// moves
Schema::create('moves', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('identifier')->unique(); // slug
    $table->string('type', 20);
    $table->enum('category', ['physical', 'special', 'status']);
    $table->unsignedSmallInteger('base_power')->nullable();
    $table->unsignedTinyInteger('accuracy'); // 0-100, 255 = guaranteed
    $table->smallInteger('priority')->default(0);
    $table->unsignedTinyInteger('pp');
    $table->string('target', 30); // MoveTarget enum
    $table->json('flags')->nullable(); // MoveFlags
    $table->json('secondary_effects')->nullable();
    $table->timestamps();
});

// abilities
Schema::create('abilities', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('identifier')->unique();
    $table->text('description')->nullable();
    $table->boolean('is_hidden')->default(false);
    $table->timestamps();
});

// items
Schema::create('items', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('identifier')->unique();
    $table->text('description')->nullable();
    $table->json('effects')->nullable(); // efectos en batalla
    $table->timestamps();
});
```

### Para efectos de habilidades/objetos: Listeners

```php
// src/Battle/Infrastructure/Listeners/AbilityListener.php

class AbilityListener
{
    public function handle(DamageCalculating $event): void
    {
        $ability = $event->pokemon->ability;
        
        match ($ability->identifier) {
            'huge-power' => $event->attackMultiplier *= 2,
            'guts' => $event->attackMultiplier *= 1.5,
            'multiscale' => $event->damageMultiplier *= 0.5,
            // ...
            default => null,
        };
    }
}
```

---

## 6. Estados Alterno (Status)

### Recomendación: PHP Enum

```php
// src/Battle/Domain/Battle/Enum/BattleStatus.php

enum BattleStatus: string
{
    case Poison = 'psn';
    case BadlyPoison = 'tox';
    case Burn = 'brn';
    case Paralysis = 'par';
    case Freeze = 'frz';
    case Sleep = 'slp';
    
    public function damagePerTurn(int $turnsActive): int
    {
        return match ($this) {
            self::Poison => 1, // 1/16 maxHP
            self::BadlyPoison => $turnsActive, // escalado
            self::Burn => 1, // 1/16 maxHP
            default => 0,
        };
    }
    
    public function canAct(): bool
    {
        return !in_array($this, [self::Freeze, self::Sleep]);
    }
    
    public function speedMultiplier(): float
    {
        return match ($this) {
            self::Paralysis => 0.25, // Gen 7+
            default => 1.0,
        };
    }
    
    public function attackMultiplier(): float
    {
        return match ($this) {
            self::Burn => 0.5, // solo Físico, sin Guts
            default => 1.0,
        };
    }
}
```

---

## 7. Sistema de Turnos

### Recomendación: Máquina de estados explícita

```php
// src/Battle/Domain/Battle/Enum/BattlePhase.php

enum BattlePhase: string
{
    case NotStarted = 'not_started';
    case TeamPreview = 'team_preview';
    case TurnStart = 'turn_start';
    case MoveSelection = 'move_selection';
    case MoveExecution = 'move_execution';
    case SwitchSelection = 'switch_selection';
    case SwitchExecution = 'switch_execution';
    case EndOfTurn = 'end_of_turn';
    case BattleOver = 'battle_over';
}
```

### Recomendación: Cola de acciones simplificada

```php
// src/Battle/Domain/Battle/ActionQueue.php

class ActionQueue
{
    /** @var Action[] */
    private array $actions = [];
    
    public function add(Action $action): void
    {
        $this->actions[] = $action;
    }
    
    /**
     * Ordenar por: priority DESC → speed DESC → random
     */
    public function sort(): void
    {
        usort($this->actions, function (Action $a, Action $b) {
            // 1. Priority: mayor = primero
            if ($a->priority !== $b->priority) {
                return $b->priority <=> $a->priority;
            }
            // 2. Speed: mayor = primero
            if ($a->pokemon->speed !== $b->pokemon->speed) {
                return $b->pokemon->speed <=> $a->pokemon->speed;
            }
            // 3. Random
            return random_int(0, 1) ? 1 : -1;
        });
    }
}
```

---

## 8. Arquitectura del Motor de Batalla

### Recomendación: Mantener Chain of Responsibility + Agregar Pipeline

```php
src/Battle/
├── Domain/
│   ├── Battle/
│   │   ├── Battle.php                 // Aggregate Root
│   │   ├── BattlePhase.php            // Enum de fases
│   │   ├── ActionQueue.php            // Cola de acciones
│   │   └── Events/                    // Domain Events
│   ├── Damage/
│   │   ├── DamageChain.php            // Chain of Responsibility
│   │   └── Steps/                     // Pasos de cálculo
│   │       ├── BaseDamageStep.php
│   │       ├── WeatherStep.php
│   │       ├── CriticalStep.php
│   │       ├── RandomStep.php
│   │       ├── StabStep.php
│   │       ├── TypeEffectivenessStep.php
│   │       └── FinalModifierStep.php
│   ├── Effect/                         // Efectos de estado
│   │   ├── StatusEffect.php
│   │   └── VolatileEffect.php
│   └── Shared/
│       ├── Constants.php              // Tablas y constantes
│       └── Enums/
│           ├── PokemonType.php
│           ├── Nature.php
│           ├── BattleStatus.php
│           └── MoveCategory.php
├── Application/                        // Use Cases
│   ├── StartBattleUseCase.php
│   ├── ExecuteMoveUseCase.php
│   ├── ApplyStatusUseCase.php
│   └── EndTurnUseCase.php
├── Infrastructure/                     // Laravel adapters
│   ├── Eloquent/
│   │   ├── BattleModel.php
│   │   ├── MoveModel.php
│   │   └── AbilityModel.php
│   ├── Listeners/
│   │   └── AbilityListener.php
│   └── Cache/
│       └── TypeChartCache.php
└── Presentation/                       // Livewire
    └── Livewire/
        └── BattleComponent.php
```

---

## 9. Prioridades de Implementación

### Fase 1: Fundamentos (Semanas 1-2)
1. ✅ Enums: `PokemonType`, `Nature`, `BattleStatus`, `MoveCategory`
2. ✅ Tabla de efectividad de tipos
3. ✅ Tabla de boost de stats
4. ✅ Migraciones para moves, abilities, items, species

### Fase 2: Motor de Daño (Semanas 3-4)
1. ✅ `DamageChain` con todos los pasos
2. ✅ Fórmula base de daño
3. ✅ Modificadores: weather, STAB, type effectiveness, critical
4. ✅ Random factor [0.85, 1.0]

### Fase 3: Estados y Efectos (Semanas 5-6)
1. ✅ Status application (burn, paralysis, etc.)
2. ✅ Volatiles principales (confusion, protect, substitute)
3. ✅ Daño pasivo (weather, poison, burn)
4. ✅ Curación (Leftovers, drain, recover)

### Fase 4: Integración (Semanas 7-8)
1. ✅ Sistema de turnos
2. ✅ Cola de acciones
3. ✅ Targeting (1v1, AOE)
4. ✅ Livewire component para UI

---

## 10. Lo que NO copiar de Showdown

| Feature | Motivo |
|---------|--------|
| **Multi-generación** | Nosotros solo hacemos Gen 9+ |
| **Todos los ~900 movimientos** | Empezar con 50-100 movimientos core |
| **Mega Evolution / Z-Moves / Dynamax** | Decidir cuáles implementar |
| **Todo el sistema de mods** | No necesitamos formatos personalizados |
| **Replay system** | No necesario para nuestro caso |
| **Matchmaking** | Fuera del alcance del motor de batalla |
| **Red tree architecture** | No aplica a Laravel monolítico |

---

## Comparación Final

| Aspecto | Pokemon Showdown | Nuestro Sistema | Diferencia Clave |
|---------|-----------------|-----------------|------------------|
| **Lenguaje** | TypeScript | PHP 8.4 | — |
| **Motor** | Clase monolítica | DDD + Chain | Más modular |
| **Datos** | Archivos estáticos | BD + Cache | Más flexible |
| **Eventos** | `runEvent()` genérico | Laravel Events | Más estándar |
| **Persistencia** | Serialización JSON | Eloquent ORM | Más robusto |
| **UI** | WebSocket client | Livewire 3 | SSR nativo |
| **Formatos** | Multi-formato | Solo 3v3 | Más simple |
| **RNG** | Determinista (replay) | Aleatorio | Sin replay |
| **Velocidad** | Optimizado para server | Eloquent queries | Potencialmente más lento |

### Ventajas de nuestro enfoque
1. **Type safety nativa** — PHP 8.4 enums, union types, readonly.
2. **Persistencia robusta** — Eloquent con migraciones.
3. **Testing más fácil** — PHPUnit con factories.
4. **Escalabilidad horizontal** — Laravel queues y cache.
5. **UI más rápida** — Livewire con SSR.

### Desventajas a mitigar
1. **Rendimiento** — Eloquent puede ser lento para muchos queries. Usar cache agresivo.
2. **Complejidad DDD** — Asegurar que no sobre-ingeniería.
3. **Sin replay** — Decidir si queremos implementar logging de batallas.
