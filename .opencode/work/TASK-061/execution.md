# TASK-061 — Ejecución y coordinación

## Preflight

- **Fecha:** 2026-11-18
- **Proyecto:** `/home/david/pokemon`
- **Rama:** `master`
- **Último commit:** `fbe370b 2026-11-18 updates`
- **Cambios locales:** `M .githooks/pre-commit` (sin cambios de contenido: 0 inserciones,
  0 borrados; preexistente, no relacionado con esta tarea). No se tocará.

## Decisiones de alcance (2026-11-18)

- Filtros: Habilidad + Movimiento, independientes y de selección única.
- Movimiento: todo el learnset (`pokemon_moves`), no solo level-up.
- Habilidad: todas, incluidas ocultas.
- UI: selectores con buscador.
- Descripciones: solo detalle de la Pokédex, fieles al efecto implementado.
- Datos: ficheros `storage/data/` + seeder; sin edición manual de BD.

## Hitos

- 2026-11-18: `analyst` completa el inventario (287 habilidades, 228 sin texto; filtros
  viables por `RelationFilter`). Resultado en `analysis.md`.
- 2026-11-18: `architect` entrega el diseño (filtros por id con columna cualificada,
  catálogos embebidos, combobox reutilizable, descripciones por `effect_key`). Resultado en
  `architecture.md`.
- 2026-11-18: plan v1 redactado y aprobado explícitamente (`Aprobar plan v1`).

## Implementación

- B1 (backend): filtros `ability`/`move`, `OpcionFiltro`, `CatalogoFiltrosPokedex` y
  controlador. Verificado con `DatagridService`: 4/4, 208/208, combinado 3, inválidos
  ignorados, `counts` estable. PHPStan corregido a 35 errores de baseline.
- F1+F2 (frontend): `<x-ui.combobox>`, factoría `comboboxFiltro` e integración en
  `/pokedex`. `view:cache` y `npm run build` OK; render con 2 comboboxes y `@js` válido.
- D1 (backend): `AbilitySeeder` de una pasada, idempotente; 287 habilidades, sin pérdida de
  textos.
- D2-D7 (backend): `storage/data/abilities_descripciones.json` con 227 claves; seeder
  `287 con texto · 0 sin texto · 0 actualizadas` en la segunda pasada.
- ADR-0005 creado por `documenter`.
- Verificación global: `php -l` OK, Pint OK, `composer analyse` 35 (baseline), JSON 227
  claves (120-229 caracteres), `abilities` 287, `asignadas_vacias` 0.

## Estado

`REVIEWING`
