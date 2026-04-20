<?php
/**
 * Plugin Name: WP Agent Memory
 * Description: WordPress-native memory catalog and retrieval tools for developer workflows.
 * Version: 0.1.0
 * Author: Local
 * Requires at least: 6.8
 * Requires PHP: 8.1
 */

namespace WPAM;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shared plugin constants used by loaders and service classes.
 */
define( 'WPAM_VERSION', '0.1.0' );
define( 'WPAM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader for third-party dependencies (e.g. league/commonmark).
if ( file_exists( WPAM_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once WPAM_PLUGIN_DIR . 'vendor/autoload.php';
}

// UNC-style custom autoloader for all plugin classes.
require_once WPAM_PLUGIN_DIR . 'classes/util/class-autoloader.php';

$autoloader = new Util\Autoloader( __NAMESPACE__, WPAM_PLUGIN_DIR );
spl_autoload_register( array( $autoloader, 'autoload' ) );

// Boot singleton plugin core.
WordPress\Core::get_instance();
