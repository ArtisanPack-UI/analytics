<?php

declare( strict_types=1 );

namespace Tests\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Compiles migrations against the MySQL schema grammar without a live server.
 *
 * Several classes of migration bug are invisible on SQLite — index key length
 * limits, foreign key naming, column type ceilings — and the test suite runs
 * on SQLite. Compiling the real MySQL grammar and inspecting the generated DDL
 * catches them without requiring a MySQL server in CI.
 *
 * @since   1.5.0
 *
 * @package Tests\Support
 */
final class MigrationCompiler
{
	/**
	 * Compile a migration's up() against the MySQL grammar.
	 *
	 * @param string $migrationFile Absolute path to the migration file.
	 *
	 * @return array<int, string> The SQL statements the migration generates.
	 *
	 * @since 1.5.0
	 */
	public static function compile( string $migrationFile ): array
	{
		$connection = new class( new PDO( 'sqlite::memory:' ) ) extends MySqlConnection {
			/**
			 * @var array<int, string>
			 */
			public array $captured = [];

			/**
			 * Report a fixed MySQL version so the grammar compiles without a live server.
			 */
			public function getServerVersion(): string
			{
				return '8.0.30';
			}

			/**
			 * Always compile as MySQL rather than MariaDB.
			 */
			public function isMaria(): bool
			{
				return false;
			}

			/**
			 * Report the driver a real MySqlConnection would.
			 *
			 * The connection is built directly rather than through the
			 * factory, so it carries no config array to read this from — and
			 * migrations that branch on the driver must see 'mysql' here or
			 * they compile the wrong path and the test proves nothing.
			 */
			public function getDriverName(): string
			{
				return 'mysql';
			}

			/**
			 * Capture compiled DDL instead of executing it against a database.
			 *
			 * @param string               $query
			 * @param array<string, mixed> $bindings
			 */
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
			DB::extend( 'mysql_fake', fn (): Connection => $connection );
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
	 * Get the absolute path to a migration file by filename fragment.
	 *
	 * @param string $fragment A distinctive part of the migration filename.
	 *
	 * @return string The absolute path to the matching migration.
	 *
	 * @since 1.5.0
	 */
	public static function path( string $fragment ): string
	{
		$matches = glob( dirname( __DIR__, 2 ) . '/database/migrations/*' . $fragment . '*.php' );

		if ( false === $matches || 1 !== count( $matches ) ) {
			throw new RuntimeException( sprintf( 'Expected exactly one migration matching "%s".', $fragment ) );
		}

		return $matches[0];
	}
}
