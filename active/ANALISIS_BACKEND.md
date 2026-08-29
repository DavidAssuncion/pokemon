# Análisis Backend — POST /api/habitats/{id}/families devuelve la familia completa con niveles reales

## Fecha
2026-08-29

## Contexto verificado

- Problema: al asignar una familia ramificada (ej. Eevee), el frontend reconstruía la familia
  client-side con `buildAssignedFamilyFromUnassigned`, que falla en cadenas ramificadas
  (infiere niveles 2,3,3; el backend asigna 2,2 vía `levelForStage`). El POST actual devuelve
  `DTOFamiliaAsignada` (`habitat_id, evolution_chain_id, assigned_count`), insuficiente.
- Solución acordada (documentada en `active/ANALISIS_FRONTEND_HABITAT_ASSIGNFAMILY.md`): el POST
  devuelve 201 con la familia COMPLETA `{evolution_chain_id, base:{id,name,icon,level},
  evolutions:[{id,name,icon,level}]}` — exactamente el shape de `DTOFamiliaDisponible`, que el
  frontend ya consume en GET `/api/habitats/{id}/families` (pestaña "Ya Asignados") y en el
  Livewire `FamilyModal`.
- `HabitatRepository::buildAvailableFamilyFromChain(int $chainId, array $members)` ya produce
  `DTOFamiliaDisponible` con `level` real por miembro (`levelForStage($member['stage'], $totalStages)`),
  idéntico al `level` que el upsert de `assignFamily` persiste. Es LA fuente correcta de niveles.
- `DTOFamiliaAsignada` solo se referencia desde `HabitatRepository`, `AsignarFamiliaAHabitat`,
  `HabitatRepositoryInterface` (grep verificado). Tras el cambio queda sin referencias → se elimina
  (regla "sin código muerto").
- `app/Livewire/Habitats/FamilyModal.php::assign()` llama a `asignarFamiliaAHabitat->handle(...)`
  e IGNORA el retorno → no se rompe al cambiar el tipo de retorno.
- Tests existentes que assert `assigned_count`/`habitat_id` del POST: 3 (3/2/1 etapas). Se actualizan
  al nuevo shape (se deriva el total de miembros como `1 + count(evolutions)` y se conservan los
  asserts de BD). `test_obtener_familias_sin_habitat_solo_cadenas_vacias` assert `count(3)` → la
  cadena ramificada nueva se crea DENTRO del test nuevo (no en `setUp`) para no alterar ese conteo.

## Decisión de diseño

**Opción 1 (mínima, recomendada)**: `assignFamily` retorna `DTOFamiliaDisponible` reutilizando
`buildAvailableFamilyFromChain`. El controlador sigue haciendo `response()->json($result->toArray(), 201)`
sin cambios. No se añade `family` anidado ni se mantienen claves viejas.

## Qué voy a tocar

| Archivo | Acción | Propósito |
|---|---|---|
| `src/Habitats/Presentation/DTOFamiliaAsignada.php` | Eliminar | Queda sin referencias tras el cambio (código muerto). |
| `src/Habitats/Infra/HabitatRepository.php` | Modificar | `assignFamily(): DTOFamiliaDisponible`; tras upsert+cache, construir y devolver la familia vía `buildAvailableFamilyFromChain` (`?? throw \LogicException` porque la base siempre existe). Se elimina la variable `$assignedCount`. |
| `src/Habitats/Domain/Repositories/HabitatRepositoryInterface.php` | Modificar | Firma `assignFamily(...): DTOFamiliaDisponible` (import nuevo, quitar import de DTOFamiliaAsignada). |
| `src/Habitats/App/AsignarFamiliaAHabitat.php` | Modificar | `handle(...): DTOFamiliaDisponible` (import nuevo, quitar import de DTOFamiliaAsignada). |
| `app/Http/Controllers/HabitatsController.php` | Sin cambios | `$result->toArray()` + 201 ya producen el nuevo shape (verificado, sin PHPDoc que ajustar). |
| `tests/Feature/Habitats/FamiliesTest.php` | Modificar | Actualizar 3 tests POST al nuevo shape + NUEVO test de cadena ramificada (Eevee: base nivel 1, TODAS las evoluciones nivel 2). |

