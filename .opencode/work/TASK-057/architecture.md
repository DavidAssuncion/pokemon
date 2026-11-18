# TASK-057 — Diseño

- **Autor:** `architect` (subagente, sesión `ses_e1277101cffeZvhYID87cetPqf`).
- **Fecha:** 2026-11-18.
- **Alcance:** solo lectura; sin cambios de código, datos ni tests.
- **Nota de coordinación:** se reproduce el diseño del `architect` sin alterar sus
  conclusiones. El plan v1 puede acotar decisiones de alcance; se indicará en `plan.md`.

---

# DECISIONES

| # | Decisión | Elegida | Alternativa descartada | Motivo |
|---|---|---|---|---|
| D1 | Restauración de `pokemon.csv` | **Restaurar el fichero completo** desde `efa5cfd^` con `git show efa5cfd^:storage/data/pokemon.csv > storage/data/pokemon.csv`, previa comprobación de que el path está limpio | Script que reescriba solo los 38 `name` | Verificado: `efa5cfd^` vs `HEAD` son **38 filas, solo la columna `name`, mismos ids y mismo orden**, 40056 B, sin cambios posteriores en ese fichero. Restaurar el blob reproduce byte a byte el fichero con el que se sembró la BD actual (1083/514) y es un comando único y reversible. El script de 38 nombres es equivalente pero añade código y una oportunidad de desalineación |
| D2 | Modelo de `pokemon_evolution` | **Mantener una fila por evolucionado** (`unique(evolved_species_id)`) | Migración a evolución por forma (`evolved_pokemon_id` o `unique(evolved_species_id, base_form_id)`) | No es necesaria para este bug: los 8 padres mal salen porque el CSV **sí** identifica la forma base; el fallo está en que el seeder descarta `base_form_id`, no en el esquema. La migración obliga a tocar 12 consumidores, backfill y el `upsert` (perdería idempotencia por clave), y no existe ningún caso en el que dos padres del mismo evolucionado deban coexistir en el catálogo sembrado. Limitación aceptada y documentada: overqwil (904) colapsa sus 3 métodos en una fila |
| D3 | Algoritmo del seeder | **Tres fases**: clasificación → plan de evolución (padre, nivel, cadena) → materialización/upsert, con `base_form_id` como fuente del padre cuando no hay fila propia | Parche ad-hoc para los 8 ids afectados; mantener las dos pasadas actuales y añadir excepciones | El parche hardcode no es reproducible ni sobrevive a la próxima importación de CSV. Las tres fases garantizan que la cadena del evolucionado ya está resuelta **antes** del `upsert` de `pokemon`, que es el orden que hoy no existe |
| D4 | Criterio de `minimum_level` | **`MIN(nivel no nulo de todas las filas del `evolved_species_id`)`**, con `40` si todas son nulas | Nivel de la fila seleccionada; primera fila del CSV; nivel de la fila `base_form_id` | La fila `base_form_id` casi nunca trae nivel (es la causa de los 40 espurios) y "primera fila" depende del orden del fichero. El mínimo es determinista, no depende del orden, corrige exactamente los 7 niveles declarados y deja invariables los otros 507 (verificado por simulación). En 156/724 mínimo y primera fila **coinciden** (14 y 34), así que la regla no inventa datos |
| D5 | Alcance | **Dentro**: restauración del CSV, algoritmo, 9 chains, 7 niveles, limpieza guardada de evoluciones obsoletas. **Fuera**: basculegion 902, claves `familia:` de `player_inventory`, `melmetal/meltan`, formas especiales (`-white-striped` y las otras ~130), iconos de caramelo, cierre global de `FamiliaPokemon` | Corregir también basculegion, melmetal e inventario en esta tarea | Basculegion exige una allowlist de formas no regionales **y** una chain propia (su species comparte chain 280 con basculin-red): es otro diseño. Melmetal es un quirk de datos ajeno (429/428). Las 3 claves `familia:22/287/109` siguen siendo válidas tras el fix (52/53, 562/563, 215/461 permanecen en su chain), así que **no hay pérdida de datos** y migrarlas es cosmético |
| D6 | Filas obsoletas / idempotencia | **Borrado guardado** de `pokemon_evolution` con `whereNotIn('evolved_species_id', $planIds)` + guardas de volumen de CSV | No borrar nunca; borrado manual en SQL | El seeder es el **único** escritor de la tabla (no hay CRUD admin de evoluciones) y la clave única hace inmortales las aristas obsoletas: sin borrado, una futura importación deja residuos silenciosos. Las guardas (volumen mínimo de CSV y plan no vacío) impiden que un fichero truncado vacíe la tabla |
| D7 | Verificación sin tests | **Ejecución real del seeder dentro de `DB::beginTransaction()` + asserts + `DB::rollBack()`** desde un script en `/tmp/opencode`, más `php -l` y Pint. PHPStan se declara **no aplicable** al seeder | Crear un test; habilitar `database/` en PHPStan | Tests desactivados por regla del proyecto; `phpstan.neon` excluye `database/` (línea `excludePaths.analyse`) y no se modifica en esta tarea. La transacción con rollback demuestra el comportamiento real sin persistir nada y cubre los 9 chains, 15 aristas, conteos y huérfanas |

---

# CONTEXTO VERIFICADO (solo lectura, 2026-11-18)

1. `git status --porcelain -- storage/data/ database/seeders/` → **vacío**. El árbol tiene 83 entradas sucias, ninguna en el ámbito.
2. `efa5cfd^` → `HEAD` en `pokemon.csv`: 1176 filas, 38 diferencias, **únicamente `name`**. `git log` confirma que `efa5cfd` es el último commit sobre ese fichero.
3. `pokemon_evolution.csv`: 542 filas, **29 con `base_form_id`**, 27 especies, 904 ×3 (idénticas). 17 especies tienen además fila propia.
4. Re-simulación independiente (CSV restaurado, reglas D3/D4): **1083 pokémon, 514 evoluciones, 9 chains, 15 aristas (8 padres + 7 niveles), 0 huérfanas, 13 aristas cross-chain**. Coincide con el analista.
5. **Hallazgo nuevo A** — `pokemon_evolution.evolves_from_species_id` **no tiene FK** (solo `evolved_species_id` → `pokemon.id` con `ON DELETE CASCADE`). La BD no protege contra huérfanas: hay que verificarlo por consulta.
6. **Hallazgo nuevo B** — La BD tiene los 1083 `name` en minúsculas (`meowth`), pero el seeder aplica `Str::ucfirst` desde `cb8cece` (task-010). **Un re-seed reescribe el `name` de las 1083 filas** (`Meowth`). Es un efecto colateral preexistente, no de este cambio, pero debe aprobarse: es el único cambio de datos fuera de las 24 filas.
7. **Hallazgo nuevo C** — Las 9 chains nuevas resuelven su caramelo por «menor `species_id`» (`ItemCatalogo::resolverFamilia`, `TransformadorResultadoExploracion::pokemonBaseDeCadena`), que en estas chains es **el evolucionado** (863 < 10161). Faltan 8 iconos `candy_pokemon/{862,863,864,865,866,867,903,904}.webp`; **los 8 existen en `public/images/iconos_webp/`**. Solo `980` está ya en `candy_pokemon/`.
8. **Hallazgo nuevo D** — Cross-chain actual = **14** (862 ya está mal: 134 vs 12134, y por eso es el único registrado en esa consulta); los otros 8 no aparecen porque comparten chain con su padre *equivocado*. Post-fix = **13**.
9. **Hallazgo nuevo E** — `BuildShowdownMovesets::normalizar()` hace `mb_strtolower`, y `ReclutadosSeeder` compara con `LOWER(name)`: el cambio de capitalización no rompe búsquedas por nombre.

