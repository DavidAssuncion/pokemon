# TASK-023 — Arquitectura

- **Estado:** NO REQUIERE NUEVO DISEÑO
- **Referencia principal:** `.opencode/work/TASK-020/architecture.md` §3.2 (fachada + procesadores sin estado) y §8 (fases y dependencias).
- **Decisión:** se mantiene la arquitectura aprobada:
  - `AgregadoBatalla` conserva sus 39 métodos públicos y delega.
  - Los componentes nuevos son **sin estado**; el estado sigue en el agregado.
  - No se introduce `FormatoSesionBatalla` ni se toca `SESSION_VERSION` (Fase 5).
  - `__wakeup()` sigue rehidratando clima, Trick Room y gas; si algún componente nuevo interviene, debe ser reconstruible sin estado.
- **Componentes objetivo (Fase 4):** `ProcesadorCondicionesRonda`, `GestorClimaTerreno`, `GestorGasReactivo`, `GestorInvocaciones`, `ProcesadorDanioDiferido`, `MotorRondas`, `EjecutorAccion`.
- **Fase 6B:** el alcance concreto depende del inventario del `analyst`; no se cambia arquitectura, solo se elimina código sin consumidores y se extrae deuda de presentación cuando exista evidencia.
- **Restricción:** cualquier desviación de TASK-020 requiere consultar al `architect` antes de implementar.
