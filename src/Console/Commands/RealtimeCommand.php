<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Console\Commands;

use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Illuminate\Console\Command;

/**
 * Command to display real-time analytics.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Console\Commands
 */
class RealtimeCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'analytics:realtime
		{--minutes=5 : Minutes to consider as real-time}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Display real-time visitor data';

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
		$minutes = (int) $this->option( 'minutes' );

		$realtime = $query->getRealtime( $minutes );

		$this->info( __( 'Real-time Analytics' ) );
		$this->line( sprintf( __( 'Active visitors in the last %d minutes' ), $minutes ) );
		$this->newLine();

		$this->table(
			[ __( 'Metric' ), __( 'Value' ) ],
			[
				[ __( 'Active Visitors' ), number_format( $realtime['active_visitors'] ) ],
				[ __( 'Timestamp' ), $realtime['timestamp'] ],
			],
		);

		return self::SUCCESS;
	}
}
