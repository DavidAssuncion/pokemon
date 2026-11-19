# TASK-066 — Petición

- **ID:** TASK-066
- **Fecha:** 2026-11-19
- **Origen:** desarrollador, sesión de OpenCode.
- **Proyecto:** `/home/david/pokemon`.
- **Tipo:** corrección de bug en filtros de la Pokédex.
- **Estado:** RECEIVED.

## Texto literal

> Revisa los filtros de la pokedx, por nombre no estaba filtrando corectamente

## Contexto conocido

- La Pokédex se sirve desde `PlayerController::pokedex` y
  `resources/views/pokedex/index.blade.php`.
- La vista usa Alpine (`pokedexApp()`) y envía el texto a
  `/datagrid/pokemon` con `params.set('search', this.searchQuery.trim())`
  (`resources/views/pokedex/index.blade.php:841-842`).
- El datagrid define `searchable: ['name']` para el recurso `pokemon`
  (`app/Providers/DatagridServiceProvider.php`).
- `DatagridService::applySearch()` aplica `LIKE %term%` sobre las columnas
  `searchable` (`app/Datagrid/DatagridService.php:113-130`).
- La vista y el proveedor tienen cambios sin commitear de TASK-061 (filtros de
  habilidad/movimiento y combobox); TASK-064 también añadió un bloque QA en el
  modal. No se deben revertir.

## Interpretación inicial

El filtro de búsqueda por nombre de la Pokédex no devuelve los resultados
esperados: puede ignorar el texto, devolver resultados incorrectos, no aplicar
el término junto con otros filtros, o perder la búsqueda al paginar/ordenar.
Hay que localizar la causa exacta y corregirla sin alterar los filtros de tipo,
esfuerzo, habilidad, movimiento, visto/atrapado ni el modo QA.

## Ambigüedades

- No se indica un ejemplo concreto (nombre buscado, resultado observado y
  esperado). El análisis debe reproducir el flujo con el código y, si no hay
  evidencia suficiente, devolver una lista de comprobaciones manuales.

## Restricciones conocidas

- No se crean, actualizan ni ejecutan tests.
- No se sobrescriben los cambios locales preexistentes (TASK-061, TASK-063,
  TASK-064 y modificaciones sin commitear en `master`).
- Sin `git push` ni operaciones destructivas.
- No se modifica `PlayerController.php` si el análisis demuestra que el fallo
  está en la vista o en el datagrid.
- La corrección debe preservar el comportamiento del resto de filtros.
