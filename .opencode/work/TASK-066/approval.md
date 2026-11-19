# TASK-066 — Aprobación

- **ID de tarea:** TASK-066
- **Estado:** APROBADO
- **Plan:** v1
- **Fecha:** 2026-11-19
- **Aprobado por:** desarrollador, mediante selección explícita en la herramienta `question`.

## Texto literal de la aprobación

```text
Aprobar plan v1
```

## Alcance autorizado

- Cambiar la búsqueda de `LIKE` a `orWhereLike()` en
  `app/Datagrid/DatagridService.php` para que no distinga mayúsculas.
- Ajustar el placeholder y `aria-label` del buscador de Pokédex a
  "Buscar por nombre...".
- Mantener la búsqueda parcial y el respeto a la pestaña activa.
- Sin búsqueda por número, sin migraciones, sin tests, sin cambios en
  TASK-061/TASK-064.

Cualquier cambio de alcance requiere una nueva versión del plan y aprobación.
