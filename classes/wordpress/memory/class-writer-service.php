<?php
/**
 * Write operations for memory entries.
 *
 * @package WPAM
 */

namespace WPAM\WordPress\Memory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Writer_Service {
    /** @var Search_Service */
    private Search_Service $search_service;

    /**
     * @param Search_Service $search_service Used to return shaped entry results after write operations.
     */
    public function __construct( Search_Service $search_service ) {
        $this->search_service = $search_service;
    }

    /**
     * Create a new published memory entry.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function create( array $input ): array {
        if ( empty( $input['title'] ) ) {
            return array( 'error' => 'title is required.' );
        }
        if ( empty( $input['summary'] ) ) {
            return array( 'error' => 'summary is required.' );
        }
        if ( empty( $input['topic'] ) || ! is_array( $input['topic'] ) ) {
            return array( 'error' => 'topic must be a non-empty array of slugs.' );
        }

        $relation_input = $this->validate_relation_inputs( $input );
        if ( isset( $relation_input['error'] ) ) {
            return array( 'error' => $relation_input['error'] );
        }

        $input = array_merge( $input, $relation_input );

        $post_data = array(
            'post_type'    => 'memory_entry',
            'post_status'  => 'publish',
            'post_title'   => sanitize_text_field( $input['title'] ),
            'post_content' => isset( $input['content'] ) ? $this->wrap_content( $input['content'] ) : '',
            'post_excerpt' => sanitize_textarea_field( $input['summary'] ),
        );

        $post_data['post_author'] = ! empty( $input['agent'] )
            ? $this->resolve_agent_user( (string) $input['agent'] )
            : get_current_user_id();

        // kses filters would strip block comment delimiters (<!-- wp:wpam/markdown -->) from post_content.
        // Bracket the insert with remove/init to preserve raw Markdown block markup.
        kses_remove_filters();
        $post_id = wp_insert_post( $post_data, true );
        kses_init_filters();

        if ( is_wp_error( $post_id ) ) {
            return array( 'error' => $post_id->get_error_message() );
        }

        $this->apply_meta( $post_id, $input );
        $this->apply_taxonomies( $post_id, $input );

        return $this->search_service->get_entry( $post_id ) ?? array( 'error' => 'Entry created but could not be retrieved.' );
    }

    /**
     * Update fields on an existing memory entry. Only fields present in $input are changed.
     *
     * @param int                  $id
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    public function update( int $id, array $input ): array {
        $post = get_post( $id );

        if ( null === $post || 'memory_entry' !== $post->post_type ) {
            return array( 'error' => 'Memory entry not found.' );
        }

        $relation_input = $this->validate_relation_inputs( $input );
        if ( isset( $relation_input['error'] ) ) {
            return array( 'error' => $relation_input['error'] );
        }

        $input = array_merge( $input, $relation_input );

        $post_fields = array( 'ID' => $id );

        if ( isset( $input['title'] ) ) {
            $post_fields['post_title'] = sanitize_text_field( $input['title'] );
        }
        if ( isset( $input['content'] ) ) {
            $post_fields['post_content'] = $this->wrap_content( $input['content'] );
        }
        if ( isset( $input['summary'] ) ) {
            $post_fields['post_excerpt'] = sanitize_textarea_field( $input['summary'] );
        }

        if ( ! empty( $input['agent'] ) ) {
            $post_fields['post_author'] = $this->resolve_agent_user( (string) $input['agent'] );
        }

        if ( count( $post_fields ) > 1 ) {
            // Same kses bracket as create — preserves block comment delimiters in post_content.
            kses_remove_filters();
            wp_update_post( $post_fields, true );
            kses_init_filters();
        }

        $this->apply_meta( $id, $input );
        $this->apply_taxonomies( $id, $input );

        return $this->search_service->get_entry( $id ) ?? array( 'error' => 'Entry updated but could not be retrieved.' );
    }

    /**
     * Trash a memory entry by ID.
     *
     * @param int $id
     *
     * @return array<string, mixed>
     */
    public function delete( int $id ): array {
        $post = get_post( $id );

        if ( null === $post || 'memory_entry' !== $post->post_type ) {
            return array( 'error' => 'Memory entry not found.' );
        }

        wp_trash_post( $id );

        return array(
            'deleted' => true,
            'id'      => $id,
        );
    }

    /**
     * Apply post meta fields from input, skipping absent keys.
     *
     * @param int                  $post_id
     * @param array<string, mixed> $input
     */
    private function apply_meta( int $post_id, array $input ): void {
        foreach ( array( 'symbol_name', 'source_path', 'source_ref' ) as $field ) {
            if ( isset( $input[ $field ] ) ) {
                update_post_meta( $post_id, $field, sanitize_text_field( $input[ $field ] ) );
            }
        }

        if ( isset( $input['source_url'] ) ) {
            update_post_meta( $post_id, 'source_url', esc_url_raw( $input['source_url'] ) );
        }

        if ( isset( $input['keywords'] ) && is_array( $input['keywords'] ) ) {
            update_post_meta( $post_id, 'keywords', $this->sanitize_keywords( $input['keywords'] ) );
        }

        if ( isset( $input['rank_bias'] ) ) {
            update_post_meta( $post_id, 'rank_bias', (float) $input['rank_bias'] );
        }
    }

