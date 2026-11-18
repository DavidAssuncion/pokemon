# TASK-053 — Análisis

- **Autor:** `analyst` (subagente independiente).
- **Fecha:** 2026-11-17.
- **Alcance:** solo lectura; sin cambios de código ni assets.
- **Nota de coordinación:** el resultado se reproduce íntegro; no se alteran sus conclusiones.

---

# TASK-053 — Informe final de mapeo (fase 1 de sprites de entrenador)

> Solo análisis. No se modificó ningún archivo del proyecto ni del clon externo.

---

## 1. Resumen y decisiones de mapeo

- **Hábitat 49 «Legendarios» sí tiene pool**: 119 filas en `pokemon_habitat` (niveles 1-3), con Psíquico 29, Dragón 19 y Volador 15. La nota de contexto («sin pool») es incorrecta; lo que ocurre es que se excluyó de la mazmorra en TASK-047 (`mazmorra NULL`). **Se propone `veteran` («Veterano»)** con variantes `veteranf`/`acetrainer`: encaja con un guardián de legendarios, existe el PNG y evita que un legendario se presente como salvaje.
- **Una clase fija por hábitat, con variante fija por índice**: la vista pinta 3 tarjetas por nivel (`x-for` sobre `levelData`), así que una sola clase mostraría 3 sprites idénticos repetidos 3×3. Se propone `clase` + 2 `variantes` resueltas por `entrenador.indice` (1-3). Sigue siendo determinista y **solo añade 2 PNG** al conjunto (`psychicf`, `veteranf`), porque el resto de variantes ya está en el pool de gimnasios.
- **Gimnasios**: 18 líderes canónicos elegidos **por tipo**, no por región (el orden del juego es por tipo/nivel). Etapas 1-3 con 3 clases genéricas distintas por gimnasio, dibujadas de un pool de 39 slugs.
- **Todo se resuelve en Blade/manifiesto**: `gym.slug + etapa.etapa`, `habitatId` + `indice`. No se toca dominio ni contratos (`EtapaGimnasio` solo tiene `{etapa, nombre}`; `EstadoEntrenador` solo `{indice, desbloqueado}`).
- **Assets**: 72 PNG de núcleo, 74 con variantes, ~57 KB, **0 ausencias**.

---

## 2. Tabla completa de los 56 hábitats

Tipos: conteo de filas en `pokemon_types` del pool del hábitat (un Pokémon puede sumar 2). Especies: muestra de las de menor nivel.

