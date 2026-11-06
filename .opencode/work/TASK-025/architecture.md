# TASK-025 — Arquitectura

- **Estado:** NO REQUIERE NUEVO DISEÑO
- **Referencia:** `.opencode/work/TASK-024/architecture.md` §5 (Ola 3: 3A-3D).
- **Decisión:** se mantiene el diseño aprobado:
  - Colaboradores `final` sin estado en `App\Livewire\Combate\Soporte`.
  - DTOs/VOs inmutables en `App\Livewire\Combate\Soporte\DTO`.
  - Propiedades públicas y métodos `wire` de `Combate` intactos.
  - Colaboradores privados resueltos en `boot()` con `??=`.
  - Estado (`$eventos`, `$eventoTick`, `$grabandoEventos`, propiedades públicas) permanece en el componente.
  - `syncViewData` es el único punto que consume el log del agregado.
- **3E** (`ResolutorAccionBatalla`) queda fuera; requiere aprobación separada.
- **Restricción:** cualquier desviación del diseño de TASK-024 requiere consultar al `architect`.
