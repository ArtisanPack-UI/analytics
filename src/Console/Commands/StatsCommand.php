<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Console\Commands;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Illuminate\Console\Command;

/**
 * Command to display analytics statistics.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Console\Commands
 */
class StatsCommand extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'analytics:stats
		{--period=7d : Date range (today, 7d, 30d, 90d)}
		{--site= : Site ID to filter by}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Display analytics statistics';

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
		$range   = $this->getDateRange();
		$filters = $this->getFilters();

		$this->info( __( 'Analytics Statistics' ) );
		$this->line( sprintf( __( 'Period: %s to %s' ), $range->startDate->format( 'Y-m-d' ), $range->endDate->format( 'Y-m-d' ) ) );
		$this->newLine();

		$stats = $query->getStats( $range, true, $filters );

		$this->table(
			[ __( 'Metric' ), __( 'Value' ), __( 'Change' ) ],
			[
				[
					__( 'Page Views' ),
					number_format( $stats['pageviews'] ),
					$this->formatChange( $stats['comparison']['pageviews'] ?? null ),
				],
				[
					__( 'Unique Visitors' ),
					number_format( $stats['visitors'] ),
					$this->formatChange( $stats['comparison']['visitors'] ?? null ),
				],
				[
					__( 'Sessions' ),
					number_format( $stats['sessions'] ),
					$this->formatChange( $stats['comparison']['sessions'] ?? null ),
				],
				[
					__( 'Bounce Rate' ),
					$stats['bounce_rate'] . '%',
					$this->formatChange( $stats['comparison']['bounce_rate'] ?? null, true ),
				],
				[
					__( 'Avg Session Duration' ),
					$this->formatDuration( $stats['avg_session_duration'] ),
					$this->formatChange( $stats['comparison']['avg_session_duration'] ?? null ),
				],
				[
					__( 'Pages Per Session' ),
					$stats['pages_per_session'],
					'-',
				],
				[
					__( 'Real-time Visitors' ),
					$stats['realtime_visitors'],
					'-',
				],
			],
		);

		return self::SUCCESS;
	}

	/**
	 * Get the date range based on period option.
	 *
	 * @return DateRange
	 *
	 * @since 1.0.0
	 */
	protected function getDateRange(): DateRange
	{
		$period = $this->option( 'period' );

		return match ( $period ) {
			'today' => DateRange::today(),
			'7d'    => DateRange::last7Days(),
			'30d'   => DateRange::last30Days(),
			'90d'   => DateRange::last90Days(),
			default => DateRange::last7Days(),
		};
	}

	/**
	 * Get filters from options.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	protected function getFilters(): array
	{
		$filters = [];

		$siteId = $this->option( 'site' );
		if ( null !== $siteId && '' !== $siteId ) {
			$filters['site_id'] = (int) $siteId;
		}

		return $filters;
	}

	/**
	 * Format a change value for display.
	 *
	 * @param array<string, mixed>|null $comparison The comparison data.
	 * @param bool                      $invert     Whether lower is better.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	protected function formatChange( ?array $comparison, bool $invert = false ): string
	{
		if ( null === $comparison ) {
			return '-';
		}

		$change = $comparison['change'] ?? 0;
		$prefix = $change > 0 ? '+' : '';

		$color = 'white';
		if ( $change > 0 ) {
			$color = $invert ? 'red' : 'green';
		} elseif ( $change < 0 ) {
			$color = $invert ? 'green' : 'red';
		}

		return "<fg={$color}>{$prefix}{$change}%</>";
	}

	/**
	 * Format duration in seconds to human readable.
	 *
	 * @param int $seconds The duration in seconds.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	protected function formatDuration( int $seconds ): string
	{
		if ( $seconds < 60 ) {
			return $seconds . 's';
		}

		$minutes = floor( $seconds / 60 );
		$secs    = $seconds % 60;

		return $minutes . 'm ' . $secs . 's';
	}
}
