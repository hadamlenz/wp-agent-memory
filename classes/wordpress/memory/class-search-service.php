<?php
/**
 * Query, ranking, and retrieval service for memory entries.
 *
 * @package WPAM
 */

namespace WPAM\WordPress\Memory;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Search_Service {
    /**
     * Default page size for search/recent responses.
     */
    private const DEFAULT_LIMIT = 10;

    /**
     * Hard guardrail for endpoint and ability `limit`.
     */
    private const MAX_LIMIT     = 50;

    /**
     * Short transient TTL for search results (seconds).
     */
    private const CACHE_TTL     = 120;

    /**
     * Half-life in days for time-decaying usage signal.
     */
    private const USAGE_HALF_LIFE_DAYS = 90;

    /**
     * Weight multiplier: explicit mark-useful vs passive search appearance.
     */
    private const USEFUL_WEIGHT = 3;

    /**
     * Scale factor for log-compressed usage signal.
     */
    private const USAGE_SCALE   = 30;

    /**
     * Maximum usage bonus — kept below taxonomy match (130) to preserve relevance primacy.
     */
    private const USAGE_CAP     = 60;

    /**
     * Bump cache version used in transient keys.
     */
    public static function bump_cache_version(): void {
        update_option( 'wpam_search_cache_version', time(), false );
    }

    /**
     * Normalize query text for case-insensitive matching/ranking.
     *
     * @param string $query Raw user query.
     *
     * @return string
     */
    public static function normalize_query( string $query ): string {
        $query = trim( strtolower( $query ) );
        $query = preg_replace( '/\s+/', ' ', $query );

        return $query ?? '';
    }

    /**
     * Parse taxonomy filter args into normalized slugs.
     *
     * @param mixed $value Comma-separated string or array input.
     *
     * @return array<int, string>
     */
    public static function parse_filter_values( $value ): array {
        if ( is_string( $value ) ) {
            $value = explode( ',', $value );
        }

        if ( ! is_array( $value ) ) {
            return array();
        }

        return array_values(
            array_filter(
                array_map(
                    static function ( $item ): string {
                        return sanitize_title( (string) $item );
                    },
                    $value
                )
            )
        );
    }

    /**
     * Score a candidate for the given query.
     *
     * Ranking precedence (higher first):
     * 1) exact/partial symbol_name
     * 2) title
     * 3) taxonomy terms
     * 4) keywords and summary
     * 5) excerpt/content
     *
     * @param array<string, mixed> $candidate Candidate fields.
     * @param string               $query     Normalized search query.
     *
     * @return float
     */
    public function score_candidate( array $candidate, string $query ): float {
        $query     = self::normalize_query( $query );
        $symbol    = self::normalize_query( (string) ( $candidate['symbol_name'] ?? '' ) );
        $title     = self::normalize_query( (string) ( $candidate['title'] ?? '' ) );
        $keywords  = self::normalize_query( implode( ' ', (array) ( $candidate['keywords'] ?? array() ) ) );
        $excerpt   = self::normalize_query( (string) ( $candidate['excerpt'] ?? '' ) );
        $content   = self::normalize_query( wp_strip_all_tags( (string) ( $candidate['content'] ?? '' ) ) );
        $terms_raw = array_merge(
            (array) ( $candidate['repo'] ?? array() ),
            (array) ( $candidate['package'] ?? array() ),
            (array) ( $candidate['topic'] ?? array() ),
            (array) ( $candidate['symbol_type'] ?? array() ),
            (array) ( $candidate['relation_role'] ?? array() ),
            (array) ( $candidate['relation_group'] ?? array() )
        );
        $terms     = self::normalize_query( implode( ' ', $terms_raw ) );

        $score = 0.0;

        if ( '' !== $query ) {
            if ( $query === $symbol ) {
                $score += 500;
            } elseif ( '' !== $symbol && str_contains( $symbol, $query ) ) {
                $score += 220;
            }

            if ( $query === $title ) {
                $score += 300;
            } elseif ( '' !== $title && str_contains( $title, $query ) ) {
                $score += 180;
            }

            if ( '' !== $terms && str_contains( $terms, $query ) ) {
                $score += 130;
            }

            if ( '' !== $keywords && str_contains( $keywords, $query ) ) {
                $score += 90;
            }

            if ( '' !== $excerpt && str_contains( $excerpt, $query ) ) {
                $score += 75;
            }

            if ( '' !== $content && str_contains( $content, $query ) ) {
                $score += 25;
            }
        }

        $score += (float) ( $candidate['rank_bias'] ?? 0 );

        $last_used = (string) ( $candidate['last_used_gmt'] ?? '' );
        $usage     = (int) ( $candidate['usage_count'] ?? 0 );
        $useful    = (int) ( $candidate['useful_count'] ?? 0 );

        if ( '' !== $last_used && ( $usage > 0 || $useful > 0 ) ) {
            $days_ago = max( 0, ( time() - (int) strtotime( $last_used ) ) / DAY_IN_SECONDS );
            $decay    = exp( -$days_ago / self::USAGE_HALF_LIFE_DAYS );
            $combined = $usage + ( $useful * self::USEFUL_WEIGHT );
            $score   += min( log( 1 + $combined ) * $decay * self::USAGE_SCALE, self::USAGE_CAP );
        }

        return $score;
    }