## Tests (TDD: rojo → verde)

1. `test_asignar_familia_3_etapas_inserta_levels_1_2_3` — 201; `evolution_chain_id`,
   `base.{id,level}=1`, `evolutions[0].level=2`, `evolutions[1].level=3`; sin `assigned_count`.
2. `test_asignar_familia_2_etapas_rattata_inserta_levels_1_2` — 201; base id 19 level 1,
   evolutions[0] id 20 level 2.
3. `test_asignar_familia_1_etapa_inserta_level_2` — 201; base id 151 level 2, evolutions vacío.
4. `test_asignar_familia_ramificada_asigna_nivel_2_a_todas_las_evoluciones` — NUEVO: cadena
   Eevee→Vaporeon/Jolteon; 201; base level 1, AMBAS evoluciones level 2 (no 2,3); BD con level 2
   en ambas. La cadena se crea dentro del test (no en setUp) para no romper
   `test_obtener_familias_sin_habitat_solo_cadenas_vacias` (assertCount 3).

## Shape exacto del JSON de respuesta (entregable para frontend)

```json
{
  "evolution_chain_id": 22,
  "base": { "id": 133, "name": "Eevee", "icon": "/images/iconos_webp/133.webp", "level": 1 },
  "evolutions": [
    { "id": 134, "name": "Vaporeon", "icon": "/images/iconos_webp/134.webp", "level": 2 },
    { "id": 135, "name": "Jolteon",  "icon": "/images/iconos_webp/135.webp", "level": 2 }
  ]
}
```
Status 201. Sin `habitat_id` ni `assigned_count` (se deriva: `1 + evolutions.length`).

## Riesgos

- **Contrato de otros endpoints intacto**: GET families, GET unassigned-families, DELETE, PATCH
  no cambian (no toco `buildAvailableFamilyFromChain`, `buildUnassignedFamilyFromChain`,
  `removeFamily`, `movePokemonToLevel`).
- **PHPStan**: `buildAvailableFamilyFromChain` devuelve `?DTOFamiliaDisponible`; tras validar
  `$members` no vacío la base siempre existe, pero para satisfacer el static analysis se usa
  `$family ?? throw new \LogicException(...)`.
- **No tocar vistas/Blade ni Livewire**: el frontend consume el body directo; `FamilyModal`
  ignora el retorno → sin cambios.
- **BD local**: no ejecutar `php artisan test` (sin BD desde host); tests listos para CI
  (RefreshDatabase sqlite :memory:).

## Entorno

- SÍ ejecutar `vendor/bin/pint --dirty --format agent` y `php -l` de cada archivo tocado.
- NO ejecutar `php artisan test`.
---

# Análisis Backend — El "primer integrante" de una familia es el de menor `species_id`

## Fecha
2026-08-29

## Contexto verificado

- Decisión de negocio confirmada: el primer integrante de una familia evolutiva es el de **menor
  `species_id`** (NO el base evolutivo/bebé). Los assets `candy_pokemon/` se generaron así
  (existe `113.webp` Chansey; NO existe `440.webp` Happiny ni `239.webp` Elekid).
- Afecta a: (a) `caramelos_familia` de exploraciones (imagen `/images/candy_pokemon/{id}.webp`),
  (b) Admin Gestión: `base` del DTO de familia + orden de las respuestas GET families /
  unassigned-families por `species_id`.
- Se MANTIENE: reparto de niveles por fase evolutiva (BFS: base evolutiva → stage 1→nivel 1,
  etc.; ramificadas → mismo nivel). `levelForStage`/`totalStages`/BFS intactos.
- Caso clave: Happiny(440)→Chansey(113)→Blissey(242). BFS: base evolutiva 440 (stage 1),
  Chansey stage 2, Blissey stage 3. Con la regla nueva: base DTO = 113 (min species_id),
  evolutions = [242, 440] (en ese orden). Los niveles siguen saliendo del BFS (440 nivel 1,
  113 nivel 2, 242 nivel 3) aunque 440 vaya en `evolutions`.
