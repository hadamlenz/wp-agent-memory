<?php

use PHPUnit\Framework\TestCase;
use WPAM\Util\Autoloader;

/**
 * Regression tests for plugin autoload path normalization.
 */
final class AutoloaderTest extends TestCase {
    /**
     * Namespace segments and class names must map to WordPress-style filenames.
     */
    public function test_autoload_resolves_wordpress_namespace_and_underscore_class_names(): void {
        $autoloader = new Autoloader( 'WPAM', dirname( __DIR__, 2 ) . '/' );
        $classes    = array(
            'WPAM\\WordPress\\Core',
            'WPAM\\WordPress\\Memory\\Search_Service',
            'WPAM\\WordPress\\Settings',
        );

        foreach ( $classes as $class_name ) {
            if ( ! class_exists( $class_name, false ) ) {
                $autoloader->autoload( $class_name );
            }

            $this->assertTrue( class_exists( $class_name, false ), sprintf( 'Failed autoload for %s', $class_name ) );
        }
    }
}
