# TASK-025 — Aprobación

- **Estado:** APROBADO
- **ID de tarea:** TASK-025
- **Versión aprobada:** v1
- **Texto literal:** «Aprobar plan v1 (3A-3D)»
- **Fecha:** 2026-11-06
- **Alcance autorizado:** Ola 2 del frontend (3A-3D):
  - 3A: `GrabadorEventosBatalla` + `SnapshotCombatiente`/`SnapshotBatalla`.
  - 3B: `SincronizadorVistaBatalla` + `VistaBatalla`.
  - 3C: `ColaTurnosBatalla`.
  - 3D: `EstadoAnimacionBatalla` + `AnimacionBatalla`.
- **No autorizado:** 3E (`ResolutorAccionBatalla`), cambios en `render()`, vistas, JS, rutas, controladores, `SESSION_VERSION`, tests, migraciones, dependencias, `git push`.
- **Restricciones:** contrato público de Livewire congelado; estado y sesión intactos; colaboradores sin estado; revisión independiente; PHPStan ≤34 y deptrac ≤100.
