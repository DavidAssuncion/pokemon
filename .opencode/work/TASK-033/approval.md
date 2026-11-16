# TASK-033 — Aprobación

- **ID de tarea:** TASK-033
- **Versión del plan:** v1
- **Estado:** APROBADO
- **Texto literal de la aprobación:** «Aprobar plan v1» (opción seleccionada por el desarrollador en la pregunta de aprobación del 2026-11-09).
- **Fecha:** 2026-11-09
- **Alcance autorizado:** plan v1 completo, incluidas las 8 decisiones de la sección 10 de `plan.md`:
  1. Añadir `$prioridadesObjetivo` y `$coberturas` (34 propiedades/18 métodos).
  2. `DESAFIO` prevalece sobre la elección de prioridad, con aviso en UI.
  3. Solo el jugador puede apoyar a aliados en retaguardia; IA intacta.
  4. Cobertura elegida solo en preparación, sin persistencia en Equipo.
  5. Reasignación de Cubrir al morir el objetivo en la siguiente ronda.
  6. Añadir `AgregadoBatalla::objetivosRivalesLegalesPara()` como lectura pura.
  7. `wire:model.live` en el select de movimiento de prioridad.
  8. Sin cambios en `SESSION_VERSION`, `BattleSessionService`, serialización de dominio, migraciones, tests ni dependencias.

## Registro

- 2026-11-09: aprobación explícita recibida. Se autoriza la implementación de TASK-033 según el plan v1.
