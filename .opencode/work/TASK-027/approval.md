# TASK-027 — Aprobación

- **Estado:** APROBADO
- **ID de tarea:** TASK-027
- **Versión aprobada:** v1
- **Texto literal:** «Aprobar plan v1 (≤150)»
- **Fecha:** 2026-11-06
- **Alcance autorizado:** fachada de `Combate` ≤150 líneas + 14 colaboradores + limpieza de una entrada obsoleta del baseline.
- **No autorizado:** agrupar estado o cambiar Blade/JS; restaurar docblocks de TASK-025; Ola 1C; Ola 4; tests; migraciones; dependencias; `git push`.
- **Restricciones:** 32 propiedades y 18 métodos públicos congelados; `render()`, vistas, JS, rutas, layout, `SESSION_VERSION` y `BattleSessionService` sin cambios; revisión independiente; PHPStan ≤34 y deptrac ≤100.