---

# DISEÑO DEL ALGORITMO

## Interpretación semántica de `base_form_id` (base del diseño)

Una fila `(evolved_species_id, base_form_id)` significa «esta evolución ocurre desde **esta forma** del pre-evolucionado». El seeder modela cada **forma** como su propio `pokemon.id` (`species_id = id` para regionales), de modo que:

- Si el evolucionado **tiene fila propia** (`base_form_id` vacío), esa fila describe su forma por defecto y el padre se toma de `pokemon_species.evolves_from_species_id` (+ re-parenting regional actual). **Precedencia obligatoria**: 28 `sandslash` (default) tiene fila con base `10101` que en realidad describe `sandslash-alola` (10102); aplicar la regla regional ahí daría `sandslash ← sandshrew-alola`, que es falso.
- Si el evolucionado **solo** tiene filas con `base_form_id` y es la forma por defecto, su único padre posible es esa forma base: `perrserker ← meowth-galar`.

## Estructuras de datos

```text
$speciesById            : array<int, array<string,string>>            // id => fila species
$pokemonRows            : list<array<string,string>>                  // pokemon.csv restaurado
$statsByPokemon         : array<int, list<row>>                       // pokemon_id => filas
$typesByPokemon         : array<int, list<row>>
$evolutionRowsByEvolved : array<int, list<row>>                       // AGRUPADO (sustituye al "última gana")
$seeded                 : array<int, array{region:?string, species:array<string,string>}>
$chainById              : array<int, int|null>                        // cadena ya resuelta por pokemon
$regionalPokemonBySpecies : array<string, array<int,int>>             // region => species original => pokemon id
$evolutionPlan          : array<int, array{parent:int, level:int}>    // evolved id => arista final
```

## Pseudocódigo (PHP de alto nivel, sin implementar)

```php
public function run(): void
{
    // ---------- Fase 0: carga + guardas de volumen ----------
    $speciesById            = $this->indexSpecies($this->loadCsv(storage_path('data/pokemon_species.csv')));
    $pokemonRows            = $this->loadCsv(storage_path('data/pokemon.csv'));
    $statsByPokemon         = $this->groupBy($this->loadCsv(...'pokemon_stats.csv'), 'pokemon_id');
    $typesByPokemon         = $this->groupBy($this->loadCsv(...'pokemon_types.csv'), 'pokemon_id');
    $evolutionRowsByEvolved = $this->groupBy($this->loadCsv(...'pokemon_evolution.csv'), 'evolved_species_id');

    $this->guardVolumes($pokemonRows, $speciesById, $evolutionRowsByEvolved);
    // lanza RuntimeException si pokemon.csv < 1000, species < 1000 o evoluciones < 500
    // (protege el borrado de obsoletas de un fichero truncado)

    $seeded = $chainById = $regionalPokemonBySpecies = [];

    // ---------- Fase 1: clasificación (omitir | normal | regional) ----------
    foreach ($pokemonRows as $row) {
        $species = $speciesById[(int) $row['species_id']] ?? null;
        if ($species === null) { continue; }

        $id = (int) $row['id'];
        $region = $row['is_default'] === '1' ? null : $this->regionOf($row['name']);

        if ($row['is_default'] !== '1' && $region === null) {
            continue;                       // 93 formas especiales omitidas (incl. 10247 basculin-white-striped)
        }

        $seeded[$id] = ['region' => $region, 'species' => $species];
        $chainById[$id] = $this->chainIdToStore($region, $species);

        if ($region !== null) {
            $regionalPokemonBySpecies[$region][(int) $row['species_id']] = $id;
        }
    }

    // ---------- Fase 2: plan de evolución (ANTES de materializar pokemon) ----------
    $evolutionPlan = [];

    foreach ($seeded as $id => $entry) {
        $speciesParent = $this->intOrNull($entry['species']['evolves_from_species_id']);
        if ($speciesParent === null) { continue; }          // sin pre-evolución

        $rows  = $evolutionRowsByEvolved[$id] ?? [];
        $level = $this->minimumLevelOf($rows) ?? self::DEFAULT_MINIMUM_LEVEL;   // 40
        $hasOwnRow = $this->anyRowWithoutBaseFormId($rows);

        $parent = $speciesParent;

        // (b) Solo filas con base_form_id y es la forma por defecto → padre = forma base
        if (! $hasOwnRow && $rows !== [] && $entry['region'] === null) {
            $baseFormId = (int) $rows[0]['base_form_id'];   // 904: las 3 filas son idénticas

            if (isset($seeded[$baseFormId])) {
                $parent = $baseFormId;
                $chainById[$id] = $chainById[$baseFormId];  // invariante intra-chain
            }
            // else: FALLBACK DOCUMENTADO (902 basculegion)
            //   parent = species.evolves_from_species_id (550), chain normal (280)
        }

        // (a) Re-parenting regional vigente (solo formas regionales del propio evolucionado)
        if ($entry['region'] !== null) {
            $parent = $regionalPokemonBySpecies[$entry['region']][$speciesParent] ?? $speciesParent;
        }

        $evolutionPlan[$id] = ['parent' => $parent, 'level' => $level];
    }

    // ---------- Fase 3: materializar filas (usa $chainById ya corregido) ----------
    // pokemon:    ['evolution_chain_id' => $chainById[$id], 'species_id' => $region === null ? (int)$row['species_id'] : $id, 'name' => Str::ucfirst($row['name']), ...]
    // stats/types: sin cambios

    // ---------- Fase 4: upsert + convergencia ----------
    // upsert pokemon(chunk 500, ['id'], [...incl. evolution_chain_id...])
    // upsert pokemon_stats, pokemon_types (sin cambios)
    // upsert pokemon_evolution(rows de $evolutionPlan, ['evolved_species_id'], ['evolves_from_species_id','minimum_level'])
    // if ($evolutionPlan !== []) { PokemonEvolution::whereNotIn('evolved_species_id', array_keys($evolutionPlan))->delete(); }
}

private function minimumLevelOf(array $rows): ?int
{
    // min de los minimum_level no vacíos; null si todas vacías
}
```

