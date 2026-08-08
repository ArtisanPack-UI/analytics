<?php

declare( strict_types=1 );

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create the analytics_anonymous_page_views table.
 *
 * Holds page views recorded before a visitor has granted analytics consent,
 * when `privacy.anonymous_mode` is enabled.
 *
 * The table deliberately has no `visitor_id`, `session_id`, `fingerprint` or
 * `ip_address` column, and no user agent string. There is nothing here to
 * correlate two rows back to one person, which is the entire point: the rows
 * cannot be joined to a visitor even by mistake, because the columns that
 * would let you do it do not exist.
 *
 * That is also why these rows live in their own table rather than in
 * `analytics_page_views` with a null `visitor_id`. Keeping them separate means
 * every existing visitor- and session-scoped query stays correct without
 * having to remember to exclude them.
 *
 * @since 1.5.0
 */
return new class extends Migration
{
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create( 'analytics_anonymous_page_views', function ( Blueprint $table ) {
			$table->id();
			$table->foreignId( 'site_id' )->nullable()->index();

			// Page data.
			$table->string( 'path', 2048 );
			$table->string( 'title', 500 )->nullable();

			// Referrer host only — never the full referring URL, which can
			// carry search terms or identifiers in its query string.
			$table->string( 'referrer_host', 255 )->nullable();

			// Coarse, non-identifying context.
			$table->string( 'device_type', 20 )->nullable();
			$table->string( 'country', 2 )->nullable();

			$table->string( 'tenant_id' )->nullable()->index();

			$table->timestamp( 'created_at' )->useCurrent();

			$table->foreign( 'site_id', 'analytics_anon_page_views_site_id_fk' )
				->references( 'id' )
				->on( 'analytics_sites' )
				->cascadeOnDelete();

			$table->index( [ 'site_id', 'created_at' ] );
		} );

		$this->addSitePathIndex();
	}

	/**
	 * Index the per-site path lookup used by the top-pages query.
	 *
	 * `path` is 2048 characters, which under utf8mb4 is 8192 bytes on its own
	 * — far past MySQL's 3072-byte limit for an index key, so the composite
	 * index has to be built over a prefix of the column there. Other drivers
	 * have no such limit and take the whole column.
	 *
	 * 191 characters is the usual utf8mb4 prefix length and is long enough to
	 * separate real request paths; the rare longer path shares a prefix
	 * bucket, which costs a filter rather than a correct answer.
	 *
	 * @since 1.5.0
	 */
	private function addSitePathIndex(): void
	{
		$indexName = 'analytics_anon_page_views_site_path_index';

		if ( in_array( DB::connection()->getDriverName(), [ 'mysql', 'mariadb' ], true ) ) {
			DB::statement( sprintf(
				'CREATE INDEX %s ON analytics_anonymous_page_views (site_id, path(191))',
				$indexName,
			) );

			return;
		}

		Schema::table( 'analytics_anonymous_page_views', function ( Blueprint $table ) use ( $indexName ) {
			$table->index( [ 'site_id', 'path' ], $indexName );
		} );
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists( 'analytics_anonymous_page_views' );
	}
};
