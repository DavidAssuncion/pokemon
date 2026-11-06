# TASK-021 — Arquitectura

- **Estado:** NO REQUIERE NUEVO DISEÑO
- **Referencia principal:** `.opencode/work/TASK-020/architecture.md` (componentes objetivo, puertos, estado, serialización, fases y riesgos).
- **Decisión:** se mantiene la arquitectura aprobada en TASK-020: fachada + delegación, extracción incremental, cálculo puro primero y serialización al final.
- **Único añadido de esta tarea:** las olas de ejecución paralela se definen en `plan.md`; no cambian los contratos ni los componentes objetivo.
- **Restricción:** cualquier desviación de la arquitectura de TASK-020 requiere consultar al `architect` antes de implementar.
