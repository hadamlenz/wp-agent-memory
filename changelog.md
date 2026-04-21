# Changelog

All notable changes to WP Agent Memory will be documented here.

## [0.1.0] — 2026-04-20

### Added
- REST API: `search`, `get-entry`, `list-recent`, `create-entry`, `update-entry`, `delete-entry`, `mark-useful`
- MCP abilities: all REST abilities plus `search-wp-docs`, `fetch-wp-doc`, `search-github-issues`
- Usage-based ranking — log-scaled, time-decayed scoring bonus based on `usage_count` and `useful_count`
- `mark-useful` ability and REST endpoint (`POST /entry/{id}/useful`) so agents can signal genuinely useful memories
- `fetch-wp-doc` ability — fetches full page content from WordPress.org documentation via the WP REST API
- Markdown block (`wpam/markdown`) — content stored as a native Gutenberg block, returned as plain Markdown to agents
- Topic, repo, package, and symbol type taxonomy filtering on search
- Agent authorship — pass an `agent` slug on create/update; a WordPress user is created automatically
