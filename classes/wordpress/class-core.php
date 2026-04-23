<?php
/**
 * Core plugin bootstrap.
 *
 * @package WPAM
 */

namespace WPAM\WordPress;

use WPAM\External\External_Sources_Service;
use WPAM\Memory\Search_Service;
use WPAM\Memory\Writer_Service;
use WPAM\WordPress\Markdown_Block;
use WPAM\WordPress\Editor;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Core {
    /**
     * Singleton instance.
     *
     * @var Core|null
     */
    private static ?Core $instance = null;

    /** @var Content_Types */
    private Content_Types $content_types;
    /** @var Search_Service */
    private Search_Service $search_service;
    /** @var Writer_Service */
    private Writer_Service $writer_service;
    /** @var Rest_Endpoints */
    private Rest_Endpoints $rest_endpoints;
    /** @var Abilities */
    private Abilities $abilities;
    /** @var Markdown_Block */
    private Markdown_Block $markdown_block;
    /** @var Settings */
    private Settings $settings;
    /** @var External_Sources_Service */
    private External_Sources_Service $external_sources_service;
    /** @var External_Abilities */
    private External_Abilities $external_abilities;
    private Editor $editor;

    /**
     * Build service graph and register hooks.
     */
    private function __construct() {
        $this->content_types   = new Content_Types();
        $this->search_service  = new Search_Service();
        $this->writer_service  = new Writer_Service( $this->search_service );
        $this->rest_endpoints  = new Rest_Endpoints( $this->search_service, $this->writer_service );
        $this->abilities       = new Abilities( $this->search_service, $this->writer_service );
        $this->markdown_block           = new Markdown_Block();
        $this->settings                 = new Settings();
        $this->external_sources_service = new External_Sources_Service();
        $this->external_abilities       = new External_Abilities( $this->external_sources_service );
        $this->editor = new Editor();

        add_action( 'init', static function (): void {
            load_plugin_textdomain( 'wp-agent-memory', false, dirname( plugin_basename( WPAM_PLUGIN_DIR . 'wp-agent-memory.php' ) ) . '/languages' );
        } );
        add_action( 'init', array( $this->content_types, 'register' ) );
        add_action( 'init', array( $this->content_types, 'register_block_bindings' ) );
        add_action( 'init', array( $this->markdown_block, 'register' ) );
        add_action( 'enqueue_block_assets', array( $this->markdown_block, 'enqueue_styles' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_scripts' ) );
        add_action( 'rest_api_init', array( $this->rest_endpoints, 'register_routes' ) );
        add_action( 'wp_abilities_api_categories_init', array( $this->abilities, 'register_category' ) );
        add_action( 'wp_abilities_api_init', array( $this->abilities, 'register' ) );
        add_action( 'wp_abilities_api_init', array( $this->external_abilities, 'register' ) );
        add_action( 'admin_menu', array( $this->settings, 'register_menu' ) );
        add_action( 'admin_init', array( $this->settings, 'register_settings' ) );
        add_action( 'admin_post_wpam_migrate_content', array( $this->settings, 'handle_migrate_content' ) );
        add_action( 'admin_post_wpam_migrate_keywords_to_topic', array( $this->settings, 'handle_migrate_keywords_to_topic' ) );
        add_filter( 'manage_users_columns', array( $this->settings, 'add_memories_column' ) );
        add_filter( 'manage_users_custom_column', array( $this->settings, 'render_memories_column' ), 10, 3 );
        add_filter( 'wp_editor_settings',  array( $this->editor, 'disable_tinymce'), 10, 2);
        add_action( 'add_meta_boxes', array( $this->editor, 'register_stats_meta_box' ) );
    }

    /**
     * Enqueue block editor scripts (block bindings source registration).
     */
    public function enqueue_editor_scripts(): void {
        $asset_file = WPAM_PLUGIN_DIR . 'build/block-bindings.asset.php';

        if ( ! file_exists( $asset_file ) ) {
            return;
        }

        $asset = require $asset_file;

        wp_enqueue_script(
            'wpam-block-bindings',
            WPAM_PLUGIN_URL . 'build/block-bindings.js',
            $asset['dependencies'],
            $asset['version'],
            true
        );
    }

    /**
     * Singleton accessor.
     *
     * @return Core
     */
    public static function get_instance(): Core {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
