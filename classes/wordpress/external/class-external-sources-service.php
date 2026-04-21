<?php
/**
 * HTTP service for querying external WordPress documentation and GitHub issues.
 *
 * @package WPAM
 */

namespace WPAM\WordPress\External;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class External_Sources_Service {
	private const WP_DOCS_CACHE_TTL  = 300;
	private const GITHUB_CACHE_TTL   = 120;
	private const WP_DOCS_BASE       = 'https://developer.wordpress.org/wp-json/wp/v2/search';
	private const WP_DOCS_REST_BASE  = 'https://developer.wordpress.org/wp-json/wp/v2/';
	private const WP_NEWS_BASE       = 'https://wordpress.org/news/wp-json/wp/v2/posts';
	private const WP_NEWS_REST_BASE  = 'https://wordpress.org/news/wp-json/wp/v2/';
	private const WP_USER_DOCS_BASE  = 'https://wordpress.org/documentation/wp-json/wp/v2/search';
	private const WP_USER_DOCS_REST_BASE = 'https://wordpress.org/documentation/wp-json/wp/v2/';
	private const GITHUB_SEARCH_BASE = 'https://api.github.com/search/issues';

	private ?string $github_token;

	public function __construct() {
		$token              = getenv( 'GITHUB_TOKEN' );
		$this->github_token = ( false !== $token && '' !== $token ) ? $token : null;
	}

	/**
	 * Search developer.wordpress.org Code Reference.
	 *
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>
	 */
	public function search_wp_docs( array $params ): array {
		$query  = isset( $params['query'] ) ? sanitize_text_field( (string) $params['query'] ) : '';
		$type   = isset( $params['type'] ) ? (string) $params['type'] : 'all';
		$limit  = isset( $params['limit'] ) ? max( 1, min( 10, (int) $params['limit'] ) ) : 5;
		$source = isset( $params['source'] ) ? (string) $params['source'] : 'developer';

		if ( '' === $query ) {
			return array( 'error' => 'query is required.' );
		}

		$cache_key = 'wpam_wp_docs_' . md5( $query . '|' . $type . '|' . $limit . '|' . $source );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		if ( 'news' === $source ) {
			$url    = add_query_arg( array( 'search' => $query, 'per_page' => $limit ), self::WP_NEWS_BASE );
			$result = $this->fetch( $url );
			if ( is_string( $result ) ) {
				return array( 'error' => $result );
			}
			$results = array_map( array( $this, 'normalize_wp_news_result' ), $result );
		} elseif ( 'user-docs' === $source ) {
			$url    = add_query_arg( array( 'search' => $query, 'per_page' => $limit ), self::WP_USER_DOCS_BASE );
			$result = $this->fetch( $url );
			if ( is_string( $result ) ) {
				return array( 'error' => $result );
			}
			$results = array_map( array( $this, 'normalize_wp_docs_result' ), $result );
		} else {
			$subtype_map = array(
				'functions' => 'wp-parser-function',
				'hooks'     => 'wp-parser-hook',
				'classes'   => 'wp-parser-class',
				'methods'   => 'wp-parser-method',
			);
			$subtype = isset( $subtype_map[ $type ] ) ? $subtype_map[ $type ] : 'any';

			$url    = add_query_arg(
				array(
					'search'   => $query,
					'per_page' => $limit,
					'subtype'  => $subtype,
				),
				self::WP_DOCS_BASE
			);
			$result = $this->fetch( $url );
			if ( is_string( $result ) ) {
				return array( 'error' => $result );
			}
			$results = array_map( array( $this, 'normalize_wp_docs_result' ), $result );
		}

		$output = array(
			'count'   => count( $results ),
			'results' => $results,
		);

		set_transient( $cache_key, $output, self::WP_DOCS_CACHE_TTL );

		return $output;
	}