- Tests existentes revisados: bulbasaur=1/ivysaur=2/venusaur=3 (min species = base evolutiva),
  rattata=19/raticate=20, mew=151, Eevee 133/134/135 → TODOS coinciden con la regla nueva
  (el min species_id es también el base evolutivo). Ninguno asume base por stage con species_id
  no correlacionado → NO requieren adaptación.
- `ExploracionesTest::test_servicio_guarda_resumen_de_resultado_en_eventos` usa asserts parciales
  de `caramelos_familia` (sin assert exacto de claves) → añadir `pokemon_id` no lo rompe.
- `ExploracionesPageTest::test_terminadas_incluyen_resumen_de_resultado` usa `assertSame` exacto
  → hay que añadir `pokemon_id` (fixture) y `stat_slug` (esperado). `test_activas_contiene_bitacora_transformada`
  → assert aditivo de `stat_slug`.

## Qué voy a tocar

| Archivo | Acción | Propósito |
|---|---|---|
| `src/Habitats/Infra/HabitatRepository.php` | Modificar | `getFamilyMembersByChain`: select + `species_id` y sort por species_id asc/id; `splitFamilyMembers`: "primero vs resto"; `getUnassignedFamilies`/`getFamiliesByHabitat`: orden por species_id mínimo de la cadena (helper `sortChainIdsByMinSpeciesId`); PHPDoc shapes. |
| `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php` | Modificar | `nombreBaseDeCadena` → `pokemonBaseDeCadena(): ?Pokemon`; `caramelosFamilia` añade `pokemon_id`; docblocks de `desde()`/`caramelosFamilia()`. |
| `app/Http/Controllers/ExploracionActivaController.php` | Modificar | Const `STAT_SLUGS`; `transformarEvento` rama `caramelo_ev` → `stat_slug`; `toTerminada` → `stat_slug` en caramelos_ev y passthrough `pokemon_id` en caramelos_familia. |
| `tests/Feature/Habitats/FamiliesTest.php` | Modificar | 2 tests nuevos (bebé posterior + orden por species_id mínimo). |
| `tests/Feature/ExploracionesTransformadorTest.php` | Crear | 2 tests del transformador (pokemon_id = 113; sin base → null). |
| `tests/Feature/ExploracionesPageTest.php` | Modificar | Asserts de `stat_slug` y `pokemon_id` (fixtures ajustados). |
| `tests/Feature/ExploracionesTest.php` | Modificar | Assert aditivo `pokemon_id` en `test_servicio_guarda_resumen_de_resultado_en_eventos`. |

No toco: `levelForStage`/`totalStages`/BFS, DTOs (los shapes `base`/`evolutions` no cambian),
interfaz, vistas/Blade.

## Tests (TDD: rojo → verde)

1. `test_familia_con_bebe_posterior_usa_menor_species_id_como_base` — cadena Happiny(440)/
   Chansey(113)/Blissey(242) con `pokemon_evolution` 440→null, 113→440, 242→113 →
   GET `/api/habitats/unassigned-families` → `base.id = 113` y `evolutions = [242, 440]` (orden).
2. `test_familias_sin_asignar_ordenadas_por_species_id_minimo_de_cadena` — 2 cadenas nuevas con
   species_id 10 y 1 → la de species_id 1 aparece antes en la respuesta.
3. `test_caramelos_familia_usa_el_miembro_de_menor_species_id_como_pokemon_id` (transformador):
   `pokemon_id = 113` (no 440) con `nombre = 'chansey'`.
4. `test_caramelos_familia_sin_pokemon_de_la_cadena_devuelve_pokemon_id_null` (transformador).
5. ExploracionesPageTest: `stat_slug = 'atk'` (stat 2) en bitácora; `pokemon_id` + `stat_slug` en
   terminada.
6. ExploracionesTest: `pokemon_id = 1` (bulbasaur, min species) en el resumen real del pipeline.

## Riesgos

