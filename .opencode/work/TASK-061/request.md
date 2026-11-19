# TASK-061 — Petición

- **ID:** TASK-061
- **Fecha:** 2026-11-18
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** contenido de habilidades + nuevo filtro de Pokédex orientado al build de equipos.
- **Estado:** RECEIVED.

## Texto literal

> Añade descripciones de lo que hacen a las habilidades. Algunas tienen un efecto personalizado, se fiel al efecto. debemos añadir un filtro en pokedex para movimiento o habilidad para facilitar el build de equipos

## Interpretación inicial

1. **Descripciones de habilidades:** completar el texto (`abilities.effect`) de las
   habilidades cuyo efecto está vacío o no describe lo que realmente hace el motor.
   Cuando el efecto implementado sea personalizado (distinto del oficial de Pokémon),
   la descripción debe reflejar el comportamiento real del código, no el texto canónico.
2. **Filtro de Pokédex:** añadir en `/pokedex` un filtro por **habilidad** y/o por
   **movimiento** para localizar especies que tengan esa habilidad o puedan aprender ese
   movimiento, con el objetivo de preparar equipos.

## Contexto conocido (pre-análisis del orquestador)

- La Pokédex se sirve en `app/Http/Controllers/PlayerController.php::pokedex()` a través de
  `DatagridService::list('pokemon', ...)` y la vista `resources/views/pokedex/index.blade.php`
  (Alpine + Tailwind, filtros actuales: vistos/no vistos/atrapados/todos, tipo, esfuerzo y
  búsqueda).
- El detalle de especie se compone en `app/Support/PresentadorDetalleEspecie.php`, que ya
  expone `abilities[].effect`; la vista pinta `hab.effect` con `line-clamp-3`.
- Las habilidades viven en `abilities` (`app/Models/Ability.php`) con `name`, `effect` y
  `data.effect_keys`.
- La carga de datos está en `database/seeders/AbilitySeeder.php`:
  - lee `storage/data/poke skills - habilidades.csv` (columna `efecto`, 58 filas) y
    `storage/data/poke habilidades.csv`;
  - después aplica `storage/data/abilities_lote1.json`, `abilities_lote2.json` y
    `abilities_lote3.json`, cuyas 201 definiciones tienen `"effect": ""` y **sobrescriben**
    con vacío el efecto cargado antes desde el CSV.
- El efecto real de combate se implementa en `src/Battle/Domain/Effects/FabricaEfectos.php`
  y se resuelve por claves de `src/Battle/Domain/Catalog/CatalogoHabilidades.php`; hay
  habilidades personalizadas del proyecto.
- El filtrado de la Pokédex se apoya en `app/Datagrid/*` (`DatagridService`,
  `DatagridDefinition`, `RelationFilter`).

## Decisiones aclaradas por el desarrollador

1. **Filtro:** dos filtros independientes en la Pokédex, **Habilidad** y **Movimiento**,
   con selección única cada uno y combinables con los filtros existentes (visto, tipo,
   esfuerzo, búsqueda).
2. **Movimiento:** el filtro considera **todo el learnset** registrado en `pokemon_moves`,
   no solo los movimientos por nivel.
3. **Habilidad:** incluye **todas** las habilidades registradas para la especie, también
   las ocultas.
4. **UI:** los dos selectores tendrán **buscador** para localizar entre cientos de
   habilidades/movimientos.
5. **Descripciones:** solo en el detalle de especie de la Pokédex y **fieles al efecto
   implementado**, incluidas las habilidades personalizadas.
6. **Persistencia:** actualizar los ficheros de `storage/data/` (CSV/JSON), ajustar el
   seeder para que no se pierdan los textos y re-ejecutarlo. La base de datos se actualiza
   a través del seeder; no se edita a mano.

## Restricciones

- Sin tests: no crear, modificar ni ejecutar la suite.
- Sin dependencias nuevas.
- No tocar `.env` ni secretos.
- No revertir ni sobrescribir cambios ajenos.
- Mantener el estilo existente de la Pokédex (Alpine + clases del design system).
- Las descripciones deben ser fieles al efecto implementado; no inventar efectos.