**Orden de pasadas y por qué:** Fase 2 completa antes de Fase 3. La chain de `863` depende de la chain de `10161`, que se calcula en Fase 1 con la fórmula regional; ninguna forma base es a su vez de clase (b) (los `base_form_id` son 10091–10253 y los 9 evolucionados son 862–980, conjuntos disjuntos: no hace falta resolución topológica). Los `upsert` respetan FK: `pokemon` antes que `pokemon_evolution`.

**Compatibilidad con lo existente:** se conservan `chainIdToStore()`, `regionOf()` (con `str_contains` para sufijos compuestos), `loadCsv()`, el `species_id = id` de regionales, el re-parenting regional y los tamaños de chunk.

---

# INVARIANTES Y CASOS LÍMITE

**Invariantes**

| Id | Invariante |
|---|---|
| I1 | `count($seeded) === 1083` con el CSV restaurado (y 1045 sin él: diferencia detectable). |
| I2 | Toda arista del plan tiene `parent ∈ $seeded` → 0 huérfanas (la BD no lo impone: no hay FK en `evolves_from_species_id`). |
| I3 | Una fila por `evolved_species_id` (unique) → `count(pokemon_evolution) === 514`. |
| I4 | Para las 9 chains corregidas, `chain(evolved) === chain(parent)` y ambos miembros comparten chain. |
| I5 | `minimum_level` es siempre entero no nulo; `MIN` de niveles no nulos o 40. |
| I6 | Idempotencia: `run(); run()` produce los mismos 1083/514 y los mismos 24 valores que `run()` una vez. |
| I7 | El seeder **no** borra `pokemon`, **no** inserta ids ausentes del CSV y **no** toca `pokemon_habitat`, `nests`, `shelters`, `player_inventory`. |
| I8 | `LOWER(pokemon.name)` es invariante al re-seed (solo cambia la capitalización de la primera letra). |
| I9 | Las 13 aristas cross-chain preexistentes no aumentan (14 → 13 al arreglar 862). |
| I10 | Un CSV truncado no vacía tablas: `guardVolumes()` aborta antes de cualquier escritura. |

**Casos límite**

- **904 overqwil ×3 filas idénticas**: dedupe por clave; `min(niveles nulos)` → 40; padre 10234.
- **902 basculegion-male**: única fila con `base_form_id` = 10247, no sembrada ⇒ fallback: padre 550, chain 280 (comportamiento actual, familia incompleta aceptada). Documentar en el docblock.
- **28/38/51/53/59/75/76/78/80/89/101/199/555/571/706/20/264** (clase a): la fila `base_form_id` se ignora para el padre; solo aporta el nivel (vía mínimo). `199 slowking` ← 79 aunque su fila base sea `slowpoke-galar`; `76 golem` ← 75 aunque su fila base sea `graveler-alola`.
- **863/864/865/866/867/903/904/980/862** (clase b): padre = forma base, chain = chain de la forma base.
- **156 quilava / 724 decidueye**: dos filas propias con `region_id = 9` y niveles distintos → mínimo (14/34). Empate con «primera fila»: decisión de producto, no de datos.
- **10168 mr-mime-galar ← 439 mime-jr** y **809 melmetal ← 808 meltan**: aristas cross-chain preexistentes, se preservan.
- **Chains que quedan de 1 miembro** tras mover 864/867/903/904/865 (113, 287, 109, 106, 35): `CadenasEvolutivas` y `ResolvedorCadenasEvolutivas` ya tienen fallback; hay 223 chains así hoy.
- **38 nombres restaurados**: en la BD actual no cambia el conjunto de ids ni `species_id` (verificado: `10091.species_id=10091`, `chain=11007`, `10161.chain=12022`); solo reaparecen en una BD nueva, que es justo lo que hace reproducible el fix.
- **`unique(evolved_species_id)`**: overqwil conserva una sola fila (la de menor nivel efectivo); no hay regresión respecto a hoy.

---

# ARCHIVOS A MODIFICAR

| Archivo | Responsable | Cambio |
|---|---|---|
| `storage/data/pokemon.csv` | `database` | Restauración íntegra desde `efa5cfd^` (38 líneas, solo `name`) |
| `database/seeders/PokemonSeeder.php` | `database` | Agrupación de filas de evolución, plan de evolución en 3 fases, `minimumLevelOf`, reasignación de chain, borrado guardado, guardas de volumen y docblocks |
| `public/images/candy_pokemon/{862,863,864,865,866,867,903,904}.webp` | `database`/desarrollador — **opcional, requiere aprobación** | Copia desde `public/images/iconos_webp/` (ya existen los 9 originales) para evitar imágenes rotas en los caramelos de las chains nuevas |

**Prohibidos en esta tarea:** `tests/**` (no se crean ni se tocan), `database/migrations/**` (no hay cambio de esquema), `app/Support/FamiliaPokemon.php`, `app/Support/ItemCatalogo.php`, `src/**`, `app/Livewire/**`, `phpstan.neon`, `pint.json`, `composer.json`, `player_inventory` (datos), cualquier otro de los 83 paths sucios, `.env` y `storage/data/pokemon_{species,stats,types,evolution}.csv`.

---

# CRITERIOS DE ACEPTACIÓN

**CA-1 — Las 9 chains (tabla de verdad, post-fix)**

| `pokemon` | nombre | `evolution_chain_id` | padre (`evolves_from_species_id`) | `minimum_level` |
|---|---|---|---|---|
| 862 | obstagoon | 134 → **12134** | 10175 (sin cambio) | 35 |
| 863 | perrserker | 22 → **12022** | 52 → **10161** | 28 |
| 864 | cursola | 113 → **12113** | 222 → **10173** | 38 |
| 865 | sirfetchd | 35 → **12035** | 83 → **10166** | 40 |
| 866 | mr-rime | 57 → **12057** | 122 → **10168** | 42 |
| 867 | runerigus | 287 → **12287** | 562 → **10179** | 40 |
| 903 | sneasler | 109 → **13109** | 215 → **10235** | 40 |
| 904 | overqwil | 106 → **13106** | 211 → **10234** | 40 |
| 980 | clodsire | 96 → **14096** | 194 → **10253** | 20 |

**CA-2 — Los 7 niveles corregidos** (padre sin cambio)

