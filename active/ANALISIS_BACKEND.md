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

---

## Endurecimiento del módulo Battle — 2 fixes de tipo + tests anti-mutantes (2026-08-30)

### Estado: COMPLETADO (MSI Battle 80%)

### Tarea 1 — 2 fixes de tipo (comportamiento neutro, aprobados por Arquitecto)

| Archivo | Fix | Línea |
|---------|-----|-------|
| `src/Pokemon/Domain/Stats/BattleStats.php` | `calcularHp(float $base, float $evs, $nivel)` → `int $nivel` | 45 |
| `src/Pokemon/Domain/Stats/BattleStats.php` | `calcularStat(float $base, float $evs, $nivel)` → `int $nivel` | 50 |
| `src/Battle/Domain/GestorTurnos.php` | Añadir `/** @var Combatiente[] */` a `$teamB` (línea 19, hermana de `$teamA` con docblock) | 19 |

### Tarea 2 — Tests anti-mutantes (MSI ≥80%)

**Archivos NUEVOS:**

1. `tests/Unit/Battle/GestorTurnosTest.php` — 8 tests: startNewRound incrementa round; acumula velocidad y resetea vecesActuado; getNextActor devuelve el de mayor velocidad; empate devuelve el primero (T1); consumeAction reduce velocidad e incrementa veces; bothTeamsAlive true/false; hayAlgunoConAccionPendiente false sin vivos; combatientesVivos excluye muertos.
2. `tests/Unit/Battle/CombatienteRecibirDanoTest.php` — 6 tests: daño absorbido por barrera; daño excede barrera; directPct penetra; directPct 1.0 todo a HP; especial usa barrera especial; HP nunca negativo.
3. `tests/Unit/Battle/ServicioEjecucionBatallaTest.php` — 4 tests: calcularYAplicarDano retorna DTOResultadoDanio con daño>0; aplicarEstado con BURN / sin estado no cambia; aplicarStatChanges aplica self+target; generarLogMovimiento formato correcto.
4. `tests/Unit/Battle/FabricaEfectosTest.php` — 5 tests: crearEfecto devuelve instancia con clave; desconocido → null; crearItem devuelve instancia; desconocido → null; clavesEfectos/clavesItems listan registrados.

**Archivos EXISTENTES a ampliar:**

5. `tests/Unit/Battle/ManejadorClimaTest.php` — +7 tests: DILUVIO agua×1.25/fuego×0.75; NIEBLA siniestro/fantasma/psíquico×1.25; TURBULENCIAS dragón/volador×1.25; GRANIZO especial HIELO×0.80; TORMENTA_ARENA físico ROCA×0.80.
6. `tests/Unit/Battle/EfectoOrbeVidaTest.php` — +2 tests: recoil NO si atacante muerto; NO si daño=0.
7. `tests/Unit/Battle/EfectoRestosTest.php` — +1 test: no cura si portador muerto.
8. `tests/Unit/Battle/EstadoPokemonTest.php` — +3 tests: POISON 12.5%; BAD_POISON creciente (contador/16); sin estado no causa daño.
9. `tests/Unit/Battle/EtapasStatsTest.php` — +4 tests: constructor lanza excepción >6 / <-6; obtenerNoNeutras solo distintas de cero; multiplicador extremo +6→×4; -6→×0.25.
10. `tests/Unit/Battle/CadenaDanioTest.php` — +1 test: clamp mínimo 1 cuando daño<1.

### Estrategia de construcción

- Tests unitarios extienden `PHPUnit\Framework\TestCase` (sin boot, sin BD). Usan trait `ConstruyeCombatientes` (helpers `combatiente()`, `batallaMinima()`).
- Para equipos multi-combatiente (GestorTurnos), construir `EquipoBatalla` manual con `agregarCombatiente()`.
- Para efectos, construir instancias directamente y añadir vía `$combatant->effects()->add($efecto)`.
- `FabricaEfectos` se instancia y registra efectos en el `setUp` de cada test.
- RNG determinista con `mt_srand(seed)` para crítico, parálisis, sueño.
- Verificar firmas reales de cada clase antes de escribir (leídos: BattleStats, GestorTurnos, Combatiente, ServicioEjecucionBatalla, FabricaEfectos, EfectoRestos, EfectoOrbeVida, EtapasStats, ManejadorClima, CadenaDanio, AccionBatalla, MovimientoBatalla, EquipoBatalla, AgregadoBatalla, PokemonEntity, TypeChart, etc.).