- Orden de respuesta nuevo en unassigned-families: los tests existentes no dependen del orden de
  familias (solo `firstWhere` / foreach / assertCount) → sin impacto. La cadena nueva del test 1
  se crea DENTRO del test (no en setUp) para no romper `assertCount(3)`.
- PHPStan: tipar `$minByChain` con docblock `@var array<int, int>`; cast (int) de min.
- phpunit assertSame: el orden de claves del array no importa en la comparación; añadir claves es
  aditivo.
- BD local: NO ejecutar `php artisan test` (sin BD desde host); tests listos para CI
  (RefreshDatabase sqlite :memory:).

## Entorno

- SÍ ejecutar `vendor/bin/pint --dirty --format agent` y `php -l` de cada archivo tocado.
- NO ejecutar `php artisan test`.

---

## Nota de cierre (Bibliotecario)

El refactor `3d4e940` unificó `STAT_NOMBRES` + `STAT_SLUGS` en la const `STATS` de
`ExploracionActivaController` (`[1=>PS/hp, 2=>Ataque/atk, 3=>Defensa/def, 4=>Ataque Especial/atksp,
5=>Defensa Especial/defsp, 6=>Velocidad/spd]`). La tabla "Qué voy a tocar" de la sección anterior
refleja el estado pre-refactor; el resultado final usa `self::STATS[$stat]`. Implementación y
tests commiteados en `3f9c869` + `3d4e940`.

---

# Análisis Backend — Eliminar la tabla `evolution_chains` (bug 23503) [2026-08-29]

## Decisión aprobada

Eliminar la tabla `evolution_chains` y toda su infraestructura (modelo, relación, FK), preservando
EXACTAMENTE el comportamiento observable: fase evolutiva en exploraciones, base de familia (menor
species_id), caramelos de familia y flujo completo de exploración.

## Contexto verificado (grep)

- `evolution_chains` solo tiene `id` + `data` (json NUNCA leído). Única dependencia dura: FK
  `caramelos.evolution_chain_id → evolution_chains.id` (`caramelos_evolution_chain_id_foreign`),
  causa del bug 23503 (cadenas 51 etc. sin fila → error de FK al insertar caramelos).
- La agrupación de familias se hace por la COLUMNA `pokemon.evolution_chain_id` (sin FK):
  `src/Habitats/Infra/HabitatRepository.php` (líneas 132-170, 290, 368-370), `CalculadorRecompensas`,
  `PersistirRecompensas`, `ExploracionActivaController:246` (campo del JSON), `HabitatsController`
  (validación `exists:pokemon,evolution_chain_id`), `PokemonSeeder` (columna). NO se tocan.
- `EvolutionChain::pokemon()` era `hasMany(Pokemon::class)` → FK por convención `evolution_chain_id`
  → MISMA columna. El mapa por columna reproduce exactamente los miembros de cada familia.
- **DESVÍO respecto al brief (riesgo documentado)**: el brief decía "ReclutamientoController solo
  usa la columna → NO lo toques", pero el grep muestra que usa la RELACIÓN en 3 sitios:
  `discard()`/`discardAll()` eager-load `pokemon.evolutionChain.pokemon` (líneas 52, 70) y
  `otorgarCaramelos()` calcula la fase con `$pokemon->evolutionChain->pokemon->where(...)->count()`
  (líneas 93-96). Sin adaptación: fatal `Call to undefined method` + grep final de
  `EvolutionChain` en app/ distinto de 0. SE TOCA con el cambio MÍNIMO (fase por columna + mapa),
  preservando el contrato de los endpoints (tests existentes cubren el comportamiento). BONUS:
  el código actual hace `$chain?->pokemon->where(...)` → Error fatal si la fila no existe
  (relación null); el nuevo código devuelve fase 1 (mismo criterio que el normalizador).

## Qué voy a tocar

