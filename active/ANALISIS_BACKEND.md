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

---

## Fase 1 conversión multiplayer — fundación de datos (2026-08-29)

### Objetivo

Preparar la estructura de datos para multi-jugador: `user_id` en todas las tablas
player-owned (con backfill condicional a usuario legacy), `player_inventory` (sustituye a
`caramelos`/`caramelos_ev`/`caramelos_tipo`), `reclutados.exp` como `jsonb` con cast DTO
`ExpReclutado` (absorbe `reclutados_exp_tipo`), `habitats.min_lvl_*` (solo columnas) e
índices de rendimiento. NO se toca auth, ni wiring de `player_inventory`, ni lógica
min_lvl, ni controladores (fases B/C/D).

### Decisiones cerradas (brief)

- `user_id` NOT NULL + FK users onDelete cascade en: reclutados, teams, reclutables,
  pokedex, exploraciones_activas.
- Backfill condicional al usuario legacy (`Legacy` / `legacy@local`, password aleatorio):
  SOLO si hay filas que migrar; en tests las tablas están vacías → no crea usuarios.
- `reclutados.exp` json → jsonb; shape `{"total": int, "tipos": {tipo: int}}`.
- `reclutados_exp_tipo` → vuelca a `exp.tipos` y se elimina.
- `caramelos*` → `player_inventory(id, user_id, item_key string(64), cantidad, unique(user_id, item_key))`.
  item_key canónico: `familia:{evolution_chain_id}`, `ev:{stat}`, `tipo:{slug}` (slug vía
  `SlugTipo::de()` — mismo resultado que `strtolower(Str::ascii())` y consistente con el
  dominio: 'Eléctrico' → 'electrico').
- `habitats` + `min_lvl_1/2/3` (unsignedInteger, nullable). `users.email` nullable,
  `users.name` unique.

### Qué toco

- **11 migraciones nuevas** (2026_08_29_000001..000011): users; user_id en
  reclutados/teams/reclutables/pokedex/exploraciones_activas; player_inventory; volcado de
  caramelos; volcado de reclutados_exp_tipo; min_lvl habitats; índices de rendimiento
  (pokemon.evolution_chain_id, pokemon_habitat(habitat_id, level), team_members.team_id,
  habitats.province_id, pokemon_evolution.evolves_from_species_id, reclutados.pokemon_id —
  verificados: NINGUNO existe hoy; team_members.pokemon_id ya es unique → no se añade).
- **app/Support/LegacyUserMigrator.php**: helper compartido de backfill condicional (chequea
  TODAS las tablas de la fase: si alguna tiene filas crea/obtiene el usuario legacy por email).
- **app/Models/Concerns/BelongsToUser.php**: trait con global scope (`user_id = auth()->id()`,
  inactivo sin auth) + `user()` + `scopeWithoutUserScope()`.
- **app/Models/Casts/ExpReclutado.php**: cast + VO inmutable (total, expTipo, sumarExpTipo,
  consumirTipos; fromRaw tolera legacy '{"total": N}', '{}', null, array).
- **Modelos**: + trait/fillable/user_id en Team, Reclutado, Reclutable, Pokedex,
  ExploracionActiva; NUEVO PlayerInventory; BORRADOS Caramelo, CarameloEv, CarameloTipo,
  ReclutadoExpTipo (+ relación `expTipos()` de Reclutado). UserFactory: name con
  `fake()->unique()->name()`.
- **Seeders**: DatabaseSeeder (usuario demo por name, experiencia 0); ReclutadosSeeder
  (crea/obtiene el usuario demo y lo asigna a reclutados/teams).

### Tests a escribir

1. `tests/Unit/ExpReclutadoTest.php` — VO: total, expTipo default 0, sumarExpTipo inmutable
   (no muta total), consumirTipos (resta, elimina a 0), legacy sin 'tipos', null, toArray,
   persistencia vía cast en Reclutado.
2. `tests/Feature/UserScopeTest.php` — aislamiento por usuario (Team/Reclutado/Reclutable/
   Pokedex/PlayerInventory), sin auth no filtra, withoutUserScope lo ignora.
