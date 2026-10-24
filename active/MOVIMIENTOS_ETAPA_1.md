# Movimientos para una primera etapa

> **Cierre de catálogo:** la revisión definitiva está en `active/REVISION_FINAL_CATALOGO.md`.
> El catálogo final queda en **665 movimientos canónicos + 7 internos + 1 duplicado = 673**.
> Las cifras de 513 movimientos y 199 habilidades de este documento son históricas.

Generado a partir de PokeAPI (`pokeapi/data/v2/csv`) y de los sets aleatorios de Showdown (`showdown/data/random-battles/gen9/sets.json`). PokeAPI y Showdown están clonados en local y excluidos de Git.

## Criterio

- **P0**: núcleo de utilidad y ataques que aparecen mucho en sets estándar.
- **P1**: cobertura y utilidad muy común; segunda tanda inmediata.
- **P2**: buena cobertura o efectos de soporte para después de cerrar el núcleo.
- Los niveles son la **mediana de niveles de aprendizaje por nivel** en PokeAPI para la última versión disponible; se muestran también mínimo/máximo y número de especies/formas con entrada level-up.
- Para el juego conviene guardar el **nivel exacto por especie** en `pokemon_moves`; la mediana es solo un fallback global.

## Núcleo propuesto

> La columna "Estado actual" refleja el catálogo antes de esta primera tanda. La sección "Implementado en la primera tanda ofensiva" indica lo que ya está dentro.

