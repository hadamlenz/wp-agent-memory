<?php

use PHPUnit\Framework\TestCase;
use WPAM\External\External_Sources_Service;

/**
 * Unit tests for External_Sources_Service URL resolution and fetch matching behavior.
 */
final class ExternalSourcesServiceTest extends TestCase {
    private External_Sources_Service $service;

    protected function setUp(): void {
        wpam_test_reset_state();

        $reflection    = new ReflectionClass( External_Sources_Service::class );
        $this->service = $reflection->newInstanceWithoutConstructor();
    }

    /**
     * @dataProvider supportedDeveloperMappingProvider
     */
    public function test_fetch_wp_doc_maps_supported_developer_sections( string $request_url, string $post_type ): void {
        $slug    = basename( trim( parse_url( $request_url, PHP_URL_PATH ) ?? '', '/' ) );
        $api_url = add_query_arg(
            array(
                'slug'    => $slug,
                '_fields' => 'title,content,link',
            ),
            'https://developer.wordpress.org/wp-json/wp/v2/' . $post_type
        );

        wpam_test_set_http_json_response(
            $api_url,
            array(
                array(
                    'title'   => array( 'rendered' => 'Doc Title' ),
                    'content' => array( 'rendered' => '<p>Doc content</p>' ),
                    'link'    => $request_url,
                ),
            )
        );

        $result = $this->service->fetch_wp_doc( array( 'url' => $request_url ) );

        $this->assertArrayNotHasKey( 'error', $result );
        $this->assertSame( 'Doc Title', $result['title'] );
        $this->assertSame( 'Doc content', $result['content'] );
        $this->assertSame( $request_url, $result['url'] );

        $calls = $GLOBALS['wpam_test_http_calls'] ?? array();
        $this->assertSame( array( $api_url ), $calls );
    }

    /**
     * @dataProvider existingSupportedMappingProvider
     */
    public function test_fetch_wp_doc_keeps_existing_supported_paths( string $request_url, string $base, string $post_type ): void {
        $slug    = basename( trim( parse_url( $request_url, PHP_URL_PATH ) ?? '', '/' ) );
        $api_url = add_query_arg(
            array(
                'slug'    => $slug,
                '_fields' => 'title,content,link',
            ),
            $base . $post_type
        );

        wpam_test_set_http_json_response(
            $api_url,
            array(
                array(
                    'title'   => array( 'rendered' => 'Legacy Title' ),
                    'content' => array( 'rendered' => '<p>Legacy content</p>' ),
                    'link'    => $request_url,
                ),
            )
        );

        $result = $this->service->fetch_wp_doc( array( 'url' => $request_url ) );

        $this->assertArrayNotHasKey( 'error', $result );
        $this->assertSame( 'Legacy Title', $result['title'] );
        $this->assertSame( 'Legacy content', $result['content'] );
        $this->assertSame( $request_url, $result['url'] );
    }

    public function test_fetch_wp_doc_returns_unsupported_error_for_unknown_developer_section(): void {
        $result = $this->service->fetch_wp_doc(
            array( 'url' => 'https://developer.wordpress.org/unknown-section/example-doc/' )
        );

        $this->assertSame(
            'URL is not a supported WordPress.org documentation page. Supported hosts: developer.wordpress.org, wordpress.org/documentation, wordpress.org/news.',
            $result['error'] ?? ''
        );

        $calls = $GLOBALS['wpam_test_http_calls'] ?? array();
        $this->assertSame( array(), $calls );
    }

    public function test_fetch_wp_doc_returns_not_found_when_slug_matches_different_path(): void {
        $request_url = 'https://developer.wordpress.org/apis/security/nonces/';
        $api_url     = add_query_arg(
            array(
                'slug'    => 'nonces',
                '_fields' => 'title,content,link',
            ),
            'https://developer.wordpress.org/wp-json/wp/v2/apis-handbook'
        );

        wpam_test_set_http_json_response(
            $api_url,
            array(
                array(
                    'title'   => array( 'rendered' => 'Nonces' ),
                    'content' => array( 'rendered' => '<p>Moved.</p>' ),
                    'link'    => 'https://developer.wordpress.org/plugins/security/nonces/',
                ),
            )
        );

        $result = $this->service->fetch_wp_doc( array( 'url' => $request_url ) );

        $this->assertSame( 'Document not found.', $result['error'] ?? '' );
    }

