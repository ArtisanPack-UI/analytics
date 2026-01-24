<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Console\Commands;

use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Command to clean up old analytics data.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Console\Commands
 */
class CleanupCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'analytics:cleanup
		{--days=90 : Delete data older than this many days}
		{--force : Skip confirmation}
		{--dry-run : Show what would be deleted without deleting}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Clean up old analytics data';

	/**
	 * Execute the console command.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function handle(): int
	{
		$days   = (int) $this->option( 'days' );
		$dryRun = $this->option( 'dry-run' );

		// Validate days parameter
		if ( $days < 1 ) {
			$this->error( __( 'The --days option must be a positive integer (1 or greater).' ) );

			return self::FAILURE;
		}

		$cutoff = Carbon::now()->subDays( $days );

		$this->info( __( 'Analytics Data Cleanup' ) );
		$this->line( sprintf( __( 'Deleting data older than %d days (before %s)' ), $days, $cutoff->format( 'Y-m-d' ) ) );
		$this->newLine();

		// Count records to delete
		$pageViewCount = PageView::where( 'created_at', '<', $cutoff )->count();
		$eventCount    = Event::where( 'created_at', '<', $cutoff )->count();
		$sessionCount  = Session::where( 'created_at', '<', $cutoff )->count();

		$this->table(
			[ __( 'Table' ), __( 'Records to Delete' ) ],
			[
				[ 'page_views', number_format( $pageViewCount ) ],
				[ 'events', number_format( $eventCount ) ],
				[ 'sessions', number_format( $sessionCount ) ],
			],
		);

		$totalCount = $pageViewCount + $eventCount + $sessionCount;

		if ( 0 === $totalCount ) {
			$this->info( __( 'No records to clean up.' ) );

			return self::SUCCESS;
		}

		if ( $dryRun ) {
			$this->warn( __( 'Dry run mode - no records were deleted.' ) );

			return self::SUCCESS;
		}

		if ( ! $this->option( 'force' ) && ! $this->confirm( sprintf( __( 'Delete %s records?' ), number_format( $totalCount ) ) ) ) {
			$this->info( __( 'Cleanup cancelled.' ) );

			return self::SUCCESS;
		}

		$this->newLine();

		try {
			DB::transaction( function () use ( $cutoff ): void {
				$this->components->task( __( 'Deleting page views' ), function () use ( $cutoff ): bool {
					PageView::where( 'created_at', '<', $cutoff )->delete();

					return true;
				} );

				$this->components->task( __( 'Deleting events' ), function () use ( $cutoff ): bool {
					Event::where( 'created_at', '<', $cutoff )->delete();

					return true;
				} );

				$this->components->task( __( 'Deleting sessions' ), function () use ( $cutoff ): bool {
					Session::where( 'created_at', '<', $cutoff )->delete();

					return true;
				} );
			} );

			$this->newLine();
			$this->info( sprintf( __( 'Successfully deleted %s records.' ), number_format( $totalCount ) ) );

			return self::SUCCESS;
		} catch ( Throwable $e ) {
			$this->newLine();
			$this->error( __( 'Cleanup failed. All changes have been rolled back.' ) );
			$this->line( $e->getMessage() );

			return self::FAILURE;
		}
	}
}