| `pokemon` | nombre | antes | después |
|---|---|---|---|
| 28 | sandslash | 40 | **22** |
| 53 | persian | 40 | **28** |
| 80 | slowbro | 40 | **37** |
| 101 | electrode | 40 | **30** |
| 555 | darmanitan-standard | 40 | **35** |
| 156 | quilava | 17 | **14** |
| 724 | decidueye | 36 | **34** |

**CA-3 — Cardinalidad y orfandad**: `pokemon` = **1083**, `pokemon_evolution` = **514**, huérfanas = **0**, cross-chain = **14 → 13**, chains de 1 miembro aumentan en 5 (113, 287, 109, 106, 35) y disminuyen en 9 (las nuevas), sin otras variaciones.

**CA-4 — Cadena normal intacta**: `52 meowth` solo evoluciona a **53 persian** (`52` targets = `[53]`); `10161 meowth-galar` solo a **863**; `10174 zigzagoon-galar` → `10175` → `862` en chain 12134; `264 linoone` (chain 134) → padre `263` y conserva su fila; `10107 meowth-alola` sigue en chain **11022** con padre `10106`… (re-parenting regional intacto).

**CA-5 — Datos restaurados**: los 38 ids `10091–10115` y `10161–10180` existen con nombre sufijado (`LOWER(name) = 'meowth-galar'` para 10161) y sin duplicados de nombre con su forma normal; en la BD actual solo cambia la capitalización.

**CA-6 — Idempotencia**: dos ejecuciones seguidas dejan 1083/514 y los 24 valores de CA-1/CA-2 idénticos; 0 filas obsoletas (`evolved_species_id` fuera del plan).

**CA-7 — No regresión fuera de alcance**: `melmetal 809←808` (429/428) y las 12 regionales sin pre-evolución regional siguen exactamente como hoy; `902` sigue con padre `550` y chain `280`.

**CA-8 — Sin cambios en otros datos**: `nests`, `shelters`, `player_inventory`, `reclutados`, `huevos`, `nest_plantings`, `pokemon_habitat` conservan sus conteos (3 / 4 / 121 filas `familia:` / 39 / 1 / 2 / sin cambio).

---

# VERIFICACIÓN REQUERIDA (sin tests)

**V0 — Pre-flight (solo lectura, obligatorio antes de tocar nada)**

```bash
cd /home/david/pokemon
git status --porcelain -- storage/data/pokemon.csv database/seeders/     # debe estar vacío
git diff --quiet efa5cfd^ HEAD -- storage/data/pokemon.csv; echo $?      # debe ser 1 (hay 38 diffs)
git diff --stat efa5cfd^ HEAD -- storage/data/pokemon.csv                # 38 insertions(+), 38 deletions(-)
```
Si aparece cualquier cambio local en esos dos paths: **detener** y reportar (no sobrescribir).

**V1 — Restauración y prueba de equivalencia exacta**

```bash
git show efa5cfd^:storage/data/pokemon.csv > storage/data/pokemon.csv
git diff --quiet efa5cfd^ -- storage/data/pokemon.csv; echo $?           # 0 ⇒ restauración byte a byte
git diff --stat -- storage/data/pokemon.csv                              # 38 (+/-), solo columna name
wc -l storage/data/pokemon.csv                                           # 1177
```

**V2 — Análisis estático**

```bash
php -l database/seeders/PokemonSeeder.php            # No syntax errors
vendor/bin/pint --dirty --format agent               # estilo (solo ficheros tocados)
composer analyse                                     # opcional: database/ está EXCLUIDO en phpstan.neon
```
PHPStan **no cubre** el seeder (`excludePaths.analyse: database/`). No se modifica `phpstan.neon` en esta tarea; se declara «no aplica» y se compensa con V3.

**V3 — Comprobación funcional sin persistencia** (`/tmp/opencode/task057_verify.php`, fuera del repo)

```php
<?php
use Database\Seeders\PokemonSeeder;
use Illuminate\Support\Facades\DB;

$fail = [];
$ok = function (bool $c, string $m) use (&$fail) { if (! $c) { $fail[] = $m; } };
$chains   = [862 => 12134, 863 => 12022, 864 => 12113, 865 => 12035, 866 => 12057,
             867 => 12287, 903 => 13109, 904 => 13106, 980 => 14096];
$parents  = [863 => 10161, 864 => 10173, 865 => 10166, 866 => 10168,
             867 => 10179, 903 => 10235, 904 => 10234, 980 => 10253, 862 => 10175];
$levels   = [28 => 22, 53 => 28, 80 => 37, 101 => 30, 555 => 35, 156 => 14, 724 => 34];

$assertState = function () use ($ok, $chains, $parents, $levels) {
    $ok(DB::table('pokemon')->count() === 1083, 'pokemon != 1083');
    $ok(DB::table('pokemon_evolution')->count() === 514, 'evoluciones != 514');
    $ok(DB::table('pokemon_evolution as e')->leftJoin('pokemon as p', 'p.id', '=', 'e.evolves_from_species_id')
        ->whereNull('p.id')->count() === 0, 'huerfanas > 0');
    foreach ($chains as $id => $chain) {
        $ok((int) DB::table('pokemon')->where('id', $id)->value('evolution_chain_id') === $chain, "chain $id");
    }
    foreach ($parents as $id => $parent) {
        $ok((int) DB::table('pokemon_evolution')->where('evolved_species_id', $id)
            ->value('evolves_from_species_id') === $parent, "parent $id");
    }
    foreach ($levels as $id => $lvl) {
        $ok((int) DB::table('pokemon_evolution')->where('evolved_species_id', $id)
            ->value('minimum_level') === $lvl, "level $id");
    }
    $ok(DB::table('pokemon_evolution')->where('evolves_from_species_id', 52)
        ->orderBy('evolved_species_id')->pluck('evolved_species_id')->all() === [53], 'meowth normal -> persian');
    $ok(DB::table('pokemon_evolution')->where('evolves_from_species_id', 10161)
        ->pluck('evolved_species_id')->all() === [863], 'meowth-galar -> perrserker');
    $ok(DB::table('pokemon_evolution')->where('evolves_from_species_id', 10174)
        ->pluck('evolved_species_id')->all() === [10175], 'zigzagoon-galar -> linoone-galar');
    $cross = DB::table('pokemon_evolution as e')
        ->join('pokemon as p', 'p.id', '=', 'e.evolved_species_id')
        ->join('pokemon as q', 'q.id', '=', 'e.evolves_from_species_id')
        ->whereColumn('p.evolution_chain_id', '<>', 'q.evolution_chain_id')->count();
    $ok($cross === 13, "cross-chain = $cross (esperado 13)");
    $ok(strtolower((string) DB::table('pokemon')->where('id', 10161)->value('name')) === 'meowth-galar', 'nombre 10161');
    $ok(DB::table('pokemon')->whereIn('id', [10091,10101,10161,10175,10180])->count() === 5, 'formas restauradas');
    $ok(DB::table('pokemon_evolution')->where('evolved_species_id', 902)
        ->where('evolves_from_species_id', 550)->exists(), 'fallback 902');
};

DB::beginTransaction();
try {
    (new PokemonSeeder())->run();
    $assertState();
    (new PokemonSeeder())->run();      // idempotencia
    $assertState();
} catch (Throwable $e) {
    DB::rollBack();
    throw $e;
}
DB::rollBack();

// Prueba de no-persistencia: los valores vuelven al estado previo
$ok((int) DB::table('pokemon_evolution')->where('evolved_species_id', 863)->value('evolves_from_species_id') === 52, 'rollback parent 863');
$ok((int) DB::table('pokemon')->where('id', 863)->value('evolution_chain_id') === 22, 'rollback chain 863');

echo $fail === [] ? "OK\n" : "FALLOS:\n- ".implode("\n- ", $fail)."\n";
exit($fail === [] ? 0 : 1);
```

