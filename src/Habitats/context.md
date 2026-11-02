# Contexto del módulo `Habitats`

> Fuente de conocimiento del módulo. Si cambias una regla de producto, un contrato o una decisión
> de diseño, actualiza este archivo en el mismo cambio. Las búsquedas guiadas están al final.

## Propósito

Provincias, hábitats y familias evolutivas: resuelve la relación pokémon ↔ hábitat, agrupa familias
por `evolution_chain_id`, calcula niveles por etapa y alimenta las vistas de administración
(«Gestión») y los modos de combate/exploración.

## Estructura y responsabilidades

| Ruta | Responsabilidad |
|---|---|
| `Domain/HabitatEntity.php`, `ProvinceEntity.php` | Entidades de hábitat y provincia. |
| `Domain/HabitatsCollection.php`, `ProvinciasCollection.php` | Colecciones tipadas. |
| `Domain/ResolvedorCadenasEvolutivas.php` | BFS de la cadena, etapas, orden por `species_id`, reparto base/evoluciones. |
| `Domain/Collections/` | `MiembrosCadenaCollection`, `PokemonCadenaCollection`, `EvolucionCadenaCollection`, `PokemonBasicoCollection`. |
| `Domain/DataTransferObjects/` | `MiembroCadena`, `PokemonCadenaBase`, `EvolucionCadena`, `RepartoFamilia`, `PokemonBasico`. |
| `Domain/Repositories/HabitatLecturaRepositoryInterface.php` | Contrato de lectura (provincias, detalle, familias). |
| `Domain/Repositories/HabitatGestionRepositoryInterface.php` | Contrato de gestión (asignar, quitar, mover). |
| `App/` | Casos de uso: listar/asignar familias, mover pokémon de nivel, validar exploración. |
| `App/Collections/ExploracionActivaCollection.php` | Exploraciones activas de un hábitat. |
| `Infra/HabitatLecturaRepository.php` | Lectura Eloquent (sin mutaciones). |
| `Infra/HabitatGestionRepository.php` | Gestión Eloquent (asignar/quitar/mover). |
| `Infra/Concerns/CadenasFamiliaHabitatsTrait.php` | Consultas compartidas de familias (BFS + mapeo). |
| `Presentation/` | DTOs de familia/tipo/pokémon para la vista. |

## Decisiones de producto y funcionamiento

1. **Familia evolutiva = `pokemon.evolution_chain_id`** (columna entera, sin FK ni tabla desde el
   bug 23503). No existe la tabla `evolution_chains`.
2. **«Primer integrante» = miembro con menor `species_id`** (desempate `id`). Es la base de display
   de la familia y el representante para caramelos/huevos. No es necesariamente el bebé/base
   evolutivo: en Happiny(440)/Chansey(113)/Blissey(242), la base de display es Chansey(113).
3. **Etapas por BFS evolutivo**: base 1, hijos directos 2, resto 3. **Nivel por etapa =
   `min(stage, 3)`**; una familia de una sola etapa → nivel 2.
4. **Familias ramificadas** (Eevee): todas las evoluciones reciben el mismo nivel real; la base
   queda en nivel 1.
5. **Reparto de niveles en la UI**: el GET de familias y el POST de asignación devuelven niveles
   reales calculados en backend; el frontend no infiere niveles.
6. **DELETE de familia** quita TODA la familia del hábitat (decisión de producto); la X del modal
   solo aparece en la tarjeta base.
7. **PATCH de pokémon** mueve UN pokémon de nivel (`level` 1|2|3), no toda la familia.
8. **`types[]` aditivo**: la unión deduplicada de tipos de todos los miembros, ordenada por id.
9. **Anti-IDOR / exploración**: `ValidadorExploracion` bloquea asignar/quitar/mover si hay
   exploraciones activas; el repositorio aplica scope de usuario donde corresponde.
10. **La familia puede estar en varios hábitats**; el módulo no impone unicidad global. Esa
    decisión afecta a Granjas (`habitatDeFamilia`) y debe resolverse allí.

## Contratos y puntos de entrada

Rutas (`routes/habitats.php` y `routes/web.php`):

```text
GET    /api/habitats/{id}/families
POST   /api/habitats/{id}/families
DELETE /api/habitats/{id}/families/{chainId}
PATCH  /api/habitats/{habitat}/pokemon/{pokemon}
GET    /api/habitats/unassigned-families
```

