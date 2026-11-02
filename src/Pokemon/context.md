# Contexto del módulo `Pokemon`

> Fuente de conocimiento del módulo. Si cambias una regla de producto, un contrato o una decisión
> de diseño, actualiza este archivo en el mismo cambio. Las búsquedas guiadas están al final.

## Propósito

Entidad de dominio del pokémon y cálculo de estadísticas de combate (stats, IVs, EVs, CP). Es la
base compartida por Battle, Exploraciones, Huevos, Granjas y Reclutamiento.

## Estructura y responsabilidades

| Ruta | Responsabilidad |
|---|---|
| `Domain/PokemonEntity.php` | Entidad: stats base, EVs, IVs, movimientos, tipos, objeto, habilidad, peso. |
| `Domain/Stats/BattleStats.php` | Stats efectivos a un nivel, con IV/EV aplicados. |
| `Domain/Stats/StatsValue.php` | Value Object de los 6 stats (base/EV/IV). |
| `Domain/Stats/DatosStats.php` | Stats base tipados (sustituye el array previo). |
| `Domain/Stats/DatosStatsCollection.php` | Colección tipada de `DatosStats`. |
| `Domain/Stats/GeneradorIvs.php` | IVs aleatorios, máximos y por nivel de nido. |
| `Domain/Stats/RangoIvs.php` | Rango `{min, max}` para IVs de huevo. |
| `Domain/Stats/CalculadorCP.php` | Cálculo de Combat Power. |
| `Domain/Evolution/PokemonFamilia.php` | Nodo mínimo de la familia (`id`, `nombre`, `speciesId`). |
| `Domain/Evolution/AristaEvolutiva.php` | Arista entre especies (`desdeSpeciesId`, `haciaSpeciesId`, `nivelMinimo`). |
| `Domain/Evolution/MiembroFamiliaEvolutiva.php` | Miembro resuelto: `stage`, `parentId`, `nivelMinimo`, `branchIndex`. |
| `Domain/Evolution/FamiliaEvolutiva.php` | Colección tipada ordenada; `miembros()`, `stageDe()`, `porId()`. |
| `Domain/Evolution/ResolvedorFamiliaEvolutiva.php` | Resuelve la familia por componentes conexas; multi-raíz o modo compatibilidad. |

## Decisiones de producto y funcionamiento

1. **Stats efectivos** (`BattleStats`):
   - HP: `floor(((2×base + IV×5 + floor(EV/4)) × nivel / 100) + nivel + 10)`.
   - Resto: `floor(((2×base + IV×5 + floor(EV/4)) × nivel / 100) + 5)`.
   - `BattleStats::IV = 5`, `BattleStats::EV = 4`.
2. **IVs 0–15**: `GeneradorIvs::aleatorios()` (0–15), `maximos()` (15) y `huevo($nidoNivel)`
   (rango por nivel de nido). Los IVs se guardan en `reclutados.ivs` (VO `StatsReclutado`).
3. **EVs**: 0–512 por stat en el modelo de juego; `BattleStats` divide por 4. Los EVs se guardan en
   `reclutados.evs`; las granjas los aplican por familia.
4. **CP** (`CalculadorCP`):
   `floor((sumaStats × nivel × 6 / 100) + (EV × ((nivel / 4) / 100 + 2)))`, nunca negativo.
   El término de stats ya incorpora IV/EV vía `BattleStats`; el segundo término es el índice de
   esfuerzo agregado.
   **Bug corregido 2026-10-31**: el código usaba factor 5 y tenía el término EV comentado; ahora
   coincide con la fórmula documentada y con `CalculadorCPTest`/`ReclutadoSerializerCPTest`.
5. **Rango de IVs de huevo** (`GeneradorIvs::rangoHuevo`): nivel 0 → 0–3; 1 → 4–7; 2 → 8–9;
   3 → 10–11; 4 → 12–14; 5 → 15.
6. **El CP del reclutado se calcula al nivel del usuario** en `ReclutadoSerializer::cpDe()`; el
   campo `nivel` del payload es actualmente el nivel de usuario si se pasa, y el nivel del
   reclutado si no. **Contrato ambiguo** detectado por `EquiposPayloadTest` (espera nivel del
   reclutado). Decidir y documentar antes de cambiar.
7. **`PokemonEntity`** envuelve los movimientos en `ColeccionMovimientos` y los tipos en
   `TiposCollection`; no expone arrays como contrato.
8. **Familia evolutiva** (`ResolvedorFamiliaEvolutiva`): recibe nodos y aristas ya cargados y
   devuelve `FamiliaEvolutiva`, sin Eloquent ni framework. Construye componentes conexas
   (aristas sin dirección), elige como raíz el nodo sin arista entrante interna —o el de menor
   `speciesId` si la componente es un ciclo— y recorre en anchura con `visited`. `stage` empieza
   en 1 en la raíz; `parentId` es el `pokemon.id` del padre; `minimum_level` procede de la arista;
   `branchIndex` es la posición entre hermanos ordenados por nivel mínimo, `speciesId` e `id`.
   Ignora aristas fuera del conjunto y bucles, deduplica por `id`/`speciesId` y ordena la salida
   por `stage`, `parentId`, `branchIndex`, `speciesId` e `id`.
9. **Familia completa y ramas:** el resolvedor procesa todas las componentes presentes en los
   nodos cargados (`multiRaiz = true` por defecto), por lo que incluye pre-evoluciones,
   evoluciones y todas las ramas. Las formas regionales se enlazan por `species_id` aunque tengan
   otro `evolution_chain_id`; `App\Support\FamiliaPokemon` amplía el cierre de especies alcanzadas
   y completa los nodos faltantes en una consulta condicional (2–3 consultas en total: cadena,
   aristas y completado).
