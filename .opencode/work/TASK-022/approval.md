# TASK-022 — Aprobación

- **Estado:** APROBADO
- **ID de tarea:** TASK-022
- **Versión aprobada:** v1
- **Texto literal:** «Aprobar plan v1 (Ola 2)»
- **Fecha:** 2026-11-06
- **Alcance autorizado:** Ola 2:
  - Frente A: Fase 3A `ServicioEjecucionBatalla`.
  - Frente B: seguimientos A (`puedeActuar`, `serializarEfectos`).
  - Frente C: documentación histórica del CRUD.
- **No autorizado:** Fase 4 (`AgregadoBatalla`), Fase 5 (serialización v22 / `SESSION_VERSION`), Fase 6B (código muerto/frontend), tests, migraciones, dependencias, `git push`.
- **Restricciones:** archivos disjuntos por frente; revisión independiente; sin tests; PHPStan 34 y deptrac 105 deben mantenerse.
