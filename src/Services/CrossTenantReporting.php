<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Models\Aggregate;
use ArtisanPackUI\Analytics\Models\Conversion;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\Models\Visitor;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for cross-tenant platform-wide reporting.
 *
 * Provides analytics across all sites for admin/platform dashboards.
 * Bypasses site scoping to aggregate data across the entire platform.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Services
 */
class CrossTenantReporting
{
	/**
	 * Allowed metrics for aggregate queries to prevent SQL injection.
	 *
	 * @var array<int, string>
	 */
	protected const ALLOWED_METRICS = [
		'pageviews',
		'visitors',
		'sessions',
		'events',
		'conversions',
		'bounce_rate',
		'avg_duration',
	];

	/**
	 * Get platform-wide statistics.
	 *
	 * @param CarbonInterface|DateRange|null $startOrRange Optional start date or DateRange.
	 * @param CarbonInterface|null           $endDate      Optional end date (only used with Carbon start).
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function getPlatformStats( CarbonInterface|DateRange|null $startOrRange = null, ?CarbonInterface $endDate = null ): array
	{
		$range = $this->resolveRange( $startOrRange, $endDate );

		return [
			'total_sites'      => Site::where( 'is_active', true )->count(),
			'active_sites'     => $this->getActiveSitesCount( $range ),
			'total_visitors'   => Visitor::allSites()
				->whereBetween( 'first_seen_at', [ $range->startDate, $range->endDate ] )
				->count(),
			'total_sessions'   => Session::allSites()
				->whereBetween( 'started_at', [ $range->startDate, $range->endDate ] )
				->count(),
			'total_pageviews'  => PageView::allSites()
				->whereBetween( 'created_at', [ $range->startDate, $range->endDate ] )
				->count(),
			'total_events'     => Event::allSites()
				->whereBetween( 'created_at', [ $range->startDate, $range->endDate ] )
				->count(),
			'total_conversions' => Conversion::allSites()
				->whereBetween( 'created_at', [ $range->startDate, $range->endDate ] )
				->count(),
			'period'           => [
				'start' => $range->startDate->toDateString(),
				'end'   => $range->endDate->toDateString(),
			],
		];
	}

	/**
	 * Get top sites by traffic.
	 *
	 * @param CarbonInterface|DateRange|null $startOrRange Optional start date or DateRange.
	 * @param CarbonInterface|int|null       $endOrLimit   Optional end date or limit.
	 * @param int                            $limit        Number of sites to return.
	 *
	 * @return Collection<int, array<string, mixed>>
	 *
	 * @since 1.0.0
	 */
	public function getTopSitesByTraffic( CarbonInterface|DateRange|null $startOrRange = null, CarbonInterface|int|null $endOrLimit = null, int $limit = 10 ): Collection
	{
		// Handle flexible argument combinations
		if ( is_int( $endOrLimit ) ) {
			$limit      = $endOrLimit;
			$endOrLimit = null;
		}

		$range = $this->resolveRange( $startOrRange, $endOrLimit instanceof CarbonInterface ? $endOrLimit : null );

		$sites = PageView::allSites()
			->select( 'site_id', DB::raw( 'COUNT(*) as pageviews' ) )
			->whereBetween( 'created_at', [ $range->startDate, $range->endDate ] )
			->groupBy( 'site_id' )
			->orderByDesc( 'pageviews' )
			->limit( $limit )
			->get();

		// Enrich with site details
		$siteIds  = $sites->pluck( 'site_id' );
		$siteData = Site::whereIn( 'id', $siteIds )->get()->keyBy( 'id' );

		return $sites->map( function ( $item ) use ( $siteData, $range ) {
			$site = $siteData->get( $item->site_id );

			return [
				'site_id'    => $item->site_id,
				'name'       => $site?->name ?? __( 'Unknown' ),
				'domain'     => $site?->domain,
				'pageviews'  => (int) $item->pageviews,
				'visitors'   => $this->getVisitorCountForSite( $item->site_id, $range ),
				'sessions'   => $this->getSessionCountForSite( $item->site_id, $range ),
			];
		} );
	}

