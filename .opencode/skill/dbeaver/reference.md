# DBeaver MCP Skill Reference

## Overview

MCP (Model Context Protocol) server for DBeaver to query databases directly from agents.

## Setup

```bash
# Install MCP server globally (one-time)
npm install -g @dbeaver/mcp-server

# Or use npx
npx @dbeaver/mcp-server
```

## Configuration

Add to `~/.config/opencode/mcp.json` or project `.mcp.json`:

```json
{
  "mcpServers": {
    "dbeaver": {
      "command": "npx",
      "args": ["@dbeaver/mcp-server"],
      "env": {
        "DBEAVER_CONNECTION": "pokemon_local",
        "DBEAVER_WORKSPACE": "/home/david/pokemon/.dbeaver"
      }
    }
  }
}
```

## Available Tools

| Tool | Description |
|------|-------------|
| `dbeaver_query` | Execute SELECT query |
| `dbeaver_tables` | List tables in schema |
| `dbeaver_describe` | Describe table structure |
| `dbeaver_execute` | Execute INSERT/UPDATE/DELETE |
| `dbeaver_transaction` | Run multiple queries in transaction |

## Usage Examples

```javascript
// Query
await mcp.dbeaver_query({
  connection: "pokemon_local",
  sql: "SELECT * FROM pokemon WHERE tipo = 'fire' LIMIT 10"
});

// Describe table
await mcp.dbeaver_describe({
  connection: "pokemon_local",
  table: "pokemon"
});

// List tables
await mcp.dbeaver_tables({
  connection: "pokemon_local",
  schema: "public"
});
```

## Project-Specific

### Connections

Configure in DBeaver first, then reference by name:
- `pokemon_local` — Local SQLite/MySQL
- `pokemon_test` — Testing database

### Common Queries

```sql
-- Pokemon con stats
SELECT p.nombre, p.tipo_1, p.tipo_2, e.hp, e.attack, e.defense
FROM pokemon p
JOIN pokemon_stats e ON p.id = e.pokemon_id
WHERE p.generacion = 1;

-- Movimientos por categoría
SELECT m.nombre, m.categoria, m.potencia, m.precision, t.nombre as tipo
FROM movimientos m
JOIN tipos t ON m.tipo_id = t.id
WHERE m.categoria = 'fisico';

-- Equipos de usuario
SELECT e.nombre, COUNT(p.id) as pokemon_count
FROM equipos e
LEFT JOIN equipo_pokemon ep ON e.id = ep.equipo_id
LEFT JOIN pokemon p ON ep.pokemon_id = p.id
WHERE e.usuario_id = 1
GROUP BY e.id;
```

## Security

- Read-only by default for agents
- Write operations require explicit approval
- Connection strings in DBeaver, not in config