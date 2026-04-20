<?php

// Minimal ABSPATH constant so plugin files with ABSPATH guards can load in unit tests.
define( 'ABSPATH', __DIR__ . '/tmp/' );

// WordPress function stubs used by unit tests (non-integration).
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( string $text ): string {
        return strip_tags( $text );
    }
}

if ( ! function_exists( 'sanitize_title' ) ) {
    function sanitize_title( string $title ): string {
        $title = strtolower( $title );
        $title = preg_replace( '/[^a-z0-9]+/', '-', $title );
        $title = trim( $title ?? '', '-' );

        return $title;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( string $str ): string {
        return trim( strip_tags( $str ) );
    }
}

if ( ! function_exists( 'get_post' ) ) {
    function get_post( $post = null ) {
        return null;
    }
}

if ( ! function_exists( 'get_the_author_meta' ) ) {
    function get_the_author_meta( string $field, int $user_id = 0 ): string {
        return '';
    }
}

// Register plugin autoloader for test classes.
require_once dirname( __DIR__, 2 ) . '/classes/util/class-autoloader.php';

$autoloader = new \WPAM\Util\Autoloader( 'WPAM', dirname( __DIR__, 2 ) . '/' );
spl_autoload_register( array( $autoloader, 'autoload' ) );
