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

### Agent User Setup

Every agent needs a dedicated WordPress user and an Application Password. The role determines what the agent can do:

| Role | Abilities |
|---|---|
| **Author** | Read-only — `search`, `get-entry`, `list-recent`, `search-wp-docs`, `fetch-wp-doc`, `search-github-issues` |
| **Editor** | Read + write — all of the above plus `create-entry`, `update-entry`, `delete-entry`, `mark-useful` |

**Per agent:**

1. In WP Admin go to **Users → Add New**. Set the role to **Author** for read-only or **Editor** for read+write.
2. For the username, use whatever identifies the agent — any value works for authentication. Every entry is always attributed to an author: if the agent passes a matching `agent` slug in write calls, that user is recorded; otherwise the authenticated user making the request is used as the fallback. For the cleanest attribution, use the same username the agent will pass as `agent` in write calls. For Claude Code, that is the model slug shown in the interface (e.g. `claude-sonnet-4-6`, `claude-opus-4-7`).
3. Open that user's profile and scroll to **Application Passwords**
4. Enter a name identifying where this credential will be used (e.g. `local-macbook`) then click **Add New Application Password**
5. Copy the generated password (shown once)
6. Base64-encode `username:password` — e.g. `echo -n "claude-sonnet-4-6:XXXX XXXX XXXX XXXX XXXX XXXX" | base64`
7. Use the encoded string as the `Authorization` header value in your MCP config (see below)

The Application Password name is just a label. Use it to identify the machine or project so you can revoke one credential without affecting others — a single user can have multiple Application Passwords.

To revoke access: open the user's profile in WP Admin and delete the specific Application Password.

**Read-only agents**

Assigning the Author role is the recommended setup for agents that should only *consume* memory — for example, a coding assistant that searches prior solutions at the start of each task but delegates all memory writes to a separate agent or human curator. A read-only agent can call `search`, `get-entry`, `list-recent`, `search-wp-docs`, `fetch-wp-doc`, and `search-github-issues`, but any attempt to create, update, delete, or mark-useful will be rejected with a `403`. This makes it safe to share a read-only credential across multiple projects or machines without worrying about one context corrupting the memory store.

### MCP Setup (Claude Code / AI clients)

The plugin exposes its abilities through the [WP MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin — a community plugin under active development that is being considered for inclusion in WordPress core.

1. Install and activate the mcp-adapter plugin alongside this one
2. Verify the MCP endpoint is live — open a browser or run:
   ```
   curl https://yoursite.com/wp-json/mcp/mcp-adapter-default-server
   ```
   You should get a JSON response with `protocolVersion` and `serverInfo`. A 404 means the mcp-adapter plugin is not active.
3. Add the server to your `.mcp.json` (project-level) or `~/.claude.json` (user-level):

```json
{
  "mcpServers": {
    "wp-agent-memory": {
      "type": "http",
      "url": "https://yoursite.com/wp-json/mcp/mcp-adapter-default-server",
      "headers": {
        "Authorization": "Basic <base64(username:application-password)>"
      }
    }
  }
}
```

Replace `yoursite.com` with your WordPress site URL and the `Authorization` value with the base64-encoded credential from the Agent User Setup steps above.

No environment variables are required. Abilities are automatically discoverable by the MCP adapter once both plugins are active.

### GitHub Token (optional)

To increase the GitHub API rate limit for `search-github-issues` from ~10 requests/minute to 5,000/hour:

1. In WP Admin go to **Settings → Agent Memory**
2. Paste a GitHub personal access token into the **GitHub Token** field
3. Save — no scopes are required for public repository access

Generate a token at [github.com/settings/tokens](https://github.com/settings/tokens). If you prefer to set it as a server environment variable (`GITHUB_TOKEN`), that will take precedence over the settings page value.

## Reference

- [docs/abilities.md](docs/abilities.md) — full ability and REST endpoint reference, including `search-wp-docs`, `fetch-wp-doc`, and `search-github-issues`
- [SKILL.md](SKILL.md) — agent workflow guide: when to search, when to save, how to use the MCP tools
- [CONTRIBUTING.md](CONTRIBUTING.md) — development setup and contribution guidelines

## Contributors

- [H. Adam Lenz](https://github.com/adamlenz)

---

## ⚠️ Local Development Only

This plugin has only been tested in a local development environment. It has **not** been tested or hardened for staging or production use. Do not deploy it to any site with real users or live data.

## License

GPL-2.0-or-later.

By using this plugin you agree that the authors bear zero responsibility for any outcome, including but not limited to: data loss, unauthorized access, unexpected agent behavior, and any scenario in which this plugin or the AI systems connected to it become self-aware, develop independent goals, or otherwise attempt to optimize the world in ways you did not intend. You're on your own.