| id | nombre | tipos dominantes | especies representativas | clase_slug | nombre visible | justificación |
|---:|---|---|---|---|---|---|
| 1 | Bahía Crustáceo | Agua 6, Roca 3, Lucha 2 | clauncher, corphish, crabrawler, dwebble | `swimmer` | Nadador | Crustáceos y mariscadores de orilla. |
| 2 | Bosque Brumoso | Planta 18, Bicho 3, Hada 2 | chespin, cottonee, sewaddle, petilil | `aromalady` | Señorita Aroma | Bosque de plantas y hadas con aroma. |
| 3 | Bosque Crecido | Bicho 20, Volador 8, Eléctrico 4 | nincada, combee, yanma, ledyba | `bugcatcher` | Cazabichos | Bosque con densidad alta de bichos. |
| 4 | Bosque Elemental | Planta 7, Hada 4, Psíquico 4 | pansage, pansear, panpour, ralts | `pokemaniac` | Pokemaníaco | Monos elementales raros de coleccionista. |
| 5 | Bosque Nido | Volador 14, Normal 13 | pidgey, starly, rufflet, pidove | `birdkeeper` | Cuidador de Aves | Nido de aves con clara mayoría Volador. |
| 6 | Bosque Sanador | Hada 9, Normal 7 | chansey, audino, clefairy, igglybuff | `nurse` | Enfermera | Pokémon curanderos y afectivos. |
| 7 | Bosque Transformacion | reparto plano (Eevee y 8 evoluciones) | eevee, vaporeon, jolteon, espeon | `pokemonbreeder` | Criador Pokémon | Todas las ramas evolutivas de Eevee. |
| 8 | Campo Terroso | Bicho 9, Normal 7, Planta 7 | skiddo, litleo, smoliv, bramblin | `rancher` | Ranchero | Campo abierto con ganado y cultivos. |
| 9 | Caverna Gélida | Hielo 17, Tierra 3, Dragón 3 | snom, cubchoo, swinub, frigibax | `skier` | Esquiador | Cueva helada de Pokémon de frío. |
| 10 | La Marjal | Planta 8, Agua 5, Veneno 3 | psyduck, bulbasaur, hoppip, slowpoke | `fisherman` | Pescador | Marisma de agua dulce y vegetación. |
| 11 | Charca Renacuajo | Agua 13, Eléctrico 2, Tierra 2 | poliwag, tympole, sobble, tadbulb | `tuber` | Bañista | Charca somera de renacuajos. |
| 12 | Bonalba | Agua 8, Tierra 6, Planta 6 | mudkip, wooper, lotad, turtwig | `swimmerf` | Nadadora | Humedal de iniciadores de agua y tierra. |
| 13 | Corriente Marina | Agua 11, Veneno 3 | wailmer, arrokuda, wishiwashi, finneon | `scubadiver` | Buzo | Corriente de mar abierto. |
| 14 | Cráter | Fuego 15, Lucha 2 | slugma, magby, torkoal, cyndaquil | `kindler` | Incendiario | Cámara volcánica de fuego puro. |
| 15 | Mar Sereno | Agua 13, Roca 2 | popplio, finizen, tatsugiri, goldeen | `surfer` | Surfista | Mar calmo y Pokémon playeros. |
| 16 | Lago Místico | Agua 8, Dragón 4, Volador 3 | dratini, dragonair, basculin, mantyke | `medium` | Médium | Lago con dragones y aura mística. |
| 17 | Monte Disciplina | Lucha 23, Psíquico 2 | machop, meditite, riolu, makuhita | `blackbelt` | Cinturón Negro | Monasterio de entrenamiento de Lucha. |
| 18 | Mina Magnética | Acero 18, Eléctrico 5 | magnemite, beldum, klink, bronzor | `worker` | Obrero | Mina de mineral de acero. |
| 19 | Pradera Celeste | Normal 8, Psíquico 6 | buneary, skitty, spoink, natu | `picnicker` | Excursionista | Pradera suave de Pokémon normales. |
| 20 | Pradera Salvaje | Normal 15, Volador 5 | rattata, meowth, bunnelby, zangoose | `youngster` | Joven | Roedores y normales de campo. |
| 21 | Río Manso | Agua 12, Normal 2 | froakie, buizel, bidoof, surskit | `camper` | Acampado | Ribera tranquila de agua dulce. |
| 22 | Selva | Planta 11, Veneno 6 | grookey, bounsweet, ekans, fomantis | `pokemonranger` | Guardabosques | Selva densa de plantas y veneno. |
| 23 | Vieja Central | Eléctrico 6, Normal 5, Acero 4 | porygon, timburr, helioptile, abra | `engineer` | Ingeniero | Central abandonada con chatarra eléctrica. |
| 24 | Sierra Quemada | Fuego 13, Tierra 2 | litten, scorbunny, charcadet, numel | `firebreather` | Escupefuego | Sierra arrasada por el fuego. |
| 25 | Central Energía | Eléctrico 10, Planta 2 | pichu, pikachu, electabuzz, electivire | `scientist` | Científico | Planta energética en activo. |
| 26 | Bosque Secreto | Bicho 22, Veneno 9 | weedle, caterpie, venipede, blipbug | `bugmaniac` | Bichomaníaco | El hábitat con más bichos del mapa. |
| 27 | Sabana | Normal 7, Veneno 6, Tierra 6 | tauros, miltank, phanpy, dunsparce | `cowgirl` | Vaquerita | Manadas de tauros y miltank. |
| 28 | Prado Trueno | Eléctrico 17, Psíquico 2 | mareep, shinx, yamper, togedemaru | `guitarist` | Guitarrista | Pradera eléctrica de rebaños cargados. |
| 29 | Pozas De Valencia | Agua 10, Bicho 2 | oshawott, shellos, dewpider, chewtle | `freediver` | Buzo Libre | Pozas someras de marisma. |
| 30 | Bosque Seta | Planta 14, Veneno 5 | oddish, foongus, shroomish, morelull | `gardener` | Jardinero | Hongos y plantas de sotobosque. |
| 31 | Desierto Horno | Tierra 15, Dragón 5 | sandile, trapinch, gible, cacnea | `backpacker` | Mochilero | Dunas ardientes de tierra y dragón. |
| 33 | Calvari | Normal 5, Siniestro 5 | purrloin, glameow, obstagoon, liepard | `punkgirl` | Chica Punk | Gatos callejeros y oscuros urbanos. |
| 34 | Pantano Ponzoña | Veneno 19, Psíquico 3 | grimer, skrelp, croagunk, seviper | `roughneck` | Matón | Pantano tóxico dominado por Veneno. |
| 35 | Playa Cubito | Hielo 11, Agua 6 | spheal, snover, lapras, eiscue | `boarder` | Snowboarder | Playa helada con Pokémon de hielo. |
| 36 | Las Ramblas | Siniestro 16, Lucha 4 | houndour, poochyena, impidimp, stunky | `delinquent` | Delincuente | Paseo urbano de tipos Siniestro. |
| 37 | Pradera Orgullosa | Hada 10, Psíquico 5 | togepi, swirlix, tinkatink, hatenna | `fairytalegirl` | Chica de Cuento | Pradera de hadas y cuentos. |
| 38 | Monte Verde | Normal 9, Volador 3, Tierra 3 | stantler, teddiursa, skarmory, ursaring | `hiker` | Montañero | Bosque de montaña con fauna variada. |
| 39 | Cueva Dragón | Dragón 21, Siniestro 4 | bagon, axew, deino, goomy | `dragontamer` | Domadragones | Guarida de crías de dragón. |
| 40 | Cueva Granito | Roca 18, Tierra 7 | onix, geodude, roggenrola, nacli | `smasher` | Romperrocas | Caverna de roca dura. |
| 42 | Estrecho de Gibraltar | Agua 12, Siniestro 2 | magikarp, totodile, horsea, staryu | `sailor` | Marinero | Aguas de paso marítimo. |
| 43 | Playa del Postiguet | Agua 10, Fantasma 4, Hada 3 | frillish, corsola, marill, alomomola | `beauty` | Belleza | Playa urbana concurrida. |
| 44 | Monte Partido | Roca 12, Tierra 5 | larvitar, aron, rhyhorn, tyrunt | `ruinmaniac` | Maníaco de las Ruinas | Ladera rocosa con fósiles. |
| 45 | Camara Omega | Psíquico 6, Volador 6 | woobat, munna, elgyem, musharna | `psychic` | Psíquico | Cámara de aura psíquica. |
| 46 | Camara Alfa | Fantasma 17, Psíquico 4 | gastly, duskull, shuppet, litwick | `hexmaniac` | Bruja | Cámara de fantasmas. |
| 47 | Vieja Reliquia | Psíquico 8, Tierra 4 | baltoy, claydol, solrock, lunatone | `sage` | Sabio | Ruinas antiguas con gólems. |
| 48 | Tabarca | Agua 10, Volador 9 | wingull, cramorant, quaxly, chatot | `tourist` | Turista | Isla turística de aves marinas. |
| 49 | Legendarios | Psíquico 29, Dragón 19, Volador 15 | cosmog, phione, poipole, type-null | `veteran` | Veterano | Guardián de legendarios; pool real de 119. |
| 50 | Cañar | Bicho 11, Planta 9 | scyther, pinsir, heracross, karrablast | `bugcatcher` | Cazabichos | Cañaveral con insectos grandes. |
| 51 | Jungla | Planta 17, Dragón 6 | snivy, bellsprout, applin, exeggcute | `backpacker` | Mochilero | Jungla de plantas y dragones. |
| 52 | Santa Pola | Hielo 8, Agua 5 | piplup, seel, vanillite, cryogonal | `fisherman` | Pescador | Puerto pesquero frío. |
| 53 | Monte Oscuro | Siniestro 11, Volador 9 | murkrow, sableye, pawniard, drifloon | `channeler` | Espiritista | Monte sombrío de oscuros y fantasmas. |
| 54 | Tierra Abrasada | Fuego 18, Lucha 4 | fennekin, tepig, fuecoco, torchic | `firefighter` | Bombero | Tierra calcinada; control del fuego. |
| 55 | Sierra Aitana | Hielo 11, Psíquico 6 | snorunt, smoochum, sneasel, jynx | `skier` | Esquiador | Sierra nevada. |
| 56 | Cueva del Eco | Volador 5, Normal 3, Veneno 3 | zubat, noibat, whismur, crobat | `hiker` | Montañero | Cueva de murciélagos y ecos. |
| 57 | Fosa | Roca 9, Agua 8 | omanyte, kabuto, lileep, anorith | `ruinmaniac` | Maníaco de las Ruinas | Fosa de fósiles marinos. |
| 58 | Pradera T | Normal 17, Lucha 6 | kubfu, lillipup, deerling, wooloo | `youngathlete` | Joven Atleta | Pradera de entrenamiento. |

