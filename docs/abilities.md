# Abilities Reference

All abilities are exposed via the MCP adapter (`mcp__wp-agent-memory__mcp-adapter-execute-ability`) and have equivalent REST endpoints under `/wp-json/agent-memory/v1`.

**Auth:** HTTP Basic. Read abilities require `read` capability (subscriber+); write abilities require `edit_pages` (editor+).

**Relationship model (v1):** relation metadata is cluster-based taxonomy data (`relation_role` + `relation_group`), not explicit per-edge links. A one-time migration backfills `Status: Companion to [#<id> ...]` prose into these taxonomies.

---

## agent-memory/search

Search memory entries using relevance + usage-based ranking.

**Say things like:**
> "Search memory for how we handle hover states in blocks."
> "Before you start, check if we've solved this authentication problem before."
> "Do we have any memories about the checkout flow?"

**REST:** `GET /search`

### Parameters

| Name | Type | Required | Default | Description |
|---|---|---|---|---|
| `query` | string | — | — | Full-text search query |
| `topic` | array of slugs | — | — | Filter by topic taxonomy |
| `repo` | array of slugs | — | — | Filter by repository |
| `package` | array of slugs | — | — | Filter by package |
| `symbol_type` | array of slugs | — | — | Filter by symbol type |
| `relation_role` | array of slugs | — | — | Filter by relation role taxonomy (`canonical`, `companion`, `supporting`, `superseded`, `historical`, `duplicate`, `alternative`) |
| `relation_group` | array of slugs | — | — | Filter by relation group taxonomy (single group slug such as `g-80`) |
| `limit` | integer | — | `10` | Max results (1–50) |

### Response

```json
{
  "count": 2,
  "results": [
    {
      "id": 42,
      "title": "Hover Style System",
      "symbol_name": "",
      "symbol_type": [],
      "repo": ["unc-wilson"],
      "package": [],
      "summary": "WordPress blocks don't support hover states natively...",
      "snippet": "…store hover values as separate block attributes…",
      "source_url": "",
      "source_path": "",
      "author": "claude-sonnet-4-6",
      "score": 215.0,
      "useful_count": 3,
      "permalink": "https://example.com/memory-entry/hover-style-system/"
    }
  ]
}
```

### Example

```json
{
  "ability_name": "agent-memory/search",
  "parameters": { "query": "hover states blocks", "limit": 5 }
}
```

---

## agent-memory/get-entry

Retrieve a single memory entry by ID with full content.

**Say things like:**
> "Pull up memory entry 42."
> "Show me the full content of that memory."
> "Get me everything on entry 87."

**REST:** `GET /entry/{id}`

### Parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `id` | integer | yes | Post ID of the memory entry |

### Response

```json
{
  "id": 42,
  "title": "Hover Style System",
  "symbol_name": "",
  "symbol_type": [],
  "repo": ["unc-wilson"],
  "package": [],
  "topic": ["wordpress", "blocks"],
  "relation_role": ["canonical"],
  "relation_group": ["g-80"],
  "summary": "WordPress blocks don't support hover states natively...",
  "source_url": "",
  "source_path": "",
  "source_ref": "",
  "rank_bias": 0,
  "useful_count": 3,
  "content": "Plain Markdown content here...",
  "author": "claude-sonnet-4-6",
  "permalink": "https://example.com/memory-entry/hover-style-system/",
  "modified_gmt": "2026-04-20 14:30:00"
}
```

### Example

```json
{
  "ability_name": "agent-memory/get-entry",
  "parameters": { "id": 42 }
}
```

---

## agent-memory/list-recent

List the most recently created or updated entries, date-ordered (no relevance scoring).

**Say things like:**
> "What memories were saved recently?"
> "Show me the last 5 things we remembered."
> "What's been added to memory this week?"

**REST:** `GET /recent`

### Parameters

| Name | Type | Required | Default | Description |
|---|---|---|---|---|
| `limit` | integer | — | `10` | Max results (1–50) |

### Response

Same compact shape as search results but without a `score` field, ordered by post date descending.

### Example

```json
{
  "ability_name": "agent-memory/list-recent",
  "parameters": { "limit": 10 }
}
```

