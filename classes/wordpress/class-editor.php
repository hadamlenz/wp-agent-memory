<?php

/**
 * editor customizations WP Agent Memory.
 *
 * @package WPAM
 */

namespace WPAM\WordPress;

class Editor {

    function disable_tinymce($settings, $editor_id) {
        if ($editor_id === 'content' && get_post_type() === 'memory_entry') {
            $settings['tinymce'] = false; // Disables visual tab
            $settings['quicktags'] = true; // Keeps the Text/HTML tab buttons
        }
        return $settings;
    }

    public function register_stats_meta_box(): void {
        add_meta_box(
            'wpam-entry-stats',
            __( 'Memory Stats', 'wp-agent-memory' ),
            array( $this, 'render_stats_meta_box' ),
            'memory_entry',
            'side',
            'default'
        );
    }

    public function render_stats_meta_box( \WP_Post $post ): void {
        $useful    = (int) get_post_meta( $post->ID, 'useful_count', true );
        $usage     = (int) get_post_meta( $post->ID, 'usage_count', true );
        $last_used = (string) get_post_meta( $post->ID, 'last_used_gmt', true );

        printf( '<p><strong>%s</strong> %d</p>', esc_html__( 'Useful count:', 'wp-agent-memory' ), $useful );
        printf( '<p><strong>%s</strong> %d</p>', esc_html__( 'Usage count:', 'wp-agent-memory' ), $usage );
        if ( $last_used ) {
            printf( '<p><strong>%s</strong> %s</p>', esc_html__( 'Last used (GMT):', 'wp-agent-memory' ), esc_html( $last_used ) );
        }
    }
}
