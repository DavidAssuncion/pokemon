# TASK-024 — Aprobación

- **Estado:** APROBADO
- **ID de tarea:** TASK-024
- **Versión aprobada:** v1
- **Texto literal:** «Aprobar plan v1 (Ola 1)»
- **Fecha:** 2026-11-06
- **Alcance autorizado:** Ola 1 de TASK-024:
  - Frente A: parciales de `equipos/index` (7 archivos).
  - Frente B: parciales de `habitats/show` (9 archivos).
  - Frente C: parcial del JS de combate.
- **No autorizado:** Ola 3 (`Livewire/Combate.php`, 3A-3D), 3E (`ResolutorAccionBatalla`), Ola 4 (componentes compartidos), scripts de Equipos/Hábitats, cambios de rutas/controladores/endpoints, `SESSION_VERSION`, tests, migraciones, dependencias, `git push`.
- **Restricciones:** copia literal de vistas; JS como script clásico; mismos ids/clases/`wire:*`; archivos disjuntos por frente; revisión independiente; PHPStan 34 y deptrac 100.
