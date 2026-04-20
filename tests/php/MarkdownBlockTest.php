<?php

use PHPUnit\Framework\TestCase;
use League\CommonMark\CommonMarkConverter;
use WPAM\WordPress\Memory\Response_Shaper;
use WPAM\WordPress\Markdown_Block;

/**
 * Unit tests for the wpam/markdown block: content extraction and rendering.
 */
final class MarkdownBlockTest extends TestCase {
    /**
     * wpam/markdown block wrapper is stripped and raw Markdown is returned.
     */
    public function test_extract_content_unwraps_markdown_block(): void {
        $post_content = "<!-- wp:wpam/markdown -->\n## Hello\n\nParagraph text.\n<!-- /wp:wpam/markdown -->";

        $result = Response_Shaper::extract_content( $post_content );

        $this->assertSame( "## Hello\n\nParagraph text.", $result );
    }

    /**
     * Non-wpam block markup is left unchanged.
     */
    public function test_extract_content_leaves_other_blocks_intact(): void {
        $post_content = "<!-- wp:some-block {\"key\":\"val\"} -->\n<div>example</div>\n<!-- /wp:some-block -->";

        $result = Response_Shaper::extract_content( $post_content );

        $this->assertSame( $post_content, $result );
    }

    /**
     * Mixed content: markdown blocks unwrapped, other blocks preserved.
     */
    public function test_extract_content_handles_mixed_content(): void {
        $post_content = "<!-- wp:wpam/markdown -->\nIntro text.\n<!-- /wp:wpam/markdown -->\n\n<!-- wp:custom/block -->\n<div>example</div>\n<!-- /wp:custom/block -->";

        $result = Response_Shaper::extract_content( $post_content );

        $this->assertStringContainsString( 'Intro text.', $result );
        $this->assertStringContainsString( '<!-- wp:custom/block -->', $result );
        $this->assertStringNotContainsString( '<!-- wp:wpam/markdown -->', $result );
    }

    /**
     * Plain text with no block markup passes through unchanged.
     */
    public function test_extract_content_passthrough_on_plain_text(): void {
        $this->assertSame( 'just plain text', Response_Shaper::extract_content( 'just plain text' ) );
    }

    /**
     * Markdown heading renders as an <h2> element.
     */
    public function test_render_converts_markdown_to_html(): void {
        $block = new Markdown_Block();
        $html  = $block->render( array(), '## Title' );

        $this->assertStringContainsString( '<h2>', $html );
        $this->assertStringContainsString( 'Title', $html );
    }

    /**
     * Empty content returns an empty string without errors.
     */
    public function test_render_empty_string_returns_empty(): void {
        $block = new Markdown_Block();

        $this->assertSame( '', $block->render( array(), '' ) );
        $this->assertSame( '', $block->render( array(), '   ' ) );
    }

    /**
     * Inline Markdown formatting is preserved in the rendered HTML.
     */
    public function test_render_preserves_inline_formatting(): void {
        $block = new Markdown_Block();
        $html  = $block->render( array(), 'This is **bold** text.' );

        $this->assertStringContainsString( '<strong>bold</strong>', $html );
    }
}
