# TASK-021 — Petición

- **Fecha:** 2026-11-06
- **Origen:** continuación de TASK-020 (Fase 1 completada y commit `fc7fecf`).
- **Petición literal:** «Adelante. puedes hacerlo en paralelo todos los cambios?»
- **Interpretación:** ejecutar las fases 2-6 restantes del refactor backend, evaluando qué se puede paralelizar.

## Contexto

- `TASK-020` cerró la Fase 1: puertos de azar/reloj y cálculo puro de Battle y Exploraciones.
- Quedan las fases 2-6 de `TASK-020/architecture.md`:
  - Fase 2: `Combatiente` en componentes.
  - Fase 3: `ServicioEjecucionBatalla` + handlers de Exploraciones.
  - Fase 4: `AgregadoBatalla`.
  - Fase 5: serialización v22 / retirada de `SESSION_VERSION`.
  - Fase 6: limpieza CRUD, código muerto y frontend.
- El working tree está limpio; HEAD `fc7fecf`; rama `task-011`.

## Restricciones

- No crear, modificar ni ejecutar tests.
- No cambiar dependencias, migraciones, rutas ni esquema sin autorización específica.
- No `git push`.
- Implementación paralela solo con archivos y responsabilidades disjuntas.
- Cada ola requiere revisión independiente y aprobación de su plan.
- No tocar `SESSION_VERSION` ni el payload de sesión hasta la Fase 5.
- No romper el comportamiento, el orden de RNG ni el shape de `eventos`.

## Aclaración de alcance

«Todos los cambios» no significa «todo a la vez». La arquitectura tiene dependencias:

- Fase 3A (`ServicioEjecucionBatalla`) depende de que la fachada de `Combatiente` esté estable.
- Fase 4 depende de la Fase 3A.
- Fase 5 depende de las fases 2 y 4.
- Fase 6B (código muerto/frontend) conviene después de las fases que pueden cambiar sus referencias.

La Ola 1 puede ejecutar en paralelo Fase 2 + Fase 3B (Exploraciones) + Fase 6A (CRUD), porque sus archivos son disjuntos.