	/**
	 * Get sites with growth compared to the previous period.
	 *
	 * Compares the current period to the previous period of equal length.
	 * For example, if the current period is 7 days, it compares to the 7 days before that.
	 *
	 * @param CarbonInterface|DateRange|null $startOrRange Optional start date or DateRange.
	 * @param CarbonInterface|null           $endDate      Optional end date (only used with Carbon start).
	 * @param int                            $limit        Number of sites to return.
	 *
	 * @return Collection<int, array<string, mixed>>
	 *
	 * @since 1.0.0
	 */
	public function getSitesWithGrowth( CarbonInterface|DateRange|null $startOrRange = null, ?CarbonInterface $endDate = null, int $limit = 10 ): Collection
	{
		$range         = $this->resolveRange( $startOrRange, $endDate );
		$previousRange = $range->previousPeriod();

		// Get current period data
		$currentData = PageView::allSites()
			->select( 'site_id', DB::raw( 'COUNT(*) as pageviews' ) )
			->whereBetween( 'created_at', [ $range->startDate, $range->endDate ] )
			->groupBy( 'site_id' )
			->get()
			->keyBy( 'site_id' );

		// Get previous period data
		$previousData = PageView::allSites()
			->select( 'site_id', DB::raw( 'COUNT(*) as pageviews' ) )
			->whereBetween( 'created_at', [ $previousRange->startDate, $previousRange->endDate ] )
			->groupBy( 'site_id' )
			->get()
			->keyBy( 'site_id' );

		// Calculate growth for all sites
		$growth = collect();
		$sites  = Site::where( 'is_active', true )->get();

		foreach ( $sites as $site ) {
			$current  = $currentData->get( $site->id )?->pageviews ?? 0;
			$previous = $previousData->get( $site->id )?->pageviews ?? 0;

			$changePercent = 0 === $previous ? ( $current > 0 ? 100 : 0 ) : round( ( ( $current - $previous ) / $previous ) * 100, 2 );

			$growth->push( [
				'site_id'        => $site->id,
				'name'           => $site->name,
				'domain'         => $site->domain,
				'current'        => (int) $current,
				'previous'       => (int) $previous,
				'change'         => (int) ( $current - $previous ),
				'change_percent' => $changePercent,
			] );
		}

		return $growth
			->sortByDesc( 'change_percent' )
			->take( $limit )
			->values();
	}

	/**
	 * Get aggregates grouped by site.
	 *
	 * Only metrics in ALLOWED_METRICS are permitted to prevent SQL injection.
	 *
	 * @param string                         $metric       The metric to aggregate (pageviews, visitors, etc.).
	 * @param CarbonInterface|DateRange|null $startOrRange Optional start date or DateRange.
	 * @param CarbonInterface|int|null       $endOrLimit   Optional end date or limit.
	 * @param int                            $limit        Number of sites to return.
	 *
	 * @return Collection<int, array<string, mixed>>
	 *
	 * @since 1.0.0
	 */
	public function getAggregatesBySite( string $metric, CarbonInterface|DateRange|null $startOrRange = null, CarbonInterface|int|null $endOrLimit = null, int $limit = 10 ): Collection
	{
		// Validate metric against allowlist to prevent SQL injection
		if ( ! in_array( $metric, self::ALLOWED_METRICS, true ) ) {
			$metric = 'pageviews';
		}

		// Handle flexible argument combinations
		if ( is_int( $endOrLimit ) ) {
			$limit      = $endOrLimit;
			$endOrLimit = null;
		}

		$range = $this->resolveRange( $startOrRange, $endOrLimit instanceof CarbonInterface ? $endOrLimit : null );

		$aggregates = Aggregate::allSites()
			->select( 'site_id', DB::raw( "SUM({$metric}) as total" ) )
			->whereBetween( 'date', [ $range->startDate->toDateString(), $range->endDate->toDateString() ] )
			->where( 'period', 'day' )
			->groupBy( 'site_id' )
			->orderByDesc( 'total' )
			->limit( $limit )
			->get();

		$siteIds  = $aggregates->pluck( 'site_id' );
		$siteData = Site::whereIn( 'id', $siteIds )->get()->keyBy( 'id' );

		return $aggregates->map( function ( $item ) use ( $siteData, $metric ) {
			$site = $siteData->get( $item->site_id );

			return [
				'site_id' => $item->site_id,
				'name'    => $site?->name ?? __( 'Unknown' ),
				'domain'  => $site?->domain,
				'metric'  => $metric,
				'value'   => (int) $item->total,
			];
		} );
	}

	/**
	 * Export platform report data.
	 *
	 * @param CarbonInterface|DateRange|null $startOrRange Optional start date or DateRange.
	 * @param CarbonInterface|string|null    $endOrFormat  Optional end date or format string.
	 * @param string                         $format       The export format (csv, json, xlsx).
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function exportPlatformReport( CarbonInterface|DateRange|null $startOrRange = null, CarbonInterface|string|null $endOrFormat = null, string $format = 'json' ): array
	{
		// Handle the format string (for backward compatibility)
		if ( is_string( $endOrFormat ) && in_array( $endOrFormat, [ 'csv', 'json' ], true ) ) {
			$format      = $endOrFormat;
			$endOrFormat = null;
		}

		$range = $this->resolveRange( $startOrRange, $endOrFormat instanceof CarbonInterface ? $endOrFormat : null );

		$data = [
			'generated_at'       => now()->toIso8601String(),
			'period'             => [
				'start' => $range->startDate->toDateString(),
				'end'   => $range->endDate->toDateString(),
			],
			'platform_stats'     => $this->getPlatformStats( $range ),
			'top_sites'          => $this->getTopSitesByTraffic( $range, null, 20 )->toArray(),
			'sites_with_growth'  => $this->getSitesWithGrowth( $range, null, 20 )->toArray(),
			'pageviews_by_site'  => $this->getAggregatesBySite( 'pageviews', $range, null, 20 )->toArray(),
			'visitors_by_site'   => $this->getAggregatesBySite( 'visitors', $range, null, 20 )->toArray(),
		];

		return $this->formatExport( $data, $format );
	}

	/**
	 * Get daily platform-wide metrics.
	 *
	 * @param CarbonInterface|DateRange|null $startOrRange Optional start date or DateRange.
	 * @param CarbonInterface|null           $endDate      Optional end date.
	 *
	 * @return Collection<int, array<string, mixed>>
	 *
	 * @since 1.0.0
	 */
	public function getDailyPlatformMetrics( CarbonInterface|DateRange|null $startOrRange = null, ?CarbonInterface $endDate = null ): Collection
	{
		$range = $this->resolveRange( $startOrRange, $endDate );

		return PageView::allSites()
			->select(
				DB::raw( 'DATE(created_at) as date' ),
				DB::raw( 'COUNT(*) as pageviews' ),
				DB::raw( 'COUNT(DISTINCT visitor_id) as visitors' ),
				DB::raw( 'COUNT(DISTINCT session_id) as sessions' ),
			)
			->whereBetween( 'created_at', [ $range->startDate, $range->endDate ] )
			->groupBy( DB::raw( 'DATE(created_at)' ) )
			->orderBy( 'date' )
			->get()
			->map( fn ( $item ) => [
				'date'      => $item->date,
				'pageviews' => (int) $item->pageviews,
				'visitors'  => (int) $item->visitors,
				'sessions'  => (int) $item->sessions,
			] );
	}