    /**
     * Search ranked memory entries.
     *
     * @param array<string, mixed> $params Request filters and query params.
     *
     * @return array<int, array<string, mixed>>
     */
    public function search( array $params ): array {
        $query         = self::normalize_query( (string) ( $params['query'] ?? '' ) );
        $limit         = max( 1, min( self::MAX_LIMIT, (int) ( $params['limit'] ?? self::DEFAULT_LIMIT ) ) );
        $repo_filter   = self::parse_filter_values( $params['repo'] ?? array() );
        $pack_filter   = self::parse_filter_values( $params['package'] ?? array() );
        $type_filter   = self::parse_filter_values( $params['symbol_type'] ?? array() );
        $topic_filter  = self::parse_filter_values( $params['topic'] ?? array() );
        $role_filter   = self::parse_filter_values( $params['relation_role'] ?? array() );
        $group_filter  = self::parse_filter_values( $params['relation_group'] ?? array() );
        $cache_key     = $this->build_cache_key( array( $query, $limit, $repo_filter, $pack_filter, $type_filter, $topic_filter, $role_filter, $group_filter ) );
        $cached_result = get_transient( $cache_key );

        if ( is_array( $cached_result ) ) {
            return $cached_result;
        }

        // Fetch 4× the requested limit (min 20, max 200) so custom scoring has a large enough
        // pool to reorder before trimming. WP_Query's built-in 's' ordering is not aware of
        // symbol_name, rank_bias, or usage signals — those are applied in score_candidate().
        $args = array(
            'post_type'      => 'memory_entry',
            'post_status'    => 'publish',
            'posts_per_page' => min( max( 20, $limit * 4 ), 200 ),
            'orderby'        => array(
                'date' => 'DESC',
            ),
            'no_found_rows'  => true,
            's'              => $query,
        );

        $tax_query = array();

        if ( ! empty( $repo_filter ) ) {
            $tax_query[] = array(
                'taxonomy' => 'memory_repo',
                'field'    => 'slug',
                'terms'    => $repo_filter,
            );
        }

        if ( ! empty( $pack_filter ) ) {
            $tax_query[] = array(
                'taxonomy' => 'memory_package',
                'field'    => 'slug',
                'terms'    => $pack_filter,
            );
        }

        if ( ! empty( $type_filter ) ) {
            $tax_query[] = array(
                'taxonomy' => 'memory_symbol_type',
                'field'    => 'slug',
                'terms'    => $type_filter,
            );
        }

        if ( ! empty( $topic_filter ) ) {
            $tax_query[] = array(
                'taxonomy' => 'memory_topic',
                'field'    => 'slug',
                'terms'    => $topic_filter,
            );
        }

        if ( ! empty( $role_filter ) ) {
            $tax_query[] = array(
                'taxonomy' => 'memory_relation_role',
                'field'    => 'slug',
                'terms'    => $role_filter,
            );
        }

        if ( ! empty( $group_filter ) ) {
            $tax_query[] = array(
                'taxonomy' => 'memory_relation_group',
                'field'    => 'slug',
                'terms'    => $group_filter,
            );
        }

        if ( ! empty( $tax_query ) ) {
            if ( count( $tax_query ) > 1 ) {
                $tax_query = array_merge( array( 'relation' => 'AND' ), $tax_query );
            }
            $args['tax_query'] = $tax_query;
        }

        $posts      = get_posts( $args );
        $candidates = array();

        foreach ( $posts as $post ) {
            if ( ! $post instanceof WP_Post ) {
                continue;
            }

            $candidate = $this->build_candidate( $post );
            $score     = $this->score_candidate( $candidate, $query );

            if ( '' !== $query && $score <= 0 ) {
                continue;
            }

            $candidates[] = Response_Shaper::search_result( $candidate, $score, $query );
        }

        usort(
            $candidates,
            static function ( array $a, array $b ): int {
                return $b['score'] <=> $a['score'];
            }
        );

        $results = array_slice( $candidates, 0, $limit );
        set_transient( $cache_key, $results, self::CACHE_TTL );

        $used_ids = array_filter( array_map( 'intval', array_column( $results, 'id' ) ) );
        if ( ! empty( $used_ids ) ) {
            self::increment_usage( $used_ids );
        }

        return $results;
    }