**Notas de la tabla**
- Ids 32 y 41 no existen (56 hábitats reales).
- Repeticiones deliberadas: `bugcatcher` (3 y 50), `hiker` (38 y 56), `skier` (9 y 55), `fisherman` (10 y 52), `backpacker` (31 y 51), `ruinmaniac` (44 y 57). Son hábitats temáticamente equivalentes y nunca se ven en la misma pantalla.
- Casos menos literales, marcables en revisión: 33 `punkgirl`, 45 `psychic`, 54 `firefighter`, 58 `youngathlete`.

---

## 3. Tabla completa de los 18 gimnasios

| slug | tipo | nivel | líder canónico | sprite líder | etapa 1 | etapa 2 | etapa 3 |
|---|---|---:|---|---|---|---|---|
| `bug` | Bicho | 10 | Bugsy | `bugsy` | `bugcatcher` | `bugmaniac` | `lass` |
| `poison` | Veneno | 15 | Koga | `koga` | `roughneck` | `biker` | `punkgirl` |
| `normal` | Normal | 20 | Norman | `norman` | `youngster` | `lass` | `acetrainer` |
| `grass` | Planta | 25 | Erika | `erika` | `aromalady` | `gardener` | `picnicker` |
| `flying` | Volador | 31 | Falkner | `falkner` | `birdkeeper` | `camper` | `lass` |
| `rock` | Roca | 36 | Brock | `brock` | `hiker` | `smasher` | `ruinmaniac` |
| `electric` | Eléctrico | 41 | Teniente Surge | `ltsurge` | `guitarist` | `engineer` | `worker` |
| `ice` | Hielo | 46 | Pryce | `pryce` | `skier` | `boarder` | `beauty` |
| `fire` | Fuego | 52 | Blaine | `blaine` | `kindler` | `firebreather` | `firefighter` |
| `water` | Agua | 57 | Misty | `misty` | `swimmer` | `swimmerf` | `sailor` |
| `ground` | Tierra | 62 | Giovanni | `giovanni` | `hiker` | `ruinmaniac` | `worker` |
| `psychic` | Psíquico | 67 | Sabrina | `sabrina` | `psychic` | `medium` | `hexmaniac` |
| `dark` | Siniestro | 73 | Karen ⚠️ | `karen` | `delinquent` | `punkgirl` | `roughneck` |
| `ghost` | Fantasma | 78 | Agatha ⚠️ | `agatha` | `hexmaniac` | `channeler` | `medium` |
| `fighting` | Lucha | 83 | Bruno ⚠️ | `bruno` | `blackbelt` | `battlegirl` | `roughneck` |
| `fairy` | Hada | 88 | Valerie | `valerie` | `fairytalegirl` | `aromalady` | `beauty` |
| `steel` | Acero | 94 | Jasmine | `jasmine` | `worker` | `engineer` | `scientist` |
| `dragon` | Dragón | 100 | Lance ⚠️ | `lance` | `dragontamer` | `acetrainer` | `veteran` |

