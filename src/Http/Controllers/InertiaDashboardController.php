<?php

/**
 * Inertia Dashboard Controller.
 *
 * Handles dashboard requests when the analytics dashboard driver is set
 * to 'inertia', returning Inertia responses with analytics data as
 * typed page props for React/Vue dashboards.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.1.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Controllers;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Http\Resources\BrowserBreakdownResource;
use ArtisanPackUI\Analytics\Http\Resources\CountryBreakdownResource;
use ArtisanPackUI\Analytics\Http\Resources\DeviceBreakdownResource;
use ArtisanPackUI\Analytics\Http\Resources\EventBreakdownResource;
use ArtisanPackUI\Analytics\Http\Resources\PageViewTimeSeriesResource;
use ArtisanPackUI\Analytics\Http\Resources\StatsResource;
use ArtisanPackUI\Analytics\Http\Resources\TopPageResource;
use ArtisanPackUI\Analytics\Http\Resources\TrafficSourceResource;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Inertia Dashboard Controller.
 *
 * Returns Inertia::render() responses for each analytics dashboard page,
 * passing serialized analytics data as page props. Mirrors the same data
 * loading as the Livewire AnalyticsDashboard component.
 *
 * @package    ArtisanPack_UI
 * @subpackage Analytics
 *
 * @since      1.1.0
 */
class InertiaDashboardController extends Controller
{
	/**
	 * Create a new InertiaDashboardController instance.
	 *
	 * @param AnalyticsQuery $analyticsQuery The analytics query service.
	 *
	 * @since 1.1.0
	 */
	public function __construct(
		protected AnalyticsQuery $analyticsQuery,
	) {
	}

	/**
	 * Show the analytics dashboard overview.
	 *
	 * GET /analytics
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return Response
	 *
	 * @since 1.1.0
	 */
	public function index( Request $request ): Response
	{
		$range   = $this->getDateRange( $request );
		$filters = $this->getFilters( $request );

		$stats          = $this->analyticsQuery->getStats( $range, true, $filters );
		$chartData      = $this->analyticsQuery->getPageViews( $range, 'day', $filters );
		$topPages       = $this->analyticsQuery->getTopPages( $range, 10, $filters );
		$trafficSources = $this->analyticsQuery->getTrafficSources( $range, 10, $filters );

		return Inertia::render( $this->getPageComponent( 'dashboard' ), [
			'stats'            => new StatsResource( $stats ),
			'chartData'        => PageViewTimeSeriesResource::collection( $chartData ),
			'topPages'         => TopPageResource::collection( $topPages ),
			'trafficSources'   => TrafficSourceResource::collection( $trafficSources ),
			'dateRange'        => $range->toArray(),
			'dateRangePreset'  => $this->getDateRangePreset( $request ),
			'dateRangePresets' => $this->getDateRangePresets(),
			'filters'          => $filters,
		] );
	}

	/**
	 * Show the pages analytics view.
	 *
	 * GET /analytics/pages
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return Response
	 *
	 * @since 1.1.0
	 */
	public function pages( Request $request ): Response
	{
		$range   = $this->getDateRange( $request );
		$limit   = $this->getLimit( $request );
		$filters = $this->getFilters( $request );

		$topPages  = $this->analyticsQuery->getTopPages( $range, $limit, $filters );
		$chartData = $this->analyticsQuery->getPageViews( $range, 'day', $filters );

		return Inertia::render( $this->getPageComponent( 'pages' ), [
			'topPages'         => TopPageResource::collection( $topPages ),
			'chartData'        => PageViewTimeSeriesResource::collection( $chartData ),
			'dateRange'        => $range->toArray(),
			'dateRangePreset'  => $this->getDateRangePreset( $request ),
			'dateRangePresets' => $this->getDateRangePresets(),
			'filters'          => $filters,
		] );
	}

