# TASK-057 — Análisis

- **Autor:** `analyst` (subagente, sesión `ses_e1287d4b6ffedRvq3HbmlYsKU6`).
- **Fecha:** 2026-11-18.
- **Alcance:** solo lectura; sin cambios de código, datos ni tests.
- **Nota de coordinación:** se reproduce el informe final del `analyst` sin alterar sus
  conclusiones. El informe corrige una inconsistencia de su primera versión (las filas
  10230/10232/10242 no existen en `pokemon_evolution.csv`).

---

# Informe final TASK-057 — Cadenas evolutivas mezcladas en seeders

## 0. Corrección de mi informe preliminar

Mi frase «las otras 3 filas con `base_form_id` son 10230/10232/10242» era **incorrecta**: esas tres filas no existen en `pokemon_evolution.csv`. Son filas de `pokemon_evolution` **generadas por la segunda pasada del seeder** para formas regionales evolucionadas (su `species.evolves_from_species_id` se re-parenta al regional). Confirmado: el CSV tiene **29 filas con `base_form_id` no vacío** y **27 especies evolucionadas distintas** (904 overqwil ×3), exactamente la lista aportada. Verificación: `git log` de `pokemon_species.csv`, `pokemon_evolution.csv`, `pokemon_stats.csv`, `pokemon_types.csv` → **un solo commit de importación** (`7c8ae8d`), sin ediciones posteriores; la única regresión de datos es `pokemon.csv`.

---

## 1. RESUMEN Y CAUSA RAÍZ

Hay **tres defectos apilados**, no uno:

1. **Regresión de datos (`pokemon.csv`).** El commit `efa5cfd` («fixes», 31-oct) eliminó el sufijo regional de **38 nombres** (18 de Alola `10091-10115`, 20 de Galar `10161-10180`): `meowth-galar` → `meowth`, `linoone-galar` → `linoone`, etc. `PokemonSeeder::regionOf()` detecta región por `str_contains($name, '-galar')`, así que esas 38 formas **dejan de sembrarse**. Sin ellas, `$regionalPokemonBySpecies` no tiene entradas de Galar/Alola y el re-parenting regional no puede enlazar nada de esas regiones.
2. **El seeder ignora `base_form_id`.** Indexa `$evolutionByPokemon[$evolved_species_id] = $evolution` (`PokemonSeeder.php:66-70`), donde **gana la última fila del CSV**, y toma el padre de `pokemon_species.evolves_from_species_id`, no de la fila de evolución. Para un evolucionado `is_default=1` no hay re-parenting (`:172`). Resultado: Perrserker ← Meowth de Kanto (52), Obstagoon ← Linoone de Hoenn (264), Sirfetch'd ← Farfetch'd de Kanto (83), etc., todos colgados de la **cadena normal**.
3. **El «última gana» corrompe `minimum_level`**, porque la fila con `base_form_id` suele no tener nivel (`null`) y el fallback es 40.

