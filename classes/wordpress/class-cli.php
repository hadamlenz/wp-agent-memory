<?php
/**
 * WP-CLI commands for WP Agent Memory.
 *
 * @package WPAM
 */

namespace WPAM\WordPress;

use WPAM\Memory\Response_Shaper;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WP Agent Memory CLI utilities.
 *
 * ## EXAMPLES
 *
 *     wp wpam migrate-content
 */
class CLI extends \WP_CLI_Command {

    /**
     * Migrate memory entries from old block format to new JSON-attribute format.
     *
     * Old format stored raw Markdown between block comment delimiters:
     *   <!-- wp:wpam/markdown -->…<!-- /wp:wpam/markdown -->
     *
     * New format stores content in the block comment JSON attributes so that
     * WordPress block validation never has to compare HTML-encoded inner content:
     *   <!-- wp:wpam/markdown {"content":"…"} --><!-- /wp:wpam/markdown -->
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : List entries that would be migrated without writing any changes.
     *
     * ## EXAMPLES
     *
     *     wp wpam migrate-content
     *     wp wpam migrate-content --dry-run
     *
     * @subcommand migrate-content
     * @when after_wp_load
     */
    public function migrate_content( array $args, array $assoc_args ): void {
        $dry_run = ! empty( $assoc_args['dry-run'] );

        $entries = get_posts( array(
            'post_type'      => 'memory_entry',
            'posts_per_page' => -1,
            'post_status'    => 'any',
        ) );

        \WP_CLI::log( sprintf( 'Checking %d entries…', count( $entries ) ) );

        $migrated = 0;

        foreach ( $entries as $entry ) {
            if ( ! str_contains( $entry->post_content, '<!-- wp:wpam/markdown' ) ) {
                continue;
            }

            $plain = Response_Shaper::extract_content( $entry->post_content );

            \WP_CLI::log( sprintf(
                '%s #%d: %s',
                $dry_run ? '[dry-run]' : 'Migrating',
                $entry->ID,
                $entry->post_title
            ) );

            if ( ! $dry_run ) {
                kses_remove_filters();
                wp_update_post( array(
                    'ID'           => $entry->ID,
                    'post_content' => $plain,
                ) );
                kses_init_filters();
            }

            $migrated++;
        }

        \WP_CLI::success( sprintf(
            '%s %d of %d entries.',
            $dry_run ? 'Would migrate' : 'Migrated',
            $migrated,
            count( $entries )
        ) );
    }
}
