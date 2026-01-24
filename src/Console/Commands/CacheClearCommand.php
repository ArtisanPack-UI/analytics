<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Console\Commands;

use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Illuminate\Console\Command;

/**
 * Command to clear analytics cache.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Console\Commands
 */
class CacheClearCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'analytics:cache:clear';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Clear analytics query cache';

	/**
	 * Execute the console command.
	 *
	 * @param AnalyticsQuery $query The analytics query service.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function handle( AnalyticsQuery $query ): int
	{
		$cleared = false;

		$this->components->task( __( 'Clearing analytics cache' ), function () use ( $query, &$cleared ): bool {
			$cleared = $query->clearCache();

			return $cleared;
		} );

		$this->newLine();

		if ( $cleared ) {
			$this->info( __( 'Analytics cache cleared successfully.' ) );
		} else {
			$this->warn( __( 'Cache driver does not support tags. Analytics cache was not cleared.' ) );
			$this->line( __( 'Consider using Redis or Memcached for selective cache clearing.' ) );
		}

		return self::SUCCESS;
	}
}