3. `tests/Feature/MigracionDatosTest.php` — rollback 4 pasos (11..8), insertar datos viejos,
   re-migrate, assert player_inventory + exp.tipos + tablas eliminadas + usuario legacy;
   + no crea legacy sin datos; + down() best-effort restaura.
4. `tests/Unit/PlayerInventoryTest.php` (sustituye a CarameloTest — modelo eliminado) —
   unique(user_id, item_key), mismo key en usuarios distintos OK, cast cantidad int.
5. `tests/Feature/MigrationStatusTest.php` — actualizado al nuevo esquema (caramelos ya no
   existen, player_inventory existe con unique, user_id NOT NULL, email nullable + name
   unique, min_lvl, tipo exp jsonb/text).

### Riesgos identificados

1. **Código que rompe al borrar modelos** (NO lo arreglo — es de fases B/C): importan
   Caramelo/CarameloEv/CarameloTipo/ReclutadoExpTipo o `expTipos`:
   `src/Exploraciones/App/PersistirRecompensas.php`,
   `src/Reclutamiento/App/ServicioEvolucion.php`,
   `app/Http/Controllers/ReclutadoController.php` (también `$reclutado->exp['total']` —
   rompe con el cast objeto), `app/Http/Controllers/ReclutamientoController.php`,
   `tests/Feature/ReclutadoEvolucionTest.php` y derivados. Los tests de controladores los
   reescriben los agentes de fases B/C; aquí solo arreglo mínimo los que fallan por
   modelos/factory (user_id NOT NULL) y documento.
2. **Datagrid 'pokemon'** leftJoins pokedex 1:1 (`pokedex.pokemon_id`) — con filas por
   usuario el join duplica filas: lo reescribe el agente de la fase B (scope por usuario en
   baseQuery). Misma historia para el registro 'pokedex'.
3. **AppServiceProvider::boot()** hace `User::first()` (single-player) — el agente de auth
   debe pasar a usuario autenticado.
4. **Down() de migraciones 8/9** son best-effort (restauran desde player_inventory /
   exp.tipos sin borrar el destino). Rollback total histórico (más allá de la 8) puede
   fallar porque la FK de caramelos ya no existe (recreada sin FK, y la migración
   `2026_08_29_000001_drop_foreign_evolution_chain_from_caramelos` espera la FK en su down).
5. **SQLite**: `jsonb` → `text` (grammar), `change()` nativo OK en L12; `dropUnique` +
   `unique` compuesto reconstruyen la tabla. Validado por el propio suite en CI.
6. **Entorno local**: los tests usan Postgres local (phpunit.xml `pokemon/secret` no existe;
   se corren con `DB_USERNAME=laravel DB_PASSWORD=laravel LOG_CHANNEL=stderr`); 5 fallos
   preexistentes ambientales (iconos PNG ausentes + SimuladorEncuentrosTest) NO son de esta
   tarea.
7. **UserFactory name unique**: `fake()->unique()->name()` para no chocar con el nuevo
   unique(name).
8. **Scopes y rutas**: el scope global hace que route-model-binding de otros usuarios dé
   404 (comportamiento deseado del multi-player; los controladores lo adaptan en B).

### Resultado (verificación local)

- Suite de alcance (36 tests) VERDE: ExpReclutado (12), PlayerInventory (5), UserScope (4),
  MigracionDatos (3), MigrationStatus (12).
- `migrate:fresh --seed` en Postgres dev: OK (usuario demo id 1, teams/reclutados con user_id,
  exp jsonb, player_inventory vacía y SIN usuario legacy — correcto).
- SQLite NO ejecutable en local (PHP sin pdo_sqlite): compatibilidad verificada a nivel de
  grammar (typeJsonb → text, compileChange nativo); CI (per docs) cubre la corrida empírica.
- Pint limpio; PHPStan: mis archivos a 0 errores (201 total vs 189 baseline = +12 solo en los
  4 archivos que referencian modelos eliminados — deuda de fases B/C); PHPMD sin cambios en
  src/; Infection sin cambios (solo cubre src/, no tocado).
