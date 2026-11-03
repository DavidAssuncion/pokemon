# TASK-009 — Tareas

Plan v1 aprobado (2026-11-03). Rama `refactor/ui-tokens`.

| ID | Fase | Responsable | Estado |
|----|------|-------------|--------|
| F0 | Preparación: rama, baseline, árbol limpio | orchestrator | completed |
| F1 | Tokens (`tokens.css`, `components.css`, partial de tipos) | frontend | completed (`100f836`) |
| F2 | Aislamiento de Bootstrap (JS/CSS) | frontend | completed (`71c33a3`) |
| F3 | Componentes base + `sprite` + piloto | frontend | completed (`6bec4cb`) |
| F4a | Layout, auth, onboarding | frontend | completed (`f58663f`) |
| F4b | Sendas, misiones, gimnasios, reclutamiento, hábitats index | frontend | completed (`e8a89c6`) |
| F4c | Pokédex, exploraciones, gimnasios internos | frontend | completed (`a1952b0`) |
| F4d | `habitats/show` | frontend | completed (`c0703e9`) |
| F4e | `equipos/index` + partial | frontend | completed (`556cedf`) |
| F5 | Combate | frontend | completed (`51bfe93`) |
| F6 | Limpieza, `TipoBadges::CLASES` y prueba de theme | frontend + backend | completed (`e7837a9`) |
| Fix shiny | `es_shiny` en sprites de equipos, hábitats, gimnasios, ficha y explorador | frontend + backend | completed (`199ab67`) |
| Fix combate | Colores pre-F5 restaurados y orden de capas para cards de ataque | frontend | completed (`71e2491`) |
| R | Revisión independiente | reviewer | completed — `APPROVED` rondas 2, 3 y 4 |
| S | Seguridad | — | no aplica |
| D | Documentación | — | no aplica |

## Cierre

- Commit de correcciones de revisión: `25237dc`.
- Estado final: `COMPLETED`.
- Pendiente: validación visual manual y decisión de fusión; sin `git push`.

## Desviaciones documentadas

- **F3/F6:** se eliminaron `ui.select`, `ui.badge`, `ui.modal` y `ui.empty-state` por 0 usos (F6). El set final es `ui.button`, `ui.input`, `ui.card`, `ui.alert`, `sprite` y `tipo-badge`.
- **F4e:** los modales de `equipos/index` siguen con `div` + Alpine tokenizado; no se usó `<dialog>`/`ui.modal` para no arriesgar el morph de Livewire sin validación en navegador.
- **F5:** `_pokemon-card` usa `x-sprite size="combat"` (128px); el icono de postura de 18px en `preparacion-panel` se mantiene como `<img>` por espacio.
- **F6:** `@source "../../src"` se conserva porque quedan clases Tailwind emitidas por PHP (`hpClass`, `posturaClase`, `claseCarta`, etc.).
- **Documentación:** `src/Shared/context.md` queda desactualizado respecto a `clases`; documentación marcada `no aplica` en el plan.