10. **Modo compatibilidad de Hábitats:** `Src\Habitats\Domain\ResolvedorCadenasEvolutivas` delega
    en `ResolvedorFamiliaEvolutiva` con `multiRaiz = false` y `maxEtapas = 3` para reproducir el
    BFS histórico de una sola base y tres etapas. Su API y sus DTO no cambian; los miembros no
    alcanzados conservan el fallback de etapa 3.

## Contratos y puntos de entrada

- `StatsValue` → entrada de `BattleStats`, `ClasificadorOfensivaDefensiva`,
  `DistribuidorStatsValor`, `MultiplicadoresStats` y mapeadores de batalla.
- `BattleStats` → stats efectivos usados por `Combatiente`.
- `GeneradorIvs` → reclutamiento, huevos, granjas y rivales.
- `CalculadorCP` → `ReclutadoSerializer` y CP de rivales.
- `FamiliaEvolutiva` → salida de `ResolvedorFamiliaEvolutiva`; la consumen
  `App\Support\FamiliaPokemon` (detalle de Pokédex) y `Src\Habitats\Domain\ResolvedorCadenasEvolutivas`.
- `family.members` (contrato del detalle de Pokédex) →
  `{evolution_chain_id, members}`, donde cada miembro es
  `{id, name, species_id, stage, parent_id, minimum_level, branch_index, icon, is_current}`.
  `members` es plano, ordenado por `stage`, `parent_id`, `branch_index`, `species_id` (con `id`
  como desempate); `stage` empieza en 1 en la raíz de su componente, `parent_id` es el id del
  Pokémon padre, `minimum_level` procede de la arista e `is_current` marca el Pokémon consultado.
  Siempre contiene al menos el Pokémon actual; `evolution_chain_id` es `null` si no tiene cadena.
  Acompañan al contrato `type_details` (`list<{id,label,slug,icon}>` por `slot`) y `location`
  (`null` o `{habitat_id,habitat_name,habitat_image,province_id,province_name,url}`).
- Tablas/columnas: `pokemon`, `pokemon_stats`, `pokemon_types`, `pokemon_moves`,
  `pokemon_evolution`, `reclutados.ivs`, `reclutados.evs`.

## Dependencias

- `Src\Shared\Tipos\TiposCollection`, `TipoPokemon`.
- `Src\Battle\Domain\MovimientoBatalla`, `ColeccionMovimientos` (deuda de dirección:
  Pokemon → Battle; ver `docs/context.md` pendiente 12).
- `Src\Shared\Domain\RangoIvs` (concepto distinto: IVs de entrenador `{principal, resto}`).

## Deuda y mejoras priorizadas (auditoría 2026-10-31)

- **[Resuelto 2026-10-31] Decisión de producto: IV ×5.** `BattleStats::IV = 5` es canónico;
  `docs/context.md`, `docs/testing.md` y el test de regresión `BattleStatsIvsTest` están alineados.
  No volver a reintroducir «×4» en documentación.
- **[Media] `RangoIvs` duplicado con semántica distinta**: `Src\Pokemon\Domain\Stats\RangoIvs`
  (`{min,max}` para huevo) y `Src\Shared\Domain\RangoIvs` (`{principal,resto}` para entrenador).
  Renombrar a `RangoIvsHuevo`/`RangoIvsEntrenador` para evitar búsquedas ambiguas.
- **[Media] `BattleStats` tiene propiedades públicas mutables** (`float $hp`, etc.). Evaluar
  convertirlo en VO inmutable o encapsular setters; hoy se construye una vez y se lee.
- **[Media] `PokemonEntity` recibe `MovimientoBatalla[]` en el constructor** aunque internamente
  usa `ColeccionMovimientos`. Aceptar la colección tipada para no crear arrays en la frontera.
- **[Baja] El cast `StatsReclutado` vive en `app/Models/Casts`**; el VO de IV/EV podría vivir en
  `src/Pokemon`/`src/Reclutamiento` y el cast limitarse a serializar.
- **Bug corregido**: `CalculadorCP` (factor 6 + término EV) y `BattleStats` (Pint:
  `const` en minúsculas). Tests: 3 unitarios + 2 feature en verde.

## Guía de búsqueda (grep)

- Clases: `PokemonEntity`, `BattleStats`, `StatsValue`, `DatosStats`, `DatosStatsCollection`,
  `GeneradorIvs`, `RangoIvs`, `CalculadorCP`, `PokemonFamilia`, `AristaEvolutiva`,
  `MiembroFamiliaEvolutiva`, `FamiliaEvolutiva`, `ResolvedorFamiliaEvolutiva`.
- Constantes: `BattleStats::IV`, `BattleStats::EV`, `GeneradorIvs::NIVEL_NIDO_MAX`,
  `CalculadorCP::FACTOR_STATS`, `DIVISOR`, `DIVISOR_NIVEL_EV`, `BASE_EV`.
- Términos: `ivs`, `evs`, `cp`, `stats`, `rangoHuevo`, `maximos`, `aleatorios`, `nivel`,
  `familia`, `multiRaiz`, `maxEtapas`, `branchIndex`, `parentId`.
- Tablas: `pokemon`, `pokemon_stats`, `pokemon_types`, `reclutados.ivs`, `reclutados.evs`.
- Tests: `tests/Unit/Pokemon/` (`CalculadorCPTest`, stats, IVs),
  `tests/Unit/Pokemon/Evolution/ResolvedorFamiliaEvolutivaTest.php`,
  `tests/Feature/ReclutadoSerializerCPTest.php`, tests de integración en Battle.
