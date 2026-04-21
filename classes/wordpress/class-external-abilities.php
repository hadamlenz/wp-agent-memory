<?php
/**
 * Ability registration for external WordPress documentation and GitHub issue search.
 *
 * @package WPAM
 */

namespace WPAM\WordPress;

use WPAM\WordPress\External\External_Sources_Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class External_Abilities {
	/** @var External_Sources_Service */
	private External_Sources_Service $service;

	public function __construct( External_Sources_Service $service ) {
		$this->service = $service;
	}

	/**
	 * Register all external source abilities on wp_abilities_api_init.
	 */
	public function register(): void {
		$this->register_wp_docs_ability();
		$this->register_fetch_wp_doc_ability();
		$this->register_github_issues_ability();
	}

	/**
	 * Permission callback — same read threshold as memory abilities.
	 */
	public function can_read(): bool {
		return current_user_can( 'read' );
	}

	/**
	 * Execute agent-memory/search-wp-docs.
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function ability_search_wp_docs( array $input = array() ): array {
		return $this->service->search_wp_docs( $input );
	}

	/**
	 * Execute agent-memory/fetch-wp-doc.
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function ability_fetch_wp_doc( array $input = array() ): array {
		return $this->service->fetch_wp_doc( $input );
	}

	/**
	 * Execute agent-memory/search-github-issues.
	 *
	 * @param array<string, mixed> $input
	 * @return array<string, mixed>
	 */
	public function ability_search_github_issues( array $input = array() ): array {
		return $this->service->search_github_issues( $input );
	}

	private function register_wp_docs_ability(): void {
		$this->register_ability(
			'agent-memory/search-wp-docs',
			array(
				'label'         => 'Search WordPress Docs',
				'description'   => 'Search WordPress documentation. Use source=developer (default) for developer.wordpress.org Code Reference; source=news for wordpress.org/news announcements; source=user-docs for wordpress.org/documentation end-user guides.',
				'input_schema'  => array(
					'type'       => 'object',
					'required'   => array( 'query' ),
					'properties' => array(
						'query'  => array( 'type' => 'string' ),
						'source' => array(
							'type'    => 'string',
							'enum'    => array( 'developer', 'news', 'user-docs' ),
							'default' => 'developer',
						),
						'type'   => array(
							'type'    => 'string',
							'enum'    => array( 'all', 'functions', 'hooks', 'classes', 'methods' ),
							'default' => 'all',
						),
						'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 10, 'default' => 5 ),
					),
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'count'   => array( 'type' => 'integer' ),
						'results' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'title'   => array( 'type' => 'string' ),
									'url'     => array( 'type' => 'string' ),
									'type'    => array( 'type' => 'string' ),
									'excerpt' => array( 'type' => 'string' ),
								),
							),
						),
						'error'   => array( 'type' => 'string' ),
					),
				),
			),
			array( $this, 'ability_search_wp_docs' )
		);
	}

	private function register_fetch_wp_doc_ability(): void {
		$this->register_ability(
			'agent-memory/fetch-wp-doc',
			array(
				'label'         => 'Fetch WordPress Doc Page',
				'description'   => 'Fetch the full content of a WordPress.org documentation page by URL. Supports developer.wordpress.org (plugin/theme handbooks and Code Reference), wordpress.org/documentation, and wordpress.org/news. Use a URL returned by search-wp-docs.',
				'input_schema'  => array(
					'type'       => 'object',
					'required'   => array( 'url' ),
					'properties' => array(
						'url' => array(
							'type'        => 'string',
							'description' => 'Full URL of the WordPress.org documentation page to fetch.',
						),
					),
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'title'   => array( 'type' => 'string' ),
						'url'     => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string', 'description' => 'Full page content as plain text.' ),
						'error'   => array( 'type' => 'string' ),
					),
				),
			),
			array( $this, 'ability_fetch_wp_doc' )
		);
	}

	private function register_github_issues_ability(): void {
		$this->register_ability(
			'agent-memory/search-github-issues',
			array(
				'label'         => 'Search WordPress GitHub Issues',
				'description'   => 'Search issues and PRs on WordPress/gutenberg and WordPress/wordpress-develop GitHub repositories.',
				'input_schema'  => array(
					'type'       => 'object',
					'required'   => array( 'query' ),
					'properties' => array(
						'query' => array( 'type' => 'string' ),
						'repo'  => array(
							'type'    => 'string',
							'enum'    => array( 'gutenberg', 'wordpress-develop', 'both' ),
							'default' => 'gutenberg',
						),
						'state' => array(
							'type'    => 'string',
							'enum'    => array( 'open', 'closed', 'all' ),
							'default' => 'open',
						),
						'type'  => array(
							'type'    => 'string',
							'enum'    => array( 'issue', 'pr', 'all' ),
							'default' => 'all',
						),
						'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 20, 'default' => 10 ),
					),
				),
				'output_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'count'   => array( 'type' => 'integer' ),
						'results' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'number'       => array( 'type' => 'integer' ),
									'title'        => array( 'type' => 'string' ),
									'url'          => array( 'type' => 'string' ),
									'state'        => array( 'type' => 'string' ),
									'type'         => array( 'type' => 'string' ),
									'created_at'   => array( 'type' => 'string' ),
									'updated_at'   => array( 'type' => 'string' ),
									'body_excerpt' => array( 'type' => 'string' ),
									'labels'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
								),
							),
						),
						'error'   => array( 'type' => 'string' ),
					),
				),
			),
			array( $this, 'ability_search_github_issues' )
		);
	}

	/**
	 * @param callable $callback
	 */
	private function register_ability( string $name, array $definition, callable $callback ): void {
		$register_fn = $this->resolve_register_function();
		if ( null === $register_fn ) {
			return;
		}

		$args = array_merge(
			$definition,
			array(
				'category'            => 'agent-memory',
				'permission_callback' => array( $this, 'can_read' ),
				'execute_callback'    => $callback,
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readOnlyHint' => true,
					),
					'mcp'          => array(
						'public' => true,
					),
				),
			)
		);

		call_user_func( $register_fn, $name, $args );
	}

	private function resolve_register_function(): ?string {
		foreach ( array( 'wp_register_ability', 'register_ability' ) as $fn ) {
			if ( function_exists( $fn ) ) {
				return $fn;
			}
		}

		return null;
	}
}
