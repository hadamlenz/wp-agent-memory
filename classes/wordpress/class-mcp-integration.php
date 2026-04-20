<?php
/**
 * Feature-flag scaffold for MCP adapter exposure.
 *
 * @package WPAM
 */

namespace WPAM\WordPress;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MCP_Integration {
    /**
     * Conditionally register adapter exposure filters when feature flag is enabled.
     */
    public function register(): void {
        $enabled = getenv( 'MCP_ADAPTER_ENABLED' );

        if ( ! in_array( strtolower( (string) $enabled ), array( '1', 'true', 'yes', 'on' ), true ) ) {
            return;
        }

        // Adapter hook names may vary by version. We expose the same read-only list
        // to the likely filter names so local wiring remains minimal.
        add_filter( 'wp_mcp_adapter_exposed_abilities', array( $this, 'expose_readonly_abilities' ) );
        add_filter( 'wordpress_mcp_adapter_exposed_abilities', array( $this, 'expose_readonly_abilities' ) );
    }

    /**
     * Merge plugin abilities into adapter's exposed ability list.
     *
     * @param mixed $abilities Existing adapter abilities.
     *
     * @return array<int, string>
     */
    public function expose_readonly_abilities( $abilities ): array {
        $abilities = is_array( $abilities ) ? $abilities : array();

        return array_values(
            array_unique(
                array_merge(
                    $abilities,
                    array(
                        'agent-memory/search',
                        'agent-memory/get-entry',
                        'agent-memory/list-recent',
                        'agent-memory/search-wp-docs',
                        'agent-memory/search-github-issues',
                    )
                )
            )
        );
    }
}
