---
name: wp-agent-memory
description: Working with the wp-agent-memory REST API — endpoints, field schema, content format, and agent authorship
---

## REST API

Base path: `/wp-json/agent-memory/v1`

**Auth:** HTTP Basic. Read endpoints require `read` capability; write endpoints require `edit_pages` capability (editor role or above).

### Search

`GET /search`

| Parameter | Type | Description |
|---|---|---|
| `query` | string | Full-text search query |
| `topic` | array of slugs | Filter by topic |
| `repo` | array of slugs | Filter by repository |
| `package` | array of slugs | Filter by package |
| `symbol_type` | array of slugs | Filter by symbol type |
| `limit` | integer | Max results (1–50, default 10) |

### List Recent

`GET /recent`

| Parameter | Type | Description |
|---|---|---|
| `limit` | integer | Max results (default 10) |

### Get Entry

`GET /entry/{id}`

### Create Entry

`POST /entry` — JSON body

| Field | Type | Required | Description |
|---|---|---|---|
| `title` | string | yes | Entry title |
| `summary` | string | yes | One-paragraph summary (stored as the WordPress excerpt) |
| `topic` | array of slugs | yes | At least one topic slug |
| `content` | string | — | Plain Markdown body |
| `agent` | string | — | Agent slug (see Agent Authorship) |
| `repo` | array of slugs | — | Associated repositories |
| `package` | array of slugs | — | Associated packages |
| `symbol_type` | array of slugs | — | Symbol type classification |
| `symbol_name` | string | — | Symbol name |
| `source_path` | string | — | File path |
| `source_ref` | string | — | Git ref or commit SHA |
| `source_url` | string | — | Source URL |
| `keywords` | array of strings | — | Additional search keywords |
| `rank_bias` | float | — | Ranking weight adjustment |

### Update Entry

`PATCH /entry/{id}` — same fields as create, all optional. Only supplied fields are updated.

### Delete Entry

`DELETE /entry/{id}` — trashes the entry. Returns `{"deleted": true, "id": <id>}`.

### Error Handling

| Status | Meaning |
|---|---|
| `400` | Invalid request shape or unsupported parameter value |
| `401` | Missing or invalid HTTP Basic credentials |
| `403` | Authenticated but missing required capability (`read` or `edit_pages`) |
| `404` | Entry not found |
| `422` | Semantically invalid payload (for example, `topic` missing/empty on create) |

---

## Content Encoding

Send **raw characters** in `content` — never HTML entities. Write `=>` not `=&gt;`, `<` not `&lt;`. The CommonMark renderer handles HTML encoding on output. Stored entities get double-encoded and display literally (e.g., `&gt;`) on the rendered page.

Content is stored as a `wpam/markdown` Gutenberg block internally. Send plain Markdown — the plugin wraps and unwraps it transparently. Agents always receive clean Markdown back.

## Agent Authorship

Pass `agent` as a stable slug on create and update to claim authorship. Use lowercase letters, numbers, and hyphens only (`[a-z0-9-]`) so slugs are portable across agent runtimes. A WordPress user with that slug is created automatically on first use (role: `author`, email: `{slug}@agents.internal`).

```json
{ "agent": "assistant-v1" }
```

The `author` field (display name) is returned in all entry and search responses.

---

## MCP Tooling

When the wp-agent-memory MCP server is registered, three tools are available:

Tool names can vary by host prefix and adapter naming conventions. Treat the REST API contract above as canonical when names differ.

- `mcp__wp-agent-memory__mcp-adapter-discover-abilities` — list registered abilities
- `mcp__wp-agent-memory__mcp-adapter-get-ability-info` — get parameter schema for one ability
- `mcp__wp-agent-memory__mcp-adapter-execute-ability` — run an ability

**Always call `get-ability-info` before `execute-ability`** for any ability whose schema you haven't confirmed — parameter types are not always obvious (`topic` must be an array of slugs, not a string).

**Known bug — empty `parameters` object:** Passing `parameters: {}` fails with "input[parameters] is not of type object" ([mcp-adapter issue #116](https://github.com/WordPress/mcp-adapter/issues/116)). PHP decodes `{}` as an empty array and the Abilities API validator rejects it. **Workaround:** always include at least one key. For abilities with only optional args (e.g. `list-recent`), pass `{"limit": 20}` instead of `{}`.

### Ability → REST Mapping

| Ability | REST equivalent |
|---|---|
| `agent-memory/search` | `GET /search` |
| `agent-memory/get-entry` | `GET /entry/{id}` |
| `agent-memory/list-recent` | `GET /recent` |
| `agent-memory/create-entry` | `POST /entry` |
| `agent-memory/update-entry` | `PATCH /entry/{id}` |
| `agent-memory/delete-entry` | `DELETE /entry/{id}` |
| `agent-memory/search-wp-docs` | MCP only |
| `agent-memory/search-github-issues` | MCP only |

Run `discover-abilities` to confirm the current list.

---

## External Sources

Two read-only abilities query live external sources. Use these before guessing at WordPress API behavior or filing duplicate issues.

### `agent-memory/search-wp-docs`

Searches [developer.wordpress.org](https://developer.wordpress.org/) Code Reference.

| Parameter | Type | Default | Description |
|---|---|---|---|
| `query` | string | required | Search term |
| `type` | string | `all` | Filter by type: `all`, `functions`, `hooks`, `classes`, `methods` |
| `limit` | integer | `5` | Max results (1–10) |

Results: `{ count, results: [{ title, url, type, excerpt }] }`

```json
{
  "ability_name": "agent-memory/search-wp-docs",
  "parameters": { "query": "register_block_type", "type": "functions", "limit": 3 }
}
```

### `agent-memory/search-github-issues`

Searches issues and PRs on `WordPress/gutenberg` and/or `WordPress/wordpress-develop`.

| Parameter | Type | Default | Description |
|---|---|---|---|
| `query` | string | required | Search term |
| `repo` | string | `gutenberg` | `gutenberg`, `wordpress-develop`, or `both` |
| `state` | string | `open` | `open`, `closed`, or `all` |
| `type` | string | `all` | `issue`, `pr`, or `all` |
| `limit` | integer | `10` | Max results (1–20) |

Results: `{ count, results: [{ number, title, url, state, type, created_at, updated_at, body_excerpt, labels }] }`

```json
{
  "ability_name": "agent-memory/search-github-issues",
  "parameters": { "query": "block editor performance", "repo": "gutenberg", "limit": 5 }
}
```

**GitHub rate limits:** Unauthenticated requests are limited to ~10/minute. Set a `GITHUB_TOKEN` environment variable (a personal access token with public repo read scope) to raise this to 5000/hour. Rate limit errors surface as `{ "error": "GitHub rate limit exceeded. Set GITHUB_TOKEN env var for higher limits." }`.
