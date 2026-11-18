# TASK-057 — Petición

- **ID:** TASK-057
- **Fecha:** 2026-11-18
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon` (Laravel 12, PHP 8.4).
- **Tipo:** corrección de datos de seed (cadenas evolutivas).
- **Estado:** RECEIVED → ANALYZING.

## Texto literal

> revisa evoluciones como meowth galar perseker u obstagoon, algunas se han mezclado, corrigelo en seeders

## Contexto conocido (sin conclusiones de análisis)

- Las cadenas evolutivas se pueblan desde `database/seeders/PokemonSeeder.php` usando
  `storage/data/pokemon.csv`, `pokemon_species.csv`, `pokemon_stats.csv`,
  `pokemon_types.csv` y `pokemon_evolution.csv`.
- El seeder ya tiene un mecanismo para separar formas regionales de las normales:
  `REGION_CODES` (alola/galar/hisui/paldea) y `chainIdToStore()`, que asigna a las formas
  regionales una `evolution_chain_id` propia (bloques 11000–14000).
- Indicio inicial (a verificar por `analyst`): `PokemonSeeder` indexa
  `pokemon_evolution.csv` por `evolved_species_id` con «última fila gana» e **ignora
  `base_form_id`**, por lo que evoluciones que en el juego solo salen de una forma regional
  (Perrserker ← Meowth de Galar, Obstagoon ← Linoone de Galar, etc.) podrían quedar
  colgadas de la pre-evolución normal y mezcladas en la cadena normal.
- Ejemplos mencionados por el desarrollador: Meowth de Galar / Perrserker / Obstagoon.

## Alcance provisional

- Corregir únicamente los seeders (previsiblemente `PokemonSeeder.php`), sin tocar los CSV
  de origen salvo que el análisis demuestre que están mal.
- No se modifica ningún otro módulo de aplicación.
- No se crean, modifican ni ejecutan tests.

## Fuera de alcance provisional

- Implementar sin plan aprobado explícitamente.
- Cambiar datos ya sembrados por otras vías, migraciones o SQL manual.
- Tocar los múltiples cambios locales preexistentes del árbol de trabajo (75 entradas en
  `git status`; ninguna en `database/seeders/` ni `storage/data/`).

## Preguntas a resolver en el análisis

1. ¿Qué familias están mezcladas exactamente y cuál es la relación correcta
   (pre-evolución → evolución, cadena) para cada una?
2. ¿Debe cubrirse solo el caso Galar o todos los casos análogos con `base_form_id`
   (Sirfetch'd, Mr. Rime, Cursola, Runerigus, Clodsire, Sneasler, Overqwil, Basculegion…)?
3. ¿Cómo debe resolverse la `evolution_chain_id` del evocado para que la familia quede
   agrupada con su pre-evolución regional y no con la normal?
4. ¿Qué efecto tiene el cambio en los consumidores de `evolution_chain_id`/`species_id`
   (CadenasEvolutivas, ResolvedorCadenasEvolutivas, reclutamiento, hábitats, admin)?
5. ¿El seeder es idempotente tras el cambio? ¿Un re-seed corrige filas ya existentes?
6. Casos límite: `minimum_level` vacío (Persian normal), filas duplicadas, formas no
   regionales no sembradas (basculin-white-striped) y evolutions de formas regionales.

## Artefactos

Se crean los 10 artefactos de la plantilla. `analysis.md` y `architecture.md` solo se
completan con el resultado de `analyst` y `architect`.