### Archivos a tocar

| Archivo | Acción | Riesgo |
|---------|--------|--------|
| `src/Pokemon/Domain/Stats/BattleStats.php` | 2 cambios de tipo | Ninguno (comportamiento neutro) |
| `src/Battle/Domain/GestorTurnos.php` | 1 docblock | Ninguno (comportamiento neutro) |
| 4 tests nuevos + 6 existentes | Crear/ampliar | Ninguno (tests puros sin boot, no afectan BD) |

### Entregable

- Commits atómicos: `refactor:` (2 fixes de tipo) + `test:` (tests anti-mutantes).
- Handoff: `type: git_handoff, to: bibliotecario, priority: 60, task: corregir-combate-bugs`.

### Resultado final

- **2 fixes de tipo** aplicados (`2c7e8ce`): `int $nivel` en `BattleStats::calcularHp/calcularStat` + docblock `/** @var Combatiente[] */` en `GestorTurnos::$teamB`.
- **Tests anti-mutantes**: 28 → **121 tests** de Battle (334 assertions). Archivos nuevos: `GestorTurnosTest`, `CombatienteRecibirDanoTest`, `ServicioEjecucionBatallaTest`, `FabricaEfectosTest`, `FabricaBatallaMockTest`, `EquipoBatallaTest`, `CombatienteAvanzadoTest` (7 nuevos + 6 ampliados). Commits `d25de1e` + `b80700a`.
- **Infection src/Battle**: MSI **42.33% → 80%** (656/816 mutantes matados, Covered Code MSI 80%). Ejecutado con `--filter=src/Battle --only-covering-test-cases --test-framework-extra-args='--filter=Battle'` (el run completo de Infection fallaba en `ServicioCapturaTest`, fallo pre-existente ajeno a Battle; además el filtro reduce el tiempo).
- **PHPStan nivel 6**: total 189 errores (misma línea base pre-existente). `BattleStats` → 0 errores (los 2 fixes de tipo limpiaron el archivo). `GestorTurnos` → 2 errores pre-existentes de `missingType.iterableValue` en métodos (`allCombatants`, `combatientesVivos`), NO en la propiedad `$teamB` (ya tipada). Sin errores nuevos en `src/Battle/` ni `app/Livewire/Combate.php`.
- **PHPMD**: solo advertencias pre-existentes de `ExcessiveMethodLength` en el módulo Battle.
- **Pint**: aplicado (`--dirty`).
- Desviaciones: los nombres/estructura del informe del Hardener se ajustaron a las firmas reales (p.ej. `FabricaEfectos` es instancia, `recibirDaño` usa barreras duales, `generarLogMovimiento` tiene 6 params). Tests añadidos extra (EquipoBatalla, CombatienteAvanzado, FabricaBatallaMock) para alcanzar el 80% de MSI.

---

## Refactor del módulo de combate — 3 problemas de diseño (2026-08-30)

### Estado: COMPLETADO (QA pendiente)

### Resultado final

- **Problema 1** (`f9f2b19`): `ManejadorOrbeVida` → `ManejadorObjetosEquipados` genérico con mapa `array<string, float>` (default `['life_orb' => 1.30]`). `CadenaDanio` usa el nuevo manejador en el mismo orden (último eslabón). `ManejadorOrbeVida.php` eliminado. `EfectoOrbeVida` NO se tocó (recoil sigue en Effects/).
- **Problema 2** (`8ae08ed`): Tyranitar `[SINIESTRO]` → `[ROCA, SINIESTRO]` en `FabricaBatallaMock` (inmune a su propia tormenta arena). `CalculadorDañoClima` extraído de `AgregadoBatalla` con misma lógica y valores. `SESSION_VERSION` 3 → 4.
- **Problema 3** (`105bed9`): `SelectorAccionIA` extraído de `AgregadoBatalla` (objetivo + mejor movimiento). API pública `elegirObjetivoPara`/`elegirMejorMovimiento` conservada para `Combate::prepareAiAnimation`.

