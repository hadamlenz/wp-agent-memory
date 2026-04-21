# Contributing to WP Agent Memory

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

## Submitting Changes

All contributions require a pull request and review before merging.

- PHP code follows WordPress coding standards
- Run `composer install` to get PHPUnit, then `vendor/bin/phpunit` to run the test suite
- JS changes require `npm run build` before committing (or use `npm run start` during development)
- The release zip is built automatically by GitHub Actions on a version tag push — do not commit `vendor/` or `blocks/markdown/build/`
