<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Controllers;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Analytics Query Controller.
 *
 * Handles API requests for analytics data queries and delegates
 * processing to the AnalyticsQuery service.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Controllers
 */
class AnalyticsQueryController extends Controller
{
	/**
	 * Create a new AnalyticsQueryController instance.
	 *
	 * @param AnalyticsQuery $analyticsQuery The analytics query service.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		protected AnalyticsQuery $analyticsQuery,
	) {
	}

	/**
	 * Get comprehensive statistics.
	 *
	 * GET /api/analytics/stats
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return JsonResponse
	 *
	 * @since 1.0.0
	 */
	public function stats( Request $request ): JsonResponse
	{
		$range       = $this->getDateRange( $request );
		$withCompare = $request->boolean( 'compare', true );
		$filters     = $this->getFilters( $request );

		$stats = $this->analyticsQuery->getStats( $range, $withCompare, $filters );

		return response()->json( [
			'success' => true,
			'data'    => $stats,
			'range'   => $range->toArray(),
		] );
	}

	/**
	 * Get top pages.
	 *
	 * GET /api/analytics/pages
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return JsonResponse
	 *
	 * @since 1.0.0
	 */
	public function pages( Request $request ): JsonResponse
	{
		$range   = $this->getDateRange( $request );
		$limit   = $this->getLimit( $request );
		$filters = $this->getFilters( $request );

		$pages = $this->analyticsQuery->getTopPages( $range, $limit, $filters );

		return response()->json( [
			'success' => true,
			'data'    => $pages,
			'range'   => $range->toArray(),
		] );
	}

	/**
	 * Get traffic sources.
	 *
	 * GET /api/analytics/sources
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return JsonResponse
	 *
	 * @since 1.0.0
	 */
	public function sources( Request $request ): JsonResponse
	{
		$range   = $this->getDateRange( $request );
		$limit   = $this->getLimit( $request );
		$filters = $this->getFilters( $request );

		$sources = $this->analyticsQuery->getTrafficSources( $range, $limit, $filters );

		return response()->json( [
			'success' => true,
			'data'    => $sources,
			'range'   => $range->toArray(),
		] );
	}

	/**
	 * Get event breakdown.
	 *
	 * GET /api/analytics/events
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return JsonResponse
	 *
	 * @since 1.0.0
	 */
	public function events( Request $request ): JsonResponse
	{
		$range   = $this->getDateRange( $request );
		$limit   = $this->getLimit( $request );
		$filters = $this->getFilters( $request );

		$events = $this->analyticsQuery->getEventBreakdown( $range, $limit, $filters );

		return response()->json( [
			'success' => true,
			'data'    => $events,
			'range'   => $range->toArray(),
		] );
	}

	/**
	 * Get device breakdown.
	 *
	 * GET /api/analytics/devices
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return JsonResponse
	 *
	 * @since 1.0.0
	 */
	public function devices( Request $request ): JsonResponse
	{
		$range   = $this->getDateRange( $request );
		$filters = $this->getFilters( $request );

		$devices = $this->analyticsQuery->getDeviceBreakdown( $range, $filters );

		return response()->json( [
			'success' => true,
			'data'    => $devices,
			'range'   => $range->toArray(),
		] );
	}

	/**
	 * Get country breakdown.
	 *
	 * GET /api/analytics/countries
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return JsonResponse
	 *
	 * @since 1.0.0
	 */
	public function countries( Request $request ): JsonResponse
	{
		$range   = $this->getDateRange( $request );
		$limit   = $this->getLimit( $request );
		$filters = $this->getFilters( $request );

		$countries = $this->analyticsQuery->getCountryBreakdown( $range, $limit, $filters );

		return response()->json( [
			'success' => true,
			'data'    => $countries,
			'range'   => $range->toArray(),
		] );
	}

	/**
	 * Get real-time visitor data.
	 *
	 * GET /api/analytics/realtime
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return JsonResponse
	 *
	 * @since 1.0.0
	 */
	public function realtime( Request $request ): JsonResponse
	{
		$minutes = $request->integer( 'minutes', 5 );

		// Clamp minutes to a reasonable range
		$minutes = max( 1, min( 30, $minutes ) );

		$realtime = $this->analyticsQuery->getRealtime( $minutes );

		return response()->json( [
			'success' => true,
			'data'    => $realtime,
		] );
	}