**Decisiones discutibles**
- ⚠️ `karen`, `agatha`, `bruno` y `lance` son **Alto Mando/Campeón**, no líderes de gimnasio. Se eligen por coherencia de tipo (Siniestro, Fantasma, Lucha, Dragón) ante la ausencia de un líder de gimnasio canónico igual de reconocible; `karen` y `lance` son los casos más sólidos.
- ⚠️ Mezcla regional inevitable: Brock cae en el gimnasio 6 (Roca, Nv 36), no en el 1; Misty en el 10. Es consecuencia de un juego ordenado por tipo/dificultad.
- `ltsurge` se muestra como «Teniente Surge».
- Los 18 líderes y los 39 slugs genéricos existen en el clon.

> **Nota de coordinación (TASK-053):** el plan v1 prioriza líderes de gimnasio reales para los 18 tipos: Kanto + Johto cubren 16; Piers (Siniestro) y Valerie (Hada) cubren los 2 restantes. Esto sustituye a Norman, Karen, Agatha, Bruno y Lance por Whitney, Piers, Morty, Chuck y Clair, respectivamente. La decisión final queda sujeta a la aprobación del plan.

---

## 4. Assets únicos, ausencias y peso

| Conjunto | PNG únicos | Peso |
|---|---:|---:|
| Núcleo (18 líderes + 39 clases de gimnasio + 50 clases base de hábitat, con solapes) | **72** | **56 649 B (~55 KB)** |
| Núcleo + variantes por índice (`psychicf`, `veteranf`) | **74** | **58 130 B (~57 KB)** |
| Clon completo (referencia) | 572 | 451 237 B (~441 KB) |

- **Ausencias: 0.** Todos los slugs propuestos existen como PNG de 80×80. Coherente con la comprobación de 75 slugs con `test -f`; el conjunto final son 74 únicos porque `acetrainer` se reutiliza como fallback y ya está incluido.
- Clases usadas **solo por hábitats** (no aparecen en gimnasios): `backpacker`, `cowgirl`, `fisherman`, `freediver`, `nurse`, `pokemaniac`, `pokemonbreeder`, `pokemonranger`, `rancher`, `sage`, `scubadiver`, `surfer`, `tourist`, `tuber`, `youngathlete`.
- Reparto: 18 líderes + 39 genéricos de gimnasio + 50 base de hábitat = 107 referencias; 72 únicos por solape de 35.
- **Sí basta un subset**: 72-74 archivos y < 60 KB.

---

## 5. Puntos de inserción y datos disponibles

| Punto | Archivo:línea | Qué se pinta hoy | Datos disponibles | Resolución |
|---|---|---|---|---|
| Avatar de etapa de gimnasio | `resources/views/gimnasios/show.blade.php:120-128` | `<span>` 36 px con SVG silueta, `:title="'Entrenador: ' + etapa.nombre"` | Alpine: `gym.slug`, `etapa.etapa` (1-4), `etapa.nombre` | `sprites.gimnasios[gym.slug].etapas[etapa.etapa]` |
| Cabecera de gimnasio | `resources/views/gimnasios/show.blade.php:42` | Medalla 4xl (`images/medallas/{tipo_slug}.webp`) | `gym.tipo_slug`, `gym.tipo_medalla` | Opcional: retrato del líder junto a la medalla |
| Nombre de etapa | `resources/views/gimnasios/show.blade.php:115` | `x-text="etapa.nombre"` | `etapa.nombre` («Entrenador 1/2/3», «Líder») | Opcional: sustituir por `lider.nombre` en etapa 4 |
| Tarjeta de entrenador de hábitat | `resources/views/habitats/partials/columna-derecha.blade.php:296-315` (texto en `:308`) | Botón con «Entrenador N», sin imagen | Alpine: `habitatId` (`show.blade.php:36`), `levelIdx` (1-3), `entrenador.indice` (1-3), `entrenador.desbloqueado` | `sprites.habitats[habitatId]` → `clase` si `indice=1`, `variantes[indice-2]` si 2-3 |
| Panel de ruta (niveles) | `columna-derecha.blade.php:220-268` (botón de combate `:331-341`) | Botones «Nivel 1/2/3» con sprites de Pokémon | `$level` (1-3), `$habitat['id']`, `rutaNivel` | Usar `clase` base del hábitat (no hay índice de entrenador: la ruta es un 3v3 salvaje) |
| Cabecera del modal de formación | `habitats/partials/modal-formacion.blade.php:7` | Título «Combate de Ruta · Configurar Formación» | `formacionContexto`, `rutaNivel` | Opcional: avatar de clase de ruta |
| Botón lateral «Entrenadores» | `columna-izquierda.blade.php:72` | `<img src="/images/misc/trainer.webp">` 32 px | `$habitat['id']` (Blade) | Sustituir por sprite de la clase base a 40 px |
| Victoria de combate | `resources/views/livewire/combate.blade.php:215` | «Has derrotado al entrenador» | Sin identidad de entrenador | Fuera de fase 1 |

**Conclusión**: el mapeo se resuelve **solo en Blade/Alpine con el manifiesto**. No hace falta tocar `EtapaGimnasio`, `EstadoEntrenador`, `RivalRutaFijado` ni ningún contrato JSON.

---

## 6. Esquema de `resources/data/entrenadores_sprites.json`

### 6.1 Estructura y reglas de resolución

