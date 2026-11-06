# TASK-022 — Petición

- **Fecha:** 2026-11-06
- **Origen:** continuación de TASK-021 (Ola 1 completada, commit `a83c9ed`).
- **Petición literal:** «Adelante con los cambios».
- **Interpretación:** ejecutar la Ola 2 del refactor backend: Fase 3A (`ServicioEjecucionBatalla`) y los seguimientos independientes acordados.

## Contexto

- `TASK-021` cerró la Ola 1 con las fases 2, 3B y 6A aprobadas.
- Quedan:
  - Fase 3A: dividir `ServicioEjecucionBatalla`.
  - Fase 4: `AgregadoBatalla`.
  - Fase 5: serialización v22 / `SESSION_VERSION`.
  - Fase 6B: código muerto/frontend.
- Seguimientos no bloqueantes de la Ola 1:
  - `EstadosCombatiente::puedeActuar()` 84 líneas.
  - `SerializadorCombatiente::serializarEfectos()` sin uso.
  - Deuda documental histórica del CRUD en README raíz, `docs/architecture.md` y `docs/context.md`.

## Restricciones

- No tests.
- No dependencias, migraciones, rutas ni esquema.
- No `git push`.
- Implementación paralela solo con archivos disjuntos.
- No tocar `SESSION_VERSION` ni el payload de sesión.
- Cada ola requiere plan y aprobación explícitos.