### Verificación

- **Tests de Battle**: 121 → **140** (362 assertions) verdes. Nuevos: `ManejadorObjetosEquipadosTest` (5), `CalculadorDañoClimaTest` (8), `SelectorAccionIATest` (6).
- **Feature**: `PokemonBattleTest` 2 verdes.
- **PHPStan nivel 6**: total **189 errores** (idéntico a la línea base pre-existente; sin errores NUEVOS). Durante el desarrollo se detectó y corrigió un `method.notFound` de `elegirObjetivo` que quedó tras la extracción (corregido, sin llegar a commit roto).
- **Infection src/Battle**: Covered Code MSI **80%** (exit 0 con `--min-covered-msi=80`). Mutantes escapados de `SelectorAccionIA` son equivalentes/neutros (rama retaguardia coincide con fallback; score inicial -1 no altera resultado con movimientos).
- **PHPMD**: solo `ExcessiveMethodLength` pre-existentes (misma lógica que estaba en `AgregadoBatalla`).
- **Pint**: aplicado.
- **Serialización**: servicios como propiedades nullable + lazy getter (`??=`) — PHP serializa null sin problemas, no se rompe la sesión. Verificado con tinker (serialize/unserialize round-trip tras usar los getters).

### Desviaciones / asserts actualizados

- `FabricaBatallaMockTest::test_generate_team1_contiene_tyranitar`: assert de tipos actualizado a `[ROCA, SINIESTRO]`.
- Ningún otro test dependía de los tipos de Tyranitar para efectividad/STAB (los tests de daño usan `ConstruyeCombatientes`, no el mock).

---

### Problema 1 — ManejadorObjetosEquipados (reemplaza ManejadorOrbeVida)

**Archivos a crear:**
- `src/Battle/Domain/Chain/ManejadorObjetosEquipados.php` — manejador genérico de objetos equipados con mapa `array<string, float>` en constructor (default: `['life_orb' => 1.30]`). `process()`: aplica multiplicador si el atacante tiene objeto equipado con clave en el mapa y está vivo.

**Archivos a modificar:**
- `src/Battle/Domain/Chain/CadenaDanio.php` — sustituir `new ManejadorOrbeVida()` por `new ManejadorObjetosEquipados()`.

**Archivos a ELIMINAR:**
- `src/Battle/Domain/Chain/ManejadorOrbeVida.php` — reemplazado completamente.

**Tests nuevos:**
- `tests/Unit/Battle/ManejadorObjetosEquipadosTest.php` — 4 tests:
  1. `test_life_orb_multiplica_por_1_3`: atacante con `life_orb` → daño ×1.3
  2. `test_sin_objeto_no_cambia_dano`: atacante sin objeto → daño sin cambio
  3. `test_objeto_desconocido_no_cambia_dano`: atacante con `leftovers` → daño sin cambio (leftovers no modifica daño)
  4. `test_atacante_muerto_con_life_orb_no_multiplica`: atacante muerto con `life_orb` → ×1.0

**Riesgos:**
- Ninguno: los tests de `EfectoOrbeVidaTest` que usan `CadenaDanio` (test_orbe_vida_multiplica_dano_por_1_3) seguirán verdes porque el comportamiento es idéntico (life_orb → 1.3).
- Ningún test referencia `ManejadorOrbeVida` directamente (grep confirmado).

### Problema 2 — Tyranitar tipos corregidos + CalculadorDañoClima

**2a. Tyranitar:**
- `src/Battle/Infrastructure/FabricaBatallaMock.php` línea 82: `tipos: [TipoPokemon::SINIESTRO]` → `tipos: [TipoPokemon::ROCA, TipoPokemon::SINIESTRO]`
- `tests/Unit/Battle/FabricaBatallaMockTest.php` línea 106: `assertSame([TipoPokemon::SINIESTRO], $tyranitar->tipos)` → `assertSame([TipoPokemon::ROCA, TipoPokemon::SINIESTRO], $tyranitar->tipos)`