    public function test_fetch_wp_doc_ignores_query_and_fragment_in_exact_match(): void {
        $request_url = 'https://developer.wordpress.org/block-editor/reference-guides/filters/?utm=1#hash';
        $api_url     = add_query_arg(
            array(
                'slug'    => 'filters',
                '_fields' => 'title,content,link',
            ),
            'https://developer.wordpress.org/wp-json/wp/v2/blocks-handbook'
        );

        wpam_test_set_http_json_response(
            $api_url,
            array(
                array(
                    'title'   => array( 'rendered' => 'Filters' ),
                    'content' => array( 'rendered' => '<p>Block filters.</p>' ),
                    'link'    => 'https://developer.wordpress.org/block-editor/reference-guides/filters/',
                ),
            )
        );

        $result = $this->service->fetch_wp_doc( array( 'url' => $request_url ) );

        $this->assertArrayNotHasKey( 'error', $result );
        $this->assertSame( 'Filters', $result['title'] );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function supportedDeveloperMappingProvider(): array {
        return array(
            'plugins'                 => array( 'https://developer.wordpress.org/plugins/security/nonces/', 'plugin-handbook' ),
            'themes'                  => array( 'https://developer.wordpress.org/themes/core-concepts/main-stylesheet/', 'theme-handbook' ),
            'block-editor'            => array( 'https://developer.wordpress.org/block-editor/reference-guides/filters/', 'blocks-handbook' ),
            'rest-api'                => array( 'https://developer.wordpress.org/rest-api/using-the-rest-api/global-parameters/', 'rest-api-handbook' ),
            'apis'                    => array( 'https://developer.wordpress.org/apis/security/nonces/', 'apis-handbook' ),
            'advanced-administration' => array( 'https://developer.wordpress.org/advanced-administration/security/application-passwords/', 'adv-admin-handbook' ),
            'coding-standards'        => array( 'https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/', 'wpcs-handbook' ),
            'secure-custom-fields'    => array( 'https://developer.wordpress.org/secure-custom-fields/tutorials/', 'scf-handbook' ),
            'reference-functions'     => array( 'https://developer.wordpress.org/reference/functions/register_block_type/', 'wp-parser-function' ),
            'reference-hooks'         => array( 'https://developer.wordpress.org/reference/hooks/init/', 'wp-parser-hook' ),
            'reference-classes'       => array( 'https://developer.wordpress.org/reference/classes/wp_query/', 'wp-parser-class' ),
            'reference-methods'       => array( 'https://developer.wordpress.org/reference/methods/wp_query/get_posts/', 'wp-parser-method' ),
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function existingSupportedMappingProvider(): array {
        return array(
            'plugins'             => array(
                'https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/',
                'https://developer.wordpress.org/wp-json/wp/v2/',
                'plugin-handbook',
            ),
            'themes'              => array(
                'https://developer.wordpress.org/themes/advanced-topics/security/',
                'https://developer.wordpress.org/wp-json/wp/v2/',
                'theme-handbook',
            ),
            'reference-functions' => array(
                'https://developer.wordpress.org/reference/functions/wp_verify_nonce/',
                'https://developer.wordpress.org/wp-json/wp/v2/',
                'wp-parser-function',
            ),
            'reference-hooks'     => array(
                'https://developer.wordpress.org/reference/hooks/nonce_life/',
                'https://developer.wordpress.org/wp-json/wp/v2/',
                'wp-parser-hook',
            ),
            'reference-classes'   => array(
                'https://developer.wordpress.org/reference/classes/wp_customizer_manager/',
                'https://developer.wordpress.org/wp-json/wp/v2/',
                'wp-parser-class',
            ),
            'reference-methods'   => array(
                'https://developer.wordpress.org/reference/methods/wp_customize_manager/get_nonces/',
                'https://developer.wordpress.org/wp-json/wp/v2/',
                'wp-parser-method',
            ),
            'user-docs'           => array(
                'https://wordpress.org/documentation/article/wordpress-editor/',
                'https://wordpress.org/documentation/wp-json/wp/v2/',
                'helphub_article',
            ),
            'news'                => array(
                'https://wordpress.org/news/2025/11/wordpress-7-0/',
                'https://wordpress.org/news/wp-json/wp/v2/',
                'posts',
            ),
        );
    }
}
