# TASK-056 — Tareas delegadas

| # | Especialista | Alcance | Estado |
|---|---|---|---|
| 1 | `analyst` A | Forja, catálogo de ítems, inventario y UI | COMPLETADO (solo lectura) |
| 2 | `analyst` B | Granjas/nidos, Pokémon, equipamiento y combate | COMPLETADO (solo lectura) |
| 3 | `architect` | Diseñar dominio, contratos, persistencia y estrategia de fases | COMPLETADO (solo diseño) |
| 4 | `backend` | F1: dominio, puerto, casos de uso, contrato y controller | COMPLETADO (handoff 2026-11-18; pendiente integración/revisión) |
| 5 | `frontend` | F1: UI de Forja | COMPLETADO (pendiente de integrar con backend) |
| 5b | `backend` | F1: corregir `categoria` → clave de sección (`medicinas`, `mejoras`, `reductores`, `resistencias`, `piezas`) | COMPLETADO |
| 5c | `backend` | F1: correcciones C1-C6 de revisión/seguridad (lock de unicidad, etapas sin padre, puertos `DefinicionesPieza`/`bloquear`, anclaje regex, mensaje genérico) | COMPLETADO |
| 6 | `documenter` | F1: `src/Forja/context.md` + decisión `categoria` en el diseño | BLOQUEADO (permisos: `src/**` deny) |
| 7 | `database` | F2: migración `recruit_gear` | COMPLETADO (archivo; **no ejecutada**) |
| 8 | `backend` | F2: equipamiento, batalla y CP | COMPLETADO (C9-C18) |
| 9 | `frontend` | F3: UI de 3 slots (condicionada) | BLOQUEADA por WIP de equipos |
| 9b | `frontend` | F1-UI v2: una tarjeta por familia + modal de craft con pestañas y 3 tipos | COMPLETADO |
| 10 | `reviewer` | Revisión independiente F1/F2 (varias iteraciones) | COMPLETADO — APPROVED |
| 11 | `security` | Auditoría F1/F2 | COMPLETADO — APPROVED |

## Pausa (2026-11-17)

Tarea pausada durante F1. El frontend está terminado; el backend sigue en segundo plano. No se
considera F1 completada ni revisada. Ver `execution.md` § «PAUSA» para el estado exacto y la lista
de reanudación.


Sin tests.
