---
name: wp-agent-memory
description: Working with the wp-agent-memory REST API — endpoints, field schema, content format, and agent authorship
---

## Agent Workflow

### At the start of every task

**When working in the `wp-agent-memory` repository:** Fetch entry #264 (`agent-memory/get-entry`) first for plugin orientation — content model, file map, architectural decisions, and available APIs. Then search.

Search before responding. Identify 1–3 topic keywords from the user's request and call `agent-memory/search` with `params.query`.

**When debugging:** run a second search after you identify the root cause, using symptom or error terms (e.g. "binding value empty frontend", "markdown not rendering"). The first search catches patterns; the second catches specific gotchas.

### At the end of a task
If a retrieved memory genuinely shaped your approach or provided the correct solution, call `agent-memory/mark-useful` with `params.id` (from the search result), `params.agent` (your model slug), and an optional `params.context` note.

**Do not mark useful if:** the memory was retrieved but ignored, was stale/incorrect, or didn't influence the response.

**Why it matters:** The `useful_count` field on each search result reflects how often agents have marked that entry as genuinely useful. Entries with higher useful counts surface higher in future searches (log-scaled, time-decayed). Calling `mark-useful` is how the system learns which memories are actually reliable.

### What to save

Save memories for:
- Non-obvious solutions, workarounds, or constraints that aren't visible in the code
- Decisions and the reasoning behind them (architecture, naming, approach)
- Patterns that recur across tasks — things you had to figure out more than once

Do not save:
- Code patterns already visible in the codebase (read the file instead)
- Things already in git history or commit messages
- Transient task state or work-in-progress notes

---

## REST API

Base path: `/wp-json/agent-memory/v1`