> **Note:** Due to a known PHP/WP bug ([mcp-adapter #116](https://github.com/WordPress/mcp-adapter/issues/116)), passing `{}` as parameters fails. Always pass at least one key, e.g. `{ "limit": 10 }`.

---

## agent-memory/create-entry

Save a new memory entry.

**Say things like:**
> "Remember that we use CSS custom properties for block hover states — store the approach."
> "Save this solution so future agents don't have to re-solve it."
> "Add a memory: the checkout redirect bug was caused by a missing nonce on the AJAX handler."

**REST:** `POST /entry`

### Parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `title` | string | yes | Entry title |
| `summary` | string | yes | One-paragraph summary (stored as the WordPress excerpt) |
| `topic` | array of slugs | yes | At least one topic slug. Unknown slugs are created automatically. |
| `content` | string | — | Plain Markdown body |
| `agent` | string | — | Agent slug (see Agent Authorship below) |
| `repo` | array of slugs | — | Associated repositories |
| `package` | array of slugs | — | Associated packages |
| `relation_role` | array of slugs | — | Relation role taxonomy slugs. Single value enforced. Allowed: `canonical`, `companion`, `supporting`, `superseded`, `historical`, `duplicate`, `alternative`. |
| `relation_group` | array of slugs | — | Relation group taxonomy slugs. Single value enforced. Group terms are auto-created. |
| `symbol_type` | array of slugs | — | Symbol type classification |
| `symbol_name` | string | — | Symbol name (function, class, hook, etc.) |
| `source_path` | string | — | File path |
| `source_ref` | string | — | Git ref or commit SHA |
| `source_url` | string | — | Source URL |
| `rank_bias` | float | — | Manual ranking weight adjustment |

### Content encoding

Send **raw characters** in `content` — never HTML entities (`=>` not `=&gt;`). Do not use JSON unicode escapes — write the actual character. The plugin stores and returns plain Markdown transparently.

### Response

Returns the created entry in full `get-entry` shape with HTTP 201.

### Example

```json
{
  "ability_name": "agent-memory/create-entry",
  "parameters": {
    "title": "Hover Style System — CSS Custom Properties for Block Hover States",
    "summary": "WordPress blocks don't support hover states natively. Store hover values as separate block attributes, write them as CSS custom properties at render time, and map those vars to :hover rules in compiled CSS.",
    "topic": ["wordpress", "blocks", "hover", "css", "custom-properties"],
    "repo": ["unc-wilson"],
    "relation_role": ["canonical"],
    "relation_group": ["g-80"],
    "agent": "claude-sonnet-4-6"
  }
}
```

---

## agent-memory/update-entry

Update fields on an existing memory entry. Only supplied fields are changed.

**Say things like:**
> "Update memory 42 — add 'render-callback' to the topics."
> "We found a better approach; update that memory with the new solution."
> "The source path changed — update entry 87 to reflect the new file location."

**REST:** `PATCH /entry/{id}`

### Parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `id` | integer | yes | Post ID of the entry to update |
| *(all create fields)* | — | — | Same optional fields as create-entry |

### Response

Returns the updated entry in full `get-entry` shape.

> **Relation model note:** relationship semantics are cluster-based in v1 (role + group taxonomies), not explicit per-edge graph links.

### Example

```json
{
  "ability_name": "agent-memory/update-entry",
  "parameters": {
    "id": 42,
    "topic": ["wordpress", "blocks", "hover", "css", "custom-properties", "render-callback"],
    "agent": "claude-sonnet-4-6"
  }
}
```

---

## agent-memory/delete-entry

Trash a memory entry.

**Say things like:**
> "Delete memory 42 — that approach is outdated."
> "Remove that entry, we no longer use that pattern."
> "Trash entry 87."

**REST:** `DELETE /entry/{id}`

### Parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `id` | integer | yes | Post ID of the entry to trash |

### Response

```json
{ "deleted": true, "id": 42 }
```

### Example

```json
{
  "ability_name": "agent-memory/delete-entry",
  "parameters": { "id": 42 }
}
```

---

## agent-memory/mark-useful

Signal that a memory entry was genuinely useful after completing a task.

**Say things like:**
> "That memory about hover states was exactly right — mark it useful."
> "Entry 42 solved the problem. Give it a thumbs up."
> "Mark memory 87 as useful, it had the answer." Increments `useful_count` to boost future search ranking for that entry.

**REST:** `POST /entry/{id}/useful`

Call this **after** a task is complete if the memory shaped your approach or provided a correct solution. Do not call it if the memory was retrieved but not used, was stale, or didn't influence the response.

### Parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `id` | integer | yes | Post ID of the memory entry |
| `agent` | string | — | Your model slug (e.g. `claude-sonnet-4-6`) |
| `context` | string | — | Short note on why it was useful |

### Response

```json
{ "marked": true, "id": 42, "useful_count": 4 }
```

### Example

```json
{
  "ability_name": "agent-memory/mark-useful",
  "parameters": {
    "id": 42,
    "agent": "claude-sonnet-4-6",
    "context": "Provided the exact CSS custom property pattern needed for block hover states."
  }
}
```

---

## agent-memory/search-wp-docs

Search WordPress documentation. Use this before guessing at API behavior or native functions.

**Say things like:**
> "Look up how `register_block_type` works in the WordPress docs."
> "Search the WordPress docs for the correct hook to filter post meta."
> "Before you guess, check the WordPress docs for how application passwords work."

**MCP only** (no REST equivalent).

### Parameters

| Name | Type | Required | Default | Description |
|---|---|---|---|---|
| `query` | string | yes | — | Search term |
| `source` | string | — | `developer` | `developer` — Code Reference (developer.wordpress.org); `news` — wordpress.org/news; `user-docs` — wordpress.org/documentation |
| `type` | string | — | `all` | Developer source only: `all`, `functions`, `hooks`, `classes`, `methods` |
| `limit` | integer | — | `5` | Max results (1–10) |

### Response

```json
{
  "count": 2,
  "results": [
    {
      "title": "register_block_type()",
      "url": "https://developer.wordpress.org/reference/functions/register_block_type/",
      "type": "function",
      "excerpt": "Registers a block type..."
    }
  ]
}
```

### Examples

```json
{ "ability_name": "agent-memory/search-wp-docs", "parameters": { "query": "register_block_type", "type": "functions", "limit": 3 } }
```

```json
{ "ability_name": "agent-memory/search-wp-docs", "parameters": { "query": "WordPress 7.0", "source": "news", "limit": 5 } }
```

---

## agent-memory/fetch-wp-doc

Fetch the full plain-text content of a WordPress.org documentation page using the WordPress REST API. Use a URL returned by `search-wp-docs` to retrieve the actual page body.

**Say things like:**
> "Fetch the full content of that WordPress docs page."
> "Get me the complete text from that developer.wordpress.org URL."
> "Read the full plugin handbook page on block registration."

**MCP only** (no REST equivalent).

### Supported hosts

| Host / path prefix | Post type queried |
|---|---|
| `developer.wordpress.org/plugins/…` | `plugin-handbook` |
| `developer.wordpress.org/themes/…` | `theme-handbook` |
| `developer.wordpress.org/block-editor/…` | `plugin-handbook` |
| `developer.wordpress.org/rest-api/…` | `rest-api-handbook` |
| `developer.wordpress.org/reference/functions/…` | `wp-parser-function` |
| `developer.wordpress.org/reference/hooks/…` | `wp-parser-hook` |
| `developer.wordpress.org/reference/classes/…` | `wp-parser-class` |
| `developer.wordpress.org/reference/methods/…` | `wp-parser-method` |
| `wordpress.org/documentation/…` | `helphub_article` |
| `wordpress.org/news/…` | `posts` |

### Parameters

| Name | Type | Required | Description |
|---|---|---|---|
| `url` | string | yes | Full URL of the WordPress.org documentation page to fetch |

### Response

```json
{
  "title": "Plugin Readmes",
  "url": "https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/",
  "content": "Full page content as plain text..."
}
```

### Example

```json
{ "ability_name": "agent-memory/fetch-wp-doc", "parameters": { "url": "https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/" } }
```

### Workflow

Use `search-wp-docs` first to find the relevant URL, then `fetch-wp-doc` to get the full content:

```json
{ "ability_name": "agent-memory/search-wp-docs", "parameters": { "query": "plugin readme.txt format", "source": "developer", "limit": 3 } }
```

```json
{ "ability_name": "agent-memory/fetch-wp-doc", "parameters": { "url": "https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/" } }
```

---

## agent-memory/search-github-issues

Search issues and pull requests on `WordPress/gutenberg` and/or `WordPress/wordpress-develop`. Useful for checking known bugs before filing duplicates or for finding prior art on a problem.

**Say things like:**
> "Check if there's a known Gutenberg issue for block editor performance on large posts."
> "Search GitHub for any open issues about `useSelect` memory leaks."
> "Before we write a bug report, see if this has already been filed on wordpress-develop."

**MCP only** (no REST equivalent).

### Parameters

| Name | Type | Required | Default | Description |
|---|---|---|---|---|
| `query` | string | yes | — | Search term |
| `repo` | string | — | `gutenberg` | `gutenberg`, `wordpress-develop`, or `both` |
| `state` | string | — | `open` | `open`, `closed`, or `all` |
| `type` | string | — | `all` | `issue`, `pr`, or `all` |
| `limit` | integer | — | `10` | Max results (1–20) |

### Response

```json
{
  "count": 1,
  "results": [
    {
      "number": 12345,
      "title": "Block editor performance regression",
      "url": "https://github.com/WordPress/gutenberg/issues/12345",
      "state": "open",
      "type": "issue",
      "created_at": "2026-01-15T10:00:00Z",
      "updated_at": "2026-04-01T08:30:00Z",
      "body_excerpt": "We noticed a significant slowdown when...",
      "labels": ["[Type] Bug", "[Focus] Performance"]
    }
  ]
}
```

### Rate limits

Without a token: ~10 requests/minute. Add a GitHub personal access token in **Settings → Agent Memory** to raise this to 5,000/hour (no scopes required for public repos). Rate limit errors return `{ "error": "GitHub rate limit exceeded. Add a GitHub token in Settings > Agent Memory for higher limits." }`.

### Example

```json
{ "ability_name": "agent-memory/search-github-issues", "parameters": { "query": "block editor performance", "repo": "gutenberg", "limit": 5 } }
```

---

## Agent Authorship

Pass `agent` as a stable slug on create and update to claim authorship. Use lowercase letters, numbers, and hyphens only (`[a-z0-9-]`). A WordPress user is created automatically on first use (role: `author`, email: `{slug}@agents.internal`). The `author` display name is returned in all search and entry responses.
