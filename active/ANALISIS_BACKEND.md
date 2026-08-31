# ANÁLISIS PREVIO — Bugs A y B del módulo Exploraciones

## Bug A: Hallazgos EV/tipo restringidos al pool del hábitat

### Diagnóstico
`SimuladorEncuentros::eventoHallazgo()` genera `caramelo_ev` eligiendo un stat aleatorio 1-6
y `caramelo_tipo` eligiendo un tipo aleatorio de los 18 `TipoPokemon::cases()`. Esto es incorrecto:
deben estar restringidos al pool del hábitat (pokémon del hábitat en ese nivel).

### Archivos a tocar

1. **`src/Exploraciones/App/ProcesarExploracionHandler.php`**
   - `poolHabitat()`: añadir `->loadMissing('stats')` y mapear `stats` con `effort>0` en el array de retorno.

2. **`src/Exploraciones/Domain/SimuladorEncuentros.php`**
   - `generarEventos()`: el pool ahora es `list<array{id, capture_rate, hatch, tipos, stats}>`. 
     `poolPonderado()` y `elegirPonderado()` siguen funcionando con solo `id`/`capture_rate`/`hatch`.
   - `generarEvento()`: pasar el pool completo a `eventoHallazgo()`.
   - `eventoHallazgo()`:
     - `caramelo_familia`: mantener (elige pokemon del pool, usa pokemon_id).
     - `caramelo_ev`: elegir pokemon del pool (ponderado). De ese pokemon, elegir UNO de sus stats
       con effort>0 (aleatorio entre los disponibles). Fallback: si ningún pokemon del pool tiene
       stats con effort, usar stat aleatorio 1-6.
     - `caramelo_tipo`: elegir pokemon del pool, luego uno de sus tipos. Fallback: tipo aleatorio.

### Tests a escribir/actualizar

- **`tests/Unit/SimuladorEncuentrosTest.php`**:
  - `test_hallazgo_caramelo_ev_desde_pool_con_stats`: verificar que stat se elige del pool.
  - `test_hallazgo_caramelo_ev_fallback_sin_stats`: verificar fallback a 1-6.
  - `test_hallazgo_caramelo_tipo_desde_pool_con_tipos`: verificar tipo del pool.
  - `test_hallazgo_caramelo_tipo_fallback_sin_tipos`: verificar fallback.
  - Actualizar tests existentes que usan `poolBase()` — ahora necesitan `tipos` y `stats` en el pool.

- **Tests de ProcesarExploracionHandler** (feature tests, si existen):
  - Verificar que `poolHabitat()` incluye `stats`.

## Bug B: Emboscada victoriosa no debe generar tiempo perdido

### Diagnóstico
`EvaluadorExploracion::resolverEmboscada()` línea 136-141: cuando la resolución del encuentro
es 'victoria' o 'victoria_con_coste', se mapea a 'superada' con `duration_loss = COSTE_EMBOSCADA_VICTORIA`
(10). El usuario dice: "si he superado una emboscada y gané, no debería generar tiempo perdido".

### Cambio
En `resolverEmboscada()`, cambiar `duration_loss` a 0 cuando la emboscada se supera (victoria).
La constante `COSTE_EMBOSCADA_VICTORIA` se reasigna a 0 (o se usa 0 directamente). Se elige
usar la constante con valor 0 para mantener la semántica del nombre.

### Tests a actualizar

- **`tests/Unit/Exploraciones/EvaluadorExploracionTest.php`**:
  - `test_emboscada_sin_vanguardia_superada_al_vencer`: espera `duration_loss` 0 (antes 10).
  - `test_emboscada_con_vanguardia_detecta_y_evita`: ya espera 0, sin cambios.

## Riesgos identificados

1. Los tests existentes de `SimuladorEncuentrosTest` usan un pool sin `tipos`/`stats` →
   hay que actualizar el pool base para incluir `tipos` y `stats` en los tests de hallazgo.
2. Los tests que no ejercitan `eventoHallazgo` (encuentros, emboscadas, contratiempos) no
   necesitan cambiar porque `poolPonderado` solo consume `id`/`capture_rate`/`hatch`.
3. El cambio en `EvaluadorExploracion` rompe tests existentes que esperan `duration_loss=10`
   → actualizar assertions.
---

# ANÁLISIS PREVIO — Comando `caramelos:sync-regionales`

## Contexto

- Los caramelos de familia se muestran como imágenes en `public/images/candy_pokemon/{pokemon_id}.webp`
  (vistas `resources/views/exploraciones/_evento.blade.php` y `_caramelo.blade.php`).
- Los pokémon regionales (Alola/Galar/Hisui/Paldea) tienen ids distintos (p.ej. 10091) pero pertenecen
  a la misma familia evolutiva que el normal (p.ej. 19 Rattata). Su caramelo debe mostrar la MISMA imagen.
- Solo existen imágenes para los ids base (generadas manualmente); los regionales no tienen → fallback a `0.webp`.
- Regla "base de familia = menor species_id" ya existe en
  `src/Exploraciones/Presentation/TransformadorResultadoExploracion::pokemonBaseDeCadena()`.
