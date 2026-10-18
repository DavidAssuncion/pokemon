# Tooling de agentes (OpenCode V2)

Documento canónico del andamiaje de agentes del proyecto: qué se adoptó, cómo está
configurado y cómo se usa. No describe el flujo de trabajo de roles (eso vive en
`docs/workflow.md`) sino las piezas de infraestructura que lo soportan.

## Decisión: Engram (memoria) vs Gentle-AI

| Opción | Veredicto | Motivo |
|--------|-----------|--------|
| **Gentle-AI** | **Descartado** | Invasivo: reescribía `default_agent`, exigía instalar un plugin global y activaba telemetría. El binario se desinstaló. |
| **Engram** | **Adoptado** | Memoria persistente entre sesiones y compactaciones. Binario standalone (Go) con SQLite + FTS5; no requiere daemon externo ni servicios. Se integra como servidor MCP. Versión **3.4.0**. |

Engram se configura como **MCP global** (`~/.config/opencode/opencode.jsonc`), de modo que
cualquier proyecto lo comparte; **no se duplica** en la config del repo. Binario:
`~/.local/bin/engram` (`engram mcp --tools=agent`).

## Arquitectura actual del tooling

### Configuración V2 (`opencode.jsonc`)

`opencode.jsonc` está migrado al esquema **V2 nativo**:

- `default_agent: analista` — agente primario por defecto.
- `agents` (plural) — **8 agentes**: `analista`, `backend`, `frontend`, `qa`, `cleaner`,
  `arquitecto`, `hardener`, `bibliotecario`. Los prompts se cargan con
  `system: "{file:./docs/agents/<x>.md}"` (interpolación de fichero soportada por V2 en
  `system`), no inline.
- `permissions` — reglas por agente ordenadas (`{action, resource, effect}`). Los agentes de
  solo análisis/revisión (analista, qa, arquitecto, hardener) deniegan `edit` + `shell`;
  bibliotecario deniega solo `shell`.
- **Modelos**: `deepseek/deepseek-flash#high` para analista, arquitecto, hardener y qa;
  `deepseek/deepseek-flash` para backend, frontend, cleaner y bibliotecario.
- `instructions` — contexto ambiental cargado siempre: `docs/context.md`,
  `docs/conventions.md`, `docs/ddd.md`, `docs/workflow.md` (además de `AGENTS.md`).
- `watcher.ignore`, `compaction.keep.tokens: 15000`, `tool_output.max_lines/max_bytes`.

Los 8 agentes quedaron intactos (mismos prompts y responsabilidades) tras la migración.

### MCP

- **Engram** — global, en `~/.config/opencode/opencode.jsonc`. Usa la forma directa
  `mcp.engram`, que el esquema V2 marca como **deprecada** pero sigue resolviendo.
- **Context7** — de proyecto, en `opencode.jsonc` bajo `mcp.servers.context7`
  (`type: remote`, `url: https://mcp.context7.com/mcp`), sin API key.
- **Formato canónico V2**: `mcp.servers.<nombre>` en `opencode.json(c)`. El formato Claude
  (`mcpServers` en `.mcp.json` / `~/.config/opencode/mcp.json`) **no lo lee** OpenCode V2.

### Skills

Convención V2: `.opencode/skills/<id>/SKILL.md`, con frontmatter YAML `name` + `description`
(la `description` es la que dispara el skill). Migradas desde el formato antiguo
`.opencode/skill/<id>/reference.md`.

- 7 skills de proyecto en `.opencode/skills/`: `laravel`, `blade`, `phpstan`, `phpunit`,
  `dbeaver`, `mcp`, `cambio-minimo`.
- 3 skills en `.agents/skills/` (formato Laravel Boost): `laravel-best-practices`,
  `infer-conventions`, `tailwindcss-development`.
- Índice en `docs/skills-index.md`.

### Comandos slash

Definidos en `.opencode/commands/*.md` (frontmatter `description` + `agent`, cuerpo con
`$ARGUMENTS`). Delegan en el agente correspondiente:

| Comando | Agente | Propósito |
|---------|--------|-----------|
| `/spec` | `analista` | Especificación funcional, sin código |
| `/qa` | `qa` | Revisión estática (sin ejecutar tests) |
| `/clean` | `cleaner` | Refactor sin nuevas features |
| `/harden` | `hardener` | Endurecimiento de código |
| `/docs` | `bibliotecario` | Documentar y cerrar la tarea |

