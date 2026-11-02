# TASK-003 — Tareas

## Fase de diagnóstico y diseño

| # | Tarea | Responsable | Estado |
|---|---|---|---|
| 1 | Flujo funcional, causa del bug individual/equipo, deuda y datos | `analyst` A | completado |
| 2 | Presentación, eventos, oportunidades de personalidad y tests | `analyst` B | completado |
| 3 | Diseño del refactor y contratos | `architect` | completado |
| 4 | Plan versionado y aprobación | `orchestrator` | completado (plan v2 aprobado) |

## Fase de implementación

| # | Tarea | Responsable | Estado |
|---|---|---|---|
| 5 | Economía de recompensas (dominio + aplicación) | `backend` | completado |
| 6 | Tiempo, creación y procesamiento bajo demanda | `backend` | completado |
| 7 | Bloqueo individual y guarda de combate | `backend` | completado |
| 8 | Presentación y personalidad (PHP) | `backend` | completado |
| 9 | Vistas Blade y accesibilidad | `frontend` | completado |
| 10 | Revisión y QA | `reviewer` / `qa` | completado (`APPROVED` / `PASS WITH KNOWN ISSUES`) |
| 11 | Seguridad | `security` | no aplica (decisión del desarrollador) |
| 12 | Correcciones post-QA (constantes 30/1, tests, objetos perdidos) | `backend` | completado |
| 13 | Documentación e informe final | `documenter` / `orchestrator` | completado |

## Deuda abierta (fuera de TASK-003)

- `phpstan` completo: 8 errores preexistentes en `SendasController`.
- Seguridad no auditada por decisión del desarrollador.
- Fase 2: god-handler, preview único, idempotencia fuerte, `withoutOverlapping()`, cast de
  `hora_limite`, limpieza de alias/columnas muertas.
