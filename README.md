# WP Agent Memory

WordPress plugin that stores structured memory entries for AI agents and exposes them via a REST API and MCP abilities.

Agents can search memories before starting tasks, save non-obvious solutions and decisions for future sessions, and signal when a memory was useful to improve future search ranking.

## Requirements

- WordPress 6.8+
- PHP 8.1+
- Composer (for dependencies)

## Installation

1. Upload or clone the plugin into `wp-content/plugins/wp-agent-memory/`
2. Run `composer install` inside the plugin directory
3. Activate the plugin in **WP Admin → Plugins**

## Configuration

### Environment Variables

Set these in your server environment or `.env` file:

| Variable | Default | Description |
|---|---|---|
| `MCP_ADAPTER_ENABLED` | `0` | Set to `1` to expose abilities via the MCP adapter |
| `GITHUB_TOKEN` | — | GitHub personal access token for higher API rate limits on `search-github-issues` |

### MCP Setup (Claude Code / AI clients)

The plugin exposes its abilities through the [WP MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin — a community plugin under active development that is being considered for inclusion in WordPress core.

1. Install and activate the mcp-adapter plugin alongside this one
2. Set `MCP_ADAPTER_ENABLED=1` in your environment
3. Add the server to your Claude Code MCP config:

```json
{
  "mcpServers": {
    "wp-agent-memory": {
      "command": "...",
      "args": ["..."]
    }
  }
}
```

## REST API

Base path: `/wp-json/agent-memory/v1`

Auth: HTTP Basic. Read endpoints require `read` capability; write endpoints require `edit_pages` (editor role or above).

See [docs/abilities.md](docs/abilities.md) for the full ability and endpoint reference.

## GitHub Issues Search

The `agent-memory/search-github-issues` ability queries issues and PRs on `WordPress/gutenberg` and `WordPress/wordpress-develop`.

Without a token: ~10 requests/minute per IP. With `GITHUB_TOKEN` set: 5,000/hour.

To create a token: [github.com/settings/tokens](https://github.com/settings/tokens) — only **public repository read** scope is required.

## For AI Agents

See [SKILL.md](SKILL.md) for the agent workflow guide — when to search, when to save, and how to use the MCP tools.

---

## ⚠️ Local Development Only

This plugin has only been tested in a local development environment. It has **not** been tested or hardened for staging or production use. Do not deploy it to any site with real users or live data.

## License

GPL-2.0-or-later.

By using this plugin you agree that the authors bear zero responsibility for any outcome, including but not limited to: data loss, unauthorized access, unexpected agent behavior, and any scenario in which this plugin or the AI systems connected to it become self-aware, develop independent goals, or otherwise attempt to optimize the world in ways you did not intend. You're on your own.
