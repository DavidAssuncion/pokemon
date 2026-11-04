# TASK-010 — Tareas

Plan v2 aprobado. Implementación y verificación completadas; quedan dos acciones de entorno/validación.

| ID | Fase | Responsable | Estado |
|----|------|-------------|--------|
| F0 | Base limpia: TASK-009 commiteado; rama `task-010` | orchestrator | completed |
| F1 | Nombres: `Str::ucfirst` en `PokemonSeeder` (sin backfill) | database | completed |
| F2 | Sesión: `SESSION_LIFETIME=525600` (config, env, compose) | backend | completed (`.env` local pendiente) |
| F3 | Shiny backend: exploraciones + evolución | backend | completed |
| F4 | Shiny vistas: `exploraciones/index`, `reclutado/show` | frontend | completed |
| F5 | Verificación estática y manual | orchestrator/especialistas | completed (estática; visual pendiente) |
| F6 | Seguridad | security | completed (`APPROVED_WITH_FINDINGS`) |
| F7 | Revisión independiente | reviewer | no aplica (plan v2 D7) |
| F8 | Informe final | orchestrator | completed |
