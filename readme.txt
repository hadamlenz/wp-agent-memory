=== WP Agent Memory ===
Contributors: adrock42
Donate link: https://github.com/adamlenz/wp-agent-memory
Tags: ai, agents, memory, rest-api, mcp
Requires at least: 6.8
Tested up to: 7.0
Stable tag: 0.1.0
Requires PHP: 8.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Stores structured memory entries for AI agents and exposes them via a REST API and MCP abilities for search, retrieval, and usage-based ranking.

== Description ==

WP Agent Memory gives AI agents a persistent, searchable memory store backed by WordPress. Agents can save non-obvious solutions, architectural decisions, and recurring patterns — then retrieve them at the start of future tasks to avoid re-solving the same problems.

**Features**

* Full-text search with relevance scoring and usage-based ranking
* REST API under `/wp-json/agent-memory/v1` for all CRUD operations
* MCP abilities interface for Claude Code, Cursor, and other AI clients
* Usage tracking — memories agents actually use surface higher in future searches
* `mark-useful` signal — agents explicitly vote up memories that shaped a correct answer
* Fetch full WordPress.org documentation pages via the WP REST API (`fetch-wp-doc`)
* Search GitHub issues on WordPress/gutenberg and WordPress/wordpress-develop
* Structured metadata: topic taxonomy, repo, package, symbol type, source path, keywords
* Markdown content stored as a native Gutenberg block, returned as plain Markdown to agents

**How agents use it**

1. At the start of a task, search memory for relevant prior solutions
2. Complete the task using any retrieved context
3. If a memory genuinely shaped the answer, call `mark-useful` to boost its future ranking
4. Save new non-obvious solutions so future agents don't start from scratch

See `SKILL.md` in the plugin directory for the full agent workflow guide.

== Installation ==

1. Upload or clone the plugin into `wp-content/plugins/wp-agent-memory/`
2. Run `composer install` inside the plugin directory
3. Activate the plugin in **Plugins > Installed Plugins**

**Optional: MCP setup for Claude Code**

To expose abilities via the MCP adapter:

1. Install and activate the [WP MCP Adapter](https://github.com/WordPress/mcp-adapter) plugin
2. Set `MCP_ADAPTER_ENABLED=1` in your server environment or `.env` file
3. Add the MCP server to your Claude Code config (see `README.md` for the full config block)

**Environment variables**

* `MCP_ADAPTER_ENABLED` — set to `1` to enable MCP abilities (default: `0`)
* `GITHUB_TOKEN` — GitHub personal access token for higher `search-github-issues` rate limits

== Frequently Asked Questions ==

= What version of WordPress is required? =

WordPress 6.8 or higher. PHP 8.1 or higher.

= Does this work without the MCP adapter? =

Yes. All functionality is available via the REST API at `/wp-json/agent-memory/v1`. The MCP adapter is only needed if you want to use the abilities interface from an AI client like Claude Code.

= What is the MCP adapter? =

The [WP MCP Adapter](https://github.com/WordPress/mcp-adapter) is a community plugin that exposes WordPress Abilities as MCP tools. It is under active development and being considered for inclusion in WordPress core.

= Is this safe to use on a production site? =

No. This plugin has only been tested in local development environments. It has not been hardened for staging or production use. Do not deploy it to any site with real users or live data.

= How does usage-based ranking work? =

Each time a memory appears in search results, its `usage_count` increments. When an agent calls `mark-useful` after a task, `useful_count` increments (weighted 3x more than passive usage). Both decay exponentially over 90 days. The bonus is additive and capped at 60 points, so a freshly-created memory with an exact title match still outranks a heavily-used memory with only a content match.

= Can I use any topic slugs? =

Yes. Unknown topic slugs are created automatically as taxonomy terms on first use.

== External Services ==

This plugin makes HTTP requests to the following external services when the corresponding abilities are used. No data is transmitted unless an agent explicitly calls these abilities.

= GitHub API (api.github.com) =

Used by the `agent-memory/search-github-issues` ability to search issues and pull requests on WordPress/gutenberg and WordPress/wordpress-develop. The search query string is sent to GitHub's API.

* [GitHub Privacy Statement](https://docs.github.com/en/site-policy/privacy-policies/github-general-privacy-statement)
* [GitHub Terms of Service](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service)

= WordPress.org APIs =

Used by `agent-memory/search-wp-docs` and `agent-memory/fetch-wp-doc` to search and retrieve pages from developer.wordpress.org, wordpress.org/documentation, and wordpress.org/news. The search query string or page URL is sent to the WordPress.org REST API.

* [WordPress.org Privacy Policy](https://wordpress.org/about/privacy/)

== Third-Party Libraries ==

This plugin bundles the following open-source libraries in the `vendor/` directory:

* [league/commonmark](https://commonmark.thephpleague.com/) — MIT License — Markdown parsing
* [spatie/commonmark-highlighter](https://github.com/spatie/commonmark-highlighter) — MIT License — Syntax highlighting extension for CommonMark
* [scrivo/highlight.php](https://github.com/scrivo/highlight.php) — BSD 3-Clause License — Syntax highlighting

All bundled libraries are GPL-compatible.

== Changelog ==

= 0.1.0 =
* Initial release — REST API, MCP abilities, usage-based ranking, Markdown block storage

For the full changelog see [changelog.md](https://github.com/adamlenz/wp-agent-memory/blob/main/changelog.md).

== Upgrade Notice ==

= 0.1.0 =
Initial release. No upgrade path needed.