| Archivo | Cambio |
|---|---|
| `database/migrations/2026_08_29_000001_drop_foreign_evolution_chain_from_caramelos.php` | NUEVA: up() drop FK; down() recrea FK |
| `database/migrations/2026_08_29_000002_drop_evolution_chains_table.php` | NUEVA: up() drop tabla; down() recrea (id + data json nullable) |
| `app/Models/EvolutionChain.php` | BORRAR (git rm) |
| `app/Models/Pokemon.php` | Quitar `evolutionChain()` + imports EvolutionChain y BelongsTo (queda sin uso) |
| `app/Models/Caramelo.php` | Quitar `evolutionChain()` + import BelongsTo |
| `src/Exploraciones/App/FinalizarExploracionHandler.php` | `cargarPokemonsDerrotados()` sin `evolutionChain.pokemon`; nuevo `cargarMiembrosDeCadenas()` → `array<int, Collection<int, Pokemon>>`; pasar mapa a `normalizar()` y `desde()` |
| `src/Exploraciones/App/NormalizadorPokemonDerrotado.php` | Firma `normalizar(Collection $pokemons, ?array $miembrosPorCadena = null)`; fase por mapa |
| `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php` | Firma `desde(..., ?array $miembrosPorCadena = null)`; `pokemonBaseDeCadena` con mapa + fallback |
| `app/Http/Controllers/ReclutamientoController.php` | DESVÍO documentado: `with('pokemon')`, fase por mapa de columna |
| `tests/Unit/CarameloTest.php` | Ints literales; quitar test de relación |
| `tests/Unit/Exploraciones/NormalizadorPokemonDerrotadoTest.php` | Ints; `with('stats','types')`; mapa en tests de fase; + test cadena sin mapa → fase 1 |
| `tests/Feature/ReclutamientoControllerTest.php` | Ints literales |
| `tests/Feature/ExploracionesPageTest.php` | Ints literales |
| `tests/Feature/ExploracionesTest.php` | Ints; + TEST REGRESIÓN bug 23503 (chain 51 sin tabla) |
| `tests/Feature/ExploracionesTransformadorTest.php` | Adaptar firma (mapa) |
| `tests/Feature/HabitatsControllerTest.php` | Ints literales |
| `tests/Feature/Habitats/FamiliesTest.php` | Ints literales (51/52/53 setUp; 54-58 tests locales) |
| `tests/Feature/MigrationStatusTest.php` | + assert evolution_chains NO existe (test rojo de la migración) |
| `active/ANALISIS_BACKEND.md` | Esta sección |

## Tests a escribir/adaptar

- Unit: `CarameloTest` (sin relación), `NormalizadorPokemonDerrotadoTest` (mapa, fase 1 sin mapa).
- Feature: `ExploracionesTest` → **`test_finalizacion_con_chain_id_sin_tabla_evolution_chains`**
  (regresión bug 23503: chain 51 sin fila → finaliza OK, `caramelos_familia` con chain 51,
  `pokemon_id` = base min species_id, insert en `caramelos` sin error de FK).
- Feature: `ExploracionesTransformadorTest`, `ReclutamientoControllerTest`, `ExploracionesPageTest`,
  `HabitatsControllerTest`, `FamiliesTest` → ids numéricos directos en la columna.
- `MigrationStatusTest` + `test_evolution_chains_table_does_not_exist`.

## Estructura del mapa de miembros (elegida y documentada)

`array<int, Collection<int, Pokemon>>` — keyed por `evolution_chain_id` (int), valor = Collection de
modelos Pokemon ligeros (`id`, `name`, `species_id`, `evolution_chain_id`) con TODOS los miembros de
la cadena (no solo los derrotados). Se construye con
`Pokemon::whereIn('evolution_chain_id', $ids)->get([...])->groupBy('evolution_chain_id')->all()`.
Fase = `$miembros->where('species_id', '<=', $pokemon->species_id)->count()`; sin cadena en el mapa
→ fase 1. Base = min species_id del mapa (fallback: derrotados con ese chainId). Mismo resultado
que la relación `evolutionChain->pokemon` (misma columna, mismo conteo).

## Riesgos para QA

1. **ReclutamientoController tocado** (desvío del brief): comportamiento preservado (fase por
   columna, idéntico al `hasMany` anterior); caso fila inexistente antes = Error fatal, ahora fase 1.
