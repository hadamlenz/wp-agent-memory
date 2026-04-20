<?php
/**
 * Shapes search and entry responses for REST/abilities.
 *
 * @package WPAM
 */

namespace WPAM\WordPress\Memory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Response_Shaper {
    /**
     * Build a short snippet around query match when possible.
     *
     * @param string $text   Source text for snippet extraction.
     * @param string $query  Query term used to center snippet.
     * @param int    $radius Context radius on each side.
     *
     * @return string
     */
    public static function build_snippet( string $text, string $query, int $radius = 90 ): string {
        $text = trim( wp_strip_all_tags( $text ) );

        if ( '' === $text ) {
            return '';
        }

        $query = Search_Service::normalize_query( $query );

        if ( '' === $query ) {
            return mb_substr( $text, 0, $radius * 2 );
        }

        $text_lc = mb_strtolower( $text );
        $pos     = mb_strpos( $text_lc, $query );

        if ( false === $pos ) {
            return mb_substr( $text, 0, $radius * 2 );
        }

        $start = max( 0, (int) $pos - $radius );
        $len   = $radius * 2;
        $slice = mb_substr( $text, $start, $len );

        if ( $start > 0 ) {
            $slice = '…' . $slice;
        }

        if ( $start + $len < mb_strlen( $text ) ) {
            $slice .= '…';
        }

        return $slice;
    }

    /**
     * Shape a compact search/list result payload consumed by REST and abilities.
     *
     * @param array<string, mixed> $candidate Normalized candidate fields.
     * @param float                $score     Computed ranking score.
     * @param string               $query     Raw query used for snippet generation.
     *
     * @return array<string, mixed>
     */
    public static function search_result( array $candidate, float $score, string $query ): array {
        $excerpt = (string) ( $candidate['excerpt'] ?? '' );
        $content = (string) ( $candidate['content'] ?? '' );

        $summary        = '' !== $excerpt ? $excerpt : trim( wp_strip_all_tags( $content ) );
        $snippet_source = $summary ?: $content;

        return array(
            'id'          => (int) ( $candidate['id'] ?? 0 ),
            'title'       => (string) ( $candidate['title'] ?? '' ),
            'symbol_name' => (string) ( $candidate['symbol_name'] ?? '' ),
            'symbol_type' => (array) ( $candidate['symbol_type'] ?? array() ),
            'repo'        => (array) ( $candidate['repo'] ?? array() ),
            'package'     => (array) ( $candidate['package'] ?? array() ),
            'summary'     => $summary,
            'snippet'     => self::build_snippet( $snippet_source, $query ),
            'source_url'  => (string) ( $candidate['source_url'] ?? '' ),
            'source_path' => (string) ( $candidate['source_path'] ?? '' ),
            'author'      => get_the_author_meta( 'display_name', (int) ( $candidate['post_author'] ?? 0 ) ),
            'score'       => round( $score, 3 ),
            'permalink'   => (string) ( $candidate['permalink'] ?? '' ),
        );
    }

    /**
     * Shape the full entry payload for /entry and get-entry ability.
     *
     * @param array<string, mixed> $candidate Normalized candidate fields.
     *
     * @return array<string, mixed>
     */
    public static function entry_result( array $candidate ): array {
        return array(
            'id'          => (int) ( $candidate['id'] ?? 0 ),
            'title'       => (string) ( $candidate['title'] ?? '' ),
            'symbol_name' => (string) ( $candidate['symbol_name'] ?? '' ),
            'symbol_type' => (array) ( $candidate['symbol_type'] ?? array() ),
            'repo'        => (array) ( $candidate['repo'] ?? array() ),
            'package'     => (array) ( $candidate['package'] ?? array() ),
            'topic'       => (array) ( $candidate['topic'] ?? array() ),
            'summary'     => (string) ( $candidate['excerpt'] ?? '' ),
            'keywords'    => (array) ( $candidate['keywords'] ?? array() ),
            'source_url'  => (string) ( $candidate['source_url'] ?? '' ),
            'source_path' => (string) ( $candidate['source_path'] ?? '' ),
            'source_ref'  => (string) ( $candidate['source_ref'] ?? '' ),
            'rank_bias'   => (float) ( $candidate['rank_bias'] ?? 0 ),
            'content'     => self::extract_content( (string) ( $candidate['content'] ?? '' ) ),
            'author'      => get_the_author_meta( 'display_name', (int) ( $candidate['post_author'] ?? 0 ) ),
            'permalink'   => (string) ( $candidate['permalink'] ?? '' ),
            'modified_gmt'=> (string) ( $candidate['modified_gmt'] ?? '' ),
        );
    }

    /**
     * Strip wpam/markdown block wrappers and return the raw Markdown they contain.
     * All other block markup is left intact so agents can learn from embedded examples.
     *
     * @param string $post_content Raw post_content from the database.
     *
     * @return string
     */
    public static function extract_content( string $post_content ): string {
        return preg_replace_callback(
            '/<!-- wp:wpam\/markdown -->(.*?)<!-- \/wp:wpam\/markdown -->/s',
            static function ( array $m ): string {
                return trim( $m[1] );
            },
            $post_content
        ) ?? $post_content;
    }
}
