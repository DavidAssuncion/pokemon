# opencode Skills Index

## Available Skills

| Skill | Path | Description |
|-------|------|-------------|
| **laravel** | `.opencode/skill/laravel/reference.md` | Laravel patterns, commands, architecture rules |
| **blade** | `.opencode/skill/blade/reference.md` | Blade components, Wireable DTOs, Alpine.js |
| **phpstan** | `.opencode/skill/phpstan/reference.md` | PHPStan levels, config, project rules, pipeline |
| **phpunit** | `.opencode/skill/phpunit/reference.md` | PHPUnit structure, TDD flow, assertions, factories |
| **dbeaver** | `.opencode/skill/dbeaver/reference.md` | DBeaver MCP server for database queries |
| **mcp** | `.opencode/skill/mcp/reference.md` | MCP servers config and usage |

## Skill Loading

Skills are auto-discovered from `.opencode/skill/*/reference.md`.

Reference in agent prompts:
```markdown
# In agent prompt
See `.opencode/skill/laravel/reference.md` for Laravel patterns.
See `.opencode/skill/phpstan/reference.md` for PHPStan levels.
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