<?php
/**
 * Core plugin bootstrap.
 *
 * @package WPAM
 */

namespace WPAM\WordPress;

use WPAM\WordPress\External\External_Sources_Service;
use WPAM\WordPress\Memory\Search_Service;
use WPAM\WordPress\Memory\Writer_Service;
use WPAM\WordPress\Markdown_Block;

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
    /** @var MCP_Integration */
    private MCP_Integration $mcp_integration;
    /** @var External_Sources_Service */
    private External_Sources_Service $external_sources_service;
    /** @var External_Abilities */
    private External_Abilities $external_abilities;

    /**
     * Build service graph and register hooks.
     */
    private function __construct() {
        $this->content_types   = new Content_Types();
        $this->search_service  = new Search_Service();
        $this->writer_service  = new Writer_Service( $this->search_service );
        $this->rest_endpoints  = new Rest_Endpoints( $this->search_service, $this->writer_service );
        $this->abilities       = new Abilities( $this->search_service, $this->writer_service );
        $this->markdown_block  = new Markdown_Block();
        $this->mcp_integration          = new MCP_Integration();
        $this->external_sources_service = new External_Sources_Service();
        $this->external_abilities       = new External_Abilities( $this->external_sources_service );

        add_action( 'init', array( $this->content_types, 'register' ) );
        add_action( 'init', array( $this->markdown_block, 'register' ) );
        add_action( 'enqueue_block_assets', array( $this->markdown_block, 'enqueue_styles' ) );
        add_action( 'rest_api_init', array( $this->rest_endpoints, 'register_routes' ) );
        add_action( 'wp_abilities_api_categories_init', array( $this->abilities, 'register_category' ) );
        add_action( 'wp_abilities_api_init', array( $this->abilities, 'register' ) );
        add_action( 'wp_abilities_api_init', array( $this->external_abilities, 'register' ) );
        add_action( 'init', array( $this->mcp_integration, 'register' ) );
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