- Suite completa: 263 passed / 80 failed. Los 80 = bucket fases B/C (8 archivos de test:
  EquiposControllerTest, ExploracionesTest, ExploracionesPageTest, ReclutadoEvolucionTest,
  ReclutamientoControllerTest, ActualizarPokedexJobTest, CapturarPokemonJobTest,
  RecompilarHabitatJsonJobTest) + 4 preexistentes ambientales (OptimizeIconsToWebpTest ×3,
  SimuladorEncuentrosTest — ya fallaban en baseline).
- Fix de estabilidad añadido: `DatagridService::applySort()` ordena por PK cuando no hay
  `sort` (Postgres devuelve orden arbitrario sin ORDER BY; los nuevos índices cambiaron el
  plan y rompían los tests de orden del datagrid).

---

## Fase B — Autenticación + aislamiento por usuario + datagrid por usuario (2026-08-29)

### Alcance (solo estos archivos)

- NUEVO `app/Http/Controllers/AuthController.php`: showLogin, login (name+password, Auth::attempt,
  regenerate, redirect '/' o intended), logout (POST, invalidar sesión), showRegister, register
  (name unique, password confirmed min:8, experiencia 0, Auth::login, redirect '/').
- `routes/web.php`: grupo guest (GET/POST /login, GET/POST /register, name('login') para el
  redirect del middleware auth), POST /logout, grupo auth envolviendo TODO lo demás
  (raíz, habitats, player, exploraciones, datagrid, cruds) con `Route::middleware('auth')->group()`
  sobre los requires existentes (habitats.php, player.php, exploraciones.php, datagrid.php).
- `app/Providers/AppServiceProvider.php`: User::first() → usuario autenticado. OJO: `auth()->user()`
  en boot() devuelve null (la sesión aún no arranca en el boot de providers). Se resuelve con un
  **View::composer('*')** que evalúa `auth()->user()` al renderizar (post-middleware). CLI sin
  auth → experiencia 0 / nivel 1. Se conserva el guard `Schema::hasTable('users')` (evaluado en
  boot, cacheado en closure).
- `app/Http/Controllers/TeamController.php` (anti-IDOR):
  - store: `Team::create(['name'=>..., 'user_id'=>auth()->id()])` en las DOS ramas (JSON y
    repo). El repo `EloquentTeamRepository::guardar` NO recibe user_id → la rama no-JSON crea
    sin user_id → violación NOT NULL. Solución: la rama no-JSON también pasa por
    `Team::create([... user_id ...])` (contrato del repo sin cambios; TeamAggregate no porta
    user_id).
  - update/destroy: route-model-binding + global scope → 404 de equipos ajenos (ya ok).
  - addMember: `team_id` validado con `exists:teams,id` + `Team::findOrFail` (el scope global
    convierte equipo ajeno en 404, igual que update/destroy); `reclutado_id` con
    `Rule::exists('reclutados','id')->where('user_id', auth()->id())` (422 para reclutados
    ajenos). `member_id` en removeMember: `findOrFail` + `abort_unless($member->team?->user_id
    === auth()->id(), 404)` (el belongsTo Team hereda el scope global → null para equipos
    ajenos).
- `app/Http/Controllers/PlayerController.php`: sin cambios de código (los listados ya filtran
  por global scope). `whereNull('regreso')` de equipos() es filtrado de NEGOCIO (exploración
  activa), NO global por usuario: se mantiene. Solo se refuerza con tests multi-usuario.
- `app/Providers/DatagridServiceProvider.php` (datagrid por usuario):
  - baseQuery 'pokemon': el leftJoin pasa a ser **leftJoin con condición en el ON**
    (`pokedex.pokemon_id = pokemon.id AND pokedex.user_id = {id}`). Sin auth (id null) →
    `user_id = NULL` nunca matchea → unión vacía → visto/atrapado NULL ≡ false (ya contemplado).
    NO usar where() post-join porque descartaría los pokémon no avistados del listado.
  - `pokemonCounts()`: vistos/atrapados solo del usuario autenticado; sin auth → 0/0 y
    no_vistos = total (misma semántica que la unión vacía).