| Bloque | Movimiento Showdown | Nombre ES | Tipo | Cat. | Poder | Precisión | Objetivo | Nivel PokeAPI (min/mediana/max) | Especies | Estado actual | Implementación |
|---|---|---|---|---|---:|---:|---|---:|---:|---|---|
| P0 | protect | Protección | Normal | Estado | — | — | self | 1 / 15 / 56 | 109 | No | Nuevo efecto |
| P0 | substitute | Sustituto | Normal | Estado | — | — | self | 21 / 40.0 / 47 | 22 | Sí (`sustituto`) | Ya existe |
| P0 | toxic | Tóxico | Veneno | Estado | — | 90 | normal | 1 / 35 / 63 | 71 | No | Nuevo movimiento |
| P0 | thunder-wave | Onda Trueno | Eléctrico | Estado | — | 90 | normal | 1 / 11.5 / 48 | 76 | No | Nuevo movimiento |
| P0 | will-o-wisp | Fuego Fatuo | Fuego | Estado | — | 85 | normal | 1 / 16.0 / 55 | 32 | No | Nuevo movimiento |
| P0 | stealth-rock | Trampa Rocas | Roca | Estado | — | — | foeSide | 1 / 24.0 / 77 | 62 | Sí (`trampa_rocas`) | Ya existe |
| P0 | rapid-spin | Giro Rápido | Normal | Físico | 50 | 100 | normal | 1 / 4 / 21 | 45 | No | Nuevo efecto |
| P0 | u-turn | Ida y Vuelta | Bicho | Físico | 70 | 100 | normal | 1 / 30.0 / 49 | 36 | No | Nuevo efecto |
| P0 | knock-off | Desarme | Siniestro | Físico | 65 | 100 | normal | 1 / 24.0 / 70 | 60 | Sí (`desarme`) | Ya existe |
| P0 | roost | Respiro | Volador | Estado | — | — | self | 1 / 30.0 / 84 | 68 | No | Nuevo movimiento |
| P0 | swords-dance | Danza Espada | Normal | Estado | — | — | self | 1 / 38.0 / 72 | 96 | No | Nuevo movimiento |
| P0 | calm-mind | Paz Mental | Psíquico | Estado | — | — | self | 1 / 33 / 99 | 71 | Sí (`paz_mental`) | Ya existe |
| P1 | taunt | Mofa | Siniestro | Estado | — | 100 | normal | 1 / 16.5 / 60 | 102 | No | Nuevo efecto |
| P1 | encore | Otra Vez | Normal | Estado | — | 100 | normal | 1 / 13 / 39 | 55 | No | Nuevo efecto |
| P1 | defog | Despejar | Volador | Estado | — | — | allAdjacent | 1 / 30 / 64 | 17 | No | Nuevo efecto |
| P1 | volt-switch | Voltiocambio | Eléctrico | Especial | 70 | 100 | normal | 1 / 33 / 70 | 13 | No | Nuevo efecto |
| P1 | trick | Truco | Psíquico | Estado | — | 100 | normal | 10 / 45 / 60 | 27 | Sí (`truco`) | Ya existe |
| P1 | nasty-plot | Maquinación | Siniestro | Estado | — | — | self | 1 / 37.5 / 75 | 94 | No | Nuevo movimiento |
| P1 | dragon-dance | Danza Dragón | Dragón | Estado | — | — | self | 1 / 42 / 72 | 31 | No | Nuevo movimiento |
| P1 | recover | Recuperación | Normal | Estado | — | — | self | 1 / 37 / 72 | 71 | No | Nuevo movimiento |
| P1 | earthquake | Terremoto | Tierra | Físico | 100 | 100 | allAdjacent | 1 / 45.0 / 80 | 74 | No | Nuevo movimiento |
| P1 | ice-beam | Rayo Hielo | Hielo | Especial | 90 | 100 | normal | 1 / 35.5 / 56 | 32 | No | Nuevo movimiento |
| P1 | thunderbolt | Rayo | Eléctrico | Especial | 90 | 100 | normal | 1 / 36 / 55 | 43 | No | Nuevo movimiento |
| P1 | flamethrower | Lanzallamas | Fuego | Especial | 90 | 100 | normal | 1 / 43 / 77 | 71 | No | Nuevo movimiento |
| P2 | surf | Surf | Agua | Especial | 90 | 100 | allAdjacent | 1 / 40 / 56 | 27 | No | Nuevo movimiento |
| P2 | shadow-ball | Bola Sombra | Fantasma | Especial | 80 | 100 | normal | 1 / 37.0 / 61 | 64 | No | Nuevo movimiento |
| P2 | giga-drain | Gigadrenado | Planta | Especial | 75 | 100 | normal | 1 / 32.0 / 60 | 70 | No | Nuevo movimiento |
| P2 | close-combat | A Bocajarro | Lucha | Físico | 120 | 100 | normal | 1 / 56.5 / 99 | 72 | No | Nuevo movimiento |
| P2 | sludge-bomb | Bomba Lodo | Veneno | Especial | 90 | 100 | normal | 27 / 37 / 60 | 19 | No | Nuevo movimiento |
| P2 | dazzling-gleam | Brillo Mágico | Hada | Especial | 80 | 100 | allAdjacentFoes | 1 / 35 / 51 | 27 | No | Nuevo movimiento |
| P2 | icy-wind | Viento Hielo | Hielo | Especial | 55 | 95 | allAdjacentFoes | 1 / 12 / 30 | 45 | No | Nuevo movimiento |
| P2 | sucker-punch | Golpe Bajo | Siniestro | Físico | 70 | 100 | normal | 1 / 28.0 / 56 | 102 | No | Nuevo movimiento |
| P2 | rest | Descanso | Psíquico | Estado | — | — | self | 1 / 27.5 / 63 | 72 | No | Nuevo movimiento |
| P2 | sleep-talk | Sonámbulo | Normal | Estado | — | — | self | 15 / 15 / 20 | 5 | No | Nuevo efecto |
| P2 | spikes | Púas | Tierra | Estado | — | — | foeSide | 1 / 28 / 44 | 15 | Sí (`puas`) | Ya existe |
| P2 | toxic-spikes | Púas Tóxicas | Veneno | Estado | — | — | foeSide | 1 / 15 / 40 | 27 | Sí (`puas_toxicas`) | Ya existe |
| P2 | leech-seed | Drenadoras | Planta | Estado | — | 90 | normal | 1 / 16.0 / 64 | 78 | Sí (`drenadoras`) | Ya existe |
| P2 | earth-power | Tierra Viva | Tierra | Especial | 90 | 100 | normal | 1 / 40 / 72 | 65 | No | Nuevo movimiento |
| P2 | psychic | Psíquico | Psíquico | Especial | 90 | 100 | normal | 1 / 40.0 / 75 | 114 | No | Nuevo movimiento |
| P2 | hydro-pump | Hidrobomba | Agua | Especial | 110 | 80 | normal | 1 / 50 / 88 | 113 | No | Nuevo movimiento |
| P2 | moonblast | Fuerza Lunar | Hada | Especial | 95 | 100 | normal | 1 / 43 / 84 | 59 | No | Nuevo movimiento |
| P2 | stone-edge | Roca Afilada | Roca | Físico | 100 | 80 | normal | 1 / 51.0 / 84 | 54 | No | Nuevo movimiento |

