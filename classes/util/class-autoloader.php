<?php
/**
 * UNC-style class autoloader for plugin classes.
 *
 * @package WPAM
 */

namespace WPAM\Util;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Autoloader {
    /**
     * Root namespace prefix to match (for example: WPAM).
     *
     * @var string
     */
    private string $namespace;

    /**
     * Absolute plugin path with trailing directory separator.
     *
     * @var string
     */
    private string $path;

    /**
     * @param string $namespace Plugin namespace root.
     * @param string $path      Plugin filesystem base path.
     */
    public function __construct( string $namespace, string $path ) {
        $this->namespace = trim( $namespace, '\\' );
        $this->path      = rtrim( $path, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
    }

    /**
     * Load class files from plugin classes directory.
     *
     * @param string $class_name Requested class name.
     */
    public function autoload( string $class_name ): void {
        // Ignore classes outside the plugin namespace.
        if ( 0 !== strpos( $class_name, $this->namespace . '\\' ) ) {
            return;
        }

        $parts = explode( '\\', $class_name );
        if ( empty( $parts ) || $parts[0] !== $this->namespace ) {
            return;
        }

        array_shift( $parts );
        if ( empty( $parts ) ) {
            return;
        }

        // Build UNC-style path: classes/<segment>/class-<class-name>.php
        $class_dir = $this->path . 'classes';
        $class     = array_pop( $parts );

        foreach ( $parts as $segment ) {
            $class_dir .= '/' . $this->to_wordpress_token( $segment );
        }

        $class_file = $class_dir . '/class-' . $this->to_wordpress_token( $class ) . '.php';

        if ( file_exists( $class_file ) ) {
            require_once $class_file;
        }
    }

    /**
     * Convert namespace/class values to WordPress-style filename tokens.
     *
     * @param string $value Raw namespace segment or class short name.
     *
     * @return string
     */
    private function to_wordpress_token( string $value ): string {
        $value = str_replace( '_', '-', $value );

        return strtolower( $value );
    }
}