- Tests: NUEVO `tests/Feature/AuthTest.php`; actualizados EquiposControllerTest,
  PlayerControllerTest, DatagridTest (actingAs + user_id + asserts de propiedad A/B).

### Piezas NO tocadas (deuda/coordinación)

- Vistas auth (`resources/views/auth/*.blade.php`) → frontend. El frontend las creó en
  paralelo (login/register + layouts/auth + AuthViewsTest); los tests de GET /login y
  GET /register usan las vistas reales. Los fixtures con stubs (`tests/Feature/Fixtures/views`)
  se descartaron al aparecer las vistas reales (eliminados).
- ExploracionesPageTest / ExploracionesViewTest / PokedexViewTest / HeaderNivelViewTest /
  HabitatsControllerTest: quedarán redirigiendo a /login sin sesión (efecto colateral del auth
  middleware) → los absorben backend C/frontend; fuera de mi alcance.
- El middleware guest/auth usa los alias por defecto de Laravel 12 (bootstrap/app.php sin
  cambios).

### Tests planeados (test rojo → verde)

1. AuthTest: registro crea usuario (experiencia 0) y autentica; login ok; login fallido;
   logout; rutas de juego → /login sin sesión; /login → '/' logueado; GET /login y /register
   renderizan (stub vía addLocation).
2. EquiposControllerTest: todos con actingAs + user_id en creates; NUEVOS: A no edita equipo
   de B (404), A no elimina equipo de B (404), A no añade miembro a equipo de B (404),
   A no añade reclutado de B (422 session error reclutado_id).
3. DatagridTest: todos con actingAs; NUEVO: la pokédex de A no muestra atrapados de B ni
   duplica filas cuando A y B tienen filas del mismo pokémon.
4. PlayerControllerTest: actingAs + user_id consistente (mismo usuario para pokedex y request).

### Riesgos

- `Auth::attempt(['name'=>...])` funciona con el provider por defecto (retrieveByCredentials
  filtra por cualquier columna; email no es especial). Verificar con test.
- Composer de AppServiceProvider: `Schema::hasTable` solo en boot; el closure usa
  `auth()->user()` por request (sin query extra por render; la sesión ya arrancó).
- leftJoin con condición en ON: funciona en Postgres y SQLite (compatible grammar estándar);
  paginate() respeta la condición del join (count con join).
- `Team::findOrFail` en addMember devuelve 404 para equipo ajeno — mismo status que
  update/destroy (consistente, sin fuga de existencia).

---

# Análisis Backend — Fase D: niveles mínimos de hábitat (min_lvl_1/2/3) + anti-IDOR exploraciones [2026-08-29]

## Contexto verificado

- FASE 1 (03d8457): columnas `habitats.min_lvl_1/2/3` (unsignedInteger NULLABLE, default null) SIN lógica;
  `exploraciones_activas.user_id` NOT NULL + trait `BelongsToUser` (global scope `belongsToUser`, inactivo
  sin auth); seeder demo (demo/password); modelos de caramelos eliminados.
- El frontend YA consume los contratos (fases paralelas): `habitats/show.blade.php` usa
  `$habitat['min_lvl_1'|'min_lvl_2'|'min_lvl_3']` (DTO detalle) y `exploraciones/index.blade.php` usa
  `$exp['min_lvl']` y `$terminada['min_lvl']` para el badge "Requiere Nv X" → shapes confirmados.
- `Rule::exists('teams','id')` usa el query builder (DB::table), NO Eloquent → el global scope de `Team`
  NO se aplica → hay que añadir `->where('user_id', auth()->id())` (anti-IDOR del store).
- `store()` NO pasa `user_id` al create → violaría NOT NULL (bug latente de la FASE 1). Se añade
  `'user_id' => auth()->id()` (por la regla del team, solo se llega aquí autenticado).
