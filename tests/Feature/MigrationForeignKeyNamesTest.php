<?php

declare( strict_types=1 );

use Illuminate\Support\Facades\DB;

/**
 * Regression coverage for issue #43.
 *
 * Compiles every analytics migration against the MySQL schema grammar and
 * asserts that every foreign key carries an explicit, deterministic, unique
 * name. Previously the `->constrained()->nullOnDelete()->index()` chain set
 * `index => true` on the ForeignKeyDefinition, which MySQL rendered as the
 * literal constraint name `1`, colliding with the next unnamed key and
 * breaking `migrate:fresh` on MySQL 8.
 *
 * The migrations are compiled against a fake MySQL connection that captures
 * the generated DDL instead of executing it, so the test needs no live
 * database server while still exercising the real MySQL grammar.
 */

/**
 * Compile a migration's up() against the MySQL grammar and return its DDL.
 *
 * @return array<int, string> The SQL statements the migration generates.
 */
function analyticsCompileMigration( string $migrationFile ): array
{
	$connection = new class( new PDO( 'sqlite::memory:' ) ) extends Illuminate\Database\MySqlConnection {
		/**
		 * @var array<int, string>
		 */
		public array $captured = [];

		public function getServerVersion(): string
		{
			return '8.0.30';
		}

		public function isMaria(): bool
		{
			return false;
		}

		public function statement( $query, $bindings = [] ): bool
		{
			$this->captured[] = $query;

			return true;
		}
	};

	$originalDefault    = config( 'database.default' );
	$originalConnection = config( 'database.connections.mysql_fake' );

	try {
		config()->set( 'database.connections.mysql_fake', [ 'driver' => 'mysql', 'database' => 'test', 'prefix' => '' ] );
		DB::extend( 'mysql_fake', fn (): Illuminate\Database\Connection => $connection );
		DB::purge( 'mysql_fake' );
		config()->set( 'database.default', 'mysql_fake' );

		$migration = require $migrationFile;
		$migration->up();

		return $connection->captured;
	} finally {
		config()->set( 'database.default', $originalDefault );
		config()->set( 'database.connections.mysql_fake', $originalConnection );
		DB::purge( 'mysql_fake' );
	}
}

/**
 * Extract the foreign key constraint names from compiled DDL.
 *
 * @param array<int, string> $statements
 *
 * @return array<int, string>
 */
function analyticsForeignKeyNames( array $statements ): array
{
	$names = [];

	foreach ( $statements as $statement ) {
		if ( preg_match_all( '/add constraint `([^`]+)` foreign key/', $statement, $matches ) ) {
			foreach ( $matches[1] as $name ) {
				$names[] = $name;
			}
		}
	}

	return $names;
}

$migrationFiles = glob( dirname( __DIR__, 2 ) . '/database/migrations/2025_01_01_*.php' );

if ( false === $migrationFiles || [] === $migrationFiles ) {
	throw new RuntimeException( 'No analytics migration files were found to test.' );
}

test( 'every analytics foreign key has an explicit, non-numeric name', function ( string $migrationFile ): void {
	$statements = analyticsCompileMigration( $migrationFile );

	expect( $statements )->not->toBeEmpty();

	$names = analyticsForeignKeyNames( $statements );

	foreach ( $names as $name ) {
		expect( $name )->not->toMatch( '/^\d+$/' );
		expect( $name )->toStartWith( 'analytics_' );
		expect( $name )->toEndWith( '_fk' );
	}
} )->with( $migrationFiles );

test( 'foreign key names are unique within each analytics table', function ( string $migrationFile ): void {
	$names = analyticsForeignKeyNames( analyticsCompileMigration( $migrationFile ) );

	expect( $names )->toEqual( array_values( array_unique( $names ) ) );
} )->with( $migrationFiles );

test( 'analytics_page_views declares the three named foreign keys from issue #43', function (): void {
	$pageViews = dirname( __DIR__, 2 ) . '/database/migrations/2025_01_01_000004_create_analytics_page_views_table.php';

	$names = analyticsForeignKeyNames( analyticsCompileMigration( $pageViews ) );

	expect( $names )->toContain(
		'analytics_page_views_site_id_fk',
		'analytics_page_views_session_id_fk',
		'analytics_page_views_visitor_id_fk',
	);
} );
