<?php

use PHPUnit\Framework\TestCase;
use WPAM\WordPress\Memory\Search_Service;
use WPAM\WordPress\Memory\Writer_Service;

/**
 * Unit tests for Writer_Service validation and sanitization logic.
 */
final class WriterServiceTest extends TestCase {
    private Writer_Service $service;

    /**
     * Initialize the service under test for each test case.
     */
    protected function setUp(): void {
        $this->service = new Writer_Service( new Search_Service() );
    }

    /**
     * create() must reject input missing the title field.
     */
    public function test_create_returns_error_when_title_missing(): void {
        $result = $this->service->create( array( 'summary' => 'A summary', 'topic' => array( 'general' ) ) );

        $this->assertArrayHasKey( 'error', $result );
        $this->assertStringContainsString( 'title', $result['error'] );
    }

    /**
     * create() must reject input missing the summary field.
     */
    public function test_create_returns_error_when_summary_missing(): void {
        $result = $this->service->create( array( 'title' => 'A title', 'topic' => array( 'general' ) ) );

        $this->assertArrayHasKey( 'error', $result );
        $this->assertStringContainsString( 'summary', $result['error'] );
    }

    /**
     * create() must reject input where topic is an empty array.
     */
    public function test_create_returns_error_when_topic_empty_array(): void {
        $result = $this->service->create( array( 'title' => 'A title', 'summary' => 'A summary', 'topic' => array() ) );

        $this->assertArrayHasKey( 'error', $result );
        $this->assertStringContainsString( 'topic', $result['error'] );
    }

    /**
     * create() must reject relation_role when the cardinality is greater than one.
     */
    public function test_create_rejects_multiple_relation_roles(): void {
        $result = $this->service->create(
            array(
                'title'         => 'A title',
                'summary'       => 'A summary',
                'topic'         => array( 'general' ),
                'relation_role' => array( 'canonical', 'companion' ),
            )
        );

        $this->assertArrayHasKey( 'error', $result );
        $this->assertStringContainsString( 'relation_role', $result['error'] );
    }

    /**
     * create() must reject unknown relation_role values.
     */
    public function test_create_rejects_unknown_relation_role(): void {
        $result = $this->service->create(
            array(
                'title'         => 'A title',
                'summary'       => 'A summary',
                'topic'         => array( 'general' ),
                'relation_role' => array( 'not-real' ),
            )
        );

        $this->assertArrayHasKey( 'error', $result );
        $this->assertStringContainsString( 'unsupported', $result['error'] );
    }

    /**
     * update() must return an error for a post ID that does not exist.
     * (get_post() is stubbed to return null in bootstrap.php.)
     */
    public function test_update_returns_error_for_nonexistent_id(): void {
        $result = $this->service->update( 99999, array( 'title' => 'New title' ) );

        $this->assertArrayHasKey( 'error', $result );
        $this->assertStringContainsString( 'not found', $result['error'] );
    }

    /**
     * sanitize_keywords joins an array of strings into a comma-separated string.
     */
    public function test_sanitize_keywords_joins_array_to_csv(): void {
        $this->assertSame( 'foo,bar,baz', $this->service->sanitize_keywords( array( 'foo', 'bar', 'baz' ) ) );
    }

    /**
     * sanitize_keywords strips surrounding whitespace from each keyword.
     */
    public function test_sanitize_keywords_trims_whitespace(): void {
        $this->assertSame( 'alpha,beta', $this->service->sanitize_keywords( array( '  alpha  ', '  beta' ) ) );
    }
}
