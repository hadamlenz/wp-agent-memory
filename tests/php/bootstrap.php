<?php

// Minimal ABSPATH constant so plugin files with ABSPATH guards can load in unit tests.
define( 'ABSPATH', __DIR__ . '/tmp/' );

// WordPress function stubs used by unit tests (non-integration).
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    /**
     * Test stub for wp_strip_all_tags().
     *
     * @param string $text Raw HTML/text input.
     * @return string
     */
    function wp_strip_all_tags( string $text ): string {
        return strip_tags( $text );
    }
}

if ( ! function_exists( 'sanitize_title' ) ) {
    /**
     * Test stub for sanitize_title().
     *
     * @param string $title Raw title.
     * @return string
     */
    function sanitize_title( string $title ): string {
        $title = strtolower( $title );
        $title = preg_replace( '/[^a-z0-9]+/', '-', $title );
        $title = trim( $title ?? '', '-' );

        return $title;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    /**
     * Test stub for sanitize_text_field().
     *
     * @param string $str Raw field value.
     * @return string
     */
    function sanitize_text_field( string $str ): string {
        return trim( strip_tags( $str ) );
    }
}

if ( ! function_exists( 'get_post' ) ) {
    /**
     * Test stub for get_post().
     *
     * @param mixed $post Unused post reference.
     * @return null
     */
    function get_post( $post = null ) {
        return null;
    }
}

if ( ! function_exists( 'get_the_author_meta' ) ) {
    /**
     * Test stub for get_the_author_meta().
     *
     * @param string $field   Requested field.
     * @param int    $user_id Requested user ID.
     * @return string
     */
    function get_the_author_meta( string $field, int $user_id = 0 ): string {
        return '';
    }
}

if ( ! class_exists( 'WP_Error' ) ) {
    /**
     * Minimal WP_Error test double used by HTTP stubs.
     */
    class WP_Error {
        private string $message;

        public function __construct( string $message = '' ) {
            $this->message = $message;
        }

        public function get_error_message(): string {
            return $this->message;
        }
    }
}

if ( ! function_exists( 'esc_url_raw' ) ) {
    /**
     * Test stub for esc_url_raw().
     */
    function esc_url_raw( string $url ): string {
        return trim( $url );
    }
}

if ( ! function_exists( 'wp_parse_url' ) ) {
    /**
     * Test stub for wp_parse_url().
     *
     * @return array<string, mixed>|false
     */
    function wp_parse_url( string $url ) {
        return parse_url( $url );
    }
}

if ( ! function_exists( 'add_query_arg' ) ) {
    /**
     * Test stub for add_query_arg() supporting array args.
     *
     * @param array<string, scalar> $args Query parameters.
     */
    function add_query_arg( array $args, string $url ): string {
        $parsed = parse_url( $url );
        if ( false === $parsed ) {
            return $url;
        }

        $base = '';
        if ( isset( $parsed['scheme'], $parsed['host'] ) ) {
            $base = $parsed['scheme'] . '://' . $parsed['host'];
            if ( isset( $parsed['port'] ) ) {
                $base .= ':' . $parsed['port'];
            }
        }
        $base .= $parsed['path'] ?? '';

        $query = array();
        if ( isset( $parsed['query'] ) ) {
            parse_str( $parsed['query'], $query );
        }

        foreach ( $args as $key => $value ) {
            $query[ $key ] = (string) $value;
        }

        $query_string = http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
        if ( '' === $query_string ) {
            return $base;
        }

        return $base . '?' . $query_string;
    }
}

if ( ! function_exists( 'get_transient' ) ) {
    /**
     * Test stub for get_transient().
     */
    function get_transient( string $key ) {
        $transients = $GLOBALS['wpam_test_transients'] ?? array();
        return $transients[ $key ] ?? false;
    }
}

if ( ! function_exists( 'set_transient' ) ) {
    /**
     * Test stub for set_transient().
     */
    function set_transient( string $key, $value, int $expiration = 0 ): bool {
        if ( ! isset( $GLOBALS['wpam_test_transients'] ) || ! is_array( $GLOBALS['wpam_test_transients'] ) ) {
            $GLOBALS['wpam_test_transients'] = array();
        }
        $GLOBALS['wpam_test_transients'][ $key ] = $value;
        return true;
    }
}

if ( ! function_exists( 'wp_remote_get' ) ) {
    /**
     * Test stub for wp_remote_get() using in-memory canned responses.
     *
     * @param array<string, mixed> $args Ignored in this stub.
     * @return array<string, mixed>|WP_Error
     */
    function wp_remote_get( string $url, array $args = array() ) {
        if ( ! isset( $GLOBALS['wpam_test_http_calls'] ) || ! is_array( $GLOBALS['wpam_test_http_calls'] ) ) {
            $GLOBALS['wpam_test_http_calls'] = array();
        }
        $GLOBALS['wpam_test_http_calls'][] = $url;

        $responses = $GLOBALS['wpam_test_http_responses'] ?? array();
        if ( ! isset( $responses[ $url ] ) ) {
            return array(
                'response' => array( 'code' => 404 ),
                'body'     => '',
            );
        }

        return $responses[ $url ];
    }
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
    /**
     * Test stub for wp_remote_retrieve_response_code().
     *
     * @param array<string, mixed> $response
     */
    function wp_remote_retrieve_response_code( array $response ): int {
        return (int) ( $response['response']['code'] ?? 0 );
    }
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
    /**
     * Test stub for wp_remote_retrieve_body().
     *
     * @param array<string, mixed> $response
     */
    function wp_remote_retrieve_body( array $response ): string {
        return (string) ( $response['body'] ?? '' );
    }
}

if ( ! function_exists( 'is_wp_error' ) ) {
    /**
     * Test stub for is_wp_error().
     */
    function is_wp_error( $thing ): bool {
        return $thing instanceof WP_Error;
    }
}

/**
 * Reset in-memory HTTP/transient test state.
 */
function wpam_test_reset_state(): void {
    $GLOBALS['wpam_test_transients']     = array();
    $GLOBALS['wpam_test_http_responses'] = array();
    $GLOBALS['wpam_test_http_calls']     = array();
}

/**
 * Seed a canned HTTP JSON response for a URL.
 *
 * @param array<mixed> $decoded_body Decoded JSON body.
 */
function wpam_test_set_http_json_response( string $url, array $decoded_body, int $status = 200 ): void {
    if ( ! isset( $GLOBALS['wpam_test_http_responses'] ) || ! is_array( $GLOBALS['wpam_test_http_responses'] ) ) {
        $GLOBALS['wpam_test_http_responses'] = array();
    }

    $GLOBALS['wpam_test_http_responses'][ $url ] = array(
        'response' => array( 'code' => $status ),
        'body'     => json_encode( $decoded_body ),
    );
}

// Register plugin autoloader for test classes.
require_once dirname( __DIR__, 2 ) . '/classes/util/class-autoloader.php';

$autoloader = new \WPAM\Util\Autoloader( 'WPAM', dirname( __DIR__, 2 ) . '/' );
spl_autoload_register( array( $autoloader, 'autoload' ) );
