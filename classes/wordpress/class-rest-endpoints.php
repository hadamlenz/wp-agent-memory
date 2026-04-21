<?php
/**
 * REST API routes for memory retrieval.
 *
 * @package WPAM
 */

namespace WPAM\WordPress;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WPAM\WordPress\Memory\Search_Service;
use WPAM\WordPress\Memory\Writer_Service;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Rest_Endpoints {
    /** @var Search_Service */
    private Search_Service $search_service;

    /** @var Writer_Service */
    private Writer_Service $writer_service;

    /**
     * @param Search_Service $search_service
     * @param Writer_Service $writer_service
     */
    public function __construct( Search_Service $search_service, Writer_Service $writer_service ) {
        $this->search_service = $search_service;
        $this->writer_service = $writer_service;
    }

    /**
     * Register plugin REST endpoints under /wp-json/agent-memory/v1.
     */
    public function register_routes(): void {
        register_rest_route(
            'agent-memory/v1',
            '/search',
            array(
                array(
                    'methods'             => 'GET',
                    'permission_callback' => array( $this, 'can_read' ),
                    'callback'            => array( $this, 'search' ),
                    'args'                => $this->search_args(),
                ),
            )
        );

        register_rest_route(
            'agent-memory/v1',
            '/recent',
            array(
                array(
                    'methods'             => 'GET',
                    'permission_callback' => array( $this, 'can_read' ),
                    'callback'            => array( $this, 'recent' ),
                    'args'                => array(
                        'limit' => array(
                            'type'              => 'integer',
                            'default'           => 10,
                            'sanitize_callback' => 'absint',
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            'agent-memory/v1',
            '/entry',
            array(
                array(
                    'methods'             => 'POST',
                    'permission_callback' => array( $this, 'can_write' ),
                    'callback'            => array( $this, 'create_entry' ),
                ),
            )
        );

        register_rest_route(
            'agent-memory/v1',
            '/entry/(?P<id>\d+)',
            array(
                array(
                    'methods'             => 'GET',
                    'permission_callback' => array( $this, 'can_read' ),
                    'callback'            => array( $this, 'entry' ),
                ),
                array(
                    'methods'             => 'PATCH',
                    'permission_callback' => array( $this, 'can_write' ),
                    'callback'            => array( $this, 'update_entry' ),
                ),
                array(
                    'methods'             => 'DELETE',
                    'permission_callback' => array( $this, 'can_write' ),
                    'callback'            => array( $this, 'delete_entry' ),
                ),
            )
        );

        register_rest_route(
            'agent-memory/v1',
            '/entry/(?P<id>\d+)/useful',
            array(
                array(
                    'methods'             => 'POST',
                    'permission_callback' => array( $this, 'can_read' ),
                    'callback'            => array( $this, 'mark_useful' ),
                    'args'                => array(
                        'agent'   => array( 'type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
                        'context' => array( 'type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_text_field' ),
                    ),
                ),
            )
        );
    }

    /**
     * Restrict read endpoints to authenticated users.
     *
     * @return bool
     */
    public function can_read(): bool {
        return current_user_can( 'read' );
    }

    /**
     * Restrict write endpoints to editors and above.
     *
     * @return bool
     */
    public function can_write(): bool {
        return current_user_can( 'edit_pages' );
    }

    /**
     * GET /search handler.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function search( WP_REST_Request $request ): WP_REST_Response {
        $params  = $request->get_params();
        $results = $this->search_service->search( $params );

        return rest_ensure_response(
            array(
                'query'   => (string) ( $params['query'] ?? '' ),
                'limit'   => isset( $params['limit'] ) ? (int) $params['limit'] : 10,
                'count'   => count( $results ),
                'results' => $results,
            )
        );
    }

    /**
     * GET /entry/<id> handler.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response|WP_Error
     */
    public function entry( WP_REST_Request $request ) {
        $id    = (int) $request->get_param( 'id' );
        $entry = $this->search_service->get_entry( $id );

        if ( null === $entry ) {
            return new WP_Error( 'agent_memory_not_found', __( 'Memory entry not found.', 'wp-agent-memory' ), array( 'status' => 404 ) );
        }

        return rest_ensure_response( $entry );
    }

    /**
     * GET /recent handler.
     *
     * @param WP_REST_Request $request Request object.
     *
     * @return WP_REST_Response
     */
    public function recent( WP_REST_Request $request ): WP_REST_Response {
        $limit   = (int) $request->get_param( 'limit' );
        $results = $this->search_service->recent( $limit );

        return rest_ensure_response(
            array(
                'count'   => count( $results ),
                'results' => $results,
            )
        );
    }

    /**
     * POST /entry handler.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function create_entry( WP_REST_Request $request ) {
        $input  = $request->get_json_params() ?? array();
        $result = $this->writer_service->create( $input );

        if ( isset( $result['error'] ) ) {
            return new WP_Error( 'agent_memory_create_failed', $result['error'], array( 'status' => 400 ) );
        }

        return new WP_REST_Response( $result, 201 );
    }

    /**
     * PATCH /entry/<id> handler.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function update_entry( WP_REST_Request $request ) {
        $id     = (int) $request->get_param( 'id' );
        $input  = $request->get_json_params() ?? array();
        $result = $this->writer_service->update( $id, $input );

        if ( isset( $result['error'] ) ) {
            $status = 'Memory entry not found.' === $result['error'] ? 404 : 400;

            return new WP_Error( 'agent_memory_update_failed', $result['error'], array( 'status' => $status ) );
        }

        return rest_ensure_response( $result );
    }

    /**
     * DELETE /entry/<id> handler.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function delete_entry( WP_REST_Request $request ) {
        $id     = (int) $request->get_param( 'id' );
        $result = $this->writer_service->delete( $id );

        if ( isset( $result['error'] ) ) {
            return new WP_Error( 'agent_memory_delete_failed', $result['error'], array( 'status' => 404 ) );
        }

        return rest_ensure_response( $result );
    }

    /**
     * POST /entry/<id>/useful handler.
     *
     * @param WP_REST_Request $request
     *
     * @return WP_REST_Response|WP_Error
     */
    public function mark_useful( WP_REST_Request $request ) {
        $id   = (int) $request->get_param( 'id' );
        $post = $id > 0 ? get_post( $id ) : null;

        if ( ! $post instanceof \WP_Post || 'memory_entry' !== $post->post_type || 'publish' !== $post->post_status ) {
            return new WP_Error( 'agent_memory_not_found', __( 'Memory entry not found.', 'wp-agent-memory' ), array( 'status' => 404 ) );
        }

        $useful = (int) get_post_meta( $id, 'useful_count', true ) + 1;
        $usage  = (int) get_post_meta( $id, 'usage_count', true ) + 1;
        $now    = gmdate( 'Y-m-d H:i:s' );

        update_post_meta( $id, 'useful_count', $useful );
        update_post_meta( $id, 'usage_count', $usage );
        update_post_meta( $id, 'last_used_gmt', $now );

        return rest_ensure_response(
            array(
                'marked'       => true,
                'id'           => $id,
                'useful_count' => $useful,
            )
        );
    }

    /**
     * REST argument schema for the /search endpoint.
     *
     * @return array<string, array<string, mixed>>
     */
    private function search_args(): array {
        return array(
            'query'       => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
            'repo'        => array(
                'type' => 'array',
            ),
            'package'     => array(
                'type' => 'array',
            ),
            'symbol_type' => array(
                'type' => 'array',
            ),
            'topic'       => array(
                'type' => 'array',
            ),
            'limit'       => array(
                'type'              => 'integer',
                'default'           => 10,
                'sanitize_callback' => static function ( $value ): int {
                    $limit = absint( $value );

                    return max( 1, min( 50, $limit ) );
                },
            ),
        );
    }
}
