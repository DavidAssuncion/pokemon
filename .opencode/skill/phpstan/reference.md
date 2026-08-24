# PHPStan Skill Reference

## Config Files

- `phpstan.neon` — Level 6 (Coder, Cleaner baseline)
- `phpstan-hardener.neon` — Level 8 (Hardener)

## Commands

```bash
# Baseline (Coder/Cleaner)
vendor/bin/phpstan analyse --configuration=phpstan.neon --memory-limit=512M

# Hardener (Level 8)
vendor/bin/phpstan analyse --configuration=phpstan-hardener.neon --memory-limit=512M

# Generate baseline
vendor/bin/phpstan analyse -c phpstan.neon --generate-baseline phpstan-baseline.php

# With baseline
vendor/bin/phpstan analyse -c phpstan.neon --error-format=table
```

## Levels Meaning

| Level | Checks |
|-------|--------|
| 0 | Basic syntax, unknown classes/functions |
| 1 | Unknown methods, properties, wrong arg count |
| 2 | Unknown magic methods, PHPDoc types |
| 3 | Return types, assigned types |
| 4 | Nullable types, missing iterable types |
| 5 | Strict generics, always-true checks |
| 6 | **Coder baseline** — missing type hints, mixed types |
| 7 | Stricter generics, callable types |
| 8 | **Hardener** — fully typed, no mixed, strict PHPDoc |
| 9 | Max strictness |

## Project-Specific Rules (from docs/architecture.md)

### Required Patterns
```php
// 1. declare(strict_types=1) en TODOS los archivos
declare(strict_types=1);

// 2. Tipos de retorno explícitos en métodos públicos
public function execute(Command $cmd): ResultDTO { ... }

// 3. Parámetros tipados
public function __construct(private UserRepositoryInterface $repo) { }

// 4. Enums para primitivas cerradas
enum EstadoPokemon: string { case BURN = 'burn'; }

// 5. Value Objects para identificadores/cantidades
readonly class PokemonId { public function __construct(public int $value) {} }

// 6. DTOs readonly en fronteras (3+ params)
readonly class DTOAccionBatalla {
    public function __construct(public int $pokemonId, public int $movimientoId) {}
}

// 7. Propiedades private/readonly, getters tipados
class Combatiente {
    private int $hp;
    public function getHp(): int { return $this->hp; }
}

// 8. Colecciones tipadas
final class ColeccionMovimientos extends Collection {
    public function get(int $index): Movimiento { ... }
}
```

### Forbidden (Violaciones Conocidas)
```php
// ❌ src/ importando App\ o Illuminate\
use App\Models\Team; // PROHIBIDO en src/

// ❌ new en routes/ — usar app()->make() o DI
Route::get('/', fn() => new Controller());

// ❌ Servicio instanciado en cada acción — inyectar por constructor
class Combate {
    public function handle() { new ServicioEjecucionBatalla(); }
}

// ❌ Arrays asociativos como contrato público — usar DTOs/Value Objects
return ['hp' => 100, 'max_hp' => 100]; // PROHIBIDO
```

## Running in Pipeline

```bash
# Coder: level 6, debe pasar
vendor/bin/phpstan analyse -c phpstan.neon

# Cleaner: level 6 + Infection
vendor/bin/phpstan analyse -c phpstan.neon
vendor/bin/infection --min-msi=80

# Hardener: level 8 + 100% MSI
vendor/bin/phpstan analyse -c phpstan-hardener.neon
vendor/bin/infection --min-msi=100
```

## Common Fixes

| Error | Fix |
|-------|-----|
| `Parameter #1 $x of method expects X, Y given` | Add type hint or cast |
| `Access to an undefined property` | Add property or use getter |
| `Call to an undefined method` | Add method or check class |
| `Mixed type passed to typed parameter` | Narrow type with `assert()` or `instanceof` |
| `PHPDoc tag @var with invalid type` | Fix PHPDoc syntax |

## Baselines

```bash
# Generar baseline inicial (solo una vez)
vendor/bin/phpstan analyse -c phpstan.neon --generate-baseline phpstan-baseline.php

# Incluir en phpstan.neon:
# includes:
#     - phpstan-baseline.php
```

**Regla:** Baseline solo para deuda técnica existente. Código nuevo debe pasar sin baseline.