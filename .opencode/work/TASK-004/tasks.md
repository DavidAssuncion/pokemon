# TASK-004 — Desglose de tareas

| # | Tarea | Responsable | Estado | Dependencias |
|---|-------|-------------|--------|--------------|
| 1 | Inventario y cuantificación de sumideros | `analyst` | completado | — |
| 2 | Inventario y cuantificación de fuentes | `analyst` | completado | — |
| 3 | Diseño base de la economía | `architect` | completado | 1, 2 |
| 4 | Addendum I: roles de niveles de hábitat | `architect` | completado | 3 |
| 5 | Addendum II: política de IV/EV por tier | `architect` | completado | 4 |
| 6 | Análisis III: Sendas, loadout y Showdown | `analyst` | completado | 5 |
| 7 | Addendum III: Sendas maestras y movesets rivales | `architect` | completado | 6 |
| 8 | Plan v5 y criterios de aceptación | `orchestrator` | completado | 7 |
| 9 | Aprobación explícita del plan v5 | `developer` | completado | 8 |
| 10 | Fase 1: estabilización contable y cadencia de exploración | `backend` | completado | 9 |
| 11 | Fase 2A: perfiles IV/EV y multiplicadores de dificultad | `backend` | completado | 10 |
| 12 | Fase 2B: estructura de Senda (1-3 ataques, 4-5 pasivos) | `backend` | completado | 11 |
| 13 | Fase 2C: selector de 6 movimientos, artefacto Showdown y config | `backend` | completado | 12 |
| 14 | Fase 2D: re-precio, captura y simulación económica | `backend` | completado | 13 |
| 15 | Fase 3: UI de Sendas, selector y preview de combate | `backend` + `frontend` | completado | 14 |
| 16 | Fase 4: ADR y documentación de módulos | `documenter` | **incompleto** (agotó pasos; no creó ADR/docs) | 10-15 |
| 17 | Revisión independiente final | `reviewer` | **parcial** (1, 2A y 2B revisadas; 2C/2D/3 sin revisión final) | 10-16 |
| 18 | QA de aceptación, regresión y golden files | `qa` | **no aplica** (tests desactivados por el desarrollador) | — |
| 19 | Auditoría de seguridad de inventario/recompensas | `security` | **omitida por el desarrollador**; auditoría parcial con 2 medium corregidos | 10-16 |
| 20 | Informe final y cierre | `orchestrator` | **completado** (`final-report.md`) | 17-19 |
