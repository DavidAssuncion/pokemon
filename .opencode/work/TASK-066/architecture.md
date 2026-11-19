# TASK-066 — Arquitectura

- **ID:** TASK-066
- **Estado:** NO APLICA
- **Fecha:** 2026-11-19

El fallo es localizado: la búsqueda ya llega correctamente al datagrid y el
`orWhere` está agrupado. La corrección es cambiar el operador de comparación a
`orWhereLike()` (insensible a mayúsculas, compatible con PostgreSQL/SQLite) y
ajustar el texto del placeholder de la vista. No hay cambios de contratos,
capas, rutas ni esquema.

No se requiere intervención de `architect`.
