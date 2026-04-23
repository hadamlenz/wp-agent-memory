<?php

use PHPUnit\Framework\TestCase;
use WPAM\WordPress\Memory\Relation_Helper;

/**
 * Unit tests for relation taxonomy helpers.
 */
final class RelationHelperTest extends TestCase {
    /**
     * relation_role accepts known locked role slugs.
     */
    public function test_validate_relation_role_accepts_known_slug(): void {
        $result = Relation_Helper::validate_relation_role_input( array( 'companion' ) );

        $this->assertArrayNotHasKey( 'error', $result );
        $this->assertSame( array( 'companion' ), $result['slugs'] );
    }

    /**
     * relation_role rejects unknown role slugs.
     */
    public function test_validate_relation_role_rejects_unknown_slug(): void {
        $result = Relation_Helper::validate_relation_role_input( array( 'unexpected' ) );

        $this->assertArrayHasKey( 'error', $result );
        $this->assertStringContainsString( 'unsupported', $result['error'] );
    }

    /**
     * relation_role enforces single-cardinality.
     */
    public function test_validate_relation_role_enforces_single_value(): void {
        $result = Relation_Helper::validate_relation_role_input( array( 'canonical', 'companion' ) );

        $this->assertArrayHasKey( 'error', $result );
        $this->assertStringContainsString( 'at most one', $result['error'] );
    }

    /**
     * relation_group enforces single-cardinality.
     */
    public function test_validate_relation_group_enforces_single_value(): void {
        $result = Relation_Helper::validate_relation_group_input( array( 'g-80', 'g-81' ) );

        $this->assertArrayHasKey( 'error', $result );
        $this->assertStringContainsString( 'at most one', $result['error'] );
    }

    /**
     * Backfill parser extracts target ID from companion status text.
     */
    public function test_extract_companion_target_id_from_status_line(): void {
        $text = 'Status: Companion to [#80 Hover Style System](https://mem.test/example).';

        $this->assertSame( 80, Relation_Helper::extract_companion_target_id( $text ) );
    }

    /**
     * Backfill parser ignores malformed or unrelated status text.
     */
    public function test_extract_companion_target_id_ignores_invalid_text(): void {
        $this->assertSame( 0, Relation_Helper::extract_companion_target_id( 'Status: Companion to [Hover Style System].' ) );
        $this->assertSame( 0, Relation_Helper::extract_companion_target_id( 'Status: Canonical guidance for hover states.' ) );
    }
}
