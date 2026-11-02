# Índice de reglas del proyecto (.ai/rules)

Reglas durables de arquitectura y convenciones del proyecto. Antes de crear o editar cualquier
fichero, lee **todos** los ficheros de reglas cuyo glob cubra la ruta en cuestión (tabla abajo)
y ejecuta `grep -rin '<keyword>' .ai/rules` para capturar reglas que el glob no cubra.

| Glob | Regla | Fichero |
|---|---|---|
| `src/**` | Convención de módulo DDD (Domain puro / App con Bus / Infra con Laravel) | `ddd-modules.md` |
| `src/**/Infra/Controllers/**` | Controladores de módulo (sin try-catch, IDs por URL salvo POST) | `infra-controllers.md` |
| `src/**/Domain/Repositories/**` | Contrato de repositorio (español, sin getCollection genérico) | `domain-repositories.md` |
| `resources/**` | Lógica de negocio y orden de colecciones en backend; frontend mínimo | `frontend-minimal-logic.md` |
| `src/**`, `app/**`, `resources/**` | Arrays prohibidos como contrato; DTOs/colecciones, constantes y límites de capa | `arrays-dto-collections.md` |

La convención completa de referencia es `docs/ddd.md`.
| `**` | Política de modelo: siempre `opencode-go/deepseek-v4.1-flash`, sin cambios | `model-policy.md` |