- `recoger`/`cerrar`: route model binding `{exploracion}` resuelve con el global scope (auth activo) →
  exploración ajena = ModelNotFound → 404. Verificar con test.
- `ValidadorExploracion::equipoDisponible/habitatTieneExploracionesActivas/exploracionesActivas` con scope
  activo consultan SOLO las exploraciones del usuario autenticado → semántica per-player correcta para
  multiplayer (equipo ya validado como propio por el store; bloqueo de construcción per-player). Sin cambios.
- `nivel()` en User usa `NivelHelper` (curva 10×nivel³). Nivel 5 = 1.250 exp; nivel 10 = 10.000 exp.
- Mensaje exacto del brief: `'Requiere nivel Nv {X} para explorar esta zona.'` (X = min_lvl).

## Decisión de diseño

- **min_lvl en dominio**: método nuevo `ValidadorExploracion::cumpleNivelMinimo(int $nivelJugador, ?int $nivelMinimo): bool`
  (`$nivelMinimo === null || $nivelJugador >= $nivelMinimo`) — regla de negocio pura, testeable sin BD;
  el controlador orquesta (lee el hábitat, pasa nivel del jugador). El valor `null` = sin restricción queda
  documentado por la firma y los tests.
- **Store**: validación con `Rule::exists('teams','id')->where('user_id', auth()->id())`; tras validar,
  `$min = $habitat->getAttribute('min_lvl_'.$data['level'])`; si `$min !== null` y
  `! cumpleNivelMinimo(nivel(), $min)` → `redirect()->back()->with('error', msg)->withInput()` o
  `response()->json(['message' => msg], 422)` si `wantsJson()`.
- **index**: aditivo `'min_lvl' => ?int` (el min del hábitat para el nivel de ESA exploración) en
  `toActiva()` y `toTerminada()`; `null` = sin badge (ya consumido por la vista).
- **DTOHabitatDetalle**: aditivo `min_lvl_1/2/3` (?int) en constructor + toArray (contrato aditivo).

## Qué voy a tocar

| Archivo | Acción | Propósito |
|---|---|---|
| `src/Habitats/App/ValidadorExploracion.php` | Modificar | + `cumpleNivelMinimo()`. |
| `app/Http/Controllers/ExploracionActivaController.php` | Modificar | store: rule exists con user_id, chequeo min_lvl, user_id en create; index: `min_lvl` en toActiva/toTerminada. |
| `src/Habitats/Presentation/DTOHabitatDetalle.php` | Modificar | + 3 campos ?int + toArray + PHPDoc. |
| `src/Habitats/Infra/HabitatRepository.php` | Modificar | getHabitatDetail puebla min_lvl_1/2/3 (fallback DTO null-hábitat con nulls). |
| `app/Models/Habitat.php` | Modificar | casts integer de min_lvl_* (solo lectura limpia). |
| `tests/Feature/Habitats/MinLvlTest.php` | NUEVO | store bloquea < min (session y JSON), permite >=, permite null, equipo ajeno error, recoger/cerrar ajeno 404. |
| `tests/Feature/ValidadorExploracionTest.php` | Modificar | + tests de `cumpleNivelMinimo` (null, límite exacto, inferior). |
| `tests/Feature/HabitatsControllerTest.php` | Modificar | + assert de min_lvl_* en viewData del show. |

## Tests (TDD: rojo → verde)

1. `test_store_bloquea_cuando_nivel_jugador_menor_que_min_lvl` — habitat min_lvl_2=10, user nivel 5 (1.250 exp), POST level=2 → redirect back, session error, BD sin fila.
2. `test_store_bloquea_min_lvl_responde_422_json` — mismo caso con `Accept: application/json` → 422 + mensaje exacto.
3. `test_store_permite_cuando_nivel_jugador_igual_al_min_lvl` — user nivel 10 (10.000 exp), min_lvl_2=10 → success + fila en BD (user_id correcto).
4. `test_store_permite_cuando_min_lvl_null` — min_lvl_1 null → success.
5. `test_store_equipo_ajeno_error_validacion` — team de B, actingAs A → session errors team_id, sin fila.
6. `test_recoger_exploracion_ajena_devuelve_404` — actingAs A, POST recoger sobre exploración de B → 404.
7. `test_cerrar_exploracion_ajena_devuelve_404` — exploración de B con regreso (si no: cerrar ya da 404 por regreso null) → 404.
8. `test_index_pasa_min_lvl_del_habitat_para_el_nivel` — GET /exploraciones con exp activa en habitat min_lvl_2=10 nivel 2 → view data `activas[0]['min_lvl'] === 10`; null en zona sin restricción.