	/**
	 * Search GitHub issues and PRs on WordPress repositories.
	 *
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>
	 */
	public function search_github_issues( array $params ): array {
		$query = isset( $params['query'] ) ? sanitize_text_field( (string) $params['query'] ) : '';
		$repo  = isset( $params['repo'] ) ? (string) $params['repo'] : 'gutenberg';
		$state = isset( $params['state'] ) ? (string) $params['state'] : 'open';
		$type  = isset( $params['type'] ) ? (string) $params['type'] : 'all';
		$limit = isset( $params['limit'] ) ? max( 1, min( 20, (int) $params['limit'] ) ) : 10;

		if ( '' === $query ) {
			return array( 'error' => 'query is required.' );
		}

		$cache_key = 'wpam_github_' . md5( $query . '|' . $repo . '|' . $state . '|' . $type . '|' . $limit );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$headers = array(
			'Accept'               => 'application/vnd.github+json',
			'X-GitHub-Api-Version' => '2022-11-28',
		);
		if ( null !== $this->github_token ) {
			$headers['Authorization'] = 'Bearer ' . $this->github_token;
		}

		if ( 'both' === $repo ) {
			$gutenberg = $this->fetch_github( $query, 'gutenberg', $state, $type, $limit, $headers );
			if ( isset( $gutenberg['error'] ) ) {
				return $gutenberg;
			}

			$wp_develop = $this->fetch_github( $query, 'wordpress-develop', $state, $type, $limit, $headers );
			if ( isset( $wp_develop['error'] ) ) {
				return $wp_develop;
			}

			$merged = array_merge( $gutenberg['results'], $wp_develop['results'] );
			usort(
				$merged,
				static function ( array $a, array $b ): int {
					return strcmp( $b['updated_at'], $a['updated_at'] );
				}
			);
			$merged = array_slice( $merged, 0, $limit );
			$output = array(
				'count'   => count( $merged ),
				'results' => $merged,
			);
		} else {
			$output = $this->fetch_github( $query, $repo, $state, $type, $limit, $headers );
		}

		if ( ! isset( $output['error'] ) ) {
			set_transient( $cache_key, $output, self::GITHUB_CACHE_TTL );
		}

		return $output;
	}

	/**
	 * Fetch full content for a WordPress.org documentation page via the WP REST API.
	 *
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>
	 */
	public function fetch_wp_doc( array $params ): array {
		$url = isset( $params['url'] ) ? esc_url_raw( (string) $params['url'] ) : '';

		if ( '' === $url ) {
			return array( 'error' => 'url is required.' );
		}

		$cache_key = 'wpam_wp_doc_' . md5( $url );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$endpoint = $this->resolve_wp_rest_endpoint( $url );
		if ( null === $endpoint ) {
			return array( 'error' => 'URL is not a supported WordPress.org documentation page. Supported hosts: developer.wordpress.org, wordpress.org/documentation, wordpress.org/news.' );
		}

		$api_url = add_query_arg(
			array(
				'slug'    => $endpoint['slug'],
				'_fields' => 'title,content,link',
			),
			$endpoint['base'] . $endpoint['post_type']
		);

		$result = $this->fetch( $api_url );
		if ( is_string( $result ) ) {
			return array( 'error' => $result );
		}

		if ( empty( $result ) || ! isset( $result[0] ) ) {
			return array( 'error' => 'Document not found.' );
		}

		$item    = $result[0];
		$title   = wp_strip_all_tags( is_array( $item['title'] ) ? ( $item['title']['rendered'] ?? '' ) : (string) ( $item['title'] ?? '' ) );
		$content = wp_strip_all_tags( is_array( $item['content'] ) ? ( $item['content']['rendered'] ?? '' ) : (string) ( $item['content'] ?? '' ) );
		$link    = (string) ( $item['link'] ?? $url );

		$content = (string) preg_replace( '/\n{3,}/', "\n\n", trim( $content ) );

		$output = array(
			'title'   => $title,
			'url'     => $link,
			'content' => $content,
		);

		set_transient( $cache_key, $output, self::WP_DOCS_CACHE_TTL );

		return $output;
	}

