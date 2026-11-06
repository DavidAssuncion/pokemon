# TASK-020 — Tareas

| ID | Tarea | Responsable | Estado |
|---|---|---|---|
| T-01 | Recepción y aclaración de alcance (Livewire se mantiene; Filament no existe) | orchestrator | Completado |
| T-02 | Mapa de responsabilidades y consumidores del backend objetivo | analyst | Completado |
| T-03 | Diseño de descomposición Battle | architect | Completado |
| T-04 | Diseño de descomposición Exploraciones | architect | Completado |
| T-05 | Consolidación de arquitectura | orchestrator | Completado |
| T-06 | Plan v1 de Fase 1 | orchestrator | Completado |
| T-07 | Aprobación del plan v1 | desarrollador | Completado («Aprobar plan v1 (Fase 1)») |
| T-08 | Implementación Fase 1 (puertos + cálculo puro) | backend | Implementado; revisión pidió correcciones |
| T-09 | Corrección de `CalculadoraPerdidas` (≤200 líneas, sin Illuminate) | backend | Completado: 199 líneas, sin Illuminate, Pint/PHPStan/deptrac OK |
| T-10 | Re-revisión independiente de la Fase 1 | reviewer | Completado: APPROVED |
| T-11 | Informe final de TASK-020 | orchestrator | Completado |
| T-12 | Fase 2 (`Combatiente` en componentes) | backend | No autorizado |
| T-13 | Fase 3 (`Servicio` + handlers de Exploraciones) | backend | No autorizado |
| T-14 | Fase 4 (`AgregadoBatalla`) | backend | No autorizado |
| T-15 | Fase 5 (serialización v22 / retirada de `SESSION_VERSION`) | backend | No autorizado |
| T-16 | Fase 6 (limpieza CRUD legado, código muerto, frontend) | backend/frontend | No autorizado |
| T-17 | Tests | — | **No aplica** (fuera de alcance) |

## Nota de cierre

La Fase 1 está completada y aprobada (`APPROVED`). Los cambios están consolidados en un único commit local y no se ha hecho `git push`. Las fases 2-6 no están autorizadas.
