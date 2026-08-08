<?php

declare( strict_types=1 );

use Tests\Support\MigrationCompiler;

/**
 * Regression coverage for the anonymous page-view index key length.
 *
 * `analytics_anonymous_page_views.path` is 2048 characters, which under
 * utf8mb4 is 8192 bytes — well past MySQL's 3072-byte limit for an index key.
 * Indexing the whole column alongside `site_id` compiled and ran fine on
 * SQLite, which has no such limit, and then failed on every MySQL install with
 * "Specified key was too long", leaving the table half-created and the
 * migration unrecorded.
 *
 * These tests compile the migration against the real MySQL grammar so the
 * limit is enforced in CI without needing a MySQL server.
 */

/**
 * The maximum bytes MySQL allows in an InnoDB index key.
 */
const ANALYTICS_MYSQL_MAX_KEY_BYTES = 3072;

/**
 * Bytes per character under utf8mb4, the charset Laravel defaults to.
 */
const ANALYTICS_UTF8MB4_BYTES_PER_CHAR = 4;

test( 'the anonymous page view path index is built over a prefix', function (): void {
	$statements = MigrationCompiler::compile(
		MigrationCompiler::path( 'create_analytics_anonymous_page_views_table' ),
	);

	$pathIndexes = array_values( array_filter(
		$statements,
		fn ( string $statement ): bool => str_contains( $statement, 'index' )
			&& str_contains( $statement, 'path' ),
	) );

	expect( $pathIndexes )->toHaveCount( 1 );
	expect( $pathIndexes[0] )->toContain( 'path(191)' );
} );

test( 'no anonymous page view index can exceed the MySQL key length limit', function (): void {
	$statements = MigrationCompiler::compile(
		MigrationCompiler::path( 'create_analytics_anonymous_page_views_table' ),
	);

	// Column widths declared by the migration, in characters.
	$columnWidths = [
		'path'          => 2048,
		'title'         => 500,
		'referrer_host' => 255,
		'device_type'   => 20,
		'country'       => 2,
		'tenant_id'     => 255,
	];

	foreach ( $statements as $statement ) {
		if ( ! preg_match( '/index [`\w]+ on [`\w]+ \((.+)\)$|index `[^`]+`\((.+)\)/i', $statement, $matches ) ) {
			continue;
		}

		$columns = $matches[1] ?: $matches[2];
		$bytes   = 0;

		foreach ( explode( ',', $columns ) as $column ) {
			$column = trim( $column );

			// A prefix index states its own length: `path(191)`.
			if ( preg_match( '/^`?(\w+)`?\((\d+)\)$/', $column, $prefix ) ) {
				$bytes += (int) $prefix[2] * ANALYTICS_UTF8MB4_BYTES_PER_CHAR;

				continue;
			}

			$name = trim( $column, '` ' );

			$bytes += isset( $columnWidths[ $name ] )
				? $columnWidths[ $name ] * ANALYTICS_UTF8MB4_BYTES_PER_CHAR
				: 8;
		}

		expect( $bytes )->toBeLessThanOrEqual(
			ANALYTICS_MYSQL_MAX_KEY_BYTES,
			sprintf( 'Index key exceeds the MySQL limit: %s', $statement ),
		);
	}
} );

test( 'the anonymous page view index name fits MySQL identifier limits', function (): void {
	$statements = MigrationCompiler::compile(
		MigrationCompiler::path( 'create_analytics_anonymous_page_views_table' ),
	);

	preg_match_all( '/index [`]?([a-z_]+)[`]?[ (]/i', implode( ' ', $statements ), $matches );

	foreach ( $matches[1] as $name ) {
		expect( strlen( $name ) )->toBeLessThanOrEqual( 64 );
	}
} );
