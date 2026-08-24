# MCP Skill Reference

## opencode MCP Configuration

### Project-level: `.mcp.json` (in project root)

```json
{
  "mcpServers": {
    "dbeaver": {
      "command": "npx",
      "args": ["@dbeaver/mcp-server"],
      "env": {
        "DBEAVER_CONNECTION": "pokemon_local"
      }
    },
    "filesystem": {
      "command": "npx",
      "args": ["@modelcontextprotocol/server-filesystem", "/home/david/pokemon"]
    },
    "github": {
      "command": "npx",
      "args": ["@modelcontextprotocol/server-github"],
      "env": {
        "GITHUB_PERSONAL_ACCESS_TOKEN": "${GITHUB_TOKEN}"
      }
    }
  }
}
```

### Global: `~/.config/opencode/mcp.json`

```json
{
  "mcpServers": {
    "dbeaver": {
      "command": "npx",
      "args": ["@dbeaver/mcp-server"]
    }
  }
}
```

## Available MCP Servers

| Server | Package | Purpose |
|--------|---------|---------|
| Filesystem | `@modelcontextprotocol/server-filesystem` | Read/write files |
| GitHub | `@modelcontextprotocol/server-github` | GitHub API |
| SQLite | `@modelcontextprotocol/server-sqlite` | Local SQLite |
| PostgreSQL | `@modelcontextprotocol/server-postgres` | PostgreSQL |
| MySQL | `@modelcontextprotocol/server-mysql` | MySQL |
| DBeaver | `@dbeaver/mcp-server` | DBeaver connections |
| Puppeteer | `@modelcontextprotocol/server-puppeteer` | Browser automation |

## Usage in Agents

```python
# In agent code (via tool calls)
result = await mcp.filesystem.read_file("/home/david/pokemon/docs/architecture.md")

result = await mcp.dbeaver.query({
    "connection": "pokemon_local",
    "sql": "SELECT * FROM pokemon LIMIT 5"
})

result = await mcp.github.list_issues({
    "owner": "user",
    "repo": "pokemon",
    "state": "open"
})
```

## Security

- Tokens via env vars (`${VAR}` syntax)
- Read-only by default
- Write tools require explicit enable
- Per-server permissions in opencode config