	/**
	 * Show the traffic sources analytics view.
	 *
	 * GET /analytics/traffic
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return Response
	 *
	 * @since 1.1.0
	 */
	public function traffic( Request $request ): Response
	{
		$range   = $this->getDateRange( $request );
		$limit   = $this->getLimit( $request );
		$filters = $this->getFilters( $request );

		$trafficSources = $this->analyticsQuery->getTrafficSources( $range, $limit, $filters );

		return Inertia::render( $this->getPageComponent( 'traffic' ), [
			'trafficSources'   => TrafficSourceResource::collection( $trafficSources ),
			'dateRange'        => $range->toArray(),
			'dateRangePreset'  => $this->getDateRangePreset( $request ),
			'dateRangePresets' => $this->getDateRangePresets(),
			'filters'          => $filters,
		] );
	}

	/**
	 * Show the audience analytics view.
	 *
	 * GET /analytics/audience
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return Response
	 *
	 * @since 1.1.0
	 */
	public function audience( Request $request ): Response
	{
		$range   = $this->getDateRange( $request );
		$filters = $this->getFilters( $request );

		$deviceBreakdown  = $this->analyticsQuery->getDeviceBreakdown( $range, $filters );
		$browserBreakdown = $this->analyticsQuery->getBrowserBreakdown( $range, 10, $filters );
		$countryBreakdown = $this->analyticsQuery->getCountryBreakdown( $range, 10, $filters );

		return Inertia::render( $this->getPageComponent( 'audience' ), [
			'deviceBreakdown'  => DeviceBreakdownResource::collection( $deviceBreakdown ),
			'browserBreakdown' => BrowserBreakdownResource::collection( $browserBreakdown ),
			'countryBreakdown' => CountryBreakdownResource::collection( $countryBreakdown ),
			'dateRange'        => $range->toArray(),
			'dateRangePreset'  => $this->getDateRangePreset( $request ),
			'dateRangePresets' => $this->getDateRangePresets(),
			'filters'          => $filters,
		] );
	}

	/**
	 * Show the events analytics view.
	 *
	 * GET /analytics/events
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return Response
	 *
	 * @since 1.1.0
	 */
	public function events( Request $request ): Response
	{
		$range   = $this->getDateRange( $request );
		$limit   = $this->getLimit( $request );
		$filters = $this->getFilters( $request );

		$eventBreakdown = $this->analyticsQuery->getEventBreakdown( $range, $limit, $filters );

		return Inertia::render( $this->getPageComponent( 'events' ), [
			'eventBreakdown'   => EventBreakdownResource::collection( $eventBreakdown ),
			'dateRange'        => $range->toArray(),
			'dateRangePreset'  => $this->getDateRangePreset( $request ),
			'dateRangePresets' => $this->getDateRangePresets(),
			'filters'          => $filters,
		] );
	}

	/**
	 * Show the realtime analytics view.
	 *
	 * GET /analytics/realtime
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return Response
	 *
	 * @since 1.1.0
	 */
	public function realtime( Request $request ): Response
	{
		$minutes  = max( 1, min( 30, $request->integer( 'minutes', 5 ) ) );
		$realtime = $this->analyticsQuery->getRealtime( $minutes );

		return Inertia::render( $this->getPageComponent( 'realtime' ), [
			'realtime' => $realtime,
		] );
	}

	/**
	 * Get the Inertia page component name for a dashboard page.
	 *
	 * @param string $page The page key.
	 *
	 * @return string The Inertia page component name.
	 *
	 * @since 1.1.0
	 */
	protected function getPageComponent( string $page ): string
	{
		$configValue = config( "artisanpack.analytics.inertia.pages.{$page}" );

		if ( is_string( $configValue ) && '' !== $configValue ) {
			return $configValue;
		}

		$defaults = [
			'dashboard' => 'Analytics/Dashboard',
			'pages'     => 'Analytics/Pages',
			'traffic'   => 'Analytics/Traffic',
			'audience'  => 'Analytics/Audience',
			'events'    => 'Analytics/Events',
			'realtime'  => 'Analytics/Realtime',
		];

		return $defaults[ $page ] ?? 'Analytics/Dashboard';
	}