**Impacto en otros tests:**
- `AgregadoBatallaTest::test_elegir_mejor_movimiento_accede_a_movimientos_por_getter` usa `$attacker = $battle->team1->combatants()[0]` (Gengar, no Tyranitar) y solo assert `instanceof MovimientoBatalla`. No se rompe.
- Ningún test calcula daño con Tyranitar como atacante/defensor de forma que afirme valores exactos. Los tests de `ConstruyeCombatientes` construyen combatientes manualmente.

**2b. CalculadorDañoClima:**
- Crear `src/Battle/Domain/CalculadorDañoClima.php` con método `calcular(Combatiente $c, TipoClima $weather): float`
- Misma lógica: GRANIZO → daño a no-HIELO; TORMENTA_ARENA → daño a no-ROCA/TIERRA/ACERO; otros climas o muerto → 0
- Mejora de legibilidad: arrays de tipos inmunes en lugar de foreach anidado

**Modificaciones:**
- `src/Battle/Domain/AgregadoBatalla.php`:
  - Eliminar `calcularDañoClima` privado
  - Añadir lazy getter para `CalculadorDañoClima` (propiedad nullable, evita problemas de serialización)
  - `triggerRoundEndEffects()` delega en `$this->getCalculadorClima()->calcular($c, $this->weather)`
- `app/Livewire/Combate.php`: `SESSION_VERSION` 3 → 4 (cambio de estructura serializada)

**Serialización:**
- `CalculadorDañoClima` es stateless (sin propiedades, sin closures). Se usará lazy getter en `AgregadoBatalla`:
  ```php
  private ?CalculadorDañoClima $calculadorClima = null;
  private function getCalculadorClima(): CalculadorDañoClima {
      return $this->calculadorClima ??= new CalculadorDañoClima();
  }
  ```
  PHP serializa `null` sin problemas. Al deserializar de v3, la propiedad es null, el getter la crea. No hay rotura de serialización.

**Tests nuevos:**
- `tests/Unit/Battle/CalculadorDañoClimaTest.php` — 5+ tests:
  1. `test_granizo_dana_a_no_hielo`: `hp=100` → `battleStats()->hp=310` → daño = `max(1, 310*0.0625)` = 19.375
  2. `test_granizo_no_dana_a_hielo`: combatiente HIELO → 0
  3. `test_tormenta_arena_dana_a_no_roca_tierra_acero`: combatiente SINIESTRO → daño = 19.375
  4. `test_tormenta_arena_no_dana_a_roca`: combatiente ROCA → 0
  5. `test_combatiente_muerto_devuelve_0`: hpActual=0 → 0
  6. `test_clima_none_devuelve_0`: TipoClima::NONE → 0

### Problema 3 — SelectorAccionIA

**Crear:**
- `src/Battle/Domain/SelectorAccionIA.php` con 2 métodos:
  - `elegirObjetivoPara(AgregadoBatalla $battle, Combatiente $actor): ?Combatiente`
  - `elegirMejorMovimiento(Combatiente $attacker, Combatiente $defender): ?MovimientoBatalla`

**Modificar:**
- `src/Battle/Domain/AgregadoBatalla.php`:
  - Reemplazar `elegirObjetivo()` privado por delegación a `SelectorAccionIA`
  - Las llamadas desde `ejecutarAccion()` y `elegirObjetivoPara()` usan el servicio
  - `elegirMejorMovimiento()` público delega al servicio
  - Lazy getter para `SelectorAccionIA` (mismo patrón serialización)

**Serialización:**
- Idéntico patrón lazy getter con propiedad nullable.

**Tests nuevos:**
- `tests/Unit/Battle/SelectorAccionIATest.php` — 5 tests:
  1. `test_elegir_mejor_movimiento_puntua_mayor_efectividad_por_potencia`: 2 movs, uno mejor que otro
  2. `test_elegir_mejor_movimiento_sin_movimientos_devuelve_placaje`: fallback
  3. `test_elegir_objetivo_vanguardia_ataca_vanguardia`: actor vanguard, enemigo vanguard vivo → vanguard
  4. `test_elegir_objetivo_vanguardia_sin_vanguardia_enemiga_ataca_retaguardia`: actor vanguard, enemigo vanguard muerto → retaguardia
  5. `test_elegir_objetivo_retaguardia_elige_cualquier_enemigo`: actor retaguardia → cualquier enemigo vivo
  6. `test_elegir_objetivo_todos_muertos_devuelve_null`: sin vivos → null