	/**
	 * Get the count of sites that had activity in the period.
	 *
	 * @param DateRange $range The date range.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	protected function getActiveSitesCount( DateRange $range ): int
	{
		return PageView::allSites()
			->whereBetween( 'created_at', [ $range->startDate, $range->endDate ] )
			->distinct( 'site_id' )
			->count( 'site_id' );
	}

	/**
	 * Get visitor count for a specific site.
	 *
	 * @param int       $siteId The site ID.
	 * @param DateRange $range  The date range.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	protected function getVisitorCountForSite( int $siteId, DateRange $range ): int
	{
		return Visitor::allSites()
			->where( 'site_id', $siteId )
			->whereBetween( 'first_seen_at', [ $range->startDate, $range->endDate ] )
			->count();
	}

	/**
	 * Get session count for a specific site.
	 *
	 * @param int       $siteId The site ID.
	 * @param DateRange $range  The date range.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	protected function getSessionCountForSite( int $siteId, DateRange $range ): int
	{
		return Session::allSites()
			->where( 'site_id', $siteId )
			->whereBetween( 'started_at', [ $range->startDate, $range->endDate ] )
			->count();
	}

	/**
	 * Format export data for the specified format.
	 *
	 * Supported formats: csv, json.
	 * Note: xlsx export is not supported as it requires the PhpSpreadsheet library.
	 *
	 * @param array<string, mixed> $data   The data to export.
	 * @param string               $format The export format (csv or json).
	 *
	 * @return array<string, mixed>
	 */
	protected function formatExport( array $data, string $format ): array
	{
		$timestamp = now()->format( 'Y-m-d_His' );

		return match ( $format ) {
			'csv' => [
				'content'      => $this->toCsv( $data ),
				'content_type' => 'text/csv',
				'filename'     => "platform-report-{$timestamp}.csv",
			],
			default => [
				'content'      => json_encode( $data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT ),
				'content_type' => 'application/json',
				'filename'     => "platform-report-{$timestamp}.json",
			],
		};
	}

	/**
	 * Convert data to CSV format.
	 *
	 * @param array<string, mixed> $data The data to convert.
	 *
	 * @return string
	 */
	protected function toCsv( array $data ): string
	{
		$output = fopen( 'php://temp', 'r+' );

		// Write platform stats
		fputcsv( $output, [ 'Metric', 'Value' ] );

		foreach ( $data['platform_stats'] as $key => $value ) {
			if ( ! is_array( $value ) ) {
				fputcsv( $output, [ $key, $value ] );
			}
		}

		fputcsv( $output, [] );

		// Write top sites
		if ( ! empty( $data['top_sites'] ) ) {
			fputcsv( $output, array_keys( $data['top_sites'][0] ?? [] ) );

			foreach ( $data['top_sites'] as $site ) {
				fputcsv( $output, array_values( $site ) );
			}
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}

	/**
	 * Resolve a DateRange from various input types.
	 *
	 * @param CarbonInterface|DateRange|null $startOrRange Start date, DateRange, or null.
	 * @param CarbonInterface|null           $endDate      End date (only used with Carbon start).
	 *
	 * @return DateRange
	 */
	protected function resolveRange( CarbonInterface|DateRange|null $startOrRange, ?CarbonInterface $endDate ): DateRange
	{
		if ( $startOrRange instanceof DateRange ) {
			return $startOrRange;
		}

		if ( $startOrRange instanceof CarbonInterface && $endDate instanceof CarbonInterface ) {
			return DateRange::fromCarbon( $startOrRange, $endDate );
		}

		return DateRange::last30Days();
	}
}