	/**
	 * Get visitor data (API-key authenticated).
	 *
	 * GET /api/analytics/v1/visitors
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return JsonResponse
	 *
	 * @since 1.0.0
	 */
	public function visitors( Request $request ): JsonResponse
	{
		$range   = $this->getDateRange( $request );
		$filters = $this->getFilters( $request );

		$visitors    = $this->analyticsQuery->getVisitors( $range, $filters );
		$pageviews   = $this->analyticsQuery->getPageViewCount( $range, $filters );
		$sessions    = $this->analyticsQuery->getSessions( $range, $filters );
		$bounceRate  = $this->analyticsQuery->getBounceRate( $range, $filters );
		$avgDuration = $this->analyticsQuery->getAverageSessionDuration( $range, $filters );

		return response()->json( [
			'success' => true,
			'data'    => [
				'visitors'             => $visitors,
				'pageviews'            => $pageviews,
				'sessions'             => $sessions,
				'bounce_rate'          => $bounceRate,
				'avg_session_duration' => $avgDuration,
			],
			'range' => $range->toArray(),
		] );
	}

	/**
	 * Get the date range from request parameters.
	 *
	 * Supports multiple formats:
	 * - start_date & end_date: Custom date range
	 * - period: Predefined period (7d, 30d, 90d, today, yesterday, etc.)
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return DateRange
	 *
	 * @since 1.0.0
	 */
	protected function getDateRange( Request $request ): DateRange
	{
		// Check for custom date range first
		$startDate = $request->query( 'start_date' );
		$endDate   = $request->query( 'end_date' );

		if ( is_string( $startDate ) && is_string( $endDate ) && '' !== $startDate && '' !== $endDate ) {
			return DateRange::fromStrings( $startDate, $endDate );
		}

		// Use predefined period
		$period = $request->query( 'period', '7d' );

		return match ( $period ) {
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
			default      => DateRange::last7Days(),
		};
	}

	/**
	 * Get filters from request parameters.
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	protected function getFilters( Request $request ): array
	{
		$filters = [];

		// Site ID filter (from request or resolved site)
		$siteId = $this->getSiteId( $request );
		if ( null !== $siteId ) {
			$filters['site_id'] = $siteId;
		}

		// Path filter
		$path = $request->query( 'path' );
		if ( is_string( $path ) && '' !== $path ) {
			$filters['path'] = $path;
		}

		// Category filter (for events)
		$category = $request->query( 'category' );
		if ( is_string( $category ) && '' !== $category ) {
			$filters['category'] = $category;
		}

		// Source package filter
		$sourcePackage = $request->query( 'source_package' );
		if ( is_string( $sourcePackage ) && '' !== $sourcePackage ) {
			$filters['source_package'] = $sourcePackage;
		}

		// Goal ID filter
		$goalId = $request->query( 'goal_id' );
		if ( is_numeric( $goalId ) ) {
			$filters['goal_id'] = (int) $goalId;
		}

		return $filters;
	}

	/**
	 * Get the limit from request parameters.
	 *
	 * @param Request $request The HTTP request.
	 * @param int     $default The default limit.
	 * @param int     $max     The maximum allowed limit.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	protected function getLimit( Request $request, int $default = 10, int $max = 100 ): int
	{
		$limit = $request->integer( 'limit', $default );

		return max( 1, min( $max, $limit ) );
	}

	/**
	 * Get the site ID from the request.
	 *
	 * Checks multiple sources:
	 * 1. Query parameter site_id
	 * 2. Header X-Analytics-Site-Id
	 * 3. Resolved site from TenantManager (set by middleware)
	 *
	 * @param Request $request The HTTP request.
	 *
	 * @return int|null
	 *
	 * @since 1.0.0
	 */
	protected function getSiteId( Request $request ): ?int
	{
		// Check query parameter
		$siteId = $request->query( 'site_id' );

		if ( null === $siteId ) {
			// Check header
			$siteId = $request->header( 'X-Analytics-Site-Id' );
		}

		if ( null === $siteId ) {
			// Check if site was resolved by middleware
			$resolvedSite = $request->attributes->get( 'analytics_site' );
			if ( null !== $resolvedSite && is_object( $resolvedSite ) && property_exists( $resolvedSite, 'id' ) ) {
				return (int) $resolvedSite->id;
			}

			return null;
		}

		// Validate that the value is numeric before casting
		$siteIdString = is_string( $siteId ) ? trim( $siteId ) : (string) $siteId;

		if ( '' === $siteIdString || ! is_numeric( $siteIdString ) ) {
			return null;
		}

		return (int) $siteIdString;
	}
}
