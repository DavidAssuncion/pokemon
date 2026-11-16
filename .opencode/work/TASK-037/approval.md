# TASK-037 — Aprobación

- **ID de tarea:** TASK-037
- **Versión del plan:** v1
- **Estado:** APROBADO
- **Texto literal de la aprobación:** «Aprobar plan v1» (opción seleccionada por el desarrollador en la pregunta de aprobación del 2026-11-09).
- **Fecha:** 2026-11-09
- **Alcance autorizado:** plan v1 completo:
  - Rival de ruta fijado al abrir el modal, snapshot en sesión con `preview_id` y TTL de 1 h.
  - Reutilización exacta del rival en `IniciarCombateRuta`.
  - `conNivel` para cambios de nivel entre preview y combate.
  - Leyenda de perfiles IV/EV y stats anónimos por Pokémon del rival.
  - `team_id` opcional en gimnasio.
  - PC de ruta exacto (`rival_fijado`).
  - Limpieza con DELETE y al iniciar combate.
  - Frontend con leyenda siempre visible, tablas accesibles y modal desplazable.
  - Sin cambios de fórmulas, balance, esquema, dependencias ni tests.
  - Sin eliminación del código muerto de TASK-035.

## Registro

- 2026-11-09: aprobación explícita recibida. Se autoriza la implementación de TASK-037 según el plan v1.
