<?php
/**
 * Helpers for relation taxonomy validation and parsing.
 *
 * @package WPAM
 */

namespace WPAM\Memory;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Relation_Helper {
    /**
     * Locked role taxonomy slug.
     */
    public const ROLE_TAXONOMY = 'memory_relation_role';

    /**
     * Group taxonomy slug.
     */
    public const GROUP_TAXONOMY = 'memory_relation_group';

    /**
     * Fixed role vocabulary (slug => label).
     *
     * @var array<string, string>
     */
    private const ROLE_LABELS = array(
        'canonical'   => 'Canonical',
        'companion'   => 'Companion',
        'supporting'  => 'Supporting',
        'superseded'  => 'Superseded',
        'historical'  => 'Historical',
        'duplicate'   => 'Duplicate',
        'alternative' => 'Alternative',
    );

    /**
     * Return the locked role slug => label map.
     *
     * @return array<string, string>
     */
    public static function role_labels(): array {
        return self::ROLE_LABELS;
    }

    /**
     * Return the locked role slugs.
     *
     * @return array<int, string>
     */
    public static function role_slugs(): array {
        return array_keys( self::ROLE_LABELS );
    }

    /**
     * Validate and normalize relation_role input.
     *
     * @param mixed $value Raw relation_role input value.
     *
     * @return array<string, mixed>
     */
    public static function validate_relation_role_input( $value ): array {
        $normalized = self::normalize_single_slug_list( $value, 'relation_role' );
        if ( isset( $normalized['error'] ) ) {
            return $normalized;
        }

        $slug = $normalized['slugs'][0] ?? '';

        if ( '' !== $slug && ! in_array( $slug, self::role_slugs(), true ) ) {
            return array( 'error' => 'relation_role contains an unsupported slug.' );
        }

        return $normalized;
    }

    /**
     * Validate and normalize relation_group input.
     *
     * @param mixed $value Raw relation_group input value.
     *
     * @return array<string, mixed>
     */
    public static function validate_relation_group_input( $value ): array {
        return self::normalize_single_slug_list( $value, 'relation_group' );
    }

    /**
     * Normalize an array of slugs for single-cardinality relation fields.
     *
     * @param mixed  $value Field value from request input.
     * @param string $field Field name for error messages.
     *
     * @return array<string, mixed>
     */
    public static function normalize_single_slug_list( $value, string $field ): array {
        if ( ! is_array( $value ) ) {
            return array( 'error' => "{$field} must be an array of slugs." );
        }

        $slugs = array_values(
            array_filter(
                array_unique(
                    array_map(
                        static function ( $item ): string {
                            return sanitize_title( (string) $item );
                        },
                        $value
                    )
                )
            )
        );

        if ( count( $slugs ) > 1 ) {
            return array( 'error' => "{$field} must contain at most one slug." );
        }

        return array( 'slugs' => $slugs );
    }

    /**
     * Parse "Status: Companion to [#<id> ...]" prose and return target entry ID.
     *
     * @param string $text Source summary/content text.
     *
     * @return int Target entry ID if found, otherwise 0.
     */
    public static function extract_companion_target_id( string $text ): int {
        if ( preg_match( '/Status:\s*Companion\s+to\s*\[#(\d+)\b/i', $text, $matches ) ) {
            return isset( $matches[1] ) ? max( 0, (int) $matches[1] ) : 0;
        }

        return 0;
    }
}
