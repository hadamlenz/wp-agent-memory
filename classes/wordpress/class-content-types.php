<?php
/**
 * Registers CPT, taxonomies, and meta fields.
 *
 * @package WPAM
 */

namespace WPAM\WordPress;

use WPAM\WordPress\Memory\Relation_Helper;
use WPAM\WordPress\Memory\Search_Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Content_Types {
    /**
     * Taxonomy slug => label suffix map used for registration.
     *
     * @var array<string, string>
     */
    private array $taxonomies = array(
        'memory_repo'        => 'Repo',
        'memory_package'     => 'Package',
        'memory_topic'       => 'Topic',
        'memory_symbol_type' => 'Symbol Type',
        'memory_relation_role'  => 'Relation Role',
        'memory_relation_group' => 'Relation Group',
    );

    /**
     * Register all content model pieces for the plugin.
     */
    public function register(): void {
        $this->register_post_type();
        $this->register_taxonomies();
        $this->seed_locked_relation_roles();
        $this->register_meta();
        $this->register_cache_invalidation_hooks();
        $this->maybe_run_relation_taxonomy_backfill();
    }

    /**
     * Register the memory_entry post type.
     */
    private function register_post_type(): void {
        register_post_type(
            'memory_entry',
            array(
                'labels'              => array(
                    'name'          => __( 'Memory Entries', 'wp-agent-memory' ),
                    'singular_name' => __( 'Memory Entry', 'wp-agent-memory' ),
                ),
                'public'              => true,
                'show_in_rest'        => true,
                'menu_position'       => 25,
                'menu_icon'           => 'dashicons-archive',
                'supports'            => array( 'title', 'editor', 'excerpt', 'custom-fields', 'revisions', 'author' ),
                'has_archive'         => false,
                'rewrite'             => array( 'slug' => 'memory-entry' ),
                // map_meta_cap => false + explicit capabilities maps CPT caps to standard page caps.
                // This means Editors (edit_pages) can create/edit/delete entries, Authors cannot.
                // Changing map_meta_cap to true would break the permission model for abilities and REST.
                'map_meta_cap'        => false,
                'capabilities'        => array(
                    'edit_post'           => 'edit_pages',
                    'read_post'           => 'read',
                    'delete_post'         => 'delete_pages',
                    'edit_posts'          => 'edit_pages',
                    'edit_others_posts'   => 'edit_others_pages',
                    'delete_posts'        => 'delete_pages',
                    'delete_others_posts' => 'delete_others_pages',
                    'publish_posts'       => 'publish_pages',
                    'read_private_posts'  => 'read_private_pages',
                    'create_posts'        => 'edit_pages',
                ),
                // Intentional: memories outlive agent users. Deleting an agent WP user must not wipe entries.
                'delete_with_user'    => false,
                'show_in_menu'        => true,
                'show_in_admin_bar'   => true,
                'show_in_nav_menus'   => false,
                'exclude_from_search' => false,
            )
        );
    }

    /**
     * Register filter taxonomies for memory dimensions, including relation role/group.
     */
    private function register_taxonomies(): void {
        add_filter( 'pre_insert_term', array( $this, 'validate_locked_relation_role_term' ), 10, 2 );

        foreach ( $this->taxonomies as $taxonomy => $label ) {
            register_taxonomy(
                $taxonomy,
                array( 'memory_entry' ),
                array(
                    'labels'            => array(
                        'name'          => sprintf( __( 'Memory %s', 'wp-agent-memory' ), $label ),
                        'singular_name' => sprintf( __( 'Memory %s', 'wp-agent-memory' ), $label ),
                    ),
                    'public'            => true,
                    'show_ui'           => true,
                    'show_admin_column' => true,
                    'show_in_rest'      => true,
                    'show_tagcloud'     => true,
                    'hierarchical'      => false,
                    'capabilities'      => array(
                        'manage_terms' => 'install_plugins',
                        'edit_terms'   => 'install_plugins',
                        'delete_terms' => 'install_plugins',
                        'assign_terms' => 'edit_pages',
                    ),
                )
            );
        }
    }

    /**
     * Block creation of unknown relation role terms.
     *
     * @param mixed  $term     Term name being inserted.
     * @param string $taxonomy Target taxonomy.
     *
     * @return mixed
     */
    public function validate_locked_relation_role_term( $term, string $taxonomy ) {
        if ( Relation_Helper::ROLE_TAXONOMY !== $taxonomy ) {
            return $term;
        }

        $slug = sanitize_title( (string) $term );

        if ( '' === $slug || in_array( $slug, Relation_Helper::role_slugs(), true ) ) {
            return $term;
        }

        return new \WP_Error(
            'wpam_invalid_relation_role',
            __( 'memory_relation_role is locked. Use one of the supported relation roles.', 'wp-agent-memory' )
        );
    }

    /**
     * Ensure locked relation role terms exist.
     */
    private function seed_locked_relation_roles(): void {
        foreach ( Relation_Helper::role_labels() as $slug => $label ) {
            if ( get_term_by( 'slug', $slug, Relation_Helper::ROLE_TAXONOMY ) ) {
                continue;
            }

            wp_insert_term(
                $label,
                Relation_Helper::ROLE_TAXONOMY,
                array(
                    'slug' => $slug,
                )
            );
        }
    }

    /**
     * One-time migration that backfills relation role/group taxonomies from prose status lines.
     */
    private function maybe_run_relation_taxonomy_backfill(): void {
        if ( '1' === (string) get_option( 'wpam_relation_taxonomy_backfill_v1_done', '0' ) ) {
            return;
        }

        $entries = get_posts(
            array(
                'post_type'      => 'memory_entry',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'no_found_rows'  => true,
                'orderby'        => 'ID',
                'order'          => 'ASC',
            )
        );

        foreach ( $entries as $entry ) {
            if ( ! $entry instanceof \WP_Post ) {
                continue;
            }

            $target_id = Relation_Helper::extract_companion_target_id( $entry->post_excerpt . "\n" . $entry->post_content );
            if ( $target_id <= 0 ) {
                continue;
            }

            $group_slug = 'g-' . $target_id;

            wp_set_post_terms( (int) $entry->ID, array( 'companion' ), Relation_Helper::ROLE_TAXONOMY, false );
            wp_set_post_terms( (int) $entry->ID, array( $group_slug ), Relation_Helper::GROUP_TAXONOMY, false );

            $target = get_post( $target_id );
            if ( ! $target instanceof \WP_Post || 'memory_entry' !== $target->post_type || 'publish' !== $target->post_status ) {
                continue;
            }

            wp_set_post_terms( $target_id, array( $group_slug ), Relation_Helper::GROUP_TAXONOMY, false );

            $target_roles = wp_get_post_terms(
                $target_id,
                Relation_Helper::ROLE_TAXONOMY,
                array(
                    'fields' => 'slugs',
                )
            );

            if ( is_wp_error( $target_roles ) || ! is_array( $target_roles ) || empty( $target_roles ) ) {
                wp_set_post_terms( $target_id, array( 'canonical' ), Relation_Helper::ROLE_TAXONOMY, false );
            }
        }

        update_option( 'wpam_relation_taxonomy_backfill_v1_done', '1', false );
    }

    /**
     * Register typed post meta used by search/ranking responses.
     */
    private function register_meta(): void {
        $shared_args = array(
            'single'       => true,
            'show_in_rest' => true,
            'type'         => 'string',
            'auth_callback' => static function (): bool {
                return current_user_can( 'edit_posts' );
            },
        );

        register_post_meta(
            'memory_entry',
            'symbol_name',
            array_merge(
                $shared_args,
                array(
                    'sanitize_callback' => 'sanitize_text_field',
                )
            )
        );

        register_post_meta(
            'memory_entry',
            'source_url',
            array_merge(
                $shared_args,
                array(
                    'sanitize_callback' => 'esc_url_raw',
                )
            )
        );

        register_post_meta(
            'memory_entry',
            'source_path',
            array_merge(
                $shared_args,
                array(
                    'sanitize_callback' => 'sanitize_text_field',
                )
            )
        );

        register_post_meta(
            'memory_entry',
            'source_ref',
            array_merge(
                $shared_args,
                array(
                    'sanitize_callback' => 'sanitize_text_field',
                )
            )
        );

        register_post_meta(
            'memory_entry',
            'keywords',
            array_merge(
                $shared_args,
                array(
                    'sanitize_callback' => array( $this, 'sanitize_keywords' ),
                )
            )
        );

        register_post_meta(
            'memory_entry',
            'rank_bias',
            array(
                'single'        => true,
                'show_in_rest'  => true,
                'type'          => 'number',
                'default'       => 0,
                'auth_callback' => static function (): bool {
                    return current_user_can( 'edit_posts' );
                },
                'sanitize_callback' => static function ( $value ): float {
                    return (float) $value;
                },
            )
        );

        // usage_count, useful_count, last_used_gmt are managed exclusively by Search_Service and
        // the mark-useful ability. auth_callback => '__return_false' blocks direct REST meta writes
        // so agents cannot manipulate ranking signals by writing to these fields themselves.
        register_post_meta(
            'memory_entry',
            'usage_count',
            array(
                'single'            => true,
                'show_in_rest'      => false,
                'type'              => 'integer',
                'default'           => 0,
                'auth_callback'     => '__return_false',
                'sanitize_callback' => 'absint',
            )
        );

        register_post_meta(
            'memory_entry',
            'useful_count',
            array(
                'single'            => true,
                'show_in_rest'      => false,
                'type'              => 'integer',
                'default'           => 0,
                'auth_callback'     => '__return_false',
                'sanitize_callback' => 'absint',
            )
        );

        register_post_meta(
            'memory_entry',
            'last_used_gmt',
            array(
                'single'            => true,
                'show_in_rest'      => false,
                'type'              => 'string',
                'default'           => '',
                'auth_callback'     => '__return_false',
                'sanitize_callback' => 'sanitize_text_field',
            )
        );
    }

    /**
     * Normalize stored keywords into a comma-separated sanitized list.
     *
     * @param mixed $value Raw meta value.
     *
     * @return string
     */
    public function sanitize_keywords( $value ): string {
        $value = is_string( $value ) ? $value : '';
        $parts = array_filter( array_map( 'trim', explode( ',', $value ) ) );

        return implode( ',', array_map( 'sanitize_text_field', $parts ) );
    }

    /**
     * Invalidate transient cache whenever search-relevant content changes.
     */
    private function register_cache_invalidation_hooks(): void {
        add_action( 'save_post_memory_entry', array( $this, 'invalidate_search_cache' ) );
        add_action( 'deleted_post', array( $this, 'invalidate_search_cache_on_delete' ), 10, 2 );
        add_action( 'set_object_terms', array( $this, 'invalidate_search_cache' ) );
        add_action( 'updated_post_meta', array( $this, 'maybe_invalidate_on_meta_update' ), 10, 3 );
    }

    /**
     * Cache invalidation callback for multiple hook signatures.
     *
     * @param mixed ...$args Ignored hook args.
     */
    public function invalidate_search_cache( ...$args ): void {
        Search_Service::bump_cache_version();
    }

    /**
     * Only bust search cache for content-relevant meta changes.
     * Usage tracking keys must not invalidate caches on every search.
     *
     * @param int    $meta_id   Updated meta row ID.
     * @param int    $object_id Post ID.
     * @param string $meta_key  Meta key that changed.
     */
    public function maybe_invalidate_on_meta_update( int $meta_id, int $object_id, string $meta_key ): void {
        static $skip_keys = array( 'usage_count', 'useful_count', 'last_used_gmt' );
        if ( in_array( $meta_key, $skip_keys, true ) ) {
            return;
        }
        $this->invalidate_search_cache();
    }

    /**
     * Delete hook callback with post context guard.
     *
     * @param int      $post_id Deleted post ID.
     * @param \WP_Post $post    Deleted post object.
     */
    public function invalidate_search_cache_on_delete( int $post_id, \WP_Post $post ): void {
        if ( 'memory_entry' !== $post->post_type ) {
            return;
        }

        $this->invalidate_search_cache();
    }
}
