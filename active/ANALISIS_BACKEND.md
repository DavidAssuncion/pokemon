# Análisis Backend — Corrección de bugs en módulo de combate + tests TDD

## Fecha
2026-08-30

## Contexto

El módulo de combate tiene 6 bugs de runtime que acceden a propiedades privadas de `PokemonEntity` en lugar de usar sus getters. `PokemonEntity` tiene `private $moves` (ColeccionMovimientos) con getter `moves()` y `private $tiposCollection` (TiposCollection) con getter `tiposCollection()`.

Además, `tests/Feature/PokemonBattleTest.php` usa `BattleAggregate` marcado `@deprecated` (duplicado de `AgregadoBatalla`). Se migra a `AgregadoBatalla` + `FabricaBatallaMock::createBattle()`.

Se crean tests unitarios en `tests/Unit/Battle/` cubriendo 10 mecánicas críticas.

## Bugs confirmados (grep + lectura)

| # | Archivo | Línea | Código bug | Corrección |
|---|---------|-------|------------|------------|
| 1 | `src/Battle/Domain/AgregadoBatalla.php` | 306 | `->moves->isEmpty()` | `->moves()->isEmpty()` |
| 2 | `src/Battle/Domain/AgregadoBatalla.php` | 313 | `->moves as $move` | `->moves() as $move` |
| 3 | `app/Livewire/Combate.php` | 228 | `->moves->all()` | `->moves()->all()` |
| 4 | `app/Livewire/Combate.php` | 427 | `->moves as $move` | `->moves() as $move` |
| 5 | `app/Livewire/Combate.php` | 476 | `->moves->get($index)` | `->moves()->get($index)` |
| 6 | `app/Livewire/Combate.php` | 533 | `->tiposCollection as $tipo` | `->tiposCollection() as $tipo` |

## Archivos a tocar

| Archivo | Acción | Propósito |
|---------|--------|-----------|
| `src/Battle/Domain/AgregadoBatalla.php` | 2 cambios | `->moves` → `->moves()` en líneas 306 y 313 |
| `app/Livewire/Combate.php` | 4 cambios | `->moves` → `->moves()` (3 veces) y `->tiposCollection` → `->tiposCollection()` (1 vez) |
| `tests/Feature/PokemonBattleTest.php` | Reescribir | Migrar de `BattleAggregate` a `AgregadoBatalla` + `FabricaBatallaMock::createBattle()` |
| `tests/Unit/Battle/CadenaDanioTest.php` | NUEVO | Tests de daño base + STAB |
| `tests/Unit/Battle/EfectoOrbeVidaTest.php` | NUEVO | Test orbe vida ×1.3 + recoil |
| `tests/Unit/Battle/EfectoRestosTest.php` | NUEVO | Test restos cura 1/16 |
| `tests/Unit/Battle/EfectoInvocadorClimaTest.php` | NUEVO | Test invocador clima SEQUIA |
| `tests/Unit/Battle/SujetoBatallaTest.php` | NUEVO | Test observer notifyDamaged/notifyFainted |
| `tests/Unit/Battle/EstadoPokemonTest.php` | NUEVO | Test BURN daño + SLEEP/PARALYSIS puedeActuar |
| `tests/Unit/Battle/EtapasStatsTest.php` | NUEVO | Test clamp -6..+6 + multiplicadores |
| `tests/Unit/Battle/ManejadorPosicionTest.php` | NUEVO | Test -50% retaguardia |
| `tests/Unit/Battle/ManejadorClimaTest.php` | NUEVO | Test ±25% clima |

## Tests TDD (rojo → verde)

### 1. `tests/Feature/PokemonBattleTest.php` migrado
- `test_battle_with_fabrica_mock_creates_2_teams_3_combatants`: CreateBattle() → 2 equipos, 3 combatientes cada uno, movimientos accesibles via `->moves()`
- `test_combatants_have_moves_accessible_via_getter`: `$combatant->pokemon()->moves()->all()` no lanza error

### 2. `tests/Unit/Battle/CadenaDanioTest.php`
- `test_calcula_dano_mayor_que_cero`: CadenaDanio::calculate() > 0 con AccionBatalla válida
- `test_dano_base_sigue_formula`: atk=100, def=100, power=50 → base=24 (semilla mt_srand para no crit)
- `test_stab_multiplica_por_1_5`: atacante con tipo = tipo movimiento → daño ×1.5

### 3. `tests/Unit/Battle/EfectoOrbeVidaTest.php`
- `test_orbe_vida_multiplica_dano_por_1_3`: atacante con life_orb → daño ×1.3
- `test_orbe_vida_recoil_10_por_ciento`: dispararDanioInfligido → HP -10% máx, log con "Orbe Vida"

### 4. `tests/Unit/Battle/EfectoRestosTest.php`
- `test_restos_cura_1_16_cada_ronda`: triggerRoundEndEffects → HP +1/16 máx, sin superar máx

### 5. `tests/Unit/Battle/EfectoInvocadorClimaTest.php`
- `test_sequia_establece_clima_en_battle_start`: triggerBattleStartEffects → weather = SEQUIA

### 6. `tests/Unit/Battle/SujetoBatallaTest.php`
- `test_notify_damaged_notifica_observador`: notifyDamaged → observer registra
- `test_notify_fainted_notifica_observador`: notifyFainted → observer registra

### 7. `tests/Unit/Battle/EstadoPokemonTest.php`
- `test_burn_causa_dano_por_ronda`: aplicarDañoStatus → HP -6.25% máx, daño > 0
- `test_sleep_impide_actuar`: puedeActuar con SLEEP → canAct false
- `test_paralysis_puede_impedir_actuar`: puedeActuar con PARALYSIS → a veces bloquea (seed determinista)
- `test_sin_estado_puede_actuar`: puedeActuar con NONE → canAct true

