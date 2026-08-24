# Revisión post-refactor: Traducción módulo batalla

**Fecha**: 2026-06-04
**Commit refactorizado**: `1c664e1` (Traducciones modulo batalla)
**Revisores**: 3 agentes backend en paralelo

---

## Resumen ejecutivo

Se revisaron ~60 archivos en `src/Battle/`, `app/`, `tests/` y `routes/`. 
Resultado: **20 hallazgos** — 6 bugs bloqueantes de runtime, 3 violaciones críticas de Clean Architecture, 2 problemas graves de traducción, y 9 de calidad de código.

---

## 🔴 BLOQUEANTES — Bugs de runtime (6)

Se encapsuló `PokemonEntity::$moves` como `private` y `$tiposCollection` como `private`, pero **6 lugares acceden como propiedad en vez de como método**:

| # | Archivo | Línea | Código erróneo | Debe ser |
|---|---------|-------|----------------|----------|
| 1 | `AgregadoBatalla.php` | 306 | `$attacker->pokemon()->moves->isEmpty()` | `->moves()->isEmpty()` |
| 2 | `AgregadoBatalla.php` | 313 | `$attacker->pokemon()->moves as $move` | `->moves() as $move` |
| 3 | `Combate.php` | 228 | `$actor->pokemon()->moves->all()` | `->moves()->all()` |
| 4 | `Combate.php` | 427 | `$actor->pokemon()->moves as $move` | `->moves() as $move` |
| 5 | `Combate.php` | 476 | `$actor->pokemon()->moves->get($index)` | `->moves()->get($index)` |
| 6 | `Combate.php` | 533 | `$actor->pokemon()->tiposCollection as $tipo` | `->tiposCollection() as $tipo` |

**Impacto**: Error `Cannot access private property PokemonEntity::$moves` / `$tiposCollection` en tiempo de ejecución.
**Urgencia**: INMEDIATA — la batalla no funciona.

---

## 🔴 GRAVES — Traducción incompleta (2)

| # | Problema | Archivo | Detalle |
|---|----------|---------|---------|
| 7 | Clase antigua `BattleAggregate.php` no eliminada | `src/Battle/Domain/BattleAggregate.php` | Marcada `@deprecated` pero aún existe junto a `AgregadoBatalla.php`. Duplica lógica. |
| 8 | Test usa clase deprecada | `tests/Feature/PokemonBattleTest.php:7,18,23` | Usa `BattleAggregate` en vez de `AgregadoBatalla`. Se romperá al eliminar la clase vieja. |

---

## 🔴 CRÍTICAS — Clean Architecture (3)

| # | Archivo | Violación | Líneas |
|---|---------|-----------|--------|
| 9 | `src/Habitats/Infra/HabitatRepository.php` | `use App\Models\Habitat`, `use App\Models\Province` + Eloquent queries | ~100 |
| 10 | `src/Equipos/Infra/EloquentTeamRepository.php` | `use App\Models\Team` + Eloquent queries | ~50 |
| 11 | `src/Reclutamiento/Infra/EloquentReclutamientoRepository.php` | `use App\Models\Reclutado` + Eloquent queries | ~66 |

**Solución**: Mover los 3 repositorios de `src/X/Infra/` a `app/Repositories/`. Las interfaces permanecen en `src/X/Domain/`.

---

## 🟡 IMPORTANTES — Calidad de código (9)

### Primitive Obsession

| # | Problema | Detalle |
|---|----------|---------|
| 12 | `NombreStat` enum creado pero NO usado | 29 ocurrencias de strings mágicos (`'attack'`, `'speed'`, etc.) en `Combatiente.php`, `ManejadorDanioBase.php`, `GestorTurnos.php`, `FabricaBatallaMock.php`, `EtapasStats.php` |
| 13 | `BattleStats` con 8 props públicas mutables | `src/Pokemon/Domain/Stats/BattleStats.php` — deberían ser `private readonly` con getters |

### Código duplicado

| # | Problema | Detalle |
|---|----------|---------|
| 14 | `EfectoInvocadorTormentaArena` duplicado | Idéntico a `EfectoInvocadorClima` salvo el mensaje de log. Podría unificarse. |

### DTOs y fronteras

| # | Problema | Detalle |
|---|----------|---------|
| 15 | `DTOAccionBatalla::$move` como `array` genérico | Sin contrato de tipos en la frontera Livewire ↔ Dominio |

### Consistencia de naming

| # | Problema | Detalle |
|---|----------|---------|
| 16 | Métodos ingleses sin traducir | `allCombatants()`, `findCombatant()`, `findCombatantById()`, `fromPosition` rompen consistencia |

### Código muerto

| # | Archivo | Razón |
|---|---------|-------|
| 17 | `src/Pokemon/Domain/Movement/MovementEntity.php` | Clase vacía, no referenciada |
| 18 | `src/Pokemon/Domain/Movement/MovementCollection.php` | Solo referenciado por `MovementFactory` (no usado) |
| 19 | `src/Pokemon/Domain/Movement/MovementFactory.php` | No es llamado por nadie |
| 20 | `src/Battle/Domain/BattleAggregate.php` | `@deprecated`, reemplazado por `AgregadoBatalla.php` |

---

## ✅ COSAS QUE ESTÁN BIEN

- **`declare(strict_types=1)` al 100%** en `src/Battle/` (45/45 archivos)
- **Namespaces correctos** en todos los archivos traducidos
- **Use statements internas actualizadas** — sin referencias a clases inglesas antiguas dentro de `src/Battle/`
- **Módulo `src/Battle/` 100% libre de dependencias Laravel** — cero violaciones de Clean Architecture en el dominio de batalla
- **Dirección de dependencias `app/ → src/` correcta** en los 28 imports verificados
- **DTOs creados y funcionando** (`DTOAccionBatalla`, `DTOMovimientoBatalla`, `DTOEquipoBatalla`)
- **4 archivos antiguos correctamente eliminados** (`battle.txt`, `TurnBattleAggregate.php`, `EndTurnHealObserver.php`, clases inglesas antiguas)

---

## 📋 PRIORIZACIÓN PARA ACCIÓN

| Prioridad | Ítems | Oleada | Acción |
|-----------|-------|--------|--------|
| 🔥 **AHORA** | 1-6: Bugs runtime (→moves, →tiposCollection) | — | Corregir acceso a propiedades privadas |
| 🔥 **AHORA** | 7-8: BattleAggregate + test desactualizado | Oleada 5 | Eliminar clase deprecada o migrar test |
| 🔴 **Oleada 4** | 9-11: Repositorios en src/ violan CA | Oleada 4 | Mover a `app/Repositories/` |
| 🟡 **Oleada 1** | 12: NombreStat enum sin usar | Oleada 1 | Reemplazar strings mágicos por enum |
| 🟡 **Oleada 3** | 13: BattleStats props públicas | Oleada 3 | Encapsular con private readonly + getters |
| 🟡 **Oleada 4** | 14: Efecto duplicado | Oleada 5 | Unificar o eliminar |
| 🟡 **Oleada 3** | 15: DTOAccionBatalla::$move array genérico | Oleada 3 | Tipar con clase concreta |
| 🟡 **Oleada 4** | 16: Métodos sin traducir | Oleada 4 | Renombrar para consistencia |
| 🟢 **Oleada 5** | 17-20: Código muerto | Oleada 5 | Eliminar archivos no usados |

---

## Archivos tocados por los bugs bloqueantes

- `src/Battle/Domain/AgregadoBatalla.php` (líneas 306, 313)
- `app/Livewire/Combate.php` (líneas 228, 427, 476, 533)

Estos 2 archivos concentran los 6 bugs que rompen la batalla en tiempo real.