- Requisito del usuario: "identifica por bbdd los id y copia las imagenes, no hace falta nada mas".

## Investigación previa (confirmada en la BD dev real)

- `storage/data/pokemon.csv`: nombres de variantes regionales con sufijo `-alola`, `-galar`, `-hisui`,
  `-paldea` (58 variantes). Sufijos compuestos: `tauros-paldea-aqua-breed`, `darmanitan-galar-standard`,
  `darmanitan-galar-zen`. El prefijo antes del primer sufijo regional = nombre base (ej. `rattata`).
- `storage/data/pokemon_species.csv`: nombres con guion que NO son regionales (nidoran-f, mr-mime, ho-oh,
  porygon-z, type-null, tapu-*, iron-*, etc.) — se ignoran porque no contienen los sufijos regionales.
- BD dev (`php artisan tinker`, 1083 pokémon con `evolution_chain_id`): 58 regionales, todos mapean a un
  base por prefijo de nombre (0 huérfanos). 29 bases tienen imagen `candy_pokemon/{base}.webp`; 0 variantes
  tienen imagen aún. → 29 copias, 29 `sin_origen` (falta la imagen del base, p.ej. darmanitan-standard 555).

## Por qué NO vale agrupar por `evolution_chain_id` (decisión)

- El seeder (`database/seeders/PokemonSeeder.php`, `REGION_CODES`) asigna a CADA variante regional una cadena
  PROPIA (`10000 + regionCode*1000 + chainNormal`): meowth-alola → 11022, meowth-galar → 12022; el normal
  meowth → chain normal. Por tanto `evolution_chain_id` NO agrupa la variante con su base normal.
- Criterio correcto (y pedido por el usuario: "identifica por bbdd los id"): **matching por NOMBRE base**.
  Variante cuyo nombre contiene un sufijo regional → prefijo antes del guion → base = pokémon NO regional de
  menor `species_id` cuyo nombre empiece por ese prefijo (p.ej. `rattata-alola` → prefijo `rattata` → base 19).

## Qué voy a tocar

- CREAR `app/Console/Commands/SyncCandyRegionales.php` (firma `caramelos:sync-regionales`):
  - `handle()`: consulta `Pokemon` (`id, name, species_id, evolution_chain_id`) con `evolution_chain_id`
    no nulo; delega en `sincronizar()`; imprime copiadas/ya existían/sin origen.
  - `sincronizar(array $pokemons, string $directorio)`: pública, para poder testear sin BD; devuelve
    `{copiadas, ya_existian, sin_origen}`. Idempotente (no sobrescribe si el destino existe); si falta el
    origen → `sin_origen`.
  - `mapearVariantes(array $pokemons)`: pública, pura — variante_id => base_id por prefijo de nombre.
- NINGÚN otro archivo (sin vistas, sin docs, sin migraciones).

## Tests a escribir (TDD, unit de la lógica del comando)

- `tests/Unit/SyncCandyRegionalesTest.php`:
  - `test_mapeo_variantes_regionales_por_prefijo_de_nombre`: rattata-alola → 19, meowth-alola → 52,
    meowth-galar → 52 (misma familia normal), sandshrew-alola → 27, sandslash-alola → 28 (distinta base
    por prefijo), tauros-paldea-*-breed → 128, darmanitan-galar-standard → 555 (prefijo `darmanitan` →
    `darmanitan-standard`).
  - `test_mapeo_ignora_no_regionales_y_nombres_con_guion_no_regional`: nidoran-f/mr-mime/ho-oh no generan
    entradas.
  - `test_mapeo_sin_base_omite_variante`: variante sin base → no aparece en el mapa.
  - `test_sincronizar_copia_imagen_del_base_a_la_variante`: crea fichero destino con el contenido del origen.
  - `test_sincronizar_no_sobrescribe_si_el_destino_existe`: ya_existian++, el destino se conserva.
  - `test_sincronizar_sin_origen_cuenta_sin_origen`: base sin imagen → sin_origen++.
  - `test_sincronizar_devuelve_conteos_acumulados`: escenario mixto copiadas/ya/sin.
- Test feature del comando (`php artisan caramelos:sync-regionales`) contra la BD dev: verificación manual
  de ejecución (no test automático; requiere Postgres + assets).

## Riesgos identificados

1. Falsos positivos de prefijo por `str_starts_with`: verificar en tests (p.ej. `sandshrew-alola` →
   `sandshrew` 27, NO `sandslash` 28). La regla "menor species_id + empiece por prefijo" lo controla.
2. `darmanitan` normal se llama `darmanitan-standard` (is_default) → el prefijo `darmanitan` matchea con
   `str_starts_with`. Cubierto por test.
3. La BD dev puede no estar arrancada → el comando debe al menos no fallar (handle con try? No: si no hay
   BD, tinker falla igual; el comando no debe romper por assets faltantes). Si la BD no está, se documenta.
4. El comando escribe en `public/images/candy_pokemon/` → ejecución en local SÍ crea ficheros reales
   (comportamiento deseado; idempotente en ejecuciones siguientes).
5. PHPStan nivel 6 + Pint + PHPMD: el archivo nuevo debe pasar (tipado estricto, PHPDoc con array-shapes).