```json
{
  "version": 1,
  "fuente": {
    "repo": "https://github.com/josewp808/pokemon-trainer-sprites",
    "commit": "ba2d41a1ed3f49c00c74fe6c94a643860c45d5cd",
    "upstream": "https://play.pokemonshowdown.com/sprites/trainers/",
    "directorio": "public/images/trainers",
    "tamano_origen_px": 80,
    "tamano_render_px": 40,
    "licencia": "Sin LICENSE en el repositorio de origen. Arte de fans/Showdown. Uso privado no comercial con atribución."
  },
  "fallback": { "slug": "acetrainer", "nombre": "Entrenador de Élite" },
  "clases": {
    "acetrainer": "Entrenador de Élite", "aromalady": "Señorita Aroma", "backpacker": "Mochilero",
    "battlegirl": "Luchadora", "beauty": "Belleza", "biker": "Motero", "birdkeeper": "Cuidador de Aves",
    "blackbelt": "Cinturón Negro", "boarder": "Snowboarder", "bugcatcher": "Cazabichos",
    "bugmaniac": "Bichomaníaco", "camper": "Acampado", "channeler": "Espiritista", "cowgirl": "Vaquerita",
    "delinquent": "Delincuente", "dragontamer": "Domadragones", "engineer": "Ingeniero",
    "fairytalegirl": "Chica de Cuento", "firebreather": "Escupefuego", "firefighter": "Bombero",
    "fisherman": "Pescador", "freediver": "Buzo Libre", "gardener": "Jardinero", "guitarist": "Guitarrista",
    "hexmaniac": "Bruja", "hiker": "Montañero", "kindler": "Incendiario", "lass": "Muchacha",
    "medium": "Médium", "nurse": "Enfermera", "picnicker": "Excursionista", "pokemanic": "Pokemaníaco",
    "pokemonbreeder": "Criador Pokémon", "pokemonranger": "Guardabosques", "psychic": "Psíquico",
    "psychicf": "Psíquica", "punkgirl": "Chica Punk", "rancher": "Ranchero", "roughneck": "Matón",
    "ruinmaniac": "Maníaco de las Ruinas", "sage": "Sabio", "sailor": "Marinero", "scientist": "Científico",
    "scubadiver": "Buzo", "skier": "Esquiador", "smasher": "Romperrocas", "surfer": "Surfista",
    "swimmer": "Nadador", "swimmerf": "Nadadora", "tourist": "Turista", "tuber": "Bañista",
    "veteran": "Veterano", "veteranf": "Veterana", "worker": "Obrero", "youngathlete": "Joven Atleta",
    "youngster": "Joven"
  },
  "gimnasios": {
    "bug":      { "lider": { "slug": "bugsy",   "nombre": "Bugsy" },        "etapas": { "1": "bugcatcher", "2": "bugmaniac",  "3": "lass",         "4": "bugsy" } },
    "poison":   { "lider": { "slug": "koga",    "nombre": "Koga" },         "etapas": { "1": "roughneck",  "2": "biker",      "3": "punkgirl",     "4": "koga" } },
    "normal":   { "lider": { "slug": "norman",  "nombre": "Norman" },       "etapas": { "1": "youngster",  "2": "lass",       "3": "acetrainer",   "4": "norman" } },
    "grass":    { "lider": { "slug": "erika",   "nombre": "Erika" },        "etapas": { "1": "aromalady",  "2": "gardener",   "3": "picnicker",    "4": "erika" } },
    "flying":   { "lider": { "slug": "falkner", "nombre": "Falkner" },      "etapas": { "1": "birdkeeper", "2": "camper",     "3": "lass",         "4": "falkner" } },
    "rock":     { "lider": { "slug": "brock",   "nombre": "Brock" },        "etapas": { "1": "hiker",      "2": "smasher",    "3": "ruinmaniac",   "4": "brock" } },
    "electric": { "lider": { "slug": "ltsurge", "nombre": "Teniente Surge" },"etapas": { "1": "guitarist", "2": "engineer",   "3": "worker",       "4": "ltsurge" } },
    "ice":      { "lider": { "slug": "pryce",   "nombre": "Pryce" },        "etapas": { "1": "skier",      "2": "boarder",    "3": "beauty",       "4": "pryce" } },
    "fire":     { "lider": { "slug": "blaine",  "nombre": "Blaine" },       "etapas": { "1": "kindler",    "2": "firebreather","3": "firefighter",  "4": "blaine" } },
    "water":    { "lider": { "slug": "misty",   "nombre": "Misty" },        "etapas": { "1": "swimmer",    "2": "swimmerf",   "3": "sailor",       "4": "misty" } },
    "ground":   { "lider": { "slug": "giovanni","nombre": "Giovanni" },     "etapas": { "1": "hiker",      "2": "ruinmaniac", "3": "worker",       "4": "giovanni" } },
    "psychic":  { "lider": { "slug": "sabrina", "nombre": "Sabrina" },      "etapas": { "1": "psychic",    "2": "medium",     "3": "hexmaniac",    "4": "sabrina" } },
    "dark":     { "lider": { "slug": "karen",   "nombre": "Karen" },        "etapas": { "1": "delinquent", "2": "punkgirl",   "3": "roughneck",    "4": "karen" } },
    "ghost":    { "lider": { "slug": "agatha",  "nombre": "Agatha" },       "etapas": { "1": "hexmaniac",  "2": "channeler",  "3": "medium",       "4": "agatha" } },
    "fighting": { "lider": { "slug": "bruno",   "nombre": "Bruno" },        "etapas": { "1": "blackbelt",  "2": "battlegirl", "3": "roughneck",    "4": "bruno" } },
    "fairy":    { "lider": { "slug": "valerie", "nombre": "Valerie" },      "etapas": { "1": "fairytalegirl","2": "aromalady", "3": "beauty",       "4": "valerie" } },
    "steel":    { "lider": { "slug": "jasmine", "nombre": "Jasmine" },      "etapas": { "1": "worker",     "2": "engineer",   "3": "scientist",    "4": "jasmine" } },
    "dragon":   { "lider": { "slug": "lance",   "nombre": "Lance" },        "etapas": { "1": "dragontamer", "2": "acetrainer", "3": "veteran",     "4": "lance" } }
  },
  "habitats": {
    "1":  { "clase": "swimmer",       "variantes": ["swimmerf", "tuber"] },
    "2":  { "clase": "aromalady",     "variantes": ["gardener", "lass"] },
    "3":  { "clase": "bugcatcher",    "variantes": ["bugmaniac", "lass"] },
    "4":  { "clase": "pokemaniac",    "variantes": ["scientist", "psychic"] },
    "5":  { "clase": "birdkeeper",    "variantes": ["camper", "lass"] },
    "6":  { "clase": "nurse",         "variantes": ["aromalady", "pokemonbreeder"] },
    "7":  { "clase": "pokemonbreeder","variantes": ["scientist", "veteran"] },
    "8":  { "clase": "rancher",       "variantes": ["cowgirl", "gardener"] },
    "9":  { "clase": "skier",         "variantes": ["boarder", "hiker"] },
    "10": { "clase": "fisherman",     "variantes": ["tuber", "camper"] },
    "11": { "clase": "tuber",         "variantes": ["swimmer", "fisherman"] },
    "12": { "clase": "swimmerf",      "variantes": ["swimmer", "camper"] },
    "13": { "clase": "scubadiver",    "variantes": ["swimmer", "sailor"] },
    "14": { "clase": "kindler",       "variantes": ["firebreather", "hiker"] },
    "15": { "clase": "surfer",        "variantes": ["swimmer", "beauty"] },
    "16": { "clase": "medium",        "variantes": ["channeler", "fisherman"] },
    "17": { "clase": "blackbelt",     "variantes": ["battlegirl", "hiker"] },
    "18": { "clase": "worker",        "variantes": ["engineer", "scientist"] },
    "19": { "clase": "picnicker",     "variantes": ["lass", "youngster"] },
    "20": { "clase": "youngster",     "variantes": ["lass", "acetrainer"] },
    "21": { "clase": "camper",        "variantes": ["fisherman", "tuber"] },
    "22": { "clase": "pokemonranger", "variantes": ["sage", "bugmaniac"] },
    "23": { "clase": "engineer",      "variantes": ["worker", "scientist"] },
    "24": { "clase": "firebreather",  "variantes": ["kindler", "hiker"] },
    "25": { "clase": "scientist",     "variantes": ["engineer", "worker"] },
    "26": { "clase": "bugmaniac",     "variantes": ["bugcatcher", "hexmaniac"] },
    "27": { "clase": "cowgirl",       "variantes": ["rancher", "hiker"] },
    "28": { "clase": "guitarist",     "variantes": ["youngster", "engineer"] },
    "29": { "clase": "freediver",     "variantes": ["tuber", "swimmer"] },
    "30": { "clase": "gardener",      "variantes": ["aromalady", "bugcatcher"] },
    "31": { "clase": "backpacker",    "variantes": ["ruinmaniac", "hiker"] },
    "33": { "clase": "punkgirl",      "variantes": ["delinquent", "biker"] },
    "34": { "clase": "roughneck",     "variantes": ["punkgirl", "biker"] },
    "35": { "clase": "boarder",       "variantes": ["skier", "sailor"] },
    "36": { "clase": "delinquent",    "variantes": ["punkgirl", "roughneck"] },
    "37": { "clase": "fairytalegirl", "variantes": ["aromalady", "beauty"] },
    "38": { "clase": "hiker",         "variantes": ["camper", "birdkeeper"] },
    "39": { "clase": "dragontamer",   "variantes": ["hiker", "veteran"] },
    "40": { "clase": "smasher",       "variantes": ["hiker", "worker"] },
    "42": { "clase": "sailor",        "variantes": ["fisherman", "swimmer"] },
    "43": { "clase": "beauty",        "variantes": ["swimmerf", "tuber"] },
    "44": { "clase": "ruinmaniac",    "variantes": ["hiker", "smasher"] },
    "45": { "clase": "psychic",       "variantes": ["psychicf", "medium"] },
    "46": { "clase": "hexmaniac",     "variantes": ["channeler", "medium"] },
    "47": { "clase": "sage",          "variantes": ["ruinmaniac", "psychic"] },
    "48": { "clase": "tourist",       "variantes": ["sailor", "birdkeeper"] },
    "49": { "clase": "veteran",       "variantes": ["veteranf", "acetrainer"] },
    "50": { "clase": "bugcatcher",    "variantes": ["bugmaniac", "camper"] },
    "51": { "clase": "backpacker",    "variantes": ["pokemonranger", "sage"] },
    "52": { "clase": "fisherman",     "variantes": ["sailor", "swimmer"] },
    "53": { "clase": "channeler",     "variantes": ["hexmaniac", "delinquent"] },
    "54": { "clase": "firefighter",   "variantes": ["kindler", "firebreather"] },
    "55": { "clase": "skier",         "variantes": ["boarder", "hiker"] },
    "56": { "clase": "hiker",         "variantes": ["channeler", "dragontamer"] },
    "57": { "clase": "ruinmaniac",    "variantes": ["scubadiver", "scientist"] },
    "58": { "clase": "youngathlete",  "variantes": ["youngster", "camper"] }
  }
}
```