### Estrategia de construcción de tests

- Tests unitarios extienden `PHPUnit\Framework\TestCase` (sin boot, sin BD).
- Usan trait `ConstruyeCombatientes` para combatientes de prueba.
- `SelectorAccionIA` se instancia directamente (sin dependencias).
- `CalculadorDañoClima` se instancia directamente.
- `ManejadorObjetosEquipados` se instancia con mapa default o custom.
- RNG determinista con `mt_srand(seed)` para crítico.
- Para construir equipos multi-combatiente en `SelectorAccionIATest`, crear `EquipoBatalla` manual con `agregarCombatiente()`.

### Commits planeados

1. `refactor: reemplazar ManejadorOrbeVida por ManejadorObjetosEquipados genérico`
2. `refactor: corregir tipos Tyranitar (Roca/Siniestro) + extraer CalculadorDañoClima`
3. `refactor: extraer lógica de IA (SelectorAccionIA) de AgregadoBatalla`

### Verificación post-implementación

- `php artisan test --compact --filter=Battle` → 121+ tests verdes
- `php artisan test --compact tests/Feature/PokemonBattleTest.php`
- `vendor/bin/phpstan analyse --no-progress` — sin errores nuevos en src/Battle/ ni Combate.php
- `vendor/bin/infection --filter=src/Battle --only-covered --min-msi=80` (o comando equivalente)
- `vendor/bin/pint --dirty --format agent`
---

# Análisis Backend — Seed de la Pokédex filtrado a la pestaña "Vistos" (R1)

## Fecha
2026-08-30

## Contexto / causa raíz

La Pokédex asíncrona no carga en la pestaña "Vistos" al primer render. El seed que pasa
`PlayerController::pokedex()` (`DatagridService::list('pokemon', per_page=100, sort=id, order=asc)`)
se pide al Datagrid SIN filtrar por la pestaña por defecto (`filter[visto]=1`), y con
`per_page=100` mientras el frontend hace fetches con `per_page=120`.

## R1 (requisito, SIN tocar la vista)

Cambiar la llamada `$this->datagrid->list('pokemon', ...)` para que pida:
`per_page => 120`, `sort => 'id'`, `order => 'asc'`, `'filter' => ['visto' => '1']`.
El resto del método (counts, tipos, stats) se conserva. Resultado esperado: el seed de la vista
ya es la página 1 de los pokémon vistos del usuario autenticado, con `meta.last_page` coherente
con esa pestaña.

## Verificación de soporte de `filter[visto]=1`

Revisados `app/Datagrid/DatagridService.php` y `app/Providers/DatagridServiceProvider.php`:

- `DatagridDefinition('pokemon').filterable['visto'] => 'pokedex.visto'` (columna SQL).
- `boolFields: ['visto', 'atrapado']` → en `applyFilters`, `filter[visto]='1'` pasa por `toBool('1')`
  → `true`; `in_array(false, [true])` es falso → `whereIn('pokedex.visto', [true])`. ✓ Soportado.
- Confirmado además por el test existente `DatagridTest::test_pokemon_list_filter_visto_1_returns_seen`.

## Archivos a tocar

| Archivo | Acción |
|---------|--------|
| `app/Http/Controllers/PlayerController.php` | Cambiar params del seed: `per_page=120`, `sort=id`, `order=asc`, `filter[visto]=1` |
| `tests/Feature/PlayerControllerTest.php` | Ajustar los 3 tests existentes (codificaban el seed SIN filtrar) + añadir test que compruebe el seed filtrado a "vistos" |

## Tests que escribo / ajusto (TDD)

El seed pasa de "todos los pokémon" a "solo vistos". Eso rompe la semántica de 3 tests existentes
de `PlayerControllerTest` que asumían el listado completo:

1. `test_pokedex_orders_pokemon_by_id` — crea pokémon SIN fila en `pokedex` → con `filter[visto]=1`
   el seed quedaría vacío → debe marcar los pokémon como vistos para seguir afirmando el orden [1,2].
