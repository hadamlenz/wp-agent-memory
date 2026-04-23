<?php

use PHPUnit\Framework\TestCase;
use WPAM\WordPress\Memory\Response_Shaper;

/**
 * Unit tests for snippet extraction and response shape contracts.
 */
final class ResponseShaperTest extends TestCase {
    /**
     * Snippet generator should include the matched query token when available.
     */
    public function test_build_snippet_centers_on_query(): void {
        $text    = 'The editor.BlockEdit filter can wrap the block edit component for custom behavior.';
        $snippet = Response_Shaper::build_snippet( $text, 'editor.blockedit', 20 );

        $this->assertStringContainsString( 'editor.BlockEdit', $snippet );
    }

    /**
     * Search response output must expose stable machine-facing fields.
     */
    public function test_search_result_shape_contains_required_fields(): void {
        $candidate = array(
            'id'          => 12,
            'title'       => 'Example',
            'symbol_name' => 'example.symbol',
            'symbol_type' => array( 'hook' ),
            'repo'        => array( 'gutenberg' ),
            'package'     => array( 'wp-data' ),
            'excerpt'     => 'Summary text',
            'content'     => '',
            'source_url'  => 'https://example.com',
            'source_path' => 'packages/example',
            'permalink'   => 'https://site.local/example',
        );

        $result = Response_Shaper::search_result( $candidate, 99.5, 'example.symbol' );

        $this->assertArrayHasKey( 'id', $result );
        $this->assertArrayHasKey( 'title', $result );
        $this->assertArrayHasKey( 'symbol_name', $result );
        $this->assertArrayHasKey( 'symbol_type', $result );
        $this->assertArrayHasKey( 'repo', $result );
        $this->assertArrayHasKey( 'package', $result );
        $this->assertArrayHasKey( 'summary', $result );
        $this->assertArrayHasKey( 'snippet', $result );
        $this->assertArrayHasKey( 'source_url', $result );
        $this->assertArrayHasKey( 'source_path', $result );
        $this->assertArrayHasKey( 'score', $result );
        $this->assertArrayHasKey( 'permalink', $result );
    }

    /**
     * Summary is sourced from the post excerpt field.
     */
    public function test_search_result_uses_excerpt_as_summary(): void {
        $candidate = array(
            'excerpt'    => 'Excerpt summary',
            'content'    => '<p>Editor content fallback</p>',
            'source_url' => '',
            'source_path'=> '',
            'permalink'  => '',
        );

        $result = Response_Shaper::search_result( $candidate, 0.0, '' );

        $this->assertSame( 'Excerpt summary', $result['summary'] );
    }

    /**
     * Summary falls back to stripped editor content when excerpt is empty.
     */
    public function test_search_result_summary_falls_back_to_content(): void {
        $candidate = array(
            'excerpt'    => '',
            'content'    => '<p>Editor content fallback</p>',
            'source_url' => '',
            'source_path'=> '',
            'permalink'  => '',
        );

        $result = Response_Shaper::search_result( $candidate, 0.0, '' );

        $this->assertSame( 'Editor content fallback', $result['summary'] );
    }

    /**
     * Full entry payload includes relation taxonomy fields.
     */
    public function test_entry_result_includes_relation_fields(): void {
        $candidate = array(
            'id'             => 98,
            'title'          => 'Companion Entry',
            'topic'          => array( 'wordpress' ),
            'relation_role'  => array( 'companion' ),
            'relation_group' => array( 'g-80' ),
            'excerpt'        => 'Summary text',
            'content'        => 'Content',
            'permalink'      => 'https://example.test/entry/98',
        );

        $result = Response_Shaper::entry_result( $candidate );

        $this->assertSame( array( 'companion' ), $result['relation_role'] );
        $this->assertSame( array( 'g-80' ), $result['relation_group'] );
    }

    /**
     * extract_content() must decode content from the JSON-attr block format.
     * The JSON is written by serialize_block_attributes() which escapes '>' as '>'.
     */
    public function test_extract_content_handles_json_attr_format(): void {
        $block = '<!-- wp:wpam/markdown {"content":"## Hello\n\nCode: `&&` and `>` chars"} -->' . "\n" .
                 '<!-- /wp:wpam/markdown -->';

        $this->assertSame(
            "## Hello\n\nCode: `&&` and `>` chars",
            Response_Shaper::extract_content( $block )
        );
    }

    /**
     * extract_content() must decode content from the self-closing block format.
     * WordPress writes <!-- wp:name {...} /--> (void block) when there is no inner content.
     */
    public function test_extract_content_handles_self_closing_format(): void {
        $block = '<!-- wp:wpam/markdown {"content":"## Title\n\nSome **content**."} /-->';

        $this->assertSame(
            "## Title\n\nSome **content**.",
            Response_Shaper::extract_content( $block )
        );
    }

    /**
     * extract_content() must handle the old raw-markdown-between-delimiters format.
     */
    public function test_extract_content_handles_old_raw_format(): void {
        $block = "<!-- wp:wpam/markdown -->\nSome **markdown**\n<!-- /wp:wpam/markdown -->";

        $this->assertSame( 'Some **markdown**', Response_Shaper::extract_content( $block ) );
    }

    /**
     * extract_content() must pass plain markdown through unchanged.
     */
    public function test_extract_content_passes_through_plain_markdown(): void {
        $plain = "## Hello\n\nJust plain markdown, no block wrappers.";

        $this->assertSame( $plain, Response_Shaper::extract_content( $plain ) );
    }
}