## Lectura rápida

- **Ya implementados y solo necesitan nivel/desbloqueo:** Sustituto, Trampa Rocas, Desarme, Paz Mental, Truco, Púas, Púas Tóxicas y Drenadoras.
- **Fáciles de añadir con el motor actual:** Tóxico, Onda Trueno, Fuego Fatuo, Danza Espada, Maquinación, Danza Dragón, Recuperación, Terremoto, Rayo Hielo, Rayo, Lanzallamas, Surf, Bola Sombra, Gigadrenado y A Bocajarro.
- **Requieren mecánica nueva:** Protección, Giro Rápido, Ida y Vuelta, Voltiocambio, Mofa, Otra Vez, Despejar, Sonámbulo y Descanso (según cómo se modele el sueño).
- **Casos de mejora:** Gigadrenado (drenaje 50% → más) y Viento Hielo (Velocidad -1 → -2 o más probabilidad).

## Implementado en la primera tanda ofensiva

Ataques físicos/especiales añadidos al catálogo, sin mecánicas nuevas:

`earthquake`, `ice-beam`, `thunderbolt`, `flamethrower`, `surf`, `shadow-ball`, `giga-drain`, `close-combat`, `sludge-bomb`, `dazzling-gleam`, `icy-wind`, `stone-edge`, `earth-power`, `psychic`, `hydro-pump`, `moonblast`, `fire-blast`, `focus-blast`, `energy-ball`, `dark-pulse`, `flash-cannon`, `iron-head`, `brave-bird`, `flare-blitz`, `rock-slide`, `discharge`, `lava-plume`, `hyper-voice`, `gunk-shot` y `bullet-punch`.

El desbloqueo usa `moves.nivel_requerido` con la mediana de PokeAPI y el nivel de la cuenta. Las asignaciones por especie se derivaron del learnset level-up de PokeAPI. El loadout de combate admite hasta 6 movimientos y el catálogo disponible para seleccionar no tiene límite por especie.

## Datos actuales del juego

- Catálogo: **513 movimientos**.
- Asignaciones: **13.476** en **1.077 especies**; sin límite de movimientos disponibles por especie.
- Loadout de combate: **hasta 6 movimientos**.
- Especies totales en `pokemon`: **1.083**.
- PokeAPI local: **937 movimientos**, **638.321 filas** de `pokemon_moves`, **1.351 Pokémon/formas**.
- Existe `moves.nivel_requerido` para el desbloqueo por nivel de cuenta; todavía no existe rango de mejora por reclutado.

## Siguiente implementación recomendada

1. Añadir `reclutado_movimiento` (`reclutado_id`, `move_id`, `rango`, `experiencia`).
2. Añadir materiales al inventario (`material:ataque`, `material:tipo:{slug}`).
3. Aplicar rango en `MapeadorPokemonBatalla` para potencia, drenaje, precisión y efectos.
4. Empezar por P0 y usar Gigadrenado/Viento Hielo como pruebas de mejora.

## Implementado en la segunda tanda ofensiva

Se añadieron las mecánicas de daño según estadísticas/HP, poder condicional,
daño diferido y clima/terreno/tipo, junto con la segunda tanda simple. El
detalle completo está en `active/MOVIMIENTOS_PENDIENTES_MECANICAS.md`.

La asignación por especie usa los learnsets level-up de PokeAPI (`lote2`) y se
mezcla con el lote anterior. No hay tope de movimientos disponibles por
especie; el único límite es el loadout de combate de 6 movimientos.

## Implementado en la tercera tanda ofensiva

Se añadieron 119 movimientos simples de base temprana e intermedios, más
Demolición y Psicocolmillo con rotura de pantallas. El detalle está en
`active/MOVIMIENTOS_PENDIENTES_MECANICAS.md`.

## Habilidades

El catálogo cuenta con **199 habilidades** registradas.
