# TASK-036 — Aprobación

- **ID de tarea:** TASK-036
- **Versión del plan:** v1
- **Estado:** APROBADO
- **Texto literal de la aprobación:** «Aprobar plan v1» (opción seleccionada por el desarrollador en la pregunta de aprobación del 2026-11-09).
- **Fecha:** 2026-11-09
- **Alcance autorizado:** plan v1 completo, incluidas las 8 decisiones de la sección 10 de `plan.md`:
  1. Alcance limitado a `sand-tomb`, `fire-spin` y `whirlpool`.
  2. Permitir el autoobjetivo bajo `DESAFIO` sin redirección.
  3. Etiqueta “SOBRE SÍ MISMO” en el panel de movimientos.
  4. Permitir gastar PP/turno aunque el escudo esté al máximo o Acua Aro ya esté activo.
  5. Usar el flag separado `actorAutoobjetivoSeleccionable` (no incluir al actor en `refsAliado`).
  6. Cambiar el log a “sobre sí mismo” en `ResolutorAccionBatalla`.
  7. Mantener la IA intacta, sin autoobjetivo.
  8. Sin cambios en `SESSION_VERSION`, `DTOAccionBatalla`, serialización, migraciones, tests ni dependencias.

## Registro

- 2026-11-09: aprobación explícita recibida. Se autoriza la implementación de TASK-036 según el plan v1.