### 8. `tests/Unit/Battle/EtapasStatsTest.php`
- `test_aplicar_cambio_clampea_a_6`: desde 5 +5 → 6; desde -5 -5 → -6
- `test_multiplicador_positivo`: +2 → (2+2)/2 = 2.0
- `test_multiplicador_negativo`: -2 → 2/(2-(-2)) = 0.5

### 9. `tests/Unit/Battle/ManejadorPosicionTest.php`
- `test_retaguardia_con_vanguardia_enemiga_viva_50_por_ciento`: defender en retaguardia + defenderTeamHasVanguard=true → ×0.5
- `test_vanguardia_sin_penalizacion`: defender en vanguardia → ×1.0

### 10. `tests/Unit/Battle/ManejadorClimaTest.php`
- `test_sequia_fuego_125`: SEQUIA + FUEGO → ×1.25
- `test_sequia_agua_075`: SEQUIA + AGUA → ×0.75
- `test_sin_clima_1`: NONE → ×1.0

## Estrategia de construcción de combatientes (unit tests)

Los tests unitarios extienden `PHPUnit\Framework\TestCase` (sin boot de Laravel, sin BD).
Para construir combatientes de prueba se usan dos enfoques:

1. **Build directo**: `new Combatiente(new PokemonEntity(...), Posicion::VANGUARDIA)` con setters para id, nombre, item, effects.
2. **EquipoBatalla::fromData()**: `EquipoBatalla::fromData([$dato], 'Equipo')` con `DatosPokemonBatalla` mínimo.

Para efectos (Orbe Vida, Restos, Invocador Clima), se construyen las instancias directamente y se añaden via `$combatant->effects()->add($efecto)`, evitando dependencia de `FabricaEfectos` (que requiere app boot).

## Riesgos

1. **RNG no determinista**: `ManejadorCritico` y `procesarParalysis` usan `mt_rand`. Se soluciona con `mt_srand(seed)` antes de llamar. Verificado: seed=1 → no crit (0.417 > 0.0625), para permite actuar (40 > 25); seed=100 → para bloquea (13 ≤ 25).
2. **Efectos no registrados sin app boot**: Los tests unitarios extienden `PHPUnit\Framework\TestCase`, no bootean Laravel. `FabricaEfectos` está vacío. Solución: construir efectos directamente y añadirlos manualmente a `ColeccionEfectos`.
3. **PokemonBattleTest (Feature) necesita DB**: Extiende `Tests\TestCase` que bootea la app. `AppServiceProvider::boot()` ejecuta `Schema::hasTable('users')` requiriendo conexión a BD. Localmente se ejecuta con `APP_ENV=testing DB_USERNAME=laravel DB_PASSWORD=laravel php artisan test --filter=PokemonBattleTest`.
4. **Combate Livewire no testeable sin sesión**: Los 4 bugs de `Combate.php` se corrigen directamente (cambio de getter). No se añade test de regresión por la complejidad de montar el componente Livewire completo. La corrección es trivial (sintaxis de getter) y verificable por PHPStan.
5. **PHPStan nivel 6**: Tras las correcciones, debe pasar sin errores en `src/Battle/Domain/AgregadoBatalla.php` y `app/Livewire/Combate.php`.

## Entorno

- Tests ejecutados localmente con `APP_ENV=testing DB_USERNAME=laravel DB_PASSWORD=laravel php artisan test --compact --filter=Battle`
- `vendor/bin/pint --dirty --format agent` al final
- `vendor/bin/phpstan analyse` nivel 6
- `vendor/bin/phpmd src/ text phpmd.xml`

---

## Estado de ejecución (backend, 2026-08-30)

### Desviaciones del plan del Analista

1. **Entorno de tests arreglado (necesario para correr la suite)**:
   - `phpunit.xml` apuntaba a `pgsql` con credenciales `pokemon`/`secret` (no existían) y puerto heredado.
   - `.env` apuntaba al puerto `5433` (inexistente en este entorno).
   - PostgreSQL local está en `127.0.0.1:5432` con usuario `laravel`/`laravel` y BDs `laravel` + `laravel_test` ya creadas.
   - Fix: `.env` → `DB_PORT=5432` + credenciales `laravel`; `phpunit.xml` → `DB_USERNAME=laravel`, `DB_PASSWORD=laravel` (puerto 5432 ya correcto).
   - **Nota**: se descartó SQLite `:memory:` (sin driver `pdo_sqlite` en el PHP del entorno; los unit tests usan `PHPUnit\Framework\TestCase` y no requieren BD).

2. **Tests unitarios**: se crean con `php artisan make:test --unit --phpunit` (extienden `PHPUnit\Framework\TestCase`, sin boot de Laravel ni BD), en subcarpeta `tests/Unit/Battle/`.

3. **Bug extra detectado en la batalla automática**: `AgregadoBatalla::elegirMejorMovimiento()` (bugs 1 y 2) solo se ejecuta en `ejecutarBatalla()`. Se añade un test de regresión unitario que llama a `elegirMejorMovimiento()` directamente para cubrir los 2 bugs de `AgregadoBatalla` (además de la migración del Feature test).

4. **`docs/conventions.md` modificado por el agente Frontend/Analista** (no es de este backend; no se toca).

### Resultado

- Tests: `tests/Feature/PokemonBattleTest.php` migrado + `tests/Unit/Battle/*` (10 mecánicas) verdes.
- PHPStan nivel 6 limpio sobre los archivos tocados.
- PHPMD y Pint aplicados.
- Commits atómicos: fix de bugs + tests TDD + migración del test deprecado.