## Riesgos

- Carreras de tests contra FASE B/C sobre la misma BD `laravel_test` (migrate:fresh intermitente) → re-ejecutar
  el filtro hasta 3 veces; verificación con BD aislada no posible (sin permisos de creación) pero suite estable.
- PHPStan level 6: acceso a `min_lvl_*` vía `getAttribute()` (mixed) con guards `!== null` + `(int)`; sin
  anotaciones @property en modelos (convención actual).
- NO tocar src/Exploraciones (C), vistas, ExploracionesTest/ExploracionesPageTest, Reclutamiento/Reclutado.

---

## Fase C — Caramelos → player_inventory, exp tipo en JSON, jobs y handler por usuario (2026-08-29)

### Contexto

- Fase 1 (03d8457) eliminó las tablas `caramelos`/`caramelos_ev`/`caramelos_tipo`/`reclutados_exp_tipo`
  y sus modelos; creó `player_inventory(user_id, item_key, cantidad)` con `BelongsToUser` en los
  modelos player-owned y el cast `ExpReclutado` en `reclutados.exp` (shape `{total, tipos}`).
- Fase B (auth, paralela) NO ha llegado a este árbol: no hay AuthController ni middleware 'auth'
  (bootstrap/app.php vacío). Sus tests con actingAs serán compatibles.