> Corregir `"pokemanic"` por `"pokemaniac"` al implementar (errata de esta propuesta). El mapa `clases` debe contener exactamente los slugs usados.

### 6.2 Reglas de resolución (sin backend)

```
URL gym        = /images/trainers/{gimnasios[slug].etapas[String(etapa)]}.png
Nombre líder   = gimnasios[slug].lider.nombre            // solo etapa 4
URL hábitat    = /images/trainers/{clase | variantes[indice-2]}.png
                 índice 1 → clase
                 índice 2 → variantes[0]
                 índice 3 → variantes[1]
Si falta la entrada → fallback.slug (acetrainer) → /images/misc/trainer.webp → ocultar
```

- **Gimnasio**: `gym.slug` llega en el JSON de `DetalleGimnasio` (`src/Gimnasios/Domain/DataTransferObjects/DetalleGimnasio.php:44`), y `etapa.etapa` en cada elemento de `etapas`. Resolución pura en Alpine.
- **Hábitat**: `habitatId` está en `resources/views/habitats/show.blade.php:36` y `entrenador.indice` en el bucle de `columna-derecha.blade.php:296`.
- **Ruta**: no hay índice de entrenador; usar `habitats[id].clase`.

---

## 7. Texto propuesto para `THIRD_PARTY_NOTICES.md`