```bash
php artisan tinker /tmp/opencode/task057_verify.php; echo "exit=$?"
```

Cada assert mapea un criterio: `pokemon`/`evoluciones`/huérfanas → CA-3; `$chains`+`$parents` → CA-1; `$levels` → CA-2; los tres `targets` → CA-4; `nombre 10161` + formas restauradas → CA-5; doble `run()` → CA-6; `cross === 13` y fallback 902 → CA-7; el bloque post-`rollBack()` demuestra que **no se persistió nada**.

**V4 — Tras la aprobación, si se aplica al entorno real (fuera de esta tarea)**

```bash
pg_dump -Fc -t pokemon -t pokemon_evolution "$DB" > /tmp/opencode/task057_pre.dump   # no usar .env en prompts
php artisan db:seed --class=PokemonSeeder --no-interaction
# repetir los SELECT de V3 fuera de transacción
```

---

# RIESGOS Y MITIGACIONES

| Sev. | Riesgo | Mitigación |
|---|---|---|
| **alto** | **Re-seed sin aplicar el fix**: Obstagoon 10175→264, 38 formas renombradas a nombre desnudo, 19 aristas obsoletas | Orden de ejecución obligatorio: V1 → código → V3 → solo entonces seed real. Prohibido `db:seed --class=PokemonSeeder` antes |
| **alto** | **Capitalización de 1083 nombres** (`cb8cece`) al re-sembrar; cambio visible y no revertible con un re-seed | Declararlo como efecto colateral conocido y aprobarlo (pregunta Q2). Capturar pre-imagen de `pokemon` (1083 filas) antes del run real. Invariante I8 y búsquedas por nombre ya son `LOWER()` |
| **alto** | **Borrado de obsoletas** con CSV truncado | `guardVolumes()` (≥1000 pokémon, ≥1000 species, ≥500 evoluciones) antes de cualquier escritura; borrado solo si el plan no está vacío |
| **medio** | **Iconos de caramelo**: base de las 9 chains nuevas = evolucionado (863…), 8 iconos ausentes → imagen rota al ganar caramelos de esas familias | Copiar los 8 `iconos_webp/{862,863,864,865,866,867,903,904}.webp` a `candy_pokemon/` (opcional, sujeto a aprobación) o abrir tarea de assets. No afecta a datos ni a la corrección |
| **medio** | **Claves `familia:` de `player_inventory`**: aparecen claves nuevas (12022, 12134…) sin inventario y las 3 existentes quedan «antiguas» | Sin migración: `22`, `287` y `109` siguen siendo chains válidas de las familias normales (52/53, 562/563, 215/461), no hay pérdida. Documentar; si se quiere cosmética, tarea aparte |
| **medio** | **`unique(evolved_species_id)`** impide modelar métodos múltiples (overqwil ×3, Eevee) | Aceptado y documentado; no es una regresión (hoy ya se colapsa) |
| **bajo** | 13 aristas cross-chain preexistentes (melmetal, 12 regionales sin pre-evo regional) | Fuera de alcance; CA-7 fija que no aumenten |
| **bajo** | Chains de 1 miembro tras mover Cursola/Overqwil/etc. | Fallback ya existente en `CadenasEvolutivas`/`ResolvedorCadenasEvolutivas`; 223 casos hoy |
| **bajo** | Pokédex sigue mostrando familias mezcladas por el cierre global de `FamiliaPokemon` | Es por diseño (documentado en el análisis); otro alcance |
| **bajo** | El seeder no corre en transacción al invocarlo con Artisan (estado parcial si falla) | Idempotente: re-ejecutar recupera. Verificación previa en transacción (V3) y `pg_dump` antes del run real |

---

# PLAN DE ROLLBACK

**Código (2 ficheros, sin commitear):**
```bash
git diff --quiet -- storage/data/pokemon.csv database/seeders/PokemonSeeder.php   # confirmar que solo esos 2 están sucios
git restore --source=HEAD -- storage/data/pokemon.csv database/seeders/PokemonSeeder.php
```
Si ya se hubiera commiteado: `git revert <sha>` (nunca `reset --hard`). Alternativa sin git: aplicar el parche inverso guardado en `/tmp/opencode/task057.patch`.

**Datos:** la única mutación es el re-seed. Antes del run real, `pg_dump -Fc -t pokemon -t pokemon_evolution` (o un volcado TSV de las 24 filas afectadas) → restaurar con `pg_restore`/`UPDATE` dirigido. El seeder es idempotente y convergente: re-ejecutarlo tras revertir el código **deshace** el fix, pero **no** recupera los nombres en minúsculas (los reescribe capitalizados), por eso la pre-imagen debe incluir `pokemon.name`. Estado resultante si solo se revierte el código y se vuelve a sembrar: Obstagoon 862→264, los 8 padres a la chain normal, los 7 niveles a 40 y las 38 formas otra vez sin sufijo (1045/495).

---

# PREGUNTAS ABIERTAS PARA EL DESARROLLADOR

Ninguna bloquea la implementación; las cuatro tienen un valor por defecto ya adoptado en el diseño. El desarrollador puede vetarlas en la aprobación.

1. **(Q1 — alcance de assets)** ¿Se incluyen las 8 copias `iconos_webp/*.webp → candy_pokemon/*.webp` para evitar imágenes rotas en los caramelos de las chains nuevas? *Por defecto: no incluirlas en esta tarea; queda como tarea de assets. Consecuencia si no se hace: 8 caramelos familiares nuevos sin icono.*
2. **(Q2 — efecto colateral de nombres)** ¿Se acepta que el re-seed real normalice la capitalización de los 1083 `name` (herencia de `cb8cece`)? *Por defecto: sí; se documenta y se captura pre-imagen. Consecuencia si no se acepta: no se puede aplicar el fix a la BD existente con el seeder, habría que hacer un UPDATE dirigido de 24 filas y dejar el catálogo divergente del código.*
3. **(Q3 — niveles 156/724)** ¿Se confirma `min` (14 y 34) como criterio canónico frente a «primera fila» o «nivel del `region_id` del juego»? *Por defecto: mínimo (coincide con las 507 filas restantes y con la primera fila en estos dos casos).*
4. **(Q4 — basculegion)** ¿Se acepta dejar 902 con padre 550 y documentar la familia incompleta, en lugar de abrir la allowlist de formas especiales? *Por defecto: sí, fuera de alcance.*

