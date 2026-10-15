# opencode Skills Index

## Available Skills

### Project skills (`.opencode/skills/`)

| Skill | Path | Description |
|-------|------|-------------|
| **laravel** | `.opencode/skills/laravel/SKILL.md` | Laravel patterns, commands, architecture rules |
| **blade** | `.opencode/skills/blade/SKILL.md` | Blade components, Wireable DTOs, Alpine.js |
| **phpstan** | `.opencode/skills/phpstan/SKILL.md` | PHPStan levels, config, project rules, pipeline |
| **phpunit** | `.opencode/skills/phpunit/SKILL.md` | PHPUnit structure, TDD flow, assertions, factories |
| **dbeaver** | `.opencode/skills/dbeaver/SKILL.md` | DBeaver MCP server for database queries |
| **mcp** | `.opencode/skills/mcp/SKILL.md` | MCP servers config and usage |

### Laravel Boost skills (`.agents/skills/`)

| Skill | Path | Description |
|-------|------|-------------|
| **laravel-best-practices** | `.agents/skills/laravel-best-practices/SKILL.md` | Laravel backend best practices (controllers, Eloquent, validation, queues, caching…) |
| **infer-conventions** | `.agents/skills/infer-conventions/SKILL.md` | Detect and record project conventions as `.ai/rules` |
| **tailwindcss-development** | `.agents/skills/tailwindcss-development/SKILL.md` | Tailwind v3/v4 utility classes for Blade/JSX/Vue templates |

## Skill Loading

OpenCode V2 auto-discovers project skills from `.opencode/skills/*/SKILL.md`
(directory form, `SKILL.md` at any depth). Each skill needs YAML frontmatter
with `name` and `description`; the `description` is what triggers the skill.

The skill ID comes from the directory name (case-sensitive, kebab-case), e.g.
`.opencode/skills/laravel/SKILL.md` → ID `laravel`. The model loads it with the
`skill` tool (`{ "id": "laravel" }`) or when mentioned as `@laravel`.

List discovered skills with:

```bash
opencode api GET /api/skill -H "x-opencode-directory: /home/david/pokemon"
```

## Project-Specific Configs

| Config | Purpose |
|--------|---------|
| `phpstan.neon` | Level 6 (Coder/Cleaner) |
| `phpstan-hardener.neon` | Level 8 (Hardener) |
| `infection.json5` | Mutation testing (MSI 80%→100%) |
| `deptrac.yaml` | Dependency analysis (src/ boundaries) |
| `pint.json` | Code style (PSR-12 + strict) |
| `phpunit.xml` | Unit + Feature test suites |

## Pipeline Integration

```
Coder:     phpstan.neon (L6) + infection (MSI≥80%) + phpunit
Cleaner:   phpstan.neon (L6) + infection (MSI≥80%) + deptrac
Hardener:  phpstan-hardener.neon (L8) + infection (MSI=100%) + deptrac
Arquitecto: deptrac (boundaries) + phpstan (L6)
```