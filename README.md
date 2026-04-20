# WP Agent Memory

WordPress plugin that stores structured memory entries for AI agents and exposes them via MCP abilities and a REST API.

## External Source Search

The plugin includes two abilities that let agents query live external sources rather than only stored memory entries.

### WordPress Developer Docs (`agent-memory/search-wp-docs`)

Searches the [WordPress Code Reference](https://developer.wordpress.org/reference/) — functions, hooks, classes, and methods. No configuration required.

### GitHub Issues (`agent-memory/search-github-issues`)

Searches issues and pull requests on `WordPress/gutenberg` and `WordPress/wordpress-develop`.

**Without a token** the GitHub API allows roughly 10 requests per minute per IP. For normal agent usage this is usually fine, but you may hit limits during heavy sessions.

**With a token** the limit rises to 5,000 requests per hour. To set one:

1. Create a GitHub personal access token at [github.com/settings/tokens](https://github.com/settings/tokens). The token only needs **public repository read** access — no additional scopes required.

2. Add it to your `.env` file in the project root:

   ```
   GITHUB_TOKEN=ghp_your_token_here
   ```

3. Restart the `wordpress` container:

   ```bash
   docker compose restart wordpress
   ```

When the token is absent or the rate limit is hit, the ability returns an `error` field with a message explaining what happened.

## MCP Abilities

See `SKILL.md` for the full ability reference and parameter schemas.

The abilities are only exposed to the MCP adapter when `MCP_ADAPTER_ENABLED=1` is set in `.env`. See `docs/environment.md` in the project root for all environment variables.