- Baseline local: 106 tests rojos = fallout de la Fase 1 (creates sin `user_id` en fixtures de
  tests que NO se actualizaron: ReclutamientoControllerTest, ReclutadoEvolucionTest,
  ExploracionesTest, Jobs/*, ServicioCapturaTest, ExploracionesPageTest). El análisis de Fase B
  delega EXPLÍCITAMENTE ExploracionesPageTest a "backend C" → lo absorbo (solo fixtures).

### Decisiones de esta fase

1. **ItemCatalogo** → `app/Support/ItemCatalogo.php` (infra: consulta App\Models + enums; no va a
   src/Shared/App porque no existe la carpeta y es infraestructura, no dominio puro — mismo criterio
   que WebpConverter). API estática:
   - `keyFamilia(int): string` → `familia:{id}`
   - `keyEv(int): string` → `ev:{stat}`
   - `keyTipo(string): string` → `tipo:{slug}` (SlugTipo::de, mismo que la migración 000008)
   - `resolve(string): array{nombre, imagen, categoria}`
   - familia: primer integrante = min species_id (desempate id asc), misma regla que
     `TransformadorResultadoExploracion::pokemonBaseDeCadena`; cadena sin pokémon → 'Desconocido' +
     `/images/candy_pokemon/0.webp`.
   - ev: nombre = `StatEnum::label()`; slug hp/atk/def/atksp/defsp/spd (const `STATS` del
     ExploracionActivaController como referencia, duplicada con comentario — contrato aditivo).
   - tipo: slug → label resolviendo `TipoEnum::cases()` con `SlugTipo::de(label) === slug`.
   - key desconocida → 'Desconocido' + fallback + categoria 'desconocida' (nunca lanza).
2. **PersistirRecompensas**: `guardarCaramelosFamilia/Ev/Tipo` escriben en `player_inventory`
   con `user_id => $usuario?->id ?? 1` (fallback defensivo: con FK cascade el dueño nunca es null
   en producción; `?? 1` solo aplica en el test artificial sin usuario). `aplicarCapturas` →
   `Reclutable` con user_id. `aplicarExperiencia` recibe `?User` igual que antes pero adapta el
   manejo del cast: `$reclutado->exp` YA ES `ExpReclutado` (objeto) → usar `->toArray()`.
3. **FinalizarExploracionHandler**: `User::first()` → `$exploracion->user` (belongsTo del trait).
   Nivel salvaje = `$usuario->nivel()`, 1 si null. Jobs de avistados llevan `$exploracion->user_id`.
4. **Jobs**: `ActualizarPokedexJob(int $userId, int $pokemonId, string $estado)` y
   `CapturarPokemonJob(int $userId, int $pokemonId, float $chance)` con updateOrCreate por
   (user_id, pokemon_id). `RecompilarHabitatJsonJob` intacto (catálogo global). DEUDA: el JSON
   del hábitat se recompila con la pokedex del usuario que disparó (multiplayer parcial).
5. **ServicioEvolucion**: firmas con `int $userId` donde hace falta (requisitos, puedeEvolucionar,
   caramelosDisponibles); `nivelDe` usa `$reclutado->exp->total()`; `consumirExpTipo` muta el JSON
   vía `consumirTipos` + save. Sin Auth dentro de src/ (el controlador pasa el userId).
6. **ReclutamientoController**: create con `user_id` (auth), jobs con userId, caramelos →
   `player_inventory` del usuario. Propiedad del reclutable: global scope + findOrFail → 404.
7. **ReclutadoController**: `darCaramelo` en transacción con `lockForUpdate` (user+keyTipo) →
   422 si ≤0 → decrement → `sumarExpTipo(100)` + save. `evolucionar` consume el JSON + job con
   userId. Propiedad del reclutado: route model binding + scope → 404.
8. **ProcesarExploraciones**: `withoutUserScope()` para procesar a TODOS los usuarios (CLI sin auth).
9. **ExploracionesTest sin usuario**: con FK cascade `user_id` NOT NULL, borrar usuarios borra la
   exploración → imposible el caso "sin dueño" por la vía normal. Se reescribe con un mock parcial
   que devuelve `user` → null (patrón `mockExploracionQueFallaAlMarcarRegreso` existente): nivel 1,
   exp a los miembros del equipo, caramelos al fallback (dueño real en DB = id 1), sin crash.

### Archivos a tocar

- NUEVO `app/Support/ItemCatalogo.php`, `tests/Unit/ItemCatalogoTest.php`,
  `tests/Feature/InventarioTest.php`.
- `src/Exploraciones/App/PersistirRecompensas.php`, `FinalizarExploracionHandler.php`.
- `app/Jobs/ActualizarPokedexJob.php`, `CapturarPokemonJob.php`.
- `src/Reclutamiento/App/ServicioEvolucion.php`, `ServicioCaptura.php`.
- `app/Http/Controllers/ReclutamientoController.php`, `ReclutadoController.php`.
- `app/Console/Commands/ProcesarExploraciones.php`.
- Tests: ReclutamientoControllerTest, ReclutadoEvolucionTest, ExploracionesTest,
  ExploracionesPageTest, Jobs/{ActualizarPokedexJobTest, CapturarPokemonJobTest},
  ServicioCapturaTest. (`RecompilarHabitatJsonJobTest` intacto.)

### Riesgos

- `$reclutado->exp['total']` (ArrayAccess sobre objeto) rompe en ServicioEvolucion::nivelDe y
  PersistirRecompensas::aplicarExperiencia → adaptar al cast (`->total()` / `->toArray()`).
- `?? 1` en player_inventory con BD sin usuarios → FK violation: solo ocurre en el test mock;
  verificado que el dueño real queda en DB (id 1).
- `lockForUpdate` es no-op en SQLite :memory: → el test de lock cubre el observable
  (1 caramelo → 1 dar OK + 2º dar → 422) y se ejecuta contra Postgres local.
- El `exists:reclutables,id` de la validación no filtra por usuario (regla global), pero el
  findOrFail posterior con scope global → 404. Sin fuga de existencia (mismo status).