    /**
     * Assign taxonomy terms from input with per-taxonomy auto-create behavior.
     *
     * @param int                  $post_id
     * @param array<string, mixed> $input
     */
    private function apply_taxonomies( int $post_id, array $input ): void {
        $map = array(
            'repo'           => array(
                'taxonomy'    => 'memory_repo',
                'auto_create' => true,
            ),
            'package'        => array(
                'taxonomy'    => 'memory_package',
                'auto_create' => true,
            ),
            'topic'          => array(
                'taxonomy'    => 'memory_topic',
                'auto_create' => true,
            ),
            'symbol_type'    => array(
                'taxonomy'    => 'memory_symbol_type',
                'auto_create' => true,
            ),
            'relation_role'  => array(
                'taxonomy'    => Relation_Helper::ROLE_TAXONOMY,
                'auto_create' => false,
            ),
            'relation_group' => array(
                'taxonomy'    => Relation_Helper::GROUP_TAXONOMY,
                'auto_create' => true,
            ),
        );

        foreach ( $map as $input_key => $taxonomy_config ) {
            if ( ! isset( $input[ $input_key ] ) || ! is_array( $input[ $input_key ] ) ) {
                continue;
            }

            $taxonomy = (string) ( $taxonomy_config['taxonomy'] ?? '' );
            if ( '' === $taxonomy ) {
                continue;
            }

            $term_ids = array();
            foreach ( $input[ $input_key ] as $slug ) {
                $term_id = ! empty( $taxonomy_config['auto_create'] )
                    ? $this->resolve_or_create_term( $taxonomy, (string) $slug )
                    : $this->resolve_existing_term( $taxonomy, (string) $slug );

                if ( $term_id > 0 ) {
                    $term_ids[] = $term_id;
                }
            }

            wp_set_post_terms( $post_id, $term_ids, $taxonomy );
        }
    }

    /**
     * Validate and normalize relation taxonomy inputs if they are present.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function validate_relation_inputs( array $input ): array {
        $normalized = array();

        if ( array_key_exists( 'relation_role', $input ) ) {
            $role = Relation_Helper::validate_relation_role_input( $input['relation_role'] );
            if ( isset( $role['error'] ) ) {
                return array( 'error' => $role['error'] );
            }

            $normalized['relation_role'] = $role['slugs'] ?? array();
        }

        if ( array_key_exists( 'relation_group', $input ) ) {
            $group = Relation_Helper::validate_relation_group_input( $input['relation_group'] );
            if ( isset( $group['error'] ) ) {
                return array( 'error' => $group['error'] );
            }

            $normalized['relation_group'] = $group['slugs'] ?? array();
        }

        return $normalized;
    }

    /**
     * Return the term ID for a slug, creating the term if it does not exist.
     *
     * @param string $taxonomy
     * @param string $slug
     *
     * @return int
     */
    private function resolve_or_create_term( string $taxonomy, string $slug ): int {
        $existing = get_term_by( 'slug', $slug, $taxonomy );

        if ( $existing instanceof \WP_Term ) {
            return $existing->term_id;
        }

        $result = wp_insert_term( $slug, $taxonomy );

        return is_wp_error( $result ) ? 0 : (int) $result['term_id'];
    }

    /**
     * Resolve the term ID for an existing taxonomy term slug.
     *
     * @param string $taxonomy
     * @param string $slug
     *
     * @return int
     */
    private function resolve_existing_term( string $taxonomy, string $slug ): int {
        $existing = get_term_by( 'slug', $slug, $taxonomy );

        return $existing instanceof \WP_Term ? (int) $existing->term_id : 0;
    }

    /**
     * Resolve a WP user ID from an agent slug, falling back to the current authenticated user.
     *
     * @param string $agent Raw agent identifier from input.
     *
     * @return int WordPress user ID.
     */
    private function resolve_agent_user( string $agent ): int {
        $slug = sanitize_user( $agent, true );

        // Note: this does NOT auto-create a WP user for unknown agent slugs — it falls back to
        // the current authenticated user. The ability schema description "a WordPress user is created
        // on first use" is aspirational; create the WP user manually before first agent writes
        // if you want per-agent attribution in the admin Users list.
        if ( '' !== $slug ) {
            $user = get_user_by( 'login', $slug );
            if ( $user ) {
                return $user->ID;
            }
        }

        return get_current_user_id();
    }

    /**
     * Wrap plain Markdown in a wpam/markdown block comment.
     * Content that already starts with block markup is stored as-is.
     *
     * @param string $content Raw content from input.
     *
     * @return string
     */
    private function wrap_content( string $content ): string {
        $content = trim( $content );

        if ( '' === $content ) {
            return '';
        }

        if ( str_starts_with( $content, '<!-- wp:' ) ) {
            return $content;
        }

        return "<!-- wp:wpam/markdown -->\n{$content}\n<!-- /wp:wpam/markdown -->";
    }

    /**
     * Normalize a keywords array into a comma-separated sanitized string.
     *
     * @param string[] $keywords
     *
     * @return string
     */
    public function sanitize_keywords( array $keywords ): string {
        $parts = array_filter( array_map( 'trim', $keywords ) );

        return implode( ',', array_map( 'sanitize_text_field', $parts ) );
    }
}
