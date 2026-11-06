# TASK-023 — Petición

- **Fecha:** 2026-11-06
- **Origen:** continuación de TASK-022 (Ola 2 completada, commit `8d21625`).
- **Petición literal:** «Adelantwe+» (interpretado como «Adelante»: continuar con la siguiente ola).
- **Interpretación:** preparar y ejecutar la Ola 3 del refactor: Fase 4 (`AgregadoBatalla`) y Fase 6B (código muerto y frontend).

## Contexto

- `TASK-021` cerró la Ola 1 (fases 2, 3B y 6A).
- `TASK-022` cerró la Ola 2 (fase 3A, seguimientos de `Combatiente` y documentación del CRUD).
- Quedan:
  - Fase 4: `AgregadoBatalla` (890 líneas, 39 métodos públicos).
  - Fase 5: serialización v22 / `SESSION_VERSION`.
  - Fase 6B: código muerto y frontend.
- `AgregadoBatalla` no tiene `__serialize` propio: se serializa el objeto completo. Mover estado antes de la Fase 5 rompe batallas en curso.

## Restricciones

- No tests.
- No dependencias, migraciones, rutas ni esquema.
- No `git push`.
- No tocar serialización ni `SESSION_VERSION` en esta ola.
- No mover estado de `AgregadoBatalla` fuera del agregado hasta la Fase 5.
- Implementación paralela solo con archivos disjuntos.
- Cada ola requiere plan y aprobación explícitos.

## Aclaración de alcance

La Fase 6B necesita inventario previo: código muerto real, vistas/componentes sin uso, duplicidades de frontend y deuda de `Livewire/Combate.php`. No se elimina nada sin evidencia de cero consumidores.