---

# BORRADOR DE ADR

- **Título:** ADR-057 — Resolución de evoluciones por `base_form_id` en `PokemonSeeder`
- **Estado:** Propuesto (pendiente de aprobación del plan v1)
- **Contexto:** `pokemon_evolution.csv` modela evoluciones por **forma** (`evolved_species_id` + `base_form_id`), pero el seeder indexaba con «última fila gana» e ignoraba `base_form_id`, tomando el padre de `pokemon_species`. Además `pokemon.csv` perdió los sufijos de 38 formas regionales en `efa5cfd`. Resultado: 9 evolucionados en la chain normal (Meowth→Perrserker, Linoone→Obstagoon…), 8 padres incorrectos, 7 niveles corrompidos y un catálogo no reproducible desde cero.
- **Decisión:** (1) restaurar `storage/data/pokemon.csv` desde `efa5cfd^`; (2) mantener una fila por evolucionado; (3) resolver en tres fases con precedencia «fila propia > fila de forma base», derivando la chain del evolucionado de la chain de su padre; (4) `minimum_level = min(niveles no nulos) ?? 40`; (5) limpieza guardada de evoluciones obsoletas; (6) verificación estática + transacción con rollback, sin tests.
- **Consecuencias:** 1083 pokémon y 514 evoluciones reproducibles; 9 chains y 15 aristas corregidas; 0 huérfanas; 13 aristas cross-chain preexistentes se conservan. Efectos aceptados: capitalización de nombres al re-sembrar, nuevas claves `familia:` sin inventario previo, overqwil con un único método, basculegion y melmetal fuera de alcance. Reversible con `git restore` de 2 ficheros y `pg_dump` previo.

---

# REVISIÓN v2 — Restricción del desarrollador: sin sufijos

- **Fecha:** 2026-11-18.
- **Autor:** `architect` (misma sesión).
- **Motivo:** el desarrollador rechazó restaurar los sufijos regionales en
  `storage/data/pokemon.csv`. Se revalida la alternativa de detección por ID.
- **Resultado:** funcionalmente equivalente: **1083 pokémon, 514 evoluciones, 0
  huérfanas, 9 chains y 15 aristas**, idéntico a la vía de restaurar el CSV. No se
  modificó ningún archivo ni se escribió en BD.

## Decisiones cambiadas

| # | Antes (v1) | Ahora (v2) | Motivo / verificación |
|---|---|---|---|
| **D1** | Restaurar `storage/data/pokemon.csv` desde `efa5cfd^` | **`storage/data/pokemon.csv` no se toca.** Único fichero de código: `database/seeders/PokemonSeeder.php` | Restricción del desarrollador. Verificado que el CSV actual conserva los 1176 ids y que la única diferencia con `efa5cfd^` son 38 `name`; nada funcional depende de esos sufijos (`regionOf()` solo tiene un llamador: el propio seeder) |
| **D1b** | Detección por sufijo en el nombre (`regionOf()`, `str_contains`) | **`private const REGION_BY_ID` (58 entradas) + `private function regionById(int $id): ?string`**; se **elimina `regionOf()`** | Verificado: 58 ids, **todos con `is_default=0`**, repartidos Alola 18 (`10091,10092,10100–10115`), Galar 20 (`10161–10180`), Hisui 16 (`10229–10244`), Paldea 4 (`10250–10253`). Los 5 vecinos `10245–10249` son exactamente los falsos positivos excluidos (`dialga-origin`, `palkia-origin`, `basculin-white-striped`, `basculegion-female`, `enamorus-therian`). Ningún `is_default=0` fuera del mapa contiene token regional en el nombre, así que el mapa es la **única** fuente de verdad |
| **D1c** | — | **`REGION_CODES` y `chainIdToStore()` no cambian** | Mismos códigos (alola 1, galar 2, hisui 3, paldea 4) y misma fórmula `10000 + code*1000 + chain`. La simulación produce exactamente las chains esperadas (12022, 12134, 12113, 12035, 12057, 12287, 13109, 13106, 14096), luego la fórmula no se toca |
| **D-control** | — | **Guarda dura sobre `base_form_id` no sembradas** + **log agregado único solo en salida verbosa** para formas omitidas | Un warning por fila produciría **93 líneas por seed** (151 `is_default=0`, 58 mapeadas): ruido no accionable. La señal realmente útil es una forma base no sembrada, que produce un padre silenciosamente incorrecto |
| **D5** | Fuera: basculegion, inventario, melmetal, iconos | **Sin cambios** + se añaden explícitamente a «fuera de alcance y documentado»: `ReclutadosSeeder`, `BuildShowdownMovesets`, `PokemonHabitatSeeder` | Ver riesgos; ninguno bloquea el fix y los tres tienen coste/riesgo propio. Postura: **no tocar código ajeno**; documentar y, si acaso, abrir tickets |
| **D7** | Transacción + rollback + asserts | Igual, **sin V1 de restauración**; V1 pasa a ser comprobación estática del mapa y de que el CSV está intacto | — |

## Guardas concretas (D-control)

```php
// (1) Guarda dura, una vez por seed: toda forma base citada debe estar sembrada
//     o pertenecer a la allowlist de fallback explícita.
private const FALLBACK_UNSEEDED_BASE_FORMS = [10247]; // basculin-white-striped (familia incompleta aceptada)

foreach ($evolutionRowsByEvolved as $evolvedId => $rows) {
    foreach ($rows as $row) {
        $base = (int) $row['base_form_id'];
        if ($base === 0 || isset($seeded[$base]) || in_array($base, self::FALLBACK_UNSEEDED_BASE_FORMS, true)) {
            continue;
        }
        throw new RuntimeException("base_form_id {$base} de {$evolvedId} no está sembrada ni en la allowlist");
    }
}

// (2) Guarda de cobertura del mapa (58 comprobaciones, coste nulo): impide que un
//     id mapeado desaparezca del CSV sin que nadie se entere.
foreach (self::REGION_BY_ID as $id => $region) {
    if (($pokemonRowsById[$id]['is_default'] ?? null) !== '0') {
        throw new RuntimeException("REGION_BY_ID contiene {$id}, que no es una forma no-default del CSV");
    }
}

// (3) Log agregado: UNA línea, y solo si la salida es verbosa. Sin -v, silencio total.
if ($unmappedFormIds !== [] && ($this->command?->getOutput()->isVerbose() ?? false)) {
    Log::info('PokemonSeeder: formas no regionales omitidas', [
        'count' => count($unmappedFormIds),
        'ids'   => $unmappedFormIds,   // 93 hoy
    ]);
}
```

