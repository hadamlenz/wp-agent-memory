<?php
/**
 * Plugin Name: WP Agent Memory
 * Plugin URI: https://github.com/adamlenz/wp-agent-memory
 * Description: Stores structured memory entries for AI agents and exposes them via a REST API and MCP abilities for search, retrieval, and usage-based ranking.
 * Version: 0.1.0
 * Author: H. Adam Lenz
 * Author URI: https://profiles.wordpress.org/adrock42/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-agent-memory
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

// Register WP-CLI commands when running in a CLI context.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    \WP_CLI::add_command( 'wpam', WordPress\CLI::class );
}
