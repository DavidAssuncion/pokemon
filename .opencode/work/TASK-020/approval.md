# TASK-020 — Aprobación

- **Estado:** APROBADO
- **ID de tarea:** TASK-020
- **Versión aprobada:** v1
- **Texto literal:** «Aprobar plan v1 (Fase 1)»
- **Fecha:** 2026-11-05
- **Alcance autorizado:** Fase 1 del plan v1:
  - Puertos compartidos `GeneradorAleatorio` y `Reloj` + adaptadores.
  - Extracción de cálculo puro de `ServicioEjecucionBatalla` (golpes, drenaje, recoil, coste de velocidad, curación, log) con delegación.
  - Extracción de cálculo puro de `FinalizarExploracionHandler` (duración, incidentes, pérdidas) con delegación.
  - Sin cambios de comportamiento, serialización, constructores públicos, `SESSION_VERSION`, `eventos`, frontend, CRUD, seguridad ni tests.
- **Restricciones:** no tests, no migraciones, no dependencias nuevas, no `git push`; Pint/PHPStan/deptrac no deben empeorar.
- **Nota:** las fases 2-6 de `architecture.md` no están autorizadas.