Justificación: (1) convierte la única ambigüedad peligrosa (padre incorrecto por forma
base ausente) en un fallo determinista, en vez de un `40`/padre normal silencioso; (2)
detecta mapas obsoletos con 58 comparaciones; (3) mantiene la visibilidad para futuras
importaciones sin inundar la salida de `db:seed`.

## Pseudocódigo ajustado

Solo cambian la Fase 1 (detección) y se añaden las guardas. Las reglas (a)/(b), el
`MIN` de niveles, `base_form_id` como padre/cadena, el re-parenting regional, el
borrado guardado y las guardas de volumen quedan igual.

```php
private const REGION_BY_ID = [
    // Alola (18)
    10091 => 'alola', 10092 => 'alola',
    10100 => 'alola', 10101 => 'alola', /* … 10102–10115 … */
    // Galar (20)
    10161 => 'galar', /* … 10162–10180 … */
    // Hisui (16)
    10229 => 'hisui', /* … 10230–10244 … */
    // Paldea (4)
    10250 => 'paldea', /* … 10251–10253 … */
];

public function run(): void
{
    // Fase 0 — carga + guardas de volumen (SIN CAMBIOS)
    //   pokemonRows ahora también se indexa por id para la guarda de cobertura:
    $pokemonRowsById = $this->indexById($pokemonRows);
    $this->guardVolumes(...);
    $this->guardRegionMapCoverage($pokemonRowsById);   // NUEVO

    // Fase 1 — clasificación: SOLO cambia la detección de región
    foreach ($pokemonRows as $row) {
        $species = $speciesById[(int) $row['species_id']] ?? null;
        if ($species === null) { continue; }

        $id = (int) $row['id'];
        $region = $row['is_default'] === '1' ? null : $this->regionById($id);   // ← antes: regionOf($row['name'])

        if ($row['is_default'] !== '1' && $region === null) {
            $unmappedFormIds[] = $id;      // 93 hoy; solo para el log verboso
            continue;
        }
        // … resto idéntico a v1: $seeded, $chainById = chainIdToStore($region, $species),
        //   $regionalPokemonBySpecies[$region][(int) $row['species_id']] = $id
    }

    // Fase 1b — NUEVA guarda de forms base (antes de materializar nada)
    $this->guardBaseFormsAreSeeded($evolutionRowsByEvolved, $seeded);

    // Fase 2 — plan de evolución: SIN CAMBIOS
    //   (a) fila propia → species.evolves_from + re-parenting regional
    //   (b) solo base_form_id y region === null → parent = base, chain = chain(base)
    //   MIN de niveles no nulos ?? 40; 904 deduplicado por clave
    //   fallback 902 → parent 550, chain 280

    // Fase 3 — materializar (usa $chainById ya corregido): SIN CAMBIOS
    // Fase 4 — upsert + borrado guardado de obsoletas: SIN CAMBIOS
}

private function regionById(int $pokemonId): ?string
{
    return self::REGION_BY_ID[$pokemonId] ?? null;
}
// regionOf() se ELIMINA (único llamador: este seeder; verificado por grep).
```

**Igualdad funcional:** con el CSV actual, `regionById` siembra 38 formas que hoy se
omiten (18 Alola + 20 Galar sin sufijo) y las 16 Hisui + 4 Paldea ya se sembraban por
sufijo. Pasan de 1045 a **1083** pokémon y de 495 a **514** evoluciones (se regeneran
las 19 aristas de formas con pre-evolución).

## Criterios actualizados (delta sobre los CA de v1)

**Sin cambios:** CA-1 (9 chains), CA-2 (7 niveles), CA-3 (1083/514/0 huérfanas/13
cross-chain), CA-4 (cadena normal intacta: `52 → [53]`, `10161 → [863]`,
`10174 → 10175 → 862`), CA-6 (idempotencia), CA-7 (melmetal, 12 regionales sin pre-evo,
902 con padre 550), CA-8 (nests/shelters/inventario/reclutados/huevos/plantings/
hábitats sin cambios).

**CA-5 sustituido (ya no hay sufijos):**

| Id | Criterio |
|---|---|
| CA-5a | `git status --porcelain -- storage/data/pokemon.csv` **vacío** al inicio y al final → el dataset no se ha tocado |
| CA-5b | Las **38 formas sin sufijo siguen sembrándose por ID**: `count(pokemon where id in <58 mapeados>) === 58`; en particular existen `10091, 10100, 10107, 10161, 10174, 10180` |
| CA-5c | **Nombres actuales conservados**, solo con la capitalización del seeder: tras el run, `10161 → 'Meowth'`, `10175 → 'Linoone'`, `10177 → 'Darmanitan-standard'`, `52 → 'Meowth'` (duplicado aceptado). Antes del run los valores en BD son los sufijados: `'Meowth-galar'`, `'Linoone-galar'`, `'Darmanitan-galar-standard'` |
| CA-5d | `regionById()` devuelve `null` para `10245–10249` (falsos positivos) y para `10017` (darmanitan-zen), `1010x` no mapeados, etc. |
| CA-5e | `REGION_CODES` y `chainIdToStore()` **no aparecen modificados** en el diff del seeder |

**Veto explícito:** si el diff toca `storage/data/pokemon.csv`, cualquier fichero de
`tests/`, `database/migrations/`, `src/`, `app/Support/*`, `public/**`, o introduce una
segunda fuente de verdad de regiones (regex por rango, lista duplicada en otro fichero),
el plan se rechaza.

## Verificación (delta sobre V0/V1/V3)

```bash
# V0 — pre-flight: el dataset y el ámbito están limpios
git status --porcelain -- storage/data/pokemon.csv        # vacío
git status --porcelain -- database/seeders/               # vacío
git diff --stat -- storage/data/pokemon.csv               # sin salida

# V1 — estático (sustituye a la restauración del CSV)
php -l database/seeders/PokemonSeeder.php
vendor/bin/pint --dirty --format agent
git diff -- database/seeders/PokemonSeeder.php | grep -E 'REGION_CODES|chainIdToStore'   # sin coincidencias
composer analyse                                          # opcional; database/ está excluido en phpstan.neon
```

