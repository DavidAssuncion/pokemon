# TASK-067 — Aprobación

- **ID de tarea:** TASK-067
- **Estado:** APROBADO
- **Plan:** v1
- **Fecha:** 2026-11-19
- **Aprobado por:** desarrollador, mediante selección explícita en la herramienta `question`.

## Texto literal de la aprobación

```text
Aprobar plan v1
```

## Alcance autorizado

- Filtrar en `ObtenerGranja` las familias sin ningún miembro avistado.
- Proyectar `base`, `evolutions` y `types` solo con miembros avistados.
- Filtrar `inventario.familia` a las cadenas visibles.
- Bloquear en backend las acciones de granja sobre familias no avistadas con
  404 neutro.
- Prefiltro silencioso en `CosecharTodo`.
- Propagar el flag QA `?unlock=1` desde el modal y leerlo en el controlador.
- Ajustar el mensaje de vacío del modal.
- Actualizar `src/Granjas/context.md`.
- Sin migraciones, sin tests, sin cambios en admin ni en el contrato de
  `/api/habitats/{id}/families`.

Cualquier cambio de alcance requiere una nueva versión del plan y aprobación.