**Auth:** HTTP Basic (WordPress Application Password). See [Authentication](#authentication) below.

**Relationship model (v1):** relation metadata is cluster-based taxonomy data (`relation_role` + `relation_group`), not explicit per-edge links. A one-time migration backfills `Status: Companion to [#<id> ...]` prose into these taxonomies.

### Search

`GET /search`

| Parameter | Type | Description |
|---|---|---|
| `query` | string | Full-text search query |
| `topic` | array of slugs | Filter by topic |
| `repo` | array of slugs | Filter by repository |
| `package` | array of slugs | Filter by package |
| `symbol_type` | array of slugs | Filter by symbol type |
| `relation_role` | array of slugs | Filter by relation role taxonomy |
| `relation_group` | array of slugs | Filter by relation group taxonomy |
| `limit` | integer | Max results (1–50, default 10) |

**Search result shape:**

| Field | Type | Description |
|---|---|---|
| `id` | integer | Entry ID — pass to `mark-useful`, `get-entry`, `update-entry` |
| `title` | string | Entry title |
| `summary` | string | One-paragraph summary |
| `snippet` | string | Query-centered excerpt from content |
| `score` | float | Relevance score (higher = better match) |
| `useful_count` | integer | Times agents marked this entry as useful |
| `repo` | array | Associated repository slugs |
| `topic` | array | Topic taxonomy slugs |
| `symbol_name` | string | Symbol identifier if set |
| `author` | string | Agent or user display name |
| `permalink` | string | WordPress admin URL |

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
| `relation_role` | array of slugs | — | Relation role taxonomy slugs (single value enforced) |
| `relation_group` | array of slugs | — | Relation group taxonomy slugs (single value enforced) |
| `symbol_type` | array of slugs | — | Symbol type classification |
| `symbol_name` | string | — | Symbol name |
| `source_path` | string | — | File path |
| `source_ref` | string | — | Git ref or commit SHA |
| `source_url` | string | — | Source URL |
| `rank_bias` | float | — | Ranking weight adjustment |

**Relation model (v1):** relationships are cluster-based taxonomy metadata (`relation_role` + `relation_group`), not explicit edge records.

### Update Entry

`PATCH /entry/{id}` — same fields as create, all optional. Only supplied fields are updated.

### Delete Entry

`DELETE /entry/{id}` — trashes the entry. Returns `{"deleted": true, "id": <id>}`.

### Mark Entry as Useful

`POST /entry/{id}/useful` — signal that a memory was genuinely useful after a task. Increments `useful_count` to boost future search ranking.

| Field | Type | Required | Description |
|---|---|---|---|
| `agent` | string | — | Agent slug (e.g. `claude-sonnet-4-6`) |
| `context` | string | — | Short note on why it was useful |

Returns `{"marked": true, "id": <id>, "useful_count": <n>}`.

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

Send **raw characters** in `content` — never HTML entities. Write `=>` not `=&gt;`, `<` not `&lt;`. The plugin stores and retrieves content so agents always see the original characters back.

Send plain Markdown — the plugin stores and returns it as-is. Do not use JSON unicode escapes (e.g. `\u002d`); write the actual character (`-`).

## Agent Authorship

Pass `agent` as a stable slug on create and update to claim authorship. Use lowercase letters, numbers, and hyphens only (`[a-z0-9-]`). The slug must match the WordPress username created during agent setup — if the user doesn't exist, authorship falls back to the authenticated user.

```json
{ "agent": "claude-sonnet-4-6" }
```

The `author` field (display name) is returned in all entry and search responses.

## Authentication

All endpoints require HTTP Basic Auth using a WordPress Application Password. The credential is stored in your MCP config as an `Authorization` header — see the Agent Setup section in [README.md](README.md) for setup steps.

Read endpoints require the `read` capability (Author role or above). Write endpoints (`create-entry`, `update-entry`, `delete-entry`, `mark-useful`) require `edit_pages` (Editor role or above).

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
| `agent-memory/mark-useful` | `POST /entry/{id}/useful` |
| `agent-memory/search-wp-docs` | MCP only |
| `agent-memory/fetch-wp-doc` | MCP only |
| `agent-memory/search-github-issues` | MCP only |

Run `discover-abilities` to confirm the current list. For full parameter schemas and response shapes for all abilities, see [docs/abilities.md](docs/abilities.md).

**Search:**
```json
{ "ability_name": "agent-memory/search", "parameters": { "query": "hover states blocks", "limit": 5 } }
```

**Create:**
```json
{
  "ability_name": "agent-memory/create-entry",
  "parameters": {
    "title": "Hover Style System — CSS Custom Properties",
    "summary": "WordPress blocks don't support hover states natively. Store hover values as separate attributes, write as CSS custom properties at render time, map to :hover rules in CSS.",
    "topic": ["wordpress", "blocks"],
    "agent": "claude-sonnet-4-6"
  }
}
```

**Mark useful:**
```json
{
  "ability_name": "agent-memory/mark-useful",
  "parameters": { "id": 42, "agent": "claude-sonnet-4-6", "context": "Provided the exact pattern needed for hover states." }
}
```

---

## External Sources

Two read-only abilities query live external sources. Use these before guessing at WordPress API behavior or filing duplicate issues.

### `agent-memory/search-wp-docs`

Searches WordPress documentation. Use `source` to target different sites.

| Parameter | Type | Default | Description |
|---|---|---|---|
| `query` | string | required | Search term |
| `source` | string | `developer` | `developer` — developer.wordpress.org Code Reference; `news` — wordpress.org/news announcements; `user-docs` — wordpress.org/documentation end-user guides |
| `type` | string | `all` | Filter by type (developer only): `all`, `functions`, `hooks`, `classes`, `methods` |
| `limit` | integer | `5` | Max results (1–10) |

Results: `{ count, results: [{ title, url, type, excerpt }] }`

```json
{
  "ability_name": "agent-memory/search-wp-docs",
  "parameters": { "query": "register_block_type", "type": "functions", "limit": 3 }
}
```

```json
{
  "ability_name": "agent-memory/search-wp-docs",
  "parameters": { "query": "WordPress 7.0", "source": "news", "limit": 5 }
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

**GitHub rate limits:** Unauthenticated requests are limited to ~10/minute. Add a GitHub personal access token in **Settings → Agent Memory** (no scopes required for public repos) to raise this to 5,000/hour. Rate limit errors surface as `{ "error": "GitHub rate limit exceeded. Add a GitHub token in Settings > Agent Memory for higher limits." }`.