### Memoria persistente (Engram)

Engram expone tools MCP (`mem_save`, `mem_search`, `mem_context`, `mem_session_summary`,
`mem_get_observation`, `mem_session_start/end`, …) y sobrevive a compactaciones.

Protocolo:

- **Guardar proactivamente** (`mem_save`) tras decisión, bug fix, descubrimiento,
  cambio de config o convención. Campos: `title` (verbo + qué), `type`
  (`bugfix|decision|architecture|discovery|pattern|config|preference`), `scope`,
  `topic_key` (opcional, para temas que evolucionan) y `content` (What / Why / Where / Learned).
- **Recuperar** con `mem_context` (historial reciente) y, si no hay match, `mem_search`;
  `mem_get_observation` para el contenido completo.
- **Cierre de sesión**: `mem_session_summary` obligatorio antes de dar una tarea por terminada.

## Adopción de patrones externos (2026-10-18)

| Fuente | Decisión |
|--------|----------|
| **Ponytail** (skill de cambio mínimo; MIT) | **Adaptado** al español como skill `cambio-minimo`, con principios derivados en los prompts del pipeline (Analista, Coder, QA, Cleaner, Hardener y Bibliotecario). |
| **Caveman** (patrones de trabajo; Apache-2.0) | **Adaptados** los patrones de trabajo (verificar sin expandir alcance, refactor seguro). **Descartado** el CLI/proxy: telemetría activa por defecto y reescritura de configuraciones de agentes (mismo motivo por el que se descartó Gentle-AI). |
| **deepseek-harness** (harness de agentes; MIT) | **No se migra de harness** (developer preview con breaking changes). Se adoptan la disciplina de no repetir checks ya pasados y el criterio «una casa por hecho» para documentación. |

La convención `atajo:` (atajos deliberados cosechados por el Bibliotecario al cierre) queda definida en `docs/conventions.md`.

## Pendientes / observaciones (no bloqueantes)

- **O1 — Skills `mcp` y `dbeaver` con formato obsoleto**: `.opencode/skills/mcp/SKILL.md` y
  `.opencode/skills/dbeaver/SKILL.md` documentan el formato V1/Claude
  (`~/.config/opencode/mcp.json`, `.mcp.json` con `mcpServers`). En V2 el formato es
  `mcp.servers` en `opencode.json(c)`. Contenido preservado por diseño, pero induce a error.
- **O2 — `docs/skills-index.md` incompleto**: listaba solo los 6 skills de `.opencode/skills`
  y omitía los 3 de `.agents/skills`. *(Actualizado en este cierre: ver `docs/skills-index.md`.)*
- **O4 — `.opencode/package.json` fija `@opencode-ai/plugin@1.18.22`**: API de plugins V1.
  Sin impacto hoy (no hay plugins locales); revisar si se adopta un plugin.
- **O6 — `.mcp.json` raíz no lo lee OpenCode V2**: el `laravel-boost` declarado ahí (formato
  `mcpServers`) no se carga. Si se necesita Boost, declararlo por la vía V2 (`mcp.servers`).
- **Nota MCP global**: la entrada `mcp.engram` usa la forma directa deprecada del esquema V2;
  funciona, pero migrarla a `mcp.servers.engram` es la forma recomendada.

## Verificado en fases previas

- `opencode models` resuelve `deepseek/deepseek-flash`.
- `opencode debug agents` resuelve la interpolación `{file:...}` en `system`.
- `default_agent = analista`.
- La skill `cambio-minimo` se descubre y carga en runtime (verificado el 2026-10-18).

## Referencias

- `opencode.jsonc` — Config V2 del proyecto (agentes, MCP, instrucciones).
- `~/.config/opencode/opencode.jsonc` — Config global (Engram).
- `docs/agents/*.md` — Prompts de los 8 agentes.
- `.opencode/skills/` / `.agents/skills/` — Skills; índice en `docs/skills-index.md`.
- `.opencode/commands/` — Comandos slash.
- `docs/workflow.md` — Pipeline de roles y handoffs.