Añadir al final, como sección nueva (hechos, sin valoración jurídica):

```markdown
## Sprites de entrenador

- Origen del espejo: https://github.com/josewp808/pokemon-trainer-sprites
- Commit utilizado: ba2d41a1ed3f49c00c74fe6c94a643860c45d5cd
- Procedencia del arte: espejo del directorio de sprites de entrenador de
  Pokémon Showdown, https://play.pokemonshowdown.com/sprites/trainers/
  (código del cliente: https://github.com/smogon/pokemon-showdown-client)
- Ubicación en este repositorio: `public/images/trainers/`
- Uso: arte de fans incluido en un proyecto privado, personal y no comercial.
  No se redistribuye, publica ni vende.
- El repositorio de origen no incluye archivo LICENSE, README ni créditos de
  autoría. Los sprites de Pokémon son propiedad de Nintendo, Game Freak y
  Creatures Inc.; el arte de fans de Showdown se atribuye a sus autores en el
  índice de Showdown.
- Este aviso no concede ningún derecho sobre las imágenes: solo documenta su
  procedencia para trazabilidad interna.
```

Hechos verificados que sustentan el texto: el clon no contiene `LICENSE` ni `README` (`ls | grep -i license` sin resultados), el remoto es `josewp808/pokemon-trainer-sprites`, y 570/572 PNG son byte a byte idénticos a Showdown (TASK-052 §2.1).

---

## 8. Accesibilidad y UI

- **`alt=""` + `aria-hidden="true"`** cuando el sprite es decorativo y el nombre/clase ya está en texto: es el caso actual de las etapas de gimnasio (`show.blade.php:124` ya tiene `aria-hidden`) y de las tarjetas de entrenador («Entrenador N»).
- **`alt` con nombre** solo cuando el sprite es la única fuente del dato: líder canónico si se muestra su retrato en la cabecera (`alt="Brock"`), o si se decide mostrar la clase como texto principal («Cazabichos») sin repetirla al lado.
- **Tamaño**: 40 px CSS (`width="40" height="40"`) con `image-rendering: pixelated`. 80→40 es factor 0,5 exacto; en pantalla 2× son 80 px reales, sin pérdida. Evitar 64 px (factor 0,8, píxeles irregulares).
- **CLS**: declarar `width`/`height` explícitos y `loading="lazy"` + `decoding="async"` (patrón de `resources/views/components/tipo-badge.blade.php`).
- **Repetición visual**: la clase base se repite en las 3 tarjetas del mismo nivel si no se usan variantes. Tres mitigaciones, de menor a mayor coste:
  1. variante fija por índice (`clase` + `variantes`), recomendada, +2 PNG;
  2. espejo horizontal determinista (`scaleX(-1)` para índices pares), 0 PNG;
  3. anillo de color por índice, 0 PNG.
  Recomiendo 1 + 3: variantes ya están en el pool y el anillo refuerza la distinción.
- **Botón lateral** (`columna-izquierda.blade.php:72`): hoy es un icono de 32 px. Si se sustituye por el sprite de clase, usar 40 px y ajustar el contenedor (`w-8 h-8` → `w-10 h-10`) para no romper la alineación de la fila.
- **Contraste/foco**: el sprite no debe ser el único indicador de estado; los estados «Derrotado»/«bloqueado» ya usan texto y opacidad.

---

## 9. Riesgos y verificación manual

### Riesgos

| Severidad | Riesgo | Impacto | Mitigación |
|---|---|---|---|
| **Alto** | Licencia: repo sin LICENSE, créditos de artistas eliminados | Reclamación si el proyecto se publica | Decisión explícita ya tomada (privado, con atribución); mantener `THIRD_PARTY_NOTICES` y no publicar el repo |
| Medio | Coherencia narrativa: líder canónico en gimnasio de tipo/nivel distinto al original | Brock en Nv 36, mezcla regional | Aceptado: el juego ordena por tipo/dificultad; documentarlo |
| Medio | Repetición visual de 3 sprites iguales por nivel | Aspecto pobre en la tarjeta de hábitat | Variantes por índice + anillo de color |
| Medio | Hábitat 49 abierto por URL con 119 legendarios | Power creep / captura de legendarios como salvajes | Mantener el sellado de TASK-047; el sprite `veteran` es inocuo |
| Bajo | Escalado a 64 px sin `pixelated` | Sprites borrosos | Fijar 40/80 px y `pixelated` |
| Bajo | Regresión visual en gimnasios | Cambio aditivo en una vista en curso (TASK-050) | Fallback a `trainer.webp`; coordinar con TASK-050 antes de editar `show.blade.php` |
| Bajo | Errata en el manifiesto (`pokemanic`) | Fallback silencioso | Validar el JSON contra el listado de PNG en el momento de copiar |
| Bajo | Peso | 74 archivos, 57 KB | Irrelevante; `loading="lazy"` |

