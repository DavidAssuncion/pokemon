# TASK-022 — Arquitectura

- **Estado:** NO REQUIERE NUEVO DISEÑO
- **Referencia principal:** `.opencode/work/TASK-020/architecture.md` §3.3 (fachada + piezas de `ServicioEjecucionBatalla`).
- **Decisión:** se mantiene la arquitectura aprobada: fachada que conserva los métodos públicos y delega en componentes, sin cambiar la secuencia de daño, RNG, efectos ni log.
- **Componentes objetivo:**
  - `EvaluadorPrecondicionesMovimiento`
  - `ResolutorPrecision`
  - `CalculadorGolpes` (ya existe)
  - `ResolutorImpacto`
  - `AplicadorDrenaje`, `AplicadorRecoil`, `AplicadorCosteVelocidad`, `AplicadorCuracion` (ya existen)
  - `AplicadorEfectosPostImpacto`
  - `AplicadorEstadoMovimiento`
  - `GeneradorLogMovimiento` (ya existe)
- **Restricción:** cualquier desviación requiere consultar al `architect` antes de implementar.
