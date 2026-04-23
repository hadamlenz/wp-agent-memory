<?php
/**
 * Query, ranking, and retrieval service for memory entries.
 *
 * @package WPAM
 */

namespace WPAM\Memory;

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
     * Normalize text for boundary-safe phrase/token matching.
     *
     * Rules:
     * - lowercase
     * - treat `-` and `_` as spaces
     * - collapse punctuation and repeated whitespace to single spaces
     *
     * @param string $text Raw input text.
     *
     * @return string
     */
    public static function normalize_phrase_text( string $text ): string {
        $text = strtolower( str_replace( array( '-', '_' ), ' ', trim( $text ) ) );
        $text = preg_replace( '/[^a-z0-9]+/', ' ', $text );
        $text = preg_replace( '/\s+/', ' ', $text );

        return trim( $text ?? '' );
    }

    /**
     * Split normalized text into unique words.
     *
     * @param string $text Normalized text.
     *
     * @return array<int, string>
     */
    public static function split_normalized_words( string $text ): array {
        if ( '' === trim( $text ) ) {
            return array();
        }

        return array_values(
            array_unique(
                array_filter(
                    array_map(
                        static function ( $word ): string {
                            return trim( (string) $word );
                        },
                        explode( ' ', $text )
                    )
                )
            )
        );
    }

    /**
     * Resolve query phrase + query terms from search parameters.
     *
     * Precedence:
     * 1) If `queries` contains one or more non-empty strings, use it.
     * 2) Otherwise use `query` string and split into term words.
     *
     * @param array<string, mixed> $params Search params.
     *
     * @return array{query: string, terms: array<int, string>}
     */
    public static function resolve_query_terms( array $params ): array {
        $terms = array();

        if ( ! empty( $params['queries'] ) && is_array( $params['queries'] ) ) {
            foreach ( $params['queries'] as $term ) {
                $normalized = self::normalize_phrase_text( (string) $term );
                if ( '' === $normalized ) {
                    continue;
                }
                $terms[] = $normalized;
            }

            $terms = array_values( array_unique( $terms ) );
            if ( ! empty( $terms ) ) {
                return array(
                    'query' => self::normalize_query( implode( ' ', $terms ) ),
                    'terms' => $terms,
                );
            }
        }

        $query = (string) ( $params['query'] ?? '' );

        return array(
            'query' => self::normalize_query( $query ),
            'terms' => self::split_normalized_words( self::normalize_phrase_text( $query ) ),
        );
    }

    /**
     * Boundary-safe phrase containment check against normalized text.
     *
     * @param string $haystack Normalized haystack text.
     * @param string $needle   Normalized needle phrase.
     *
     * @return bool
     */
    public static function phrase_contains( string $haystack, string $needle ): bool {
        $haystack = trim( $haystack );
        $needle   = trim( $needle );

        if ( '' === $haystack || '' === $needle ) {
            return false;
        }

        return str_contains( ' ' . $haystack . ' ', ' ' . $needle . ' ' );
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
     * Score a candidate for the given query phrase and tokenized terms.
     *
     * Ranking precedence (higher first):
     * 1) exact/partial symbol_name
     * 2) title
     * 3) taxonomy terms
     * 4) excerpt/content
     *
     * @param array<string, mixed> $candidate   Candidate fields.
     * @param string               $query       Normalized query phrase.
     * @param array<int, string>   $query_terms Normalized query terms (OR semantics).
     *
     * @return float
     */
    public function score_candidate( array $candidate, string $query, array $query_terms = array() ): float {
        $query     = self::normalize_query( $query );
        $symbol    = self::normalize_query( (string) ( $candidate['symbol_name'] ?? '' ) );
        $title     = self::normalize_query( (string) ( $candidate['title'] ?? '' ) );
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

        $symbol_phrase = self::normalize_phrase_text( (string) ( $candidate['symbol_name'] ?? '' ) );
        $title_phrase  = self::normalize_phrase_text( (string) ( $candidate['title'] ?? '' ) );
        $terms_phrase  = self::normalize_phrase_text( implode( ' ', $terms_raw ) );
        $excerpt_phrase = self::normalize_phrase_text( (string) ( $candidate['excerpt'] ?? '' ) );
        $content_phrase = self::normalize_phrase_text( wp_strip_all_tags( (string) ( $candidate['content'] ?? '' ) ) );

        if ( empty( $query_terms ) && '' !== $query ) {
            $query_terms = self::split_normalized_words( self::normalize_phrase_text( $query ) );
        }

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

            if ( '' !== $excerpt && str_contains( $excerpt, $query ) ) {
                $score += 75;
            }

            if ( '' !== $content && str_contains( $content, $query ) ) {
                $score += 25;
            }
        }

        $matched_title_terms  = 0;
        $matched_symbol_terms = 0;
        foreach ( $query_terms as $term ) {
            $term = self::normalize_phrase_text( (string) $term );
            if ( '' === $term ) {
                continue;
            }

            if ( self::phrase_contains( $symbol_phrase, $term ) ) {
                $score += 110;
                ++$matched_symbol_terms;
            }

            if ( self::phrase_contains( $title_phrase, $term ) ) {
                $score += 90;
                ++$matched_title_terms;
            }

            if ( self::phrase_contains( $terms_phrase, $term ) ) {
                $score += 60;
            }

            if ( self::phrase_contains( $excerpt_phrase, $term ) ) {
                $score += 30;
            }

            if ( self::phrase_contains( $content_phrase, $term ) ) {
                $score += 10;
            }
        }

        if ( $matched_symbol_terms > 1 ) {
            $score += 20 * ( $matched_symbol_terms - 1 );
        }

        if ( $matched_title_terms > 1 ) {
            $score += 20 * ( $matched_title_terms - 1 );
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
        $resolved      = self::resolve_query_terms( $params );
        $query         = $resolved['query'];
        $query_terms   = $resolved['terms'];
        $search_terms  = array_values(
            array_unique(
                array_filter(
                    array_merge(
                        '' !== $query ? array( $query ) : array(),
                        $query_terms
                    )
                )
            )
        );
        if ( empty( $search_terms ) ) {
            $search_terms = array( '' );
        }
        $limit         = max( 1, min( self::MAX_LIMIT, (int) ( $params['limit'] ?? self::DEFAULT_LIMIT ) ) );
        $repo_filter   = self::parse_filter_values( $params['repo'] ?? array() );
        $pack_filter   = self::parse_filter_values( $params['package'] ?? array() );
        $type_filter   = self::parse_filter_values( $params['symbol_type'] ?? array() );
        $topic_filter  = self::parse_filter_values( $params['topic'] ?? array() );
        $role_filter   = self::parse_filter_values( $params['relation_role'] ?? array() );
        $group_filter  = self::parse_filter_values( $params['relation_group'] ?? array() );
        $cache_key     = $this->build_cache_key( array( $search_terms, $limit, $repo_filter, $pack_filter, $type_filter, $topic_filter, $role_filter, $group_filter ) );
        $cached_result = get_transient( $cache_key );

        if ( is_array( $cached_result ) ) {
            return $cached_result;
        }

        // Fetch 4× the requested limit (min 20, max 200) per search variant so token queries
        // can match independently (OR semantics), then merge/dedupe by post ID before scoring.
        $posts_per_variant = min( max( 20, $limit * 4 ), 200 );
        $posts_by_id       = array();
        foreach ( $search_terms as $search_term ) {
            $args = array(
                'post_type'      => 'memory_entry',
                'post_status'    => 'publish',
                'posts_per_page' => $posts_per_variant,
                'orderby'        => array(
                    'date' => 'DESC',
                ),
                'no_found_rows'  => true,
                's'              => (string) $search_term,
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

            foreach ( get_posts( $args ) as $post ) {
                if ( $post instanceof WP_Post ) {
                    $posts_by_id[ (int) $post->ID ] = $post;
                }
            }
        }

        $posts      = array_values( $posts_by_id );
        $candidates = array();

        foreach ( $posts as $post ) {
            if ( ! $post instanceof WP_Post ) {
                continue;
            }

            $candidate = $this->build_candidate( $post );
            $score     = $this->score_candidate( $candidate, $query, $query_terms );

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
     * List all topic terms with usage counts.
     *
     * @return array<int, array{slug: string, count: int}>
     */
    public function list_topics(): array {
        $terms = get_terms(
            array(
                'taxonomy'   => 'memory_topic',
                'hide_empty' => false,
            )
        );

        if ( ! is_array( $terms ) ) {
            return array();
        }

        $topics = array();
        foreach ( $terms as $term ) {
            if ( ! isset( $term->slug ) ) {
                continue;
            }

            $topics[] = array(
                'slug'  => (string) $term->slug,
                'count' => isset( $term->count ) ? (int) $term->count : 0,
            );
        }

        usort(
            $topics,
            static function ( array $a, array $b ): int {
                $count_sort = $b['count'] <=> $a['count'];
                if ( 0 !== $count_sort ) {
                    return $count_sort;
                }

                return strcmp( $a['slug'], $b['slug'] );
            }
        );

        return $topics;
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

        return array(
            'id'          => $id,
            'title'       => get_the_title( $id ),
            'excerpt'     => $post->post_excerpt,
            'content'     => $post->post_content,
            'symbol_name' => $symbol_name,
            'source_url'  => (string) get_post_meta( $id, 'source_url', true ),
            'source_path' => (string) get_post_meta( $id, 'source_path', true ),
            'source_ref'  => (string) get_post_meta( $id, 'source_ref', true ),
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
