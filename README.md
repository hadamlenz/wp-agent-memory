# WP Agent Memory

WordPress plugin that stores structured memory entries for AI agents and exposes them via a REST API and MCP abilities.

Agents can search memories before starting tasks, save non-obvious solutions and decisions for future sessions, and signal when a memory was useful to improve future search ranking.

## Requirements

- WordPress 6.8+
- PHP 8.1+

## Installation

### From a release zip

1. Download the latest `wp-agent-memory.x.x.x.zip` from the [Releases](../../releases) page
2. In WP Admin go to **Plugins → Add New → Upload Plugin** and upload the zip
3. Activate the plugin

The release zip includes compiled JS and Composer dependencies — no build step needed.

### From source

1. Clone the repository into `wp-content/plugins/wp-agent-memory/`
2. Install PHP dependencies: `composer install`
3. Install JS dependencies and build the block: `npm install && npm run build`
4. Activate the plugin in **WP Admin → Plugins**

## Configuration

### Environment Variables

Set these in your server environment or `.env` file:

| Variable | Default | Description |
|---|---|---|
| `MCP_ADAPTER_ENABLED` | `0` | Set to `1` to expose abilities via the MCP adapter |
| `GITHUB_TOKEN` | — | GitHub personal access token for higher API rate limits on `search-github-issues` |

### Agent Setup

Each agent that needs write access requires a dedicated WordPress user and an Application Password. Read-only agents also need credentials — the plugin requires authentication on all endpoints.

**Per agent:**

1. In WP Admin go to **Users → Add New** and create a user with the agent's slug as the username (e.g. `claude-sonnet-4-6`, role: Author)
2. Open that user's profile and scroll to **Application Passwords**
3. Enter a name that identifies where this credential will be used — e.g. `local-macbook` or `project-agent-memory` — then click **Add New Application Password**
4. Copy the generated password (shown once)
5. Base64-encode `username:password` — e.g. `claude-sonnet-4-6:XXXX XXXX XXXX XXXX XXXX XXXX`
6. Add the credential to your MCP config (see below)

The Application Password name is only a label for your reference. Use it to identify the machine or project so you can revoke one context without affecting others — a single agent user can have multiple Application Passwords.

To revoke access: go to the user's profile in WP Admin and delete the specific Application Password.

### MCP Setup (Claude Code / AI clients)

The plugin exposes its abilities through the [WP MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin — a community plugin under active development that is being considered for inclusion in WordPress core.

1. Install and activate the mcp-adapter plugin alongside this one
2. Set `MCP_ADAPTER_ENABLED=1` in your environment
3. Add the server to your `.mcp.json` (project-level) or `~/.claude.json` (user-level):

```json
{
  "mcpServers": {
    "wp-agent-memory": {
      "type": "http",
      "url": "https://yoursite.com/wp-json/mcp/v1",
      "headers": {
        "Authorization": "Basic <base64(username:application-password)>"
      }
    }
  }
}
```

Replace `yoursite.com` with your WordPress site URL and the `Authorization` value with the base64-encoded credential from the Agent Setup steps above.

## Development

The plugin includes a Gutenberg block for storing Markdown content. Build it with `@wordpress/scripts`:

```bash
npm install
npm run build   # production build → blocks/markdown/build/
npm run start   # watch mode for development
```

| Script | Description |
|---|---|
| `npm run build` | Compile the Markdown block for production |
| `npm run start` | Watch and rebuild on changes (development) |

## REST API

Base path: `/wp-json/agent-memory/v1`

Auth: HTTP Basic. Read endpoints require `read` capability; write endpoints require `edit_pages` (editor role or above).

See [docs/abilities.md](docs/abilities.md) for the full ability and endpoint reference.

## WordPress Docs Lookup

The `agent-memory/search-wp-docs` ability searches WordPress developer documentation and returns matching page URLs. Use `agent-memory/fetch-wp-doc` to retrieve the full content of any returned URL via the WordPress.org REST API.

Supported hosts: `developer.wordpress.org` (plugin/theme handbooks, Code Reference), `wordpress.org/documentation`, `wordpress.org/news`.

## GitHub Issues Search

The `agent-memory/search-github-issues` ability queries issues and PRs on `WordPress/gutenberg` and `WordPress/wordpress-develop`.

Without a token: ~10 requests/minute per IP. With `GITHUB_TOKEN` set: 5,000/hour.

To create a token: [github.com/settings/tokens](https://github.com/settings/tokens) — only **public repository read** scope is required.

## For AI Agents

See [SKILL.md](SKILL.md) for the agent workflow guide — when to search, when to save, and how to use the MCP tools.

## Contributing

All contributions require a pull request and review before merging.

- PHP code follows WordPress coding standards
- Run `composer install` to get PHPUnit, then `vendor/bin/phpunit` to run the test suite
- JS changes require `npm run build` before committing (or use `npm run start` during development)
- The release zip is built automatically by GitHub Actions on a version tag push — do not commit `vendor/` or `blocks/markdown/build/`

## Contributors

- [H. Adam Lenz](https://github.com/adamlenz)

---

## ⚠️ Local Development Only

This plugin has only been tested in a local development environment. It has **not** been tested or hardened for staging or production use. Do not deploy it to any site with real users or live data.

## License

GPL-2.0-or-later.

By using this plugin you agree that the authors bear zero responsibility for any outcome, including but not limited to: data loss, unauthorized access, unexpected agent behavior, and any scenario in which this plugin or the AI systems connected to it become self-aware, develop independent goals, or otherwise attempt to optimize the world in ways you did not intend. You're on your own.
