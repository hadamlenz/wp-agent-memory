<?php
/**
 * Ability registration for machine-facing memory operations.
 *
 * @package WPAM
 */

namespace WPAM\WordPress;

use WPAM\Memory\Search_Service;
use WPAM\Memory\Writer_Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Abilities {
    /** @var Search_Service */
    private Search_Service $search_service;

    /** @var Writer_Service */
    private Writer_Service $writer_service;

    /**
     * @param Search_Service $search_service
     * @param Writer_Service $writer_service
     */
    public function __construct( Search_Service $search_service, Writer_Service $writer_service ) {
        $this->search_service = $search_service;
        $this->writer_service = $writer_service;
    }

    /**
     * Register the three read-only ability contracts.
     */
    public function register_category(): void {
        if ( function_exists( 'wp_register_ability_category' ) ) {
            wp_register_ability_category(
                'agent-memory',
                array(
                    'label'       => 'Agent Memory',
                    'description' => 'Memory retrieval tools for AI agents.',
                )
            );
        }
    }

    /**
     * Register all read/write memory abilities.
     */
    public function register(): void {
        $this->register_search_ability();
        $this->register_get_entry_ability();
        $this->register_recent_ability();
        $this->register_list_topics_ability();
        $this->register_create_ability();
        $this->register_update_ability();
        $this->register_delete_ability();
        $this->register_mark_useful_ability();
        $this->register_prune_topics_in_title_ability();
    }

    /**
     * Permission callback for read abilities.
     *
     * @return bool
     */
    public function can_read(): bool {
        return current_user_can( 'read' );
    }

    /**
     * Permission callback for write abilities.
     *
     * @return bool
     */
    public function can_write(): bool {
        return current_user_can( 'edit_pages' );
    }

    /**
     * Register search ability schema and callback.
     */
    private function register_search_ability(): void {
        $this->register_ability(
            'agent-memory/search',
            array(
                'label'       => 'Search Agent Memory',
                'description' => 'Search memory entries using symbol-aware ranking.',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'query'       => array( 'type' => 'string' ),
                        'queries'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Explicit OR-style term list. Takes precedence over `query` when non-empty.' ),
                        'repo'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                        'package'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                        'symbol_type' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                        'topic'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                        'relation_role'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                        'relation_group' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                        'limit'       => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50 ),
                    ),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'count'   => array( 'type' => 'integer' ),
                        'results' => array( 'type' => 'array' ),
                    ),
                ),
            ),
            array( $this, 'ability_search' )
        );
    }

    /**
     * Register get-entry ability schema and callback.
     */
    private function register_get_entry_ability(): void {
        $this->register_ability(
            'agent-memory/get-entry',
            array(
                'label'       => 'Get Agent Memory Entry',
                'description' => 'Retrieve a single structured memory entry by ID.',
                'input_schema' => array(
                    'type'       => 'object',
                    'required'   => array( 'id' ),
                    'properties' => array(
                        'id' => array( 'type' => 'integer', 'minimum' => 1 ),
                    ),
                ),
                'output_schema' => array(
                    'type' => 'object',
                ),
            ),
            array( $this, 'ability_get_entry' )
        );
    }

    /**
     * Register list-recent ability schema and callback.
     */
    private function register_recent_ability(): void {
        $this->register_ability(
            'agent-memory/list-recent',
            array(
                'label'       => 'List Recent Agent Memory Entries',
                'description' => 'List recently created or updated memory entries.',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50 ),
                    ),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'count'   => array( 'type' => 'integer' ),
                        'results' => array( 'type' => 'array' ),
                    ),
                ),
            ),
            array( $this, 'ability_list_recent' )
        );
    }

    /**
     * Register list-topics ability schema and callback.
     */
    private function register_list_topics_ability(): void {
        $this->register_ability(
            'agent-memory/list-topics',
            array(
                'label'       => 'List Memory Topics',
                'description' => 'List all memory_topic taxonomy slugs with usage counts.',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(),
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
                                    'slug'  => array( 'type' => 'string' ),
                                    'count' => array( 'type' => 'integer' ),
                                ),
                            ),
                        ),
                    ),
                ),
            ),
            array( $this, 'ability_list_topics' )
        );
    }

    /**
     * Register ability through whichever function name exists in the runtime.
     *
     * @param string   $name       Ability name.
     * @param array    $definition Ability metadata and schemas.
     * @param callable $callback   Execution callback.
     * @param bool     $read_only  Whether this is a read-only ability.
     */
    private function register_ability( string $name, array $definition, callable $callback, bool $read_only = true ): void {
        $register_fn = $this->resolve_register_function();
        if ( null === $register_fn ) {
            return;
        }

        $args = array_merge(
            $definition,
            array(
                'category'            => 'agent-memory',
                'permission_callback' => $read_only ? array( $this, 'can_read' ) : array( $this, 'can_write' ),
                'execute_callback'    => $callback,
                'meta'                => array(
                    'show_in_rest' => true,
                    'annotations'  => array(
                        'readOnlyHint' => $read_only,
                    ),
                    'mcp'          => array(
                        'public' => true,
                    ),
                ),
            )
        );

        call_user_func( $register_fn, $name, $args );
    }

    /**
     * Register create-entry ability schema and callback.
     */
    private function register_create_ability(): void {
        $this->register_ability(
            'agent-memory/create-entry',
            array(
                'label'       => 'Create Agent Memory Entry',
                'description' => 'Create a new published memory entry.',
                'input_schema' => array(
                    'type'       => 'object',
                    'required'   => array( 'title', 'summary', 'topic' ),
                    'properties' => array(
                        'title'       => array( 'type' => 'string' ),
                        'summary'     => array( 'type' => 'string' ),
                        'topic'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'minItems' => 1 ),
                        'content'     => array( 'type' => 'string' ),
                        'symbol_name' => array( 'type' => 'string' ),
                        'symbol_type' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                        'repo'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                        'package'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                        'relation_role'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Relation role taxonomy slugs (single value enforced).' ),
                        'relation_group' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Relation group taxonomy slugs (single value enforced).' ),
                        'source_url'  => array( 'type' => 'string' ),
                        'source_path' => array( 'type' => 'string' ),
                        'source_ref'  => array( 'type' => 'string' ),
                        'rank_bias'   => array( 'type' => 'number' ),
                        'agent'       => array( 'type' => 'string', 'description' => 'Stable slug identifying the calling agent (e.g. claude-sonnet-4-6). Sets post_author; a WordPress user is created on first use.' ),
                    ),
                ),
                'output_schema' => array(
                    'type' => 'object',
                ),
            ),
            array( $this, 'ability_create_entry' ),
            false
        );
    }

    /**
     * Register update-entry ability schema and callback.
     */
    private function register_update_ability(): void {
        $this->register_ability(
            'agent-memory/update-entry',
            array(
                'label'       => 'Update Agent Memory Entry',
                'description' => 'Update fields on an existing memory entry by ID.',
                'input_schema' => array(
                    'type'       => 'object',
                    'required'   => array( 'id' ),
                    'properties' => array(
                        'id'          => array( 'type' => 'integer', 'minimum' => 1 ),
                        'title'       => array( 'type' => 'string' ),
                        'summary'     => array( 'type' => 'string' ),
                        'topic'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                        'content'     => array( 'type' => 'string' ),
                        'symbol_name' => array( 'type' => 'string' ),
                        'symbol_type' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                        'repo'        => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                        'package'     => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
                        'relation_role'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Relation role taxonomy slugs (single value enforced).' ),
                        'relation_group' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => 'Relation group taxonomy slugs (single value enforced).' ),
                        'source_url'  => array( 'type' => 'string' ),
                        'source_path' => array( 'type' => 'string' ),
                        'source_ref'  => array( 'type' => 'string' ),
                        'rank_bias'   => array( 'type' => 'number' ),
                        'agent'       => array( 'type' => 'string', 'description' => 'Stable slug identifying the calling agent (e.g. claude-sonnet-4-6). Sets post_author; a WordPress user is created on first use.' ),
                    ),
                ),
                'output_schema' => array(
                    'type' => 'object',
                ),
            ),
            array( $this, 'ability_update_entry' ),
            false
        );
    }

    /**
     * Register delete-entry ability schema and callback.
     */
    private function register_delete_ability(): void {
        $this->register_ability(
            'agent-memory/delete-entry',
            array(
                'label'       => 'Delete Agent Memory Entry',
                'description' => 'Trash a memory entry by ID.',
                'input_schema' => array(
                    'type'       => 'object',
                    'required'   => array( 'id' ),
                    'properties' => array(
                        'id' => array( 'type' => 'integer', 'minimum' => 1 ),
                    ),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'deleted' => array( 'type' => 'boolean' ),
                        'id'      => array( 'type' => 'integer' ),
                    ),
                ),
            ),
            array( $this, 'ability_delete_entry' ),
            false
        );
    }

    /**
     * Execute agent-memory/create-entry.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function ability_create_entry( array $input = array() ): array {
        return $this->writer_service->create( $input );
    }

    /**
     * Execute agent-memory/update-entry.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function ability_update_entry( array $input = array() ): array {
        $id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;

        return $this->writer_service->update( $id, $input );
    }

    /**
     * Execute agent-memory/delete-entry.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function ability_delete_entry( array $input = array() ): array {
        $id = isset( $input['id'] ) ? absint( $input['id'] ) : 0;

        return $this->writer_service->delete( $id );
    }

    /**
     * Register mark-useful ability schema and callback.
     */
    private function register_mark_useful_ability(): void {
        $this->register_ability(
            'agent-memory/mark-useful',
            array(
                'label'       => 'Mark Memory Entry as Useful',
                'description' => 'Signal that a memory entry was genuinely useful after a task. Increments its useful_count to boost future ranking.',
                'input_schema' => array(
                    'type'       => 'object',
                    'required'   => array( 'id' ),
                    'properties' => array(
                        'id'      => array( 'type' => 'integer', 'minimum' => 1, 'description' => 'Post ID of the memory entry.' ),
                        'agent'   => array( 'type' => 'string', 'description' => 'Stable slug of the calling agent (e.g. claude-sonnet-4-6).' ),
                        'context' => array( 'type' => 'string', 'description' => 'Short note on why the memory was useful.' ),
                    ),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'marked'       => array( 'type' => 'boolean' ),
                        'id'           => array( 'type' => 'integer' ),
                        'useful_count' => array( 'type' => 'integer' ),
                    ),
                ),
            ),
            array( $this, 'ability_mark_useful' ),
            false
        );
    }

    /**
     * Register prune-topics-in-title ability schema and callback.
     */
    private function register_prune_topics_in_title_ability(): void {
        $this->register_ability(
            'agent-memory/prune-topics-in-title',
            array(
                'label'       => 'Prune Topics Found in Entry Titles',
                'description' => 'Remove memory_topic assignments when the topic phrase appears in the entry title. Terms are unassigned from entries; taxonomy terms are not deleted.',
                'input_schema' => array(
                    'type'       => 'object',
                    'properties' => array(),
                ),
                'output_schema' => array(
                    'type'       => 'object',
                    'properties' => array(
                        'scanned_entries'           => array( 'type' => 'integer' ),
                        'updated_entries'           => array( 'type' => 'integer' ),
                        'removed_topic_assignments' => array( 'type' => 'integer' ),
                        'removed_by_topic'          => array(
                            'type' => 'object',
                        ),
                    ),
                ),
            ),
            array( $this, 'ability_prune_topics_in_title' ),
            false
        );
    }

    /**
     * Execute agent-memory/mark-useful.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function ability_mark_useful( array $input = array() ): array {
        $id   = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
        $post = $id > 0 ? get_post( $id ) : null;

        if ( ! $post instanceof \WP_Post || 'memory_entry' !== $post->post_type || 'publish' !== $post->post_status ) {
            return array( 'error' => 'Memory entry not found.' );
        }

        $useful = (int) get_post_meta( $id, 'useful_count', true ) + 1;
        $usage  = (int) get_post_meta( $id, 'usage_count', true ) + 1;
        $now    = gmdate( 'Y-m-d H:i:s' );

        update_post_meta( $id, 'useful_count', $useful );
        update_post_meta( $id, 'usage_count', $usage );
        update_post_meta( $id, 'last_used_gmt', $now );

        return array(
            'marked'       => true,
            'id'           => $id,
            'useful_count' => $useful,
        );
    }

    /**
     * Execute agent-memory/prune-topics-in-title.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function ability_prune_topics_in_title( array $input = array() ): array {
        return $this->writer_service->prune_topics_in_title();
    }

    /**
     * Resolve the ability registration function exposed by current WP runtime.
     *
     * @return string|null
     */
    private function resolve_register_function(): ?string {
        foreach ( array( 'wp_register_ability', 'register_ability' ) as $function_name ) {
            if ( function_exists( $function_name ) ) {
                return $function_name;
            }
        }

        return null;
    }

    /**
     * Execute agent-memory/search.
     *
     * @param array<string, mixed> $input Ability input.
     *
     * @return array<string, mixed>
     */
    public function ability_search( array $input = array() ): array {
        $results = $this->search_service->search( $input );

        return array(
            'count'   => count( $results ),
            'results' => $results,
        );
    }

    /**
     * Execute agent-memory/get-entry.
     *
     * @param array<string, mixed> $input Ability input.
     *
     * @return array<string, mixed>
     */
    public function ability_get_entry( array $input = array() ): array {
        $id    = isset( $input['id'] ) ? absint( $input['id'] ) : 0;
        $entry = $this->search_service->get_entry( $id );

        if ( null === $entry ) {
            return array(
                'error' => 'Memory entry not found.',
            );
        }

        $topic_slugs              = ! empty( $entry['topic'] ) ? (array) $entry['topic'] : array();
        $entry['related_by_topic'] = $this->search_service->get_related_by_topic( $id, $topic_slugs );

        return $entry;
    }

    /**
     * Execute agent-memory/list-recent.
     *
     * @param array<string, mixed> $input Ability input.
     *
     * @return array<string, mixed>
     */
    public function ability_list_recent( array $input = array() ): array {
        $limit   = isset( $input['limit'] ) ? absint( $input['limit'] ) : 10;
        $results = $this->search_service->recent( $limit );

        return array(
            'count'   => count( $results ),
            'results' => $results,
        );
    }

    /**
     * Execute agent-memory/list-topics.
     *
     * @param array<string, mixed> $input Ability input.
     *
     * @return array<string, mixed>
     */
    public function ability_list_topics( array $input = array() ): array {
        $results = $this->search_service->list_topics();

        return array(
            'count'   => count( $results ),
            'results' => $results,
        );
    }
}
