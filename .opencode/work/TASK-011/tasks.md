# TASK-011 — Tareas

| ID | Fase | Responsable | Estado |
|----|------|-------------|--------|
| A1 | Analizar inventario, objetos, combate, exploraciones, granja y assets | `analyst` | `completed` |
| DEC | Resolver decisiones de alcance y reglas (sección 11 de `analysis.md`) | `desarrollador` | `completed` |
| A2 | Analizar modos de exploración, bonguri por hábitat, bonus de origen y plantación/cosecha en nidos | `analyst` | `completed` |
| D1 | Diseñar modelo de datos, contratos y flujo de combate | `architect` | `completed` |
| P1 | Redactar plan v2 (F1) y solicitar aprobación | `orchestrator` | `completed` |
| AP1 | Aprobación explícita del plan v2 | `desarrollador` | `completed` |
| I1 | Implementación backend/dominio F1 | `backend` | `completed` |
| I2 | Migraciones F1 (`modo`, `bonguri`) | `database` | `completed` (aplicadas en local) |
| I3 | Vistas F1: selector de modo y resumen de botín | `frontend` | `completed` |
| R1 | Revisión independiente F1 | `reviewer` | `completed` (APPROVED) |
| S1 | Auditoría de seguridad F1 | `security` | `completed` (APPROVED) |
| F1 | Informe final F1 | `orchestrator` | `completed` |
| C1 | Backfill provincial en instalaciones nuevas (`BonguriHabitatSeeder`) | `database` | `completed` |
| C2 | Aplicar `multiplicadorBotin` y `factorTiempo`; cerrar farmeo instantáneo | `backend` | `completed` |
| C2b | Ajustar `factorTiempo` a `inicioVuelta` (75 % de la duración) | `backend` | `completed` |
| C3 | Mínimo de 60 min para `return_time` | `backend` | `completed` |
