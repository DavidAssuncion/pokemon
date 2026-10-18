---
name: PHPStan
description: "Niveles y configuración de PHPStan (phpstan.neon L6 / phpstan-hardener.neon L8), reglas de tipado estricto del proyecto y su uso en el pipeline (Coder/Cleaner/Hardener). Úsalo al configurar PHPStan, interpretar o corregir errores de análisis estático, generar baselines o aplicar tipado estricto con DTOs, Collections, Enums y Value Objects."
---

# PHPStan

## Config Files

- `phpstan.neon` — Level 6 (Coder, Cleaner baseline)
- `phpstan-hardener.neon` — Level 8 (Hardener)

## Commands

```bash
# Canónicos (scripts Composer, baseline incluido)
composer run analyse        # Level 6 (Coder/Cleaner)
composer run analyse:hard   # Level 8 (Hardener)

# Regenerar baselines (solo contra árbol limpio y al reducir deuda)
vendor/bin/phpstan analyse -c phpstan.neon --generate-baseline=phpstan-baseline.php
vendor/bin/phpstan analyse -c phpstan-hardener.neon --generate-baseline=phpstan-hardener-baseline.php
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
composer run analyse

# Cleaner: level 6 + Infection
composer run analyse
vendor/bin/infection --min-msi=80

# Hardener: level 8 + 100% MSI
composer run analyse:hard
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

- `phpstan-baseline.php` — L6 (221 entradas / 279 errores), incluido desde `phpstan.neon`.
- `phpstan-hardener-baseline.php` — errores exclusivos de L8 (157 entradas / 196 errores),
  incluido desde `phpstan-hardener.neon` (que a su vez incluye el baseline L6).

Regeneración (solo contra árbol limpio y al reducir deuda):

```bash
vendor/bin/phpstan analyse -c phpstan.neon --generate-baseline=phpstan-baseline.php
vendor/bin/phpstan analyse -c phpstan-hardener.neon --generate-baseline=phpstan-hardener-baseline.php
```

**Regla:** los baselines congelan deuda preexistente; no se añaden entradas nuevas. Código nuevo
debe pasar sin baseline.