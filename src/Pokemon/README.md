# Pokemon

Entidad de dominio del pokémon y cálculo de estadísticas de combate. Es una base
compartida por Battle, Exploraciones y los módulos de combate.

## Mapa de carpetas

| Ruta | Contenido |
|---|---|
| `Domain/PokemonEntity.php` | Entidad de pokémon (id, nombre, tipos, stats, movimientos) |
| `Domain/Stats/BattleStats.php` | Stats calculados a un nivel (HP, ataque, defensas, velocidad) |
| `Domain/Stats/StatsValue.php` | Value Object de los 6 stats (base/EVs/IVs) |
| `Domain/Stats/DatosStats.php` | Stats base tipados (sustituye el array previo) |
| `Domain/Stats/DatosStatsCollection.php` | Colección tipada de `DatosStats` |
| `Domain/Stats/GeneradorIvs.php` | IVs aleatorios y máximos |
| `Domain/Stats/CalculadorCP.php` | Cálculo de CP |

## Puntos de entrada

- `DatosStats` → entrada de `ClasificadorOfensivaDefensiva`, `MultiplicadoresStats`,
  `DistribuidorStatsValor` y mapeadores de batalla.
- `StatsValue` → EVs/IVs y stats base.
- `BattleStats` → stats efectivos usados por `Combatiente`.

## Relaciones

- `Battle` consume `PokemonEntity`/`DatosStats` para crear combatientes.
- `Exploraciones` usa `DatosStats` para capacidad y matchup.
- Los modelos Eloquent viven en `app/Models/Pokemon.php` (no en este módulo).

## Tests

`tests/Unit/Pokemon/` (stats, CP, IVs) y tests de integración en `Battle`.