2. Bug latente del transformador: `$deLaCadena?->evolutionChain?->pokemon->sortBy()` con
   `$deLaCadena` null → Error fatal (no null). El nuevo código devuelve `null` (comportamiento
   INTENCIONAL documentado por `test_caramelos_familia_sin_pokemon_de_la_cadena_devuelve_pokemon_id_null`).
3. El `down()` de la migración 000001 recrea la FK asumiendo filas válidas; con el bug 23503
   (caramelos con chain 51 huérfana) el rollback fallaría → documentado, el down es best-effort.
4. Tests NO ejecutables en local (sin BD): van a CI (RefreshDatabase + SQLite :memory:).
5. `evolution_chain_id` de `caramelos` se conserva (columna + unique) — contrato intacto.

## Validación

- `vendor/bin/pint --dirty --format agent` + `php -l` en todo lo tocado.
- `vendor/bin/phpstan analyse` (nivel 6+, sin BD).
- `vendor/bin/phpmd src/ text phpmd.xml`.
- NO ejecutar `php artisan test` (sin BD desde host); tests a CI.
- Grep final: `EvolutionChain` SOLO en docs/ y active/ (0 en src/, app/, tests/);
  `evolutionChain` en código → 0.

---

# Endurecimiento P1 (Hardener) — eliminación de `evolution_chains` (commits `daeadef198` + `c6877c5`)

## Fecha
2026-08-29

## Contexto verificado

- La tabla `evolution_chains` fue eliminada (`daeadef198`) y `fase()` quedó con guard clause
  explícita (`c6877c5`). El mapa de miembros por cadena se construye en
  `FinalizarExploracionHandler::cargarMiembrosDeCadenas` con
  `whereIn('evolution_chain_id', $chainIds)->get(['id','name','species_id','evolution_chain_id'])`.
- `TransformadorResultadoExploracion::pokemonBaseDeCadena` (líneas ~121-135): el fallback
  `$pokemons->first(fn ...)` elige el PRIMER derrotado de la cadena SIN ordenar, mientras el
  docblock dice "menor species_id" → indeterminista (depende del orden de la query).
- `caramelos` conserva columna `evolution_chain_id` + `unique` (migración
  `2026_08_29_000001_drop_foreign_evolution_chain_from_caramelos`); FK eliminada.
- `CalculadorRecompensas::calcularCaramelosFamilia`: `groupBy('evolutionChainId')` →
  `sum('fase')` → UNA `RecompensaFamilia` por cadena. `fase()` = nº de miembros del mapa con
  `species_id <= actual` (sin cadena en mapa → 1).
- Tests de regresión existentes: `test_finalizacion_con_chain_id_sin_tabla_evolution_chains_finaliza_ok`
  (bitácora 2× bulbasaur, fase 1) y `test_caramelos_familia_sin_mapa_fallback_a_los_derrotados_de_la_cadena`
  (orden SQLite rowid → no matan indeterminismo).

## Mejoras a aplicar (3, aprobadas por el Hardener)

### 1. Determinismo del fallback en `pokemonBaseDeCadena`
- **Archivo**: `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php`.
- **Cambio**: `sortBy('species_id')` ANTES del `first(fn ...)` → el primer coincidente de la
  colección ordenada es el de menor species_id (sortBy estable). Docblock actualizado.
- **Tests**: NUEVO unit en `ExploracionesTransformadorTest` con colección construida a mano
  (charmander sp4 primero, bulbasaur sp1 después) → RED con el código actual (devolvería
  charmander), GREEN tras el fix.

### 2. Test de flujo completo con fase > 1 determinista (mata M1/M2 del handler)
- **Archivo**: `tests/Feature/ExploracionesTest.php`.
- **Patrón**: clon de `test_finalizacion_con_chain_id_sin_tabla_evolution_chains_finaliza_ok`
  (crearContexto indefinido + bitácora controlada + CommandBus).
- **Bitácora**: 2 derrotas de charmander (id 2, species_id 4, fase 2 en cadena {bulbasaur sp1,
  charmander sp4}).