```php
// V3 — mismo script /tmp/opencode/task057_verify.php, transacción + rollback.
// Asserts añadidos/cambiados (el resto idéntico a v1: 1083/514/9 chains/15 aristas/0 huérfanas/13 cross-chain/7 niveles):
$map = (new ReflectionClass(PokemonSeeder::class))->getReflectionConstant('REGION_BY_ID')->getValue();
$ok(count($map) === 58, 'mapa != 58');
$ok(count(array_filter($map, fn ($r) => $r === 'alola')) === 18, 'alola != 18');
$ok(count(array_filter($map, fn ($r) => $r === 'galar')) === 20, 'galar != 20');
$ok(count(array_filter($map, fn ($r) => $r === 'hisui')) === 16, 'hisui != 16');
$ok(count(array_filter($map, fn ($r) => $r === 'paldea')) === 4, 'paldea != 4');
$ok(! isset($map[10245]) && ! isset($map[10247]) && ! isset($map[10249]), 'falso positivo mapeado');
$csv = array_column(array_map('str_getcsv', file(base_path('storage/data/pokemon.csv'))), 7, 0); // id => is_default
foreach (array_keys($map) as $id) { $ok(($csv[$id] ?? null) === '0', "id $id no es is_default=0 en el CSV"); }
$ok(DB::table('pokemon')->whereIn('id', array_keys($map))->count() === 58, 'no se sembraron las 58 formas');
$ok(strtolower(DB::table('pokemon')->where('id', 10161)->value('name')) === 'meowth', 'nombre 10161');
$ok(strtolower(DB::table('pokemon')->where('id', 10175)->value('name')) === 'linoone', 'nombre 10175');
$ok(strtolower(DB::table('pokemon')->where('id', 10177)->value('name')) === 'darmanitan-standard', 'nombre 10177');
// tras rollBack(): los nombres vuelven a los sufijados → demuestra no-persistencia
$ok(strtolower(DB::table('pokemon')->where('id', 10161)->value('name')) === 'meowth-galar', 'rollback nombre 10161');
```

## Riesgos nuevos (medidos)

| Sev. | Riesgo | Dato verificado | Postura |
|---|---|---|---|
| **alto** | **`pokemon.csv` queda como fuente corrupta**: nombres duplicados y sin sufijo. Cualquier consumidor que resuelva por nombre es ambiguo | **36 nombres compartidos por 73 pokémon** (37 ids sobrantes); 38 formas Alola/Galar colisionan con su forma normal. Hoy en BD hay **0** colisiones porque los nombres conservan sufijo | Fuera de alcance (restricción aceptada). Documentar en el ADR y en el docblock del mapa: **la identidad es el `id`, nunca el nombre** |
| **alto** | **`PokemonHabitatSeeder`: no re-ejecutar sobre BD existente** | BD: **1006 pares** `(pokemon_id, habitat_id)` = 1 hábitat por pokémon. CSV: **787 pares**. **204 pares del CSV no existen en BD** → un re-run dejaría **204 pokémon en 2 hábitats** (el analista decía 202; la medición exacta es 204). El seeder solo hace `upsert`, no borra | Fuera de alcance. **Prohibido** ejecutarlo; ni este plan ni el fix lo requieren (la cadena cambia, el hábitat no) |
| **medio** | **`BuildShowdownMovesets`**: indexa por nombre normalizado; el re-seed elimina los sufijos | Hoy **0** claves ambiguas en BD; tras el re-seed, **36 claves** pasan a ambiguas (el analista estimó 14; el recuento medido es 36). Solo afecta si se regenera el artefacto | Fuera de alcance. Si se regenera, revisar esas 36 claves antes de commitear el artefacto |
| **medio** | **`ReclutadosSeeder`**: `LOWER(name) = ?` / `LIKE` **sin `orderBy`** → `Zigzagoon` puede resolverse a `10174` | Está **comentado** en `DatabaseSeeder` (no se ejecuta). Es preexistente al cambio (el CSV ya está corrupto) | Fuera de alcance. Recomendación de una línea para un ticket aparte: `->orderBy('id')` o `where('id','<',10000)` |
| **medio** | **Pérdida irreversible de los sufijos en la BD** al re-sembrar: ningún re-seed puede restaurarlos (el CSV ya no los tiene) | BD actual: `10161 = 'Meowth-galar'`; tras el run: `'Meowth'` | **El `pg_dump` previo pasa de recomendable a obligatorio** (ver rollback) |
| **bajo** | Iconos de caramelo: base de las 9 chains nuevas = evolucionado (`863`…), 8 iconos ausentes en `candy_pokemon/` (existen en `iconos_webp/`) | Medido en v1, sin cambios | Sigue siendo opcional y sujeto a aprobación; no bloquea |

## Plan de rollback (actualizado)

**Código — un solo fichero:**
```bash
git diff --quiet -- database/seeders/PokemonSeeder.php   # confirmar que es el único sucio del ámbito
git restore --source=HEAD -- database/seeders/PokemonSeeder.php
# si ya estuviera commiteado: git revert <sha>  (nunca reset --hard)
# alternativa sin git: parche inverso en /tmp/opencode/task057.patch
```

**Datos — ahora hay una operación irreversible, así que el dump es obligatorio antes del run real:**
```bash
pg_dump -Fc -t pokemon -t pokemon_evolution <db> > /tmp/opencode/task057_pre.dump
```
- Revertir código y volver a sembrar **no** recupera los sufijos: el CSV corrupto los
  reescribirá sin sufijo otra vez. La única vuelta atrás de los nombres es
  `pg_restore` del dump (o `git show efa5cfd^:storage/data/pokemon.csv`, que sigue
  disponible pero que **no** se aplicará como parte de este plan).
- El fix de datos es idempotente y convergente: revertir el código **y** re-sembrar
  devuelve Obstagoon `862→264`, los 8 padres a la chain normal, los 7 niveles a `40` y
  el catálogo a 1045/495.
- Si solo se revierte el código y no se vuelve a sembrar, la BD queda correcta y
  consistente con el fix; el riesgo vuelve en el próximo `db:seed`.

## Preguntas (3, ninguna bloqueante)

1. **Guarda dura sobre `base_form_id`**: ¿apruebas que una forma base no sembrada
   (fuera de la allowlist `[10247]`) haga **fallar** el seeder en lugar de degradar a
   padre normal? *Por defecto: sí — es la única forma de que un futuro CSV no
   introduzca padres incorrectos en silencio.*
2. **Log de las 93 formas omitidas**: ¿se acepta **una sola línea `Log::info` y solo
   con `-v`**, o prefieres silencio total? *Por defecto: una línea en verboso.*
3. **Divergencias documentadas**: ¿confirmas que `ReclutadosSeeder`,
   `BuildShowdownMovesets` y `PokemonHabitatSeeder` quedan **fuera de alcance** (solo
   documentados en el ADR, con la nota «no re-ejecutar hábitats»), sin abrir tickets en
   esta tarea? *Por defecto: sí.*
