# TASK-058 — Aprobación

- **ID de tarea:** TASK-058
- **Estado:** APROBADO
- **Plan:** v1
- **Fecha:** 2026-11-18
- **Texto literal de la aprobación:** `Aprobar plan v1`

## Alcance autorizado

1. `resources/views/reclutamiento/partials/_grid.blade.php`: cambiar
   `:disabled="$store.reclutamientoNavbar.accionEnCurso"` por
   `:disabled="$store.reclutamientoNavbar.accionEnCurso !== ''"`.
2. `resources/views/reclutamiento/partials/_modal-descartar.blade.php`: idem.
3. `resources/views/reclutamiento/partials/_modal-descartar-todos.blade.php`: idem.
4. `src/Exploraciones/Infra/Controllers/ExpedicionesPanelController.php`:
   inyectar `ProcesarExploracionesPendientes`, procesar en `index()` con
   `paraUsuario($userId)` y en `detalle()` con `una($exploracion)` + `refresh()`,
   y actualizar el docblock.

## Exclusiones

- No tocar stores JS, `CalculadorEstadoExpedicion`, `EstadoExpedicion`,
  `PresentadorExploraciones`, migraciones, datos ni assets.
- No tocar `resources/css/*`, `equipos/*`, `gimnasios/*` ni el resto de tareas.
- No crear, modificar ni ejecutar tests.
- No leer `.env` ni secretos.