- **Asserts**: regreso OK; `caramelos` UNA fila `evolution_chain_id 51, cantidad 4`
  (2 × fase 2); `eventos['resultado']['caramelos_familia']` = `[{51, 'bulbasaur', 1, 4}]`.
- **Mata**: M1 `whereIn('evolution_chain_id',...)` → `whereIn('id',...)` (mapa vacío → fase 1 →
  cantidad 2 ≠ 4) y M2 pérdida de `species_id` en el select del mapa (fase 1 → cantidad 2 ≠ 4).

### 3. Test de 2 cadenas en la misma exploración (mata `sortBy('evolutionChainId')`)
- **Archivo**: `tests/Feature/ExploracionesTransformadorTest.php` (unit del transformador, más
  simple y directo que el flujo completo).
- **Setup**: pokémon en 2 cadenas (51: bulbasaur sp1 + charmander sp4; 52: pikachu sp25 +
  raichu sp26); `ResultadoRecompensas` con 2 `RecompensaFamilia` insertadas desordenadas
  [52, 51]; mapa con ambas cadenas.
- **Asserts**: salida EXACTA `[{51, 'bulbasaur', 1, 5}, {52, 'pikachu', 3, 3}]` → 2 entradas
  independientes (base = min species_id de SU cadena, sin cruces) y ordenadas por
  `evolution_chain_id` (inserción [52,51] ≠ esperado → mata la eliminación del sort).

## Archivos a tocar

| Archivo | Acción |
|---|---|
| `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php` | Fix fallback + docblock |
| `tests/Feature/ExploracionesTransformadorTest.php` | +2 tests (fallback determinista, 2 cadenas) |
| `tests/Feature/ExploracionesTest.php` | +1 test (fase 2 determinista) |
| `active/ANALISIS_BACKEND.md` | Análisis previo (este) |

## Riesgos identificados

1. El fallback con `sortBy('species_id')` cambia el orden de la colección keyBy id del handler:
   `avistados`/`capturados` usan `sortBy('id')`/`get()` propios → sin impacto.
2. Tests NO ejecutables en local (sin BD): van a CI (RefreshDatabase + SQLite :memory:); la
   validación local se limita a pint + php -l + phpstan (sin BD).
3. No tocar vistas/Blade ni tests existentes (solo añadir).
4. `sortBy` es estable → empates de species_id conservan orden de inserción (determinista dado
   el mismo query).

## Validación

- `vendor/bin/pint --dirty --format agent` + `php -l` en todo lo tocado.
- NO ejecutar `php artisan test` (sin BD desde host); tests a CI.

---

# Fix zona horaria: `config/app.php` UTC → Europe/Madrid (bug "regresar antes de las 12:50" 2h tarde)

## Fecha

2026-08-29 — Agente BACKEND.

## Diagnóstico (confirmado)

- `config/app.php:70` → `'timezone' => 'UTC'`; el usuario está en Europe/Madrid (UTC+2 en verano).
- `ExploracionActivaController::store()` (línea 87) interpreta el `return_time` "HH:MM" local
  como hora UTC: `Carbon::today()->setTimeFromTimeString($data['return_time'])` → la
  `hora_limite` se persiste 2h tarde → `toActiva()`/`finExploracion()` (línea 315-316) y
  `ProcesarExploracionHandler::finExploracion()` (línea 98-99, `Carbon::today()->setTimeFromTimeString($exp->hora_limite)`)
  calculan el fin 2h tarde → la exploración termina tarde y el frontend (`toLocaleTimeString('es-ES')`)
  muestra +2h. Bug funcional, no solo visual.
- Fix mínimo: cambiar la zona de la app en `config/app.php`. No hace falta tocar src/ ni el
  controlador: TODAS las interpretaciones de `hora_limite` pasan por `Carbon::today()` (zona de la
  app) → al cambiar la zona, el ciclo completo store → fin → tick queda coherente en Madrid.

## Impacto en tests (auditoría completa)

