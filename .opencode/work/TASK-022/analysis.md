# TASK-022 — Análisis

- **Estado:** NO REQUIERE NUEVO ANÁLISIS
- **Referencia principal:** `.opencode/work/TASK-020/analysis.md` (mapa de `ServicioEjecucionBatalla`, métodos, consumidores y riesgos).
- **Referencia de cierre:** `.opencode/work/TASK-021/final-report.md`.
- **Cobertura:** `calcularYAplicarDano`, `calcularYAplicarDanoContra`, `aplicarEstado`, `aplicarStatChanges`, `aplicarEstadoYStatChanges`, `generarLogMovimiento`, `esquiva`, `dispararReacciones`, `aplicarAutodestruccion`, drenaje/recoil/coste/curación.
- **Brecha conocida:** la secuencia exacta de precondiciones, precisión, multi-golpe, efectos globales y post-impacto debe preservarse literalmente; cualquier reordenación es regresión.
- **Decisión:** no se lanza un nuevo `analyst`; el mapa existente es suficiente.