- `HabitatRepositoryInterface`: `allProvinciasWithHabitats()`, `getPokemonsByHabitat()` →
  `PokemonBasicoCollection`, `getHabitatDetail()`, `getFamiliesByHabitat()`,
  `getUnassignedFamilies()`, `assignFamily()`, `removeFamily()`, `movePokemonToLevel()`.
- `ObtenerPokemonsPorHabitat::handle()` → `PokemonBasicoCollection`.
- `ValidadorExploracion::exploracionesActivas()` → `ExploracionActivaCollection`.
- `ResolvedorCadenasEvolutivas::getFamilyMembersByChain(...)` → `MiembrosCadenaCollection`.
- Tablas: `provinces`, `habitats`, `pokemon_habitat`, `pokemon`.
- UI: modal Alpine `habitatShow()` en `resources/views/habitats/show.blade.php`; sin refresco
  pesado, mutaciones locales tras 200 OK.

## Dependencias

- `Src\Shared\Domain\NivelHelper`, `Src\Shared\Tipos\TipoPokemon`, `TiposCollection`.
- `App\Models\Habitat`, `Province`, `Pokemon`, `PokemonType`.
- `Src\Exploraciones\Domain\ValueObjects\PoolHabitat` (consumo del pool).
- `Src\Granjas\App\FamiliaEnHabitat` (validación de familia en hábitat).

## Deuda y mejoras priorizadas (auditoría 2026-10-31)

- **[Resuelto 2026-10-31] `HabitatRepository` dividido** en `HabitatLecturaRepository` (5 lecturas)
  y `HabitatGestionRepository` (3 mutaciones), cada uno con su interfaz en `Domain/Repositories/`;
  las consultas compartidas de familias viven en `Infra/Concerns/CadenasFamiliaHabitatsTrait.php`.
- **[Alta] `ResolvedorCadenasEvolutivas` y `App\Support\CadenasEvolutivas` resuelven familias con
  criterios relacionados pero distintos** (BFS/etapas vs mapa Eloquent por cadena). Documentar la
  frontera y evitar que un módulo reimplemente el BFS.
- **[Media] `Presentation/` sigue siendo una capa de DTOs de vista**; la convención canónica los
  ubica en `Domain/DataTransferObjects`. Migrar por módulo (strangler) y evitar nuevos arrays en
  `Presentation`.
- **[Media] `resources/views/habitats/show.blade.php` contiene `@php` y cálculos de UI**
  (bloqueos, conteos, porcentajes). Precalcular en `ObtenerHabitatDetalle`/presentador; la vista
  solo debe presentar.
- **[Media] `getFamiliesByHabitat()`/`getUnassignedFamilies()` devuelven estructuras anidadas**
  (base/evolutions/types). Mantener los DTOs (`DTOFamiliaDisponible`, `DTOFamiliaSinHabitat`) como
  contrato y no añadir arrays ad hoc.
- **`levelForStage()` ya vive en `ResolvedorCadenasEvolutivas`** (dominio puro); los repositorios
  solo lo invocan.

## Guía de búsqueda (grep)

- Clases: `HabitatEntity`, `ProvinceEntity`, `HabitatLecturaRepositoryInterface`,
  `HabitatGestionRepositoryInterface`, `HabitatLecturaRepository`, `HabitatGestionRepository`,
  `CadenasFamiliaHabitatsTrait`,
  `ResolvedorCadenasEvolutivas`, `ObtenerHabitatDetalle`, `ObtenerFamiliasDisponibles`,
  `ObtenerFamiliasSinHabitat`, `AsignarFamiliaAHabitat`, `EliminarFamiliaDeHabitat`,
  `MoverPokemonDeNivel`, `ValidadorExploracion`, `MiembroCadena`, `RepartoFamilia`.
- Términos: `evolution_chain_id`, `species_id`, `primer integrante`, `familia`, `nivel`,
  `unassigned-families`, `levelForStage`, `BFS`.
- Rutas/términos: `/api/habitats/{id}/families`, `habitatShow`, `habitats/show`.
- Tablas: `provinces`, `habitats`, `pokemon_habitat`, `pokemon`.
- Tests: `tests/Unit/Shared/FaseEvolutivaTest.php`, `tests/Feature/Habitats/`,
  `tests/Feature/ValidadorExploracionTest.php`.