    /**
     * Increment passive usage counters for a set of returned entry IDs.
     * Fires after the cache is written so it does not affect the current response.
     *
     * @param int[] $post_ids Post IDs to mark as used.
     */
    private static function increment_usage( array $post_ids ): void {
        $now = gmdate( 'Y-m-d H:i:s' );
        foreach ( $post_ids as $post_id ) {
            $current = (int) get_post_meta( $post_id, 'usage_count', true );
            update_post_meta( $post_id, 'usage_count', $current + 1 );
            update_post_meta( $post_id, 'last_used_gmt', $now );
        }
    }

    /**
     * Load one published memory_entry by ID in full structured form.
     *
     * @param int $id Post ID.
     *
     * @return array<string, mixed>|null
     */
    public function get_entry( int $id ): ?array {
        $post = get_post( $id );

        if ( ! $post instanceof WP_Post || 'memory_entry' !== $post->post_type || 'publish' !== $post->post_status ) {
            return null;
        }

        return Response_Shaper::entry_result( $this->build_candidate( $post ) );
    }

    /**
     * List recent entries using same compact shape as search results.
     *
     * @param int $limit Max results to return.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recent( int $limit = self::DEFAULT_LIMIT ): array {
        $limit = max( 1, min( self::MAX_LIMIT, $limit ) );

        $posts = get_posts(
            array(
                'post_type'      => 'memory_entry',
                'post_status'    => 'publish',
                'posts_per_page' => $limit,
                'orderby'        => array(
                    'date' => 'DESC',
                ),
                'no_found_rows'  => true,
            )
        );

        $results = array();

        foreach ( $posts as $post ) {
            if ( ! $post instanceof WP_Post ) {
                continue;
            }

            $candidate = $this->build_candidate( $post );
            $results[] = Response_Shaper::search_result( $candidate, (float) ( $candidate['rank_bias'] ?? 0 ), '' );
        }

        return $results;
    }

    /**
     * Build transient key namespaced by cache version + query/filter payload.
     *
     * @param array<int, mixed> $parts Key material.
     *
     * @return string
     */
    private function build_cache_key( array $parts ): string {
        $version = (string) get_option( 'wpam_search_cache_version', '1' );

        return 'wpam_search_' . md5( wp_json_encode( array_merge( array( $version ), $parts ) ) ?: $version );
    }

    /**
     * Convert a WP_Post into normalized candidate data for scoring/shaping.
     *
     * @param WP_Post $post Memory entry post.
     *
     * @return array<string, mixed>
     */
    private function build_candidate( WP_Post $post ): array {
        $id          = (int) $post->ID;
        $symbol_name = (string) get_post_meta( $id, 'symbol_name', true );
        $keywords    = (string) get_post_meta( $id, 'keywords', true );

        return array(
            'id'          => $id,
            'title'       => get_the_title( $id ),
            'excerpt'     => $post->post_excerpt,
            'content'     => $post->post_content,
            'symbol_name' => $symbol_name,
            'source_url'  => (string) get_post_meta( $id, 'source_url', true ),
            'source_path' => (string) get_post_meta( $id, 'source_path', true ),
            'source_ref'  => (string) get_post_meta( $id, 'source_ref', true ),
            'keywords'    => array_values( array_filter( array_map( 'trim', explode( ',', $keywords ) ) ) ),
            'rank_bias'    => (float) get_post_meta( $id, 'rank_bias', true ),
            'usage_count'  => (int) get_post_meta( $id, 'usage_count', true ),
            'useful_count' => (int) get_post_meta( $id, 'useful_count', true ),
            'last_used_gmt'=> (string) get_post_meta( $id, 'last_used_gmt', true ),
            'permalink'   => get_permalink( $id ),
            'repo'        => $this->term_slugs( $id, 'memory_repo' ),
            'package'     => $this->term_slugs( $id, 'memory_package' ),
            'topic'       => $this->term_slugs( $id, 'memory_topic' ),
            'symbol_type' => $this->term_slugs( $id, 'memory_symbol_type' ),
            'relation_role'  => $this->term_slugs( $id, 'memory_relation_role' ),
            'relation_group' => $this->term_slugs( $id, 'memory_relation_group' ),
            'modified_gmt'=> $post->post_modified_gmt,
            'post_author' => (int) $post->post_author,
        );
    }

    /**
     * Read taxonomy term slugs for one post/taxonomy pair.
     *
     * @param int    $post_id  Post ID.
     * @param string $taxonomy Taxonomy slug.
     *
     * @return array<int, string>
     */
    private function term_slugs( int $post_id, string $taxonomy ): array {
        $terms = get_the_terms( $post_id, $taxonomy );

        if ( ! is_array( $terms ) ) {
            return array();
        }

        return array_values(
            array_filter(
                array_map(
                    static function ( $term ): string {
                        return isset( $term->slug ) ? (string) $term->slug : '';
                    },
                    $terms
                )
            )
        );
    }
}
