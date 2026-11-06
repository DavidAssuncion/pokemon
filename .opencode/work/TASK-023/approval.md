# TASK-023 — Aprobación

- **Estado:** APROBADO
- **ID de tarea:** TASK-023
- **Versión aprobada:** v1
- **Texto literal:** «Aprobar plan v1 (Ola 3)»
- **Fecha:** 2026-11-06
- **Alcance autorizado:** Ola 3:
  - Frente A: Fase 4 — `AgregadoBatalla` como fachada + procesadores sin estado.
  - Frente B: Fase 6B — eliminación de los 7 archivos muertos listados en el plan.
- **No autorizado:** Fase 5 (serialización v22 / `SESSION_VERSION`), descomposición de `Livewire/Combate.php`, JS inline de combate, rutas no-op, `EfectoMoody`, `EstadoPendiente`, `GeneradorAleatorioPhp`, `RelojSistema`, `config/ayuda.php`, `misiones/index`, vistas gigantes, tests, migraciones, dependencias, `git push`.
- **Restricciones:** sin propiedades nuevas en `AgregadoBatalla`; sin mover estado; sin cambios de serialización; archivos disjuntos por frente; revisión independiente; PHPStan ≤34 y deptrac ≤100.