	/**
	 * Resolve the WP REST API base URL, post type, and slug for a WordPress.org docs URL.
	 *
	 * @return array{base: string, post_type: string, slug: string}|null
	 */
	private function resolve_wp_rest_endpoint( string $url ): ?array {
		$parsed = wp_parse_url( $url );
		$host   = $parsed['host'] ?? '';
		$path   = trim( $parsed['path'] ?? '', '/' );
		$parts  = explode( '/', $path );
		$slug   = (string) end( $parts );

		if ( '' === $slug ) {
			return null;
		}

		if ( 'developer.wordpress.org' === $host ) {
			$section = $parts[0] ?? '';

			if ( 'reference' === $section && isset( $parts[1] ) ) {
				$ref_map = array(
					'functions' => 'wp-parser-function',
					'hooks'     => 'wp-parser-hook',
					'classes'   => 'wp-parser-class',
					'methods'   => 'wp-parser-method',
				);
				$post_type = $ref_map[ $parts[1] ] ?? 'wp-parser-function';
			} else {
				$section_map = array(
					'plugins'      => 'plugin-handbook',
					'themes'       => 'theme-handbook',
					'block-editor' => 'plugin-handbook',
					'rest-api'     => 'rest-api-handbook',
				);
				$post_type = $section_map[ $section ] ?? 'plugin-handbook';
			}

			return array(
				'base'      => self::WP_DOCS_REST_BASE,
				'post_type' => $post_type,
				'slug'      => $slug,
			);
		}

		if ( 'wordpress.org' === $host ) {
			if ( str_starts_with( $path, 'documentation/' ) ) {
				return array(
					'base'      => self::WP_USER_DOCS_REST_BASE,
					'post_type' => 'helphub_article',
					'slug'      => $slug,
				);
			}

			if ( str_starts_with( $path, 'news/' ) ) {
				return array(
					'base'      => self::WP_NEWS_REST_BASE,
					'post_type' => 'posts',
					'slug'      => $slug,
				);
			}
		}

		return null;
	}

	/**
	 * Execute a single GitHub issues search request.
	 *
	 * @param array<string, string> $headers
	 * @return array<string, mixed>
	 */
	private function fetch_github( string $query, string $repo, string $state, string $type, int $limit, array $headers ): array {
		$url    = add_query_arg(
			array(
				'q'        => $this->build_github_query( $query, $repo, $state, $type ),
				'per_page' => $limit,
				'sort'     => 'updated',
				'order'    => 'desc',
			),
			self::GITHUB_SEARCH_BASE
		);
		$result = $this->fetch( $url, $headers, true );

		if ( is_string( $result ) ) {
			return array( 'error' => $result );
		}

		$items = isset( $result['items'] ) && is_array( $result['items'] ) ? $result['items'] : array();

		return array(
			'count'   => count( $items ),
			'results' => array_map( array( $this, 'normalize_github_result' ), $items ),
		);
	}

