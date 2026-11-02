# TASK-007 — Fase evolutiva de los caramelos de familia

## Petición literal

> Me dan mas caramelos pichu que pikachu, cuando al ser bebes deberian de dar menos, son los mas debiles de la familia

## Contexto verificado

- `NormalizadorPokemonDerrotado::fase()` cuenta miembros de la familia con `species_id <= species_id actual`.
- Pichu tiene `species_id 172`; Pikachu `25`; Raichu `26`. Por eso Pichu obtiene fase 3, Pikachu fase 1 y Raichu fase 2: el orden está invertido para bebés con número de Pokédex alto.
- La tabla `pokemon_evolution` contiene `25 ← 172` y `26 ← 25`; la etapa real es Pichu 1, Pikachu 2, Raichu 3.
- Afecta a recompensas reales de exploración, ruta y entrenador, y a la estimación previa (`ExploracionActivaController::cadenasDelPool()`).

## Restricciones

- No cambiar la fórmula general de recompensas ni otros ejes (EXP, EV, tipo).
- No tocar migraciones ni dependencias.
- Respetar el WIP de TASK-004 en los archivos compartidos.
- Tests al cierre del módulo.

## Criterios de aceptación preliminares

- Pichu (bebé) da menos caramelos de familia que Pikachu; Pikachu menos que Raichu.
- La etapa se calcula con `pokemon_evolution` (`evolves_from_species_id`), no con `species_id`.
- La estimación previa usa el mismo orden que la recompensa real.
- Familias sin datos de evolución mantienen un fallback seguro (etapa 1).
- Tests de normalizador, calculador y estimador en verde.
