# TASK-012 — Solicitud

## Petición literal

> Adelante f2, f3, f4 y f5

## Interpretación

Continuar el sistema de bonguris, bayas, fabricación y uso de objetos en combate con las fases F2–F5 definidas en `.opencode/work/TASK-011/architecture.md` §10:

| Fase | Entregable |
|---|---|
| **F2** | Plantación y cosecha en nidos: migración `nest_plantings`, `PlantarBaya`, `CosecharPlantacion`, reglas de crecimiento, UI de huecos. |
| **F3** | Influencia de plantaciones y bonus por Pokémon del hábitat en el botín de exploración; auto-cosecha opcional. |
| **F4** | Forja: módulo `src/Forja`, catálogo de recetas, `ListarRecetas`, `Fabricar`, endpoints y UI. |
| **F5** | Uso de objetos en combate: `AccionObjeto`, efectos, fases Livewire, descuento de inventario, resistencia temporal y bump de sesión. |

## Contexto

- Proyecto: `/home/david/pokemon` — Laravel 12 / PHP 8.4 / Livewire 4.
- La Fase 1 está implementada, revisada y auditada en `TASK-011`, sin commitear, sobre la rama `task-011`.
- El diseño técnico de F2–F5 ya existe en `.opencode/work/TASK-011/architecture.md` (secciones 3.3–3.6, 4.2–4.4, 5, 6.3–6.6, 7, 13.2–13.5).
- El análisis de requisitos y del estado previo está en `.opencode/work/TASK-011/analysis.md`.
- La aprobación de TASK-011 cubre **solo F1**.

## Restricciones

- F2–F5 se ejecutan en una tarea nueva con plan y aprobación propios.
- No se crean, actualizan ni ejecutan tests; `tests/` queda fuera de alcance.
- No se toca `.githooks/pre-commit` ni `MVP.txt` (cambios locales preexistentes).
- No se hace `git push`, `git reset --hard`, `git clean` ni `git checkout --`.
- No se instalan ni actualizan dependencias.
- No se leen ni transmiten secretos.
- Las claves de objeto que llegan del cliente se validan siempre contra catálogo.
- Los cambios de F1 no se revierten ni se reescriben salvo corrección justificada.

## Decisiones de producto vigentes

- D-01: un único objeto equipado por Pokémon.
- D-02: usar un objeto consume la acción del aliado.
- D-03: objetos usados contra el enemigo solo aplican debuff.
- D-04: equipar descuenta inventario y exige propiedad.
- D-05: orden exploración/botín → granja → forja → combate.
- D-06: bonguri por hábitat con mapping provincial.
- D-10: sin tope duro por expedición; el botín se regula con pesos.
- D-11: `factorTiempo` proporcional al tiempo efectivo.
- D-12: `multiplicadorBotin` escala la probabilidad de bonguri.

## Criterio de cierre

Implementar F2, F3, F4 y F5 con verificación estática, revisión independiente, auditoría de seguridad cuando aplique e informe final. Sin tests.
