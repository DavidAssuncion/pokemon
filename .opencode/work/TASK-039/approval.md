# TASK-039 — Aprobación

- **ID de tarea:** TASK-039
- **Versión del plan:** v1
- **Estado:** APROBADO
- **Texto literal de la aprobación:** «Aprobar plan v1» (opción seleccionada por el desarrollador en la pregunta de aprobación del 2026-11-09).
- **Fecha:** 2026-11-09
- **Alcance autorizado:** plan v1 completo, incluidas las 7 decisiones de la sección 10 de `plan.md`:
  1. Eliminar por completo el método público `pasarTurno` (18 → 17 métodos).
  2. Mantener `Retrasar turno`, `Atacar con X`, «Cambiar objetivo», objetos y preparación.
  3. Considerar `Guardia`/`Cubrir` como acción válida.
  4. Fallback automático con log solo cuando no haya movimiento ni objeto aplicable.
  5. El fallback se difiere si la preparación está pendiente.
  6. Sin aviso visible fuera del registro y sin puente de compatibilidad.
  7. Sin cambios en IA, `SESSION_VERSION`, serialización, migraciones, dependencias ni tests.

## Registro

- 2026-11-09: aprobación explícita recibida. Se autoriza la implementación de TASK-039 según el plan v1.
