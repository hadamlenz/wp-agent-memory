<?php
/**
 * Markdown rendering for memory_entry posts via the_content filter.
 *
 * @package WPAM
 */

namespace WPAM\WordPress;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\MarkdownConverter;
use Spatie\CommonMarkHighlighter\FencedCodeRenderer;
use Spatie\CommonMarkHighlighter\IndentedCodeRenderer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Markdown_Block {

    /**
     * Hook the content filter. No block type is registered — content is plain markdown in post_content.
     */
    public function register(): void {
        // Priority 1 — run before wpautop (10) and wptexturize (10).
        add_filter( 'the_content', array( $this, 'render_content' ), 1 );
    }

    /**
     * Convert raw markdown to HTML for memory_entry posts only.
     * Removes wpautop and wptexturize so they don't mangle markdown before CommonMark runs.
     *
     * @param string $content Raw post content.
     * @return string
     */
    public function render_content( string $content ): string {
        if ( get_post_type() !== 'memory_entry' ) {
            return $content;
        }

        remove_filter( 'the_content', 'wpautop' );
        remove_filter( 'the_content', 'wptexturize' );

        return $this->convert( $content );
    }

    /**
     * Run markdown through CommonMark with syntax highlighting.
     *
     * @param string $markdown Raw markdown string.
     * @return string Rendered HTML.
     */
    public function convert( string $markdown ): string {
        $markdown = trim( $markdown );

        if ( '' === $markdown ) {
            return '';
        }

        $environment = new Environment(
            array(
                'html_input'         => 'escape',
                'allow_unsafe_links' => false,
            )
        );
        $environment->addExtension( new CommonMarkCoreExtension() );
        $environment->addRenderer( FencedCode::class, new FencedCodeRenderer() );
        $environment->addRenderer( IndentedCode::class, new IndentedCodeRenderer() );

        return ( new MarkdownConverter( $environment ) )->convert( $markdown )->getContent();
    }

    /**
     * Enqueue the highlight.php GitHub CSS theme for frontend rendering.
     */
    public function enqueue_styles(): void {
        $css_path = WPAM_PLUGIN_DIR . 'vendor/scrivo/highlight.php/styles/github.css';

        if ( ! file_exists( $css_path ) ) {
            return;
        }

        wp_register_style(
            'wpam-highlight-github',
            WPAM_PLUGIN_URL . 'vendor/scrivo/highlight.php/styles/github.css',
            array(),
            WPAM_VERSION
        );
        wp_enqueue_style( 'wpam-highlight-github' );
        wp_add_inline_style(
            'wpam-highlight-github',
            '
            pre {
                background-color: #f6f8fa;
                border: 1px solid #d0d7de;
                border-radius: 6px;
                padding: 16px;
                overflow-x: auto;
                font-size: 85%;
                line-height: 1.45;
            }
            pre code.hljs {
                background: transparent;
                padding: 0;
            }
        '
        );
    }
}
