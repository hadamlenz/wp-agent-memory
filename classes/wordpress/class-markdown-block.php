<?php
/**
 * Server-side render for the wpam/markdown block.
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
     * Register the wpam/markdown dynamic block.
     */
    public function register(): void {
        register_block_type(
            dirname( __DIR__, 2 ) . '/blocks/markdown',
            array(
                'render_callback' => array( $this, 'render' ),
            )
        );
    }

    /**
     * Render Markdown inner content as syntax-highlighted HTML.
     *
     * @param array<string, mixed> $attributes Block attributes (includes 'content' when called via ServerSideRender).
     * @param string               $content    Raw Markdown stored between block comment tags (empty during editor preview).
     *
     * @return string
     */
    public function render( array $attributes, string $content ): string {
        // $content is populated when rendering saved post content; $attributes['content']
        // is used when ServerSideRender calls the REST block-renderer from the editor.
        $markdown = trim( $content !== '' ? $content : ( $attributes['content'] ?? '' ) );

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
     * Enqueue the highlight.php GitHub CSS theme for frontend and block editor.
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
