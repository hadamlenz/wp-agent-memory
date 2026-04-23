<?php

use PHPUnit\Framework\TestCase;
use WPAM\Memory\Search_Service;

/**
 * Unit tests for query normalization, filter parsing, and ranking precedence.
 */
final class SearchServiceTest extends TestCase {
    /**
     * Query normalization should trim/collapse spacing and lowercase values.
     */
    public function test_normalize_query_collapses_spacing_and_case(): void {
        $this->assertSame( 'editor.blockedit', Search_Service::normalize_query( '  Editor.BlockEdit  ' ) );
        $this->assertSame( '@wordpress/data select', Search_Service::normalize_query( '@WordPress/Data   select' ) );
    }

    /**
     * Exact symbol matches must outrank title-only matches.
     */
    public function test_score_prefers_exact_symbol_over_title_and_content(): void {
        $service = new Search_Service();

        $symbol_exact = array(
            'symbol_name' => 'editor.BlockEdit',
            'title'       => 'Block edit wrapper',
            'repo'        => array( 'gutenberg' ),
            'package'     => array( 'block-editor' ),
            'topic'       => array( 'blocks' ),
            'symbol_type' => array( 'hook' ),
            'summary'     => 'Hook details',
            'excerpt'     => '',
            'content'     => '',
            'rank_bias'   => 0,
        );

        $title_match = array(
            'symbol_name' => 'something-else',
            'title'       => 'editor.BlockEdit',
            'repo'        => array(),
            'package'     => array(),
            'topic'       => array(),
            'symbol_type' => array(),
            'summary'     => '',
            'excerpt'     => '',
            'content'     => '',
            'rank_bias'   => 0,
        );

        $this->assertGreaterThan(
            $service->score_candidate( $title_match, 'editor.BlockEdit' ),
            $service->score_candidate( $symbol_exact, 'editor.BlockEdit' )
        );
    }

    /**
     * Filters accept both CSV and array input forms.
     */
    public function test_filter_value_parsing_supports_csv_and_arrays(): void {
        $this->assertSame(
            array( 'gutenberg', 'wp-data' ),
            Search_Service::parse_filter_values( 'gutenberg,wp-data' )
        );

        $this->assertSame(
            array( 'block-editor', 'hooks' ),
            Search_Service::parse_filter_values( array( 'Block Editor', 'Hooks' ) )
        );
    }

    /**
     * Multi-word queries should still score title matches when individual terms match.
     */
    public function test_score_supports_or_style_term_matching_for_multi_word_query(): void {
        $service = new Search_Service();
        $candidate = array(
            'symbol_name' => '',
            'title'       => 'Hover Style System',
            'repo'        => array(),
            'package'     => array(),
            'topic'       => array(),
            'symbol_type' => array(),
            'summary'     => '',
            'excerpt'     => '',
            'content'     => '',
            'rank_bias'   => 0,
        );

        $this->assertGreaterThan( 0, $service->score_candidate( $candidate, 'hover vars' ) );
    }

    /**
     * Entries matching more query terms in the title should rank above entries matching fewer.
     */
    public function test_score_prefers_more_title_term_matches(): void {
        $service = new Search_Service();

        $single_term_match = array(
            'symbol_name' => '',
            'title'       => 'Hover Controls',
            'repo'        => array(),
            'package'     => array(),
            'topic'       => array(),
            'symbol_type' => array(),
            'summary'     => '',
            'excerpt'     => '',
            'content'     => '',
            'rank_bias'   => 0,
        );

        $double_term_match = array(
            'symbol_name' => '',
            'title'       => 'Hover Vars Controls',
            'repo'        => array(),
            'package'     => array(),
            'topic'       => array(),
            'symbol_type' => array(),
            'summary'     => '',
            'excerpt'     => '',
            'content'     => '',
            'rank_bias'   => 0,
        );

        $this->assertGreaterThan(
            $service->score_candidate( $single_term_match, 'hover vars' ),
            $service->score_candidate( $double_term_match, 'hover vars' )
        );
    }

    /**
     * `queries` array takes precedence over `query` string when non-empty.
     */
    public function test_resolve_query_terms_prefers_queries_array(): void {
        $resolved = Search_Service::resolve_query_terms(
            array(
                'query'   => 'should not be used',
                'queries' => array( 'hover', 'vars' ),
            )
        );

        $this->assertSame( array( 'hover', 'vars' ), $resolved['terms'] );
        $this->assertSame( 'hover vars', $resolved['query'] );
    }

    /**
     * Query-only fallback should split the string into normalized term words.
     */
    public function test_resolve_query_terms_splits_query_when_queries_missing(): void {
        $resolved = Search_Service::resolve_query_terms(
            array(
                'query' => 'Hover Vars',
            )
        );

        $this->assertSame( array( 'hover', 'vars' ), $resolved['terms'] );
        $this->assertSame( 'hover vars', $resolved['query'] );
    }

    /**
     * Relation fields must not affect ranking when absent or empty.
     */
    public function test_score_unchanged_when_relation_fields_are_absent_or_empty(): void {
        $service = new Search_Service();

        $baseline = array(
            'symbol_name' => '',
            'title'       => 'Hover Style System',
            'repo'        => array( 'unc-wilson' ),
            'package'     => array(),
            'topic'       => array( 'blocks' ),
            'symbol_type' => array(),
            'summary'     => '',
            'excerpt'     => 'Companion entry guidance',
            'content'     => '',
            'rank_bias'   => 0,
        );

        $with_empty_relation = $baseline;
        $with_empty_relation['relation_role']  = array();
        $with_empty_relation['relation_group'] = array();

        $this->assertSame(
            $service->score_candidate( $baseline, 'hover' ),
            $service->score_candidate( $with_empty_relation, 'hover' )
        );
    }
}
