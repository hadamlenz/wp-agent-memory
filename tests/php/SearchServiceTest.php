<?php

use PHPUnit\Framework\TestCase;
use WPAM\WordPress\Memory\Search_Service;

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
