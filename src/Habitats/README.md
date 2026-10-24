# Habitats

Provincias, hábitats y relación con pokémon/familias evolutivas. Resuelve cadenas
evolutivas y satisface las vistas de administración y de combate.

## Mapa de carpetas

| Ruta | Contenido |
|---|---|
| `Domain/HabitatEntity.php`, `ProvinceEntity.php` | Entidades de hábitat y provincia |
| `Domain/HabitatsCollection.php`, `ProvinciasCollection.php` | Colecciones tipadas |
| `Domain/ResolvedorCadenasEvolutivas.php` | BFS de la cadena, etapas y reparto base/evoluciones |
| `Domain/Collections/` | `MiembrosCadenaCollection`, `PokemonCadenaCollection`, `EvolucionCadenaCollection`, `PokemonBasicoCollection` |
| `Domain/DataTransferObjects/` | `MiembroCadena`, `PokemonCadenaBase`, `EvolucionCadena`, `RepartoFamilia`, `PokemonBasico` |
| `Domain/Repositories/HabitatRepositoryInterface.php` | Contrato de lectura/gestión de hábitats |
| `App/` | Casos de uso: listar/assignar familias, mover pokémon de nivel, validar exploración |
| `App/Collections/ExploracionActivaCollection.php` | Exploraciones activas de un hábitat |
| `Infra/HabitatRepository.php` | Implementación Eloquent |
| `Presentation/` | DTOs de familia/tipo/pokémon para la vista |

## Puntos de entrada

- `HabitatRepositoryInterface`: `allProvinciasWithHabitats()`,
  `getPokemonsByHabitat()` → `PokemonBasicoCollection`,
  `getHabitatDetail()`, `getFamiliesByHabitat()`, `getUnassignedFamilies()`,
  `assignFamily()`, `removeFamily()`, `movePokemonToLevel()`.
- `ObtenerPokemonsPorHabitat::handle()` → `PokemonBasicoCollection`.
- `ValidadorExploracion::exploracionesActivas()` → `ExploracionActivaCollection`.
- `ResolvedorCadenasEvolutivas::getFamilyMembersByChain(PokemonCadenaCollection, EvolucionCadenaCollection)`
  → `MiembrosCadenaCollection`.

## Reglas

- La base de la familia es el miembro con menor `species_id` (desempate `id`).
- Etapas: base 1, hijos directos 2, resto 3 (BFS).
- Nivel por etapa: `min(stage, 3)`; cadenas de 1 etapa → nivel 2.
- El repositorio carga datos; el dominio solo calcula (sin Eloquent).

## Tests

`tests/Unit/Shared/FaseEvolutivaTest.php` (BFS/etapas),
`tests/Feature/Habitats/` (familias, asignación, endpoints) y
`tests/Feature/ValidadorExploracionTest.php`.