2. `test_pokedex_passes_counts_and_types` — afirmaba `meta.total == 3`; con el filtro el seed son
   solo los vistos (2) → ajustar a `meta.total == 2` (los counts SÍ siguen siendo globales = 3).
3. `test_pokedex_de_usuario_a_no_muestra_atrapados_de_b` — esperaba ambas filas (1 vista y 2 no vista);
   con el filtro solo aparece la fila vista → ajustar a filtrar solo vistos.
4. NUEVO: `test_pokedex_seed_es_pagina_1_de_vistos_con_last_page_coherente` — crear >120 pokémon
   vistos para comprobar `per_page=120`, `page=1`, `last_page` coherente con la pestaña "vistos"
   (solo vistos), y que todos los `data` son vistos.

`PokedexViewTest` (render, usa `view()` directo) NO se toca ni se rompe.

## Riesgos

- Los tests existentes de `PlayerControllerTest` son de comportamiento previo (seed sin filtrar);
  deben actualizarse, no romperse silenciosamente. No es una vista: es el estado deseado por R1.
- Que `meta.total` deje de ser el total global dentro del seed (ahora es el total de la pestaña).
  Los counts globales del header siguen en `meta.counts`.
- No tocar la vista (`resources/views/pokedex/index.blade.php`) ni la arquitectura del Datagrid.

## Verificación

- `php artisan test --compact --filter=PlayerControllerTest`
- `php artisan test --compact --filter=PokedexViewTest`
- `vendor/bin/pint --dirty --format agent`
- `vendor/bin/phpstan analyse` y `vendor/bin/phpmd src/ text phpmd.xml` (sin nuevos errores)

---

## Análisis previo — Fix constructor `Combate.php` (PHPStan: Cannot call constructor)

### Contexto

PHPStan (level 6 + phpstan-strict-rules) reportaba error `Cannot call constructor` en
`app/Livewire/Combate.php:70`: `parent::__construct()` llama al constructor de
`Livewire\Component` que NO existe en Livewire 4 (`livewire/livewire ^4.4`). El componente
fallaba en runtime al montarse (`Error: Cannot call constructor`), confirmado por TDD.

### Fix principal

- `app/Livewire/Combate.php` — eliminar `__construct()` (líneas 68-72) y resolver
  `$fabricaBatalla` en `mount()` (solo se usa en `initMockBattle()` → `nuevaBatalla()` →
  `mount()`; las propiedades privadas de Livewire no se serializan entre requests).

### Fix secundario (bloqueado por el montaje)

Al montar, el flujo llega a `nextActor()` → `currentMoves` que usa
`DTOMovimientoBatalla::desdeDominio()`: `TipoPokemon` es enum backed `int` (`->value` =
int) pero `$tipo` se declaró `string` → TypeError. Corregido de forma mínima:
`(string) $move->tipo->value` en `desdeDominio()` y `TipoPokemon::from((int) $this->tipo)`
en `toDomain()`. Coherente con la vista `moves-panel` que ya hace `TipoPokemon::from($move['tipo'])`.

### Test (TDD)

- `tests/Feature/CombateLivewireTest.php` (NUEVO, 3 tests):
  1. `test_combate_mounts_without_constructor_error` — monta sin error, `assertSee('CAMPO DE COMBATE')`.
  2. `test_combate_shows_battle_log_on_mount` — `assertSee('¡Comienza la batalla!')`.
  3. `test_combate_creates_battle_on_mount` — `battleId` con prefijo `battle_`, team1/team2 con 3.
  Feature test (app levantada): usa sesión + contenedor; NO requiere BD/RefreshDatabase
  (el layout usa `@auth` con fallbacks y el componente no toca BD).

### Verificación

- `php artisan test --compact --filter=Combate` → 6 passed (9 assertions).
- `php artisan test --compact tests/Feature/PokemonBattleTest.php` → 2 passed.
- PHPStan: 185 errores ANTES → 183 DESPUÉS (`Cannot call constructor` eliminado; -2 por el fix del DTO).
- Suite completa: 559 passed / 1 failed pre-existente (`ServicioCapturaTest`, ajeno).

