# TASK-021 — Aprobación

- **Estado:** APROBADO
- **ID de tarea:** TASK-021
- **Versión aprobada:** v1
- **Texto literal:** «Aprobar plan v1 (Ola 1)»
- **Fecha:** 2026-11-06
- **Alcance autorizado:** Ola 1 de TASK-021:
  - Frente A: Fase 2 — `Combatiente` en componentes.
  - Frente B: Fase 3B — handlers de Exploraciones.
  - Frente C: Fase 6A — limpieza del CRUD legado.
- **No autorizado:** Fase 3A (`ServicioEjecucionBatalla`), Fase 4 (`AgregadoBatalla`), Fase 5 (serialización v22 / `SESSION_VERSION`), Fase 6B (código muerto/frontend), tests, migraciones, dependencias, `git push`.
- **Restricciones:** archivos disjuntos por frente; revisión independiente; sin tests; PHPStan/deptrac no deben empeorar.