**Manifestación funcional concreta:** `ServicioEvolucion::idsEvolucion()` busca `where('evolves_from_species_id', $pokemon->species_id)` (`ServicioEvolucion.php:80-84`). Hoy un **Meowth normal puede evolucionar a Perrserker** y un **Linoone normal a Obstagoon**, porque ambos comparten padre. En el panel de hábitats, la familia `22` (Meowth/Persian/**Perrserker**) aparece en el hábitat 20 (Kanto) mientras Meowth-galar está solo en el 33.

**Dato clave de estado:** la BD **no refleja el código actual**. Tiene 1083 pokémon y 514 evoluciones (sembrada **antes** de `efa5cfd`, con los nombres regionales intactos); el seeder actual produciría 1045 y 495. Un `db:seed` hoy **empeoraría** el catálogo (rompe Obstagoon `10175→264`, deja 19 aristas obsoletas y no restaura nada).

---

## 2. TABLA DEFINITIVA — 27 especies con fila `base_form_id`

`padre BD` = valor real en `pokemon_evolution`; `padre correcto`/`chain correcta`/`nivel correcto` = resultado tras restaurar el CSV y aplicar reglas (a)/(b) de §5. `filas` = `base_form_id/nivel` en orden del CSV (`—` = fila normal).

| id | nombre | is_default | species_id | filas (base_form/nivel) | padre BD | padre correcto | chain BD | chain correcta | nivel BD | nivel correcto | clase |
|---|---|---|---|---|---|---|---|---|---|---|---|
| 20 | raticate | 1 | 20 | —/20; 10091/20 | 19 | 19 | 7 | 7 | 20 | 20 | a |
| 28 | sandslash | 1 | 28 | —/22; 10101/null | 27 | 27 | 11 | 11 | **40** | **22** | a |
| 38 | ninetales | 1 | 38 | —/null; 10103/null | 37 | 37 | 15 | 15 | 40 | 40 | a |
| 51 | dugtrio | 1 | 51 | —/26; 10105/26 | 50 | 50 | 21 | 21 | 26 | 26 | a |
| 53 | persian | 1 | 53 | —/28; 10107/null | 52 | 52 | 22 | 22 | **40** | **28** | a |
| 59 | arcanine | 1 | 59 | —/null; 10229/null | 58 | 58 | 25 | 25 | 40 | 40 | a |
| 75 | graveler | 1 | 75 | —/25; 10109/25 | 74 | 74 | 31 | 31 | 25 | 25 | a |
| 76 | golem | 1 | 76 | —/null; 10110/null | 75 | 75 | 31 | 31 | 40 | 40 | a |
| 78 | rapidash | 1 | 78 | —/40; 10162/40 | 77 | 77 | 32 | 32 | 40 | 40 | a |
| 80 | slowbro | 1 | 80 | —/37; 10164/null | 79 | 79 | 33 | 33 | **40** | **37** | a |
| 89 | muk | 1 | 89 | —/38; 10112/38 | 88 | 88 | 38 | 38 | 38 | 38 | a |
| 101 | electrode | 1 | 101 | —/30; 10231/null | 100 | 100 | 44 | 44 | **40** | **30** | a |
| 199 | slowking | 1 | 199 | —/null; 10164/null | 79 | 79 | 33 | 33 | 40 | 40 | a |
| 264 | linoone | 1 | 264 | —/20; 10174/20 | 263 | 263 | 134 | 134 | 20 | 20 | a |
| 555 | darmanitan-standard | 1 | 555 | —/35; 10176/null | 554 | 554 | 282 | 282 | **40** | **35** | a |
| 571 | zoroark | 1 | 571 | —/30; 10238/30 | 570 | 570 | 291 | 291 | 30 | 30 | a |
| 706 | goodra | 1 | 706 | —/50; 10241/50 | 705 | 705 | 362 | 362 | 50 | 50 | a |
| 862 | obstagoon | 1 | 862 | 10175/35 | 10175 | **10175** | **134** | **12134** | 35 | 35 | b |
| 863 | perrserker | 1 | 863 | 10161/28 | **52** | **10161** | **22** | **12022** | 28 | 28 | b |
| 864 | cursola | 1 | 864 | 10173/38 | **222** | **10173** | **113** | **12113** | 38 | 38 | b |
| 865 | sirfetchd | 1 | 865 | 10166/null | **83** | **10166** | **35** | **12035** | 40 | 40 | b |
| 866 | mr-rime | 1 | 866 | 10168/42 | **122** | **10168** | **57** | **12057** | 42 | 42 | b |
| 867 | runerigus | 1 | 867 | 10179/null | **562** | **10179** | **287** | **12287** | 40 | 40 | b |
| 902 | basculegion-male | 1 | 902 | 10247/null | 550 | **550 (decisión)** | 280 | **280 (decisión)** | 40 | 40 | b* |
| 903 | sneasler | 1 | 903 | 10235/null | **215** | **10235** | **109** | **13109** | 40 | 40 | b |
| 904 | overqwil | 1 | 904 | 10234/null (**×3**) | **211** | **10234** | **106** | **13106** | 40 | 40 | b |
| 980 | clodsire | 1 | 980 | 10253/20 | **194** | **10253** | **96** | **14096** | 20 | 20 | b |

**Clasificación:** (a) 17 especies con fila normal → **se conserva** el padre normal y solo se corrige el nivel. (b) 10 especies que **solo** tienen fila con `base_form_id` → padre = forma base regional y chain regional. (b*) 902 basculegion: su forma base `10247 basculin-white-striped` **no está sembrada** (ver §5.3).

> Nota sobre 862: en BD el padre ya es 10175 (parche manual o siembra previa), pero **el código actual generaría 264**. Es el único registro donde el código y la BD divergen.

---

## 3. EVIDENCIA (archivo:línea)

**Seeder**
- `database/seeders/PokemonSeeder.php:66-70` — índice `$evolutionByPokemon[$evolved_species_id]`: última fila del CSV gana; `base_form_id` descartado.
- `:105-112` — `is_default!=1` y `regionOf()===null` ⇒ `continue` (omite rotom-heat y **también** las 36 formas Alola/Galar con nombre desnudo).
- `:127` — `evolution_chain_id` fijado por `chainIdToStore($region, $species)`; para `is_default=1` siempre la chain normal.
- `:162-187` — segunda pasada: padre de `pokemon_species.evolves_from_species_id` (`:163`), re-parenting **solo** si `region !== null` (`:172-179`), nivel de `$evolutionByPokemon` con default 40 (`:185`).
- `:210-212` — `upsert(..., ['evolved_species_id'], [...])`; **sin delete**.
- `:223-232` / `:239-248` — `chainIdToStore()` y `regionOf()`.
- `database/migrations/2026_08_28_201528_add_unique_constraints_to_pokemon_seed_tables.php:24` — `unique('evolved_species_id')`: **una fila por evolucionado** (incompatible con modelo por forma).

**Consumidores**
- `src/Reclutamiento/App/ServicioEvolucion.php:80-84` — `where('evolves_from_species_id', $pokemon->species_id)`: **aquí se materializa el bug** (Meowth → Perrserker).
- `app/Support/CadenasEvolutivas.php:31-48` (`miembrosDe`, agrupa por chain); `:62-133` (`etapasDe`); **`:126`** descarta aristas cuyo padre no comparte chain.
- `src/Habitats/Domain/ResolvedorCadenasEvolutivas.php:86` (`multiRaiz: false`), **`:97`** (`stage ?? 3`), `:106-112` nivel por etapa.
- `src/Pokemon/Domain/Evolution/ResolvedorFamiliaEvolutiva.php:103` — ignora aristas fuera del conjunto de nodos; `:250-283` selección de componente en modo compatibilidad.
- `app/Support/FamiliaPokemon.php:26-41,126-190` — **cierre global no dirigido**: el detalle de Pokédex ya mezcla chains por diseño (usado en `PresentadorDetallePokemon.php:226`).
- `app/Http/Controllers/ReclutamientoController.php:140-157`, fallback `species_id <=` en `:154`.
- `app/Support/ItemCatalogo.php:28-31` (`familia:{chain}`), `:123-140` (base = menor `species_id`).
- **Panel admin de hábitats**: `src/Habitats/Infra/HabitatLecturaRepository.php:125-156` (`getFamiliesByHabitat`: chains desde `pokemon_habitat`), `:158-180` (`getUnassignedFamilies`), `src/Habitats/Infra/Concerns/CadenasFamiliaHabitatsTrait.php:116-147` (`cargarMiembrosFamilia`: miembros por `evolution_chain_id` + evoluciones filtradas por `evolved_species_id` ∈ cadena), `:40-95` (construcción de la familia), `src/Habitats/Infra/Queries/DetallePokemonFamiliaQuery.php:26-40` y `:53-60` (exige `pokemon.evolution_chain_id == $chainId` del request).
- `src/Granjas/App/ResolverBonosFamilia.php:28-56` + `app/Models/Nido.php` (tabla `nests`).
- `src/Exploraciones/Presentation/TransformadorResultadoExploracion.php:104-142` (base = menor `species_id`), `src/Exploraciones/Domain/CalculadorRecompensas.php:157-215` (caramelos por chain).

**No existe** un «panel Admin de Pokémon» ni un BFS propio: el único BFS de administración es el de hábitats (`CadenasFamiliaHabitatsTrait` + `ResolvedorCadenasEvolutivas`). El CRUD admin existente es solo de gimnasios (`src/Gimnasios/Infra/Controllers/AdminGymController.php`) y no toca evoluciones.

---

## 4. IMPACTO EN CONSUMIDORES Y BD

**Uso de cada consumidor:** todos agrupan por `evolution_chain_id` (hábitats, nidos, refugios, caramelos, panel admin) salvo `ServicioEvolucion` y `FamiliaPokemon`, que usan `pokemon_evolution`/cierre global.

**Efecto de mover los 9 evolucionados a la chain regional:**

| Consumidor | Efecto | ¿Rompe? |
|---|---|---|
| `ServicioEvolucion` | Meowth(52) deja de ofrecer Perrserker; lo ofrece Meowth-galar(10161). Igual Linoone-galar→Obstagoon, etc. | **Corrige el bug** |
| `CadenasEvolutivas::etapasDe` / `ReclutamientoController` | Etapas pasan a calcularse con arista intra-chain válida (hoy Perrserker=2 vía 52; después 2 vía 10161). Caramelos van a `familia:12022`… | Mejora; **cambia clave de caramelo** |
| `ResolvedorCadenasEvolutivas` (panel admin hábitats) | Familias nuevas de 2 miembros con etapa real 1/2; hoy Perrserker aparece en la familia 22 y Obstagoon cae en `stage 3` fallback | Mejora |
| `HabitatLecturaRepository` | Las nuevas chains **ya están asignadas** porque todas las pre-evoluciones regionales están en `pokemon_habitat` (10161→h33, 10166→h20, 10168→h55, 10173→h43, 10175→h33, 10179→h45, 10234→h13, 10235→h55, 10253→h34). No quedan huérfanas | No |
| `DetallePokemonFamiliaQuery` | Sigue exigiendo coincidencia de chain del request; el front pasa el chain de la familia listada | No |
| `ItemCatalogo::resolverFamilia` | Base de `familia:12022` = 10161 (icono `candy_pokemon/10161.webp`) | Solo visual, en claves nuevas |
| `FamiliaPokemon` (detalle Pokédex) | Cierre global: **ya muestra** la familia completa; no cambia | No (sigue “mezclando” por diseño) |
| `ResolverBonosFamilia` / `nests` / `shelters` | Solo si hubiera bonos en las chains origen | Ver conteos ↓ |

**Conteos en BD (solo SELECT):**

| Tabla | Filas en chains afectadas (22,134,35,57,113,287,96,109,106,280) | Detalle |
|---|---|---|
| `nests` (3 en total) | **0** | chains existentes: 34, 44, 60 |
| `shelters` (4 en total) | **0** | chains: 34, 44, 60, 147 |
| `player_inventory` `familia:` (121 filas, 3 usuarios, 119 claves) | **3 filas** | `familia:22` (1 usuario, cantidad 20), `familia:287` (1 usuario, 8), `familia:109` (1 usuario, 20) |
| `player_inventory` claves nuevas (12022, 12134, 12113, 12035, 12057, 12287, 13109, 13106, 14096) | **0** | — |
| `reclutados` (39 en total) / `reclutables` | **0** de las 10 especies afectadas | — |
| `huevos` (1 en total) | **0** | — |
| `nest_plantings` | 2 en total (no depende de chain) | — |

**Filas que cambia un re-seed corregido:** `pokemon` **9** (solo `evolution_chain_id`) + `pokemon_evolution` **15** (8 padres + 7 niveles) = **24 filas**. Sin restaurar el CSV: 0 en `pokemon` y 1 en `pokemon_evolution` (Obstagoon 10175→264, es decir, **empeora**), más 19 aristas obsoletas que el seeder no borra.

**Visibilidad por hábitat (hoy → después):** familia 22 en hábitat 20 (Kanto) contiene Perrserker → pasa a hábitat 33 (Meowth-galar). Obstagoon (hoy en chain 134, sin hábitat) pasa al hábitat 33. Clodsire pasa del hábitat 12 (Wooper Kanto) al 34 (Wooper-paldea). Sirfetch'd, Cursola, Mr. Rime, Runerigus, Sneasler y Overqwil no cambian de hábitat (pre-evo regional en el mismo hábitat), solo de agrupación.

---

## 5. SIMULACIÓN POST-FIX

**Reglas simuladas** (Python sobre CSV; CSV restaurado desde `efa5cfd^`, sin tocar BD):
- (a) Si hay fila **sin** `base_form_id`: padre = `species.evolves_from_species_id` (con re-parenting regional actual si es regional); nivel = nivel de esa fila (null→40).
- (b) Si **solo** hay filas con `base_form_id` y esa forma base está sembrada: padre = `base_form_id`; chain = chain de la forma base; nivel = nivel de la fila.
- (c) Si la forma base no está sembrada: se mantiene el padre normal (decisión pendiente).

**Resultado:** **1083 pokémon**, **514 evoluciones** (idéntico a la BD actual en cardinalidad; el CSV restaurado reproduce exactamente el catálogo existente).

**Aristas que cambian (15):**

| id | nombre | BD `(padre,nivel)` | post-fix |
|---|---|---|---|
| 28 | sandslash | (27, 40) | (27, **22**) |
| 53 | persian | (52, 40) | (52, **28**) |
| 80 | slowbro | (79, 40) | (79, **37**) |
| 101 | electrode | (100, 40) | (100, **30**) |
| 156 | quilava | (155, 17) | (155, **14**) |
| 555 | darmanitan-standard | (554, 40) | (554, **35**) |
| 724 | decidueye | (723, 36) | (723, **34**) |
| 863 | perrserker | (52, 28) | (**10161**, 28) |
| 864 | cursola | (222, 38) | (**10173**, 38) |
| 865 | sirfetchd | (83, 40) | (**10166**, 40) |
| 866 | mr-rime | (122, 42) | (**10168**, 42) |
| 867 | runerigus | (562, 40) | (**10179**, 40) |
| 903 | sneasler | (215, 40) | (**10235**, 40) |
| 904 | overqwil | (211, 40) | (**10234**, 40) |
| 980 | clodsire | (194, 20) | (**10253**, 20) |

**Chains que cambian (9 filas en `pokemon`):** 862 134→12134, 863 22→12022, 864 113→12113, 865 35→12035, 866 57→12057, 867 287→12287, 903 109→13109, 904 106→13106, 980 96→14096.

**Verificación de chains por arista en las familias afectadas — todas correctas:**

| Chain nueva | Miembros | Aristas intra-chain |
|---|---|---|
| 12022 | 10161 meowth-galar, 863 perrserker | 10161→863 ✔ |
| 12134 | 10174 zigzagoon-galar, 10175 linoone-galar, 862 obstagoon | 10174→10175, 10175→862 ✔ |
| 12113 | 10173 corsola-galar, 864 cursola | ✔ |
| 12035 | 10166 farfetchd-galar, 865 sirfetchd | ✔ |
| 12057 | 10168 mr-mime-galar, 866 mr-rime | 10168→866 ✔ (queda 10168←439 mime-jr, cross-chain **preexistente**: no existe mime-jr-galar) |
| 12287 | 10179 yamask-galar, 867 runerigus | ✔ |
| 13109 | 10235 sneasel-hisui, 903 sneasler | ✔ |
| 13106 | 10234 qwilfish-hisui, 904 overqwil | ✔ |
| 14096 | 10253 wooper-paldea, 980 clodsire | ✔ |

**Huérfanas: 0** (todo `evolves_from_species_id` existe en `pokemon`). Globalmente quedan **13 aristas cross-chain**, todas preexistentes y ajenas a las 9 familias: `melmetal 809←808` (chain 429 vs 428, quirk de datos independiente) y 12 formas regionales sin pre-evolución regional (raichu-alola, exeggutor-alola, marowak-alola, weezing-galar, mr-mime-galar, typhlosion-hisui, samurott-hisui, lilligant-hisui, braviary-hisui, sligo→goodra-hisui, avalugg-hisui, decidueye-hisui). Son el comportamiento «familia de 1» aceptado en `PokemonSeeder.php:166-168`.

**Dónde falla cada regla:**
- **(a)** no falla en ningún caso, pero **no basta**: deja 8 padres mal (863, 864, 865, 866, 867, 903, 904, 980) porque el padre correcto no está en `pokemon_species`.
- **(b)** falla en **902 basculegion**: `10247 basculin-white-striped` existe en `pokemon.csv` con `is_default=0` y nombre con sufijo… que **no es de región** (`-white-striped`), por lo que `regionOf()` devuelve null y queda omitida. Para sembrarla haría falta una allowlist de formas especiales **y** una chain propia (su `species.evolution_chain_id` es 280, la misma que basculin-red-striped → colisión). Recomendación: **dejar 550 como padre** (comportamiento actual) y documentarlo como familia incompleta, o abrir tarea aparte para formas especiales.
- **(a)/(b)** no cubren `156`/`724`: dos filas normales con niveles distintos (`region_id` 9), donde «primera fila» y «mínimo» coinciden (14 y 34) pero es una **decisión de producto**, no un hecho.

**Niveles corruptos exactos (7):** 28 `40→22`, 53 `40→28`, 80 `40→37`, 101 `40→30`, 555 `40→35` (**causa `base_form_id`**) y 156 `17→14`, 724 `36→34` (**causa duplicado por `region_id`**). No hay más: en los 40 evolucionados con >1 fila, el resto coincide con el mínimo. Regla simple y equivalente: `minimum_level = MIN(nivel no nulo de las filas)`.

---

## 6. RIESGOS

- **[alto] Re-seed sin corregir empeora los datos**: Obstagoon 10175→264, 19 aristas obsoletas, 38 formas no regeneradas. No ejecutar `db:seed --class=PokemonSeeder` hasta aplicar el fix completo.
- **[alto] Cambio de clave de caramelo**: 3 filas de `player_inventory` (`familia:22`, `familia:287`, `familia:109`) quedan “viejas”; los caramelos futuros irán a claves nuevas. `familia:22` y `familia:287` siguen siendo válidas para Meowth/Persian y Yamask/Cofagrigus, pero el jugador verá dos caramelos “Meowth”/“Yamask” distintos. **Sin migración**, impacta a 1 usuario por clave (no se revelan identidades).
- **[medio] Pokédex sigue mostrando familias mezcladas** por el cierre global de `FamiliaPokemon` (por diseño). Si el objetivo es que la Pokédex también separe, es otro alcance.
- **[medio] `unique('evolved_species_id')` impide modelar métodos múltiples** (Overqwil ×3, Eevee ×4): el fix debe quedarse en «una fila por evolucionado» o exigir migración de esquema.
- **[medio] CSV como fuente corrupta**: cualquier fix solo en el seeder se pierde en la próxima reinstalación; restaurar `pokemon.csv` es imprescindible.
- **[bajo] Cadenas de 1 miembro** (113, 106 al mover Cursola/Overqwil): `etapasDe` omite chains sin aristas y cae al fallback; ya ocurre hoy en 223 cadenas.
- **[bajo] `melmetal 809 ← meltan 808`** con chains 429/428: arista cross-chain ajena a esta tarea, detectada de paso.

---

## 7. PREGUNTAS ABIERTAS PARA `architect` (6)

1. ¿Se **restaura** `storage/data/pokemon.csv` (`git show efa5cfd^:storage/data/pokemon.csv`) como parte del plan, o se trata como dato externo intocable y se compensa en el seeder con un mapa id→región?
2. ¿El fix se limita a **una fila por evolucionado** (respetando el unique actual) o se abre migración a evolución **por forma** (`base_form_id` + unique compuesto)?
3. Para **902 basculegion**: ¿se deja con padre 550 (familia incompleta) o se extiende el seeder a «formas especiales con chain propia» (`-white-striped`, etc.)? Esto último afecta a otras 130 formas alternas omitidas.
4. ¿Se **migran** las 3 filas existentes de `player_inventory` `familia:22/287/109` (y futuras) a las claves regionales, o se acepta convivencia y se documenta en UI?
5. Niveles 156 y 724 (`region_id` 9): ¿criterio canónico = **primera fila**, **mínimo** o «la del `region_id` del juego»? Propongo mínimo (coincide con todas las demás).
6. ¿Se corrige también `melmetal`/`meltan` (chains 429 vs 428) en esta tarea o se registra como incidencia separada?

---

## 8. VERIFICACIÓN EJECUTADA

- `git status` (sin cambios en `database/` ni `storage/data/`), `git log --oneline` de los 5 CSV, `git show efa5cfd -- storage/data/pokemon.csv` (38 renombrados, 0 diferencias en otras columnas).
- Lectura íntegra de: `PokemonSeeder.php`, `CadenasEvolutivas.php`, `ResolvedorCadenasEvolutivas.php`, `ResolvedorFamiliaEvolutiva.php`, `FamiliaPokemon.php`, `ReclutamientoController.php`, `ReclutadoController.php` (referencias), `ServicioEvolucion.php`, `ItemCatalogo.php`, `ResolverBonosFamilia.php`, `HabitatLecturaRepository.php`, `CadenasFamiliaHabitatsTrait.php`, `DetallePokemonFamiliaQuery.php`, `HabitatsController.php`, `TransformadorResultadoExploracion.php`, `Nido.php`, migraciones de `pokemon_evolution` y unique constraints.
- Python sobre CSV: 29 filas `base_form_id`, 27 especies, 40 evolucionados multi-fila, 37 nombres duplicados, simulación completa de ambas pasadas y diff contra BD.
- **PostgreSQL en solo lectura** (`laravel@127.0.0.1:5432`) vía `php artisan tinker --execute` con `DB::table(...)->get()/count()/pluck()`: 1083 pokémon, 514 evoluciones, 0 huérfanas, membresía de chains 22/134/35/57/113/287/96/109/106/280, `nests`=3, `shelters`=4, `player_inventory` familia (121 filas/3 usuarios/119 claves; 3 filas en chains afectadas), `reclutados`=39, `huevos`=1, `nest_plantings`=2, `pokemon_habitat` de las 24 especies relevantes.
- **No se modificó ningún archivo del proyecto ni se escribió en BD.** No se leyó `.env`. Los volcados usados para el diff están en `/tmp/opencode/db_pokemon.tsv` y `/tmp/opencode/db_evo.tsv` (temporales, fuera del repo).