	/**
	 * Perform an HTTP GET request and return the decoded JSON body or an error string.
	 *
	 * @param array<string, string> $headers
	 * @return array<mixed>|string
	 */
	private function fetch( string $url, array $headers = array(), bool $is_github = false ): array|string {
		$response = wp_remote_get(
			$url,
			array(
				'headers' => array_merge( array( 'User-Agent' => 'wp-agent-memory/1.0' ), $headers ),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( in_array( $code, array( 403, 429 ), true ) ) {
			if ( $is_github ) {
				return 'GitHub rate limit exceeded. Set GITHUB_TOKEN env var for higher limits.';
			}
			return sprintf( 'Request forbidden (HTTP %d).', $code );
		}

		if ( $code >= 500 ) {
			return sprintf( 'External API unavailable (HTTP %d).', $code );
		}

		if ( 200 !== $code ) {
			return sprintf( 'Unexpected response (HTTP %d).', $code );
		}

		$decoded = json_decode( $body, true );
		if ( ! is_array( $decoded ) ) {
			return 'Failed to parse API response.';
		}

		return $decoded;
	}

	/**
	 * @param array<string, mixed> $item
	 * @return array<string, string>
	 */
	private function normalize_wp_news_result( array $item ): array {
		$title   = wp_strip_all_tags( isset( $item['title']['rendered'] ) ? (string) $item['title']['rendered'] : '' );
		$url     = isset( $item['link'] ) ? (string) $item['link'] : '';
		$type    = 'news';
		$excerpt = wp_strip_all_tags( isset( $item['excerpt']['rendered'] ) ? (string) $item['excerpt']['rendered'] : '' );
		return compact( 'title', 'url', 'type', 'excerpt' );
	}

	/**
	 * @param array<string, mixed> $item
	 * @return array<string, string>
	 */
	private function normalize_wp_docs_result( array $item ): array {
		$raw_title = $item['title'] ?? '';
		$title     = wp_strip_all_tags( is_array( $raw_title ) ? ( $raw_title['rendered'] ?? '' ) : (string) $raw_title );
		$url       = isset( $item['url'] ) ? (string) $item['url'] : '';
		$type      = isset( $item['subtype'] ) ? (string) $item['subtype'] : '';

		$excerpt = '';
		if ( isset( $item['excerpt'] ) ) {
			$raw     = is_array( $item['excerpt'] ) ? ( $item['excerpt']['rendered'] ?? '' ) : (string) $item['excerpt'];
			$excerpt = wp_strip_all_tags( $raw );
		}

		return compact( 'title', 'url', 'type', 'excerpt' );
	}

	/**
	 * @param array<string, mixed> $item
	 * @return array<string, mixed>
	 */
	private function normalize_github_result( array $item ): array {
		$type   = isset( $item['pull_request'] ) ? 'pr' : 'issue';
		$body   = isset( $item['body'] ) && is_string( $item['body'] ) ? $item['body'] : '';
		$labels = array();
		if ( isset( $item['labels'] ) && is_array( $item['labels'] ) ) {
			foreach ( $item['labels'] as $label ) {
				if ( isset( $label['name'] ) ) {
					$labels[] = (string) $label['name'];
				}
			}
		}

		return array(
			'number'       => isset( $item['number'] ) ? (int) $item['number'] : 0,
			'title'        => isset( $item['title'] ) ? (string) $item['title'] : '',
			'url'          => isset( $item['html_url'] ) ? (string) $item['html_url'] : '',
			'state'        => isset( $item['state'] ) ? (string) $item['state'] : '',
			'type'         => $type,
			'created_at'   => isset( $item['created_at'] ) ? (string) $item['created_at'] : '',
			'updated_at'   => isset( $item['updated_at'] ) ? (string) $item['updated_at'] : '',
			'body_excerpt' => substr( $body, 0, 200 ),
			'labels'       => $labels,
		);
	}

	/**
	 * Build a GitHub search query string with repo and type qualifiers.
	 */
	private function build_github_query( string $query, string $repo, string $state, string $type ): string {
		$repo_map = array(
			'gutenberg'         => 'WordPress/gutenberg',
			'wordpress-develop' => 'WordPress/wordpress-develop',
		);
		$q = $query . ' repo:' . ( $repo_map[ $repo ] ?? 'WordPress/gutenberg' );

		if ( 'issue' === $type ) {
			$q .= ' is:issue';
		} elseif ( 'pr' === $type ) {
			$q .= ' is:pr';
		}

		if ( 'open' === $state ) {
			$q .= ' is:open';
		} elseif ( 'closed' === $state ) {
			$q .= ' is:closed';
		}

		return $q;
	}
}