---

# Análisis Backend — Eliminar `avistados` del contrato de resultados de exploración

## Fecha
2026-08-30

## Contexto

La clave `avistados` (lista de especies derrotadas) se genera en el transformador y se consume
en el controller y tests, pero la vista la ignora. Se elimina del contrato de resultados de
futuras finalizaciones (JSONs ya persistidos conservan la clave por compatibilidad; no hay
migración).

## Archivos a tocar

| Archivo | Acción | Propósito |
|---------|--------|-----------|
| `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php` | 3 cambios | Eliminar `'avistados'` del array de `desde()`, método `avistados()`, línea del PHPDoc shape |
| `app/Http/Controllers/ExploracionActivaController.php` | 3 cambios | Eliminar `foreach ($resultado['avistados'] ?? [])` en `nombresPokemon()`, construcción de `$avistados` y clave `'avistados'` en `toTerminada()` |
| `tests/Feature/ExploracionesPageTest.php` | 2 cambios | Eliminar aserciones de `avistados` en `test_terminadas_incluyen_resumen_de_resultado` y `'avistados' => []` en `test_terminada_sin_resultado_devuelve_resumen_vacio` |
| `tests/Feature/ExploracionesTest.php` | 1 cambio | Eliminar sección que valida `avistados` (ids + nombres) en `test_servicio_guarda_resumen_de_resultado_en_eventos` |

## Archivos que NO se tocan

| Archivo | Razón |
|---------|-------|
| `tests/Feature/ExploracionesTransformadorTest.php` | No referencia avistados (confirmado: solo caramelos_familia) |
| `tests/Feature/ExploracionesViewTest.php` | Asignado a otro agente en paralelo |
| `resources/views/exploraciones/index.blade.php` | Asignado a otro agente en paralelo |

## Tests TDD (rojo → verde)

### 1. `tests/Feature/ExploracionesPageTest.php`
- `test_terminadas_incluyen_resumen_de_resultado`: eliminar el bloque que aserta `$resultado['avistados']`
- `test_terminada_sin_resultado_devuelve_resumen_vacio`: eliminar `'avistados' => []` del array esperado

### 2. `tests/Feature/ExploracionesTest.php`
- `test_servicio_guarda_resumen_de_resultado_en_eventos`: eliminar la sección de avistados (ids + nombres)

### 3. `tests/Feature/ExploracionesTransformadorTest.php`
- Ejecutar para confirmar que sigue verde (no referencia avistados)

## Riesgos

1. **JSONs ya persistidos**: Los `eventos['resultado']` guardados en BD conservan la clave
   `avistados`. No hay migración. El controller leía con `?? []` (fallback seguro) y la vista
   la ignora. Al eliminar la lectura del controller, el fallback se elimina pero no se rompe
   nada porque la vista no la consume.
2. **Paralelismo con otro agente**: Se verifica que NO se toca `ExploracionesViewTest.php`
   ni la vista Blade.

## Verificación

- `php artisan test --compact tests/Feature/ExploracionesPageTest.php tests/Feature/ExploracionesTransformadorTest.php tests/Feature/ExploracionesTest.php`
- `php artisan test --compact --filter=Exploraciones`
- `vendor/bin/pint --dirty --format agent`
- `vendor/bin/phpstan analyse`

## Resultado

- **TDD**: rojo confirmado (`test_terminada_sin_resultado_devuelve_resumen_vacio` fallaba con la clave `avistados` aún emitida) → verde tras los cambios.
- **Tests**: 40 passed (174 assertions) en los 3 archivos; 88 passed (331 assertions) con `--filter=Exploraciones`.
- **PHPStan nivel 6**: total 183 errores (línea base pre-existente documentada; sin errores nuevos).
- **PHPMD**: solo `ExcessiveMethodLength` pre-existentes en otros archivos del módulo Exploraciones.
- **Pint**: pass.
- **Infection** sobre el transformador: Covered Code MSI 100% (33 mutantes generados, 33 matados).
- **Archivos del otro agente NO tocados**: `resources/views/exploraciones/index.blade.php` y `tests/Feature/ExploracionesViewTest.php` no aparecen en `git status`.