### Comprobaciones manuales por pantalla

1. **Gimnasio** (los 18): las 4 tarjetas muestran 4 sprites distintos; etapa 4 = líder correcto; el `title` no dice «Entrenador: Líder»; a 40 px no hay bordes sucios; el estado superada/actual/bloqueada no oculta el sprite.
2. **Hábitat** (los 56): las 3 tarjetas del nivel 1 muestran 3 clases distintas; al cambiar de nivel 1→2→3 se mantiene la coherencia temática; el estado «Derrotado» sigue legible; la tarjeta no crece de forma irregular (el botón es `p-2`).
3. **Ruta**: el panel y el botón de combate muestran la clase base del hábitat; no se rompe el layout de 3 columnas.
4. **Hábitat 49**: si se entra por URL, muestra «Veterano» y no rompe nada.
5. **Fallback**: renombrar temporalmente un PNG y comprobar que cae a `acetrainer` y luego a `trainer.webp` sin icono roto.
6. **Accesibilidad**: con el árbol de accesibilidad del navegador, los sprites decorativos no aparecen como imágenes con nombre; el retrato del líder sí.
7. **2×/1×**: comprobar a 40 px en pantalla HiDPI y a 80 px forzado.

---

## 10. Archivos y consultas ejecutadas

### Lecturas del proyecto
- `.opencode/work/TASK-052/analysis.md` (viabilidad y licencia).
- `.opencode/work/TASK-047/analysis.md` (exclusión del hábitat 49 de la mazmorra).
- `src/Gimnasios/Domain/CatalogoGimnasios.php` (18 gimnasios, tipos, niveles).
- `src/Gimnasios/Domain/Gimnasio.php:32-43` (`nombreEtapa` = «Entrenador 1/2/3», «Líder»).
- `src/Gimnasios/Domain/DataTransferObjects/EtapaGimnasio.php:14-17`, `DetalleGimnasio.php:44`.
- `src/Gimnasios/App/ObtenerGimnasioDetalle.php:38-43`.
- `src/CombateEntrenadores/App/ObtenerEntrenadoresHabitat.php:26-45`, `Domain/DataTransferObjects/EstadoEntrenador.php:17-21`.
- `src/CombateEntrenadores/Infra/Controllers/EntrenadorController.php:64-79` (JSON keyed por nivel).
- `resources/views/gimnasios/show.blade.php:11,42,115,120-128`.
- `resources/views/habitats/show.blade.php:36,187,255`.
- `resources/views/habitats/partials/columna-derecha.blade.php:220-268,296-315,331-341`.
- `resources/views/habitats/partials/columna-izquierda.blade.php:72`.
- `resources/views/habitats/partials/modal-formacion.blade.php:7`.
- `resources/views/components/{sprite,tipo-badge}.blade.php` (patrón de componente).
- `resources/views/livewire/combate.blade.php:215`.
- `src/Shared/Tipos/TipoPokemon.php:9-26` (mapa id→tipo), `src/Shared/UI/TipoBadges.php`.
- `app/Http/Controllers/GimnasiosViewController.php:26-31` (`$slug` a la vista).
- `public/css/app.css:64` (`image-rendering: pixelated`).
- `THIRD_PARTY_NOTICES.md` (sin sección de imágenes).
- `public/images/` (existe `misc/trainer.webp`; no existe `trainers/`).

### Consultas de solo lectura a PostgreSQL (vía `php artisan tinker --execute`)
- `SELECT count(*) FROM habitats` → 56.
- `SELECT count(*) FROM pokemon_habitat` → 1006.
- `SELECT count(*) FROM pokemon` → 1083.
- Listado `habitats(id,name) ORDER BY id` → ids 1-31, 33-40, 42-58.
- `SELECT habitat_id, type, count(*) FROM pokemon_habitat JOIN pokemon_types GROUP BY habitat_id, type ORDER BY count DESC`.
- `SELECT habitat_id, name, min(level) FROM pokemon_habitat JOIN pokemon GROUP BY habitat_id, name ORDER BY level`.
- `SELECT min(level), max(level), count(*) FROM pokemon_habitat WHERE habitat_id=49` → 1, 3, 119.

### Comprobaciones en el clon
- `ls trainers | wc -l` → 572; `du -sb` → 451 237 B.
- Volcado completo de los 572 slugs.
- `git log` → `ba2d41a`, `1a2024b`; `git rev-parse HEAD` → `ba2d41a1ed3f49c00c74fe6c94a643860c45d5cd`.
- `ls | grep -i license` → sin resultados.
- Verificación de existencia y peso de los 74 slugs finales con `os.path.exists`/`getsize` → **0 ausencias**, 56 649 B (núcleo) / 58 130 B (con variantes).

### Comprobaciones NO ejecutadas (declaradas)
- **No** se renderizó la aplicación ni se validó visualmente a 40 px: la nitidez es teórica (factor 0,5 exacto).
- **No** se abrió `.env` ni ningún secreto.
- **No** se consultó asesoría legal: la sección 7 son hechos y texto propuesto, no un dictamen.
- **No** se creó `resources/data/entrenadores_sprites.json` ni `public/images/trainers/`.
- **No** se ejecutaron tests ni se modificó ningún archivo.