| Test | Dependencia de zona | Acción |
|---|---|---|
| `tests/Unit/SimuladorEncuentrosTest.php:150-152` (asserts `+00:00` en ISO) | NO bootea la app (`PHPUnit\Framework\TestCase`); parse y emisión usan la misma tz por defecto de PHP | NINGUNA (zona-agnóstico por construcción) |
| `tests/Unit/Exploraciones/CalculadorTiemposTest.php` | Idem (unit puro, `format('Y-m-d H:i:s')` relativo) | NINGUNA |
| `tests/Unit/Exploraciones/*`, `ExploracionActivaTest`, `TeamTest`, `ValidadorExploracionTest`, `HabitatsControllerTest`, `EquiposControllerTest` | Solo `now()` relativo | NINGUNA |
| `tests/Feature/ExploracionesViewTest.php` (fixtures con `Z`) | Fixtures de vista passthrough, sin Carbon | NINGUNA (los `Z` son entradas, no salidas de la app) |
| `tests/Feature/ExploracionesPageTest.php` (bitácora con `Z`) | `transformarEvento` hace passthrough del timestamp | NINGUNA; asserts de `inicio`/`fin` ya usan `equalTo` (instantes) |
| `tests/Feature/ExploracionesTest.php:294` (`test_hora_limite_pasada_completa_en_siguiente_tick`) | `now()->subHour()->format('H:i')` coherente en la nueva zona, PERO flaky en la ventana 00:00–01:00 (subHour cruza de día → hora_limite futura) | AJUSTE: `travelTo` fijo (patrón del test hermano) para eliminar la ventana de medianoche |
| `tests/Feature/ExploracionesTest.php:308` (`test_hora_limite_futura_no_completa`) | Ya tiene `travelTo('2026-08-28 10:00:00')` → determinista | NINGUNA |

## Tests a escribir (TDD)

1. NUEVO en `tests/Feature/ExploracionesPageTest.php`:
   `test_store_con_return_time_guarda_hora_limite_12_50_en_europe_madrid`
   - POST `/exploraciones` con `team_id`, `habitat_id`, `level`, `return_time='12:50'`.
   - Asserts: `hora_limite` parseada → `timezoneName === 'Europe/Madrid'` y `format('H:i') === '12:50'`;
     equivalencia UTC sin hardcodear offset (`Carbon::today('Europe/Madrid')->setTimeFromTimeString('12:50')->utc()->format('H:i')`);
     E2E: `fin` emitido por `toActiva()` (GET /exploraciones) `equalTo` hoy 12:50 Madrid.
   - Nota: `hora_limite` NO tiene cast datetime en `ExploracionActiva` → se lee como string
     (columna `time`; el Carbon se persiste vía `prepareBindings` como 'Y-m-d H:i:s'); el assert
     parsea con Carbon en la zona de la app (Europe/Madrid) → robusto a ambos formatos
     ('2026-08-28 12:50:00' o '12:50:00').

## Archivos a tocar

| Archivo | Acción |
|---|---|
| `config/app.php` | `'timezone' => 'UTC'` → `'Europe/Madrid'` |
| `tests/Feature/ExploracionesTest.php` | `test_hora_limite_pasada_completa_en_siguiente_tick`: añadir `travelTo` fijo + `travelBack` |
| `tests/Feature/ExploracionesPageTest.php` | +1 test de regresión (POST store con return_time) |
| `active/ANALISIS_BACKEND.md` | Análisis previo (este) |

## Riesgos identificados

1. No hay cobertura previa de `store()` (POST /exploraciones) → el test de regresión cubre el
   hueco y fija la zona en el camino store → fin.
2. `Carbon::parse($exploracion->hora_limite)` depende de la zona de la app (Europe/Madrid) — es
   justo la regresión que se quiere fijar.
3. El offset exacto depende del DST (verano +2, invierno +1): los asserts usan equivalencia
   calculada en runtime (nunca hardcodear '10:50').
4. Tests NO ejecutables en local (sin BD): van a CI (RefreshDatabase + SQLite :memory:);
   validación local: pint + php -l + phpstan (phpstan no analiza tests/).
5. No tocar vistas/Blade ni src/ (sin uso explícito de zona UTC en src/ — confirmado por grep:
   `timezone|UTC` solo aparece en config/app.php).