	/**
	 * Get the date range from request parameters.
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return DateRange
	 *
	 * @since 1.1.0
	 */
	protected function getDateRange( Request $request ): DateRange
	{
		$startDate = $request->query( 'start_date' );
		$endDate   = $request->query( 'end_date' );

		if ( is_string( $startDate ) && is_string( $endDate ) && '' !== $startDate && '' !== $endDate ) {
			try {
				return DateRange::fromStrings( $startDate, $endDate );
			} catch ( Throwable ) {
				// Fall through to default preset on malformed dates
			}
		}

		$preset = $this->getDateRangePreset( $request );

		return match ( $preset ) {
			'today'      => DateRange::today(),
			'yesterday'  => DateRange::yesterday(),
			'7d'         => DateRange::last7Days(),
			'30d'        => DateRange::last30Days(),
			'90d'        => DateRange::last90Days(),
			'this_week'  => DateRange::thisWeek(),
			'last_week'  => DateRange::lastWeek(),
			'this_month' => DateRange::thisMonth(),
			'last_month' => DateRange::lastMonth(),
			'this_year'  => DateRange::thisYear(),
			default      => DateRange::last30Days(),
		};
	}

	/**
	 * Get the date range preset from the request.
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return string The date range preset.
	 *
	 * @since 1.1.0
	 */
	protected function getDateRangePreset( Request $request ): string
	{
		$allowedPresets = [
			'today', 'yesterday', '7d', '30d', '90d',
			'this_week', 'last_week', 'this_month', 'last_month', 'this_year',
		];

		$period = $request->query( 'period' );

		if ( is_string( $period ) && in_array( $period, $allowedPresets, true ) ) {
			return $period;
		}

		$configDefault = config( 'artisanpack.analytics.dashboard.default_date_range', '30d' );

		return is_string( $configDefault ) ? $configDefault : '30d';
	}

	/**
	 * Get available date range presets.
	 *
	 * @return array<string, string>
	 *
	 * @since 1.1.0
	 */
	protected function getDateRangePresets(): array
	{
		return [
			'today'      => __( 'Today' ),
			'yesterday'  => __( 'Yesterday' ),
			'7d'         => __( 'Last 7 days' ),
			'30d'        => __( 'Last 30 days' ),
			'90d'        => __( 'Last 90 days' ),
			'this_week'  => __( 'This week' ),
			'last_week'  => __( 'Last week' ),
			'this_month' => __( 'This month' ),
			'last_month' => __( 'Last month' ),
			'this_year'  => __( 'This year' ),
		];
	}

	/**
	 * Get filters from request parameters.
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.1.0
	 */
	protected function getFilters( Request $request ): array
	{
		$filters = [];

		$siteId = $request->query( 'site_id' );
		if ( is_string( $siteId ) && ctype_digit( $siteId ) && '0' !== $siteId ) {
			$filters['site_id'] = (int) $siteId;
		}

		$path = $request->query( 'path' );
		if ( is_string( $path ) && '' !== $path ) {
			$filters['path'] = $path;
		}

		$category = $request->query( 'category' );
		if ( is_string( $category ) && '' !== $category ) {
			$filters['category'] = $category;
		}

		return $filters;
	}

	/**
	 * Get the result limit from request parameters.
	 *
	 * @param Request $request The HTTP request.
	 * @param int     $default The default limit.
	 * @param int     $max     The maximum allowed limit.
	 *
	 * @return int
	 *
	 * @since 1.1.0
	 */
	protected function getLimit( Request $request, int $default = 10, int $max = 100 ): int
	{
		$limit = $request->integer( 'limit', $default );

		return max( 1, min( $max, $limit ) );
	}
}
