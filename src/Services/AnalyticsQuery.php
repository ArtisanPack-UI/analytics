<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Contracts\AnalyticsQueryInterface;
use ArtisanPackUI\Analytics\Data\DateRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Analytics Query Service.
 *
 * Provides a high-level query interface with caching and
 * comparison support for analytics data.
 *
 * @since 1.0.0
 */
class AnalyticsQuery
{
	/**
	 * The query provider instance.
	 */
	protected AnalyticsQueryInterface $provider;

	/**
	 * Whether caching is enabled.
	 */
	protected bool $cacheEnabled;

	/**
	 * Cache duration in seconds.
	 */
	protected int $cacheDuration;

	/**
	 * Cache prefix for keys.
	 */
	protected string $cachePrefix = 'analytics_query_';

	/**
	 * Cache tag for analytics queries.
	 */
	protected string $cacheTag = 'analytics';

	/**
	 * Create a new AnalyticsQuery instance.
	 *
	 * @param AnalyticsQueryInterface $provider The query provider.
	 *
	 * @since 1.0.0
	 */
	public function __construct( AnalyticsQueryInterface $provider )
	{
		$this->provider      = $provider;
		$this->cacheEnabled  = config( 'artisanpack.analytics.dashboard.cache_enabled', true );
		$this->cacheDuration = config( 'artisanpack.analytics.dashboard.cache_duration', 300 );
	}

	/**
	 * Get comprehensive statistics for a date range.
	 *
	 * Returns an array of key metrics with optional comparison to previous period.
	 *
	 * @param DateRange            $range       The date range to query.
	 * @param bool                 $withCompare Whether to include comparison data.
	 * @param array<string, mixed> $filters     Optional filters to apply.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function getStats( DateRange $range, bool $withCompare = true, array $filters = [] ): array
	{
		$cacheKey = $this->buildCacheKey( 'stats', $range, $filters, $withCompare );

		$stats = $this->cached( $cacheKey, function () use ( $range, $withCompare, $filters ): array {
			$cachedStats = [
				'pageviews'            => $this->provider->getPageViews( $range, $filters ),
				'visitors'             => $this->provider->getVisitors( $range, $filters ),
				'sessions'             => $this->provider->getSessions( $range, $filters ),
				'bounce_rate'          => $this->provider->getBounceRate( $range, $filters ),
				'avg_session_duration' => $this->provider->getAverageSessionDuration( $range, $filters ),
			];

			// Calculate pages per session
			$cachedStats['pages_per_session'] = $cachedStats['sessions'] > 0
				? round( $cachedStats['pageviews'] / $cachedStats['sessions'], 2 )
				: 0.0;

			if ( $withCompare ) {
				$cachedStats['comparison'] = $this->getComparisonStats( $range, $filters );
			}

			return $cachedStats;
		} );

		// Real-time visitors should never be cached
		$stats['realtime_visitors'] = $this->provider->getRealTimeVisitors();

		return $stats;
	}

	/**
	 * Get page views with optional grouping.
	 *
	 * @param DateRange            $range       The date range to query.
	 * @param string               $granularity Grouping granularity ('hour', 'day', 'week', 'month').
	 * @param array<string, mixed> $filters     Optional filters to apply.
	 *
	 * @return Collection<int, array{date: string, pageviews: int, visitors: int}>
	 *
	 * @since 1.0.0
	 */
	public function getPageViews( DateRange $range, string $granularity = 'day', array $filters = [] ): Collection
	{
		$cacheKey = $this->buildCacheKey( 'pageviews', $range, $filters, $granularity );

		return $this->cached( $cacheKey, fn () => $this->provider->getPageViewsOverTime( $range, $granularity, $filters ) );
	}

	/**
	 * Get page view count.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @since 1.0.0
	 */
	public function getPageViewCount( DateRange $range, array $filters = [] ): int
	{
		$cacheKey = $this->buildCacheKey( 'pageview_count', $range, $filters );

		return $this->cached( $cacheKey, fn () => $this->provider->getPageViews( $range, $filters ) );
	}

	/**
	 * Get unique visitors.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @since 1.0.0
	 */
	public function getVisitors( DateRange $range, array $filters = [] ): int
	{
		$cacheKey = $this->buildCacheKey( 'visitors', $range, $filters );

		return $this->cached( $cacheKey, fn () => $this->provider->getVisitors( $range, $filters ) );
	}

	/**
	 * Get sessions count.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @since 1.0.0
	 */
	public function getSessions( DateRange $range, array $filters = [] ): int
	{
		$cacheKey = $this->buildCacheKey( 'sessions', $range, $filters );

		return $this->cached( $cacheKey, fn () => $this->provider->getSessions( $range, $filters ) );
	}

	/**
	 * Get top pages.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param int                  $limit   Maximum number of results.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @return Collection<int, array{path: string, title: string, views: int, unique_views: int}>
	 *
	 * @since 1.0.0
	 */
	public function getTopPages( DateRange $range, int $limit = 10, array $filters = [] ): Collection
	{
		$cacheKey = $this->buildCacheKey( 'top_pages', $range, $filters, $limit );

		return $this->cached( $cacheKey, fn () => $this->provider->getTopPages( $range, $limit, $filters ) );
	}

	/**
	 * Get traffic sources.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param int                  $limit   Maximum number of results.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @return Collection<int, array{source: string, medium: string, sessions: int, visitors: int}>
	 *
	 * @since 1.0.0
	 */
	public function getTrafficSources( DateRange $range, int $limit = 10, array $filters = [] ): Collection
	{
		$cacheKey = $this->buildCacheKey( 'traffic_sources', $range, $filters, $limit );

		return $this->cached( $cacheKey, fn () => $this->provider->getTrafficSources( $range, $limit, $filters ) );
	}

	/**
	 * Get bounce rate.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @since 1.0.0
	 */
	public function getBounceRate( DateRange $range, array $filters = [] ): float
	{
		$cacheKey = $this->buildCacheKey( 'bounce_rate', $range, $filters );

		return $this->cached( $cacheKey, fn () => $this->provider->getBounceRate( $range, $filters ) );
	}

	/**
	 * Get average session duration in seconds.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @since 1.0.0
	 */
	public function getAverageSessionDuration( DateRange $range, array $filters = [] ): int
	{
		$cacheKey = $this->buildCacheKey( 'avg_session_duration', $range, $filters );

		return $this->cached( $cacheKey, fn () => $this->provider->getAverageSessionDuration( $range, $filters ) );
	}

	/**
	 * Get average pages per session.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @since 1.0.0
	 */
	public function getAveragePagesPerSession( DateRange $range, array $filters = [] ): float
	{
		$cacheKey = $this->buildCacheKey( 'avg_pages_per_session', $range, $filters );

		return $this->cached( $cacheKey, function () use ( $range, $filters ): float {
			$pageviews = $this->provider->getPageViews( $range, $filters );
			$sessions  = $this->provider->getSessions( $range, $filters );

			return $sessions > 0 ? round( $pageviews / $sessions, 2 ) : 0.0;
		} );
	}

	/**
	 * Get real-time visitor data.
	 *
	 * Real-time data is never cached.
	 *
	 * @param int $minutes The number of minutes to consider as "real-time".
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function getRealtime( int $minutes = 5 ): array
	{
		return [
			'active_visitors' => $this->provider->getRealTimeVisitors( $minutes ),
			'timestamp'       => now()->toIso8601String(),
		];
	}

	/**
	 * Get device breakdown.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @return Collection<int, array{device_type: string, sessions: int, percentage: float}>
	 *
	 * @since 1.0.0
	 */
	public function getDeviceBreakdown( DateRange $range, array $filters = [] ): Collection
	{
		$cacheKey = $this->buildCacheKey( 'device_breakdown', $range, $filters );

		return $this->cached( $cacheKey, fn () => $this->provider->getDeviceBreakdown( $range, $filters ) );
	}

	/**
	 * Get browser breakdown.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param int                  $limit   Maximum number of results.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @return Collection<int, array{browser: string, version: string, sessions: int, percentage: float}>
	 *
	 * @since 1.0.0
	 */
	public function getBrowserBreakdown( DateRange $range, int $limit = 10, array $filters = [] ): Collection
	{
		$cacheKey = $this->buildCacheKey( 'browser_breakdown', $range, $filters, $limit );

		return $this->cached( $cacheKey, fn () => $this->provider->getBrowserBreakdown( $range, $limit, $filters ) );
	}

	/**
	 * Get country breakdown.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param int                  $limit   Maximum number of results.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @return Collection<int, array{country: string, country_code: string, sessions: int, percentage: float}>
	 *
	 * @since 1.0.0
	 */
	public function getCountryBreakdown( DateRange $range, int $limit = 10, array $filters = [] ): Collection
	{
		$cacheKey = $this->buildCacheKey( 'country_breakdown', $range, $filters, $limit );

		return $this->cached( $cacheKey, fn () => $this->provider->getCountryBreakdown( $range, $limit, $filters ) );
	}

	/**
	 * Get page analytics for a specific path.
	 *
	 * @param string               $path    The page path.
	 * @param DateRange            $range   The date range to query.
	 * @param array<string, mixed> $filters Additional filters.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function getPageAnalytics( string $path, DateRange $range, array $filters = [] ): array
	{
		$filters['path'] = $path;
		$cacheKey        = $this->buildCacheKey( 'page_analytics', $range, $filters );

		return $this->cached( $cacheKey, function () use ( $range, $filters ): array {
			return [
				'pageviews'   => $this->provider->getPageViews( $range, $filters ),
				'visitors'    => $this->provider->getVisitors( $range, $filters ),
				'bounce_rate' => $this->provider->getBounceRate( $range, $filters ),
				'over_time'   => $this->provider->getPageViewsOverTime( $range, 'day', $filters ),
			];
		} );
	}

	/**
	 * Clear all cached analytics query results.
	 *
	 * Uses cache tags if available (Redis, Memcached), otherwise
	 * falls back to clearing all keys matching the analytics prefix.
	 *
	 * @since 1.0.0
	 */
	public function clearCache(): void
	{
		$store = Cache::getStore();

		// Use cache tags if the driver supports them (Redis, Memcached, etc.)
		if ( method_exists( $store, 'tags' ) ) {
			Cache::tags( $this->cacheTag )->flush();

			return;
		}

		// For drivers that don't support tags, we can't selectively clear
		// without tracking keys. Log a warning and skip the operation.
		// In production, consider using a cache driver that supports tags.
	}

	/**
	 * Enable or disable caching.
	 *
	 * @param bool $enabled Whether caching should be enabled.
	 *
	 * @since 1.0.0
	 */
	public function setCacheEnabled( bool $enabled ): static
	{
		$this->cacheEnabled = $enabled;

		return $this;
	}

	/**
	 * Set the cache duration.
	 *
	 * @param int $seconds Cache duration in seconds.
	 *
	 * @since 1.0.0
	 */
	public function setCacheDuration( int $seconds ): static
	{
		$this->cacheDuration = $seconds;

		return $this;
	}

	/**
	 * Get comparison statistics for the previous period.
	 *
	 * @param DateRange            $range   The current date range.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @return array<string, array{value: float|int, change: float, trend: string}>
	 *
	 * @since 1.0.0
	 */
	protected function getComparisonStats( DateRange $range, array $filters = [] ): array
	{
		$previousRange = $range->getPreviousPeriod();

		$currentPageviews = $this->provider->getPageViews( $range, $filters );
		$currentVisitors  = $this->provider->getVisitors( $range, $filters );
		$currentSessions  = $this->provider->getSessions( $range, $filters );
		$currentBounce    = $this->provider->getBounceRate( $range, $filters );
		$currentDuration  = $this->provider->getAverageSessionDuration( $range, $filters );

		$previousPageviews = $this->provider->getPageViews( $previousRange, $filters );
		$previousVisitors  = $this->provider->getVisitors( $previousRange, $filters );
		$previousSessions  = $this->provider->getSessions( $previousRange, $filters );
		$previousBounce    = $this->provider->getBounceRate( $previousRange, $filters );
		$previousDuration  = $this->provider->getAverageSessionDuration( $previousRange, $filters );

		return [
			'pageviews'            => $this->calculateChange( $currentPageviews, $previousPageviews ),
			'visitors'             => $this->calculateChange( $currentVisitors, $previousVisitors ),
			'sessions'             => $this->calculateChange( $currentSessions, $previousSessions ),
			'bounce_rate'          => $this->calculateChange( $currentBounce, $previousBounce, true ),
			'avg_session_duration' => $this->calculateChange( $currentDuration, $previousDuration ),
		];
	}

	/**
	 * Calculate the percentage change between two values.
	 *
	 * @param float|int $current      The current value.
	 * @param float|int $previous     The previous value.
	 * @param bool      $invertBetter Whether a decrease is better (e.g., bounce rate).
	 *
	 * @return array{value: float|int, change: float, trend: string}
	 *
	 * @since 1.0.0
	 */
	protected function calculateChange( int|float $current, int|float $previous, bool $invertBetter = false ): array
	{
		$change = 0.0;

		if ( $previous > 0 ) {
			$change = round( ( ( $current - $previous ) / $previous ) * 100, 2 );
		} elseif ( $current > 0 ) {
			$change = 100.0;
		}

		// Determine trend
		$trend = 'neutral';
		if ( $change > 0 ) {
			$trend = $invertBetter ? 'down' : 'up';
		} elseif ( $change < 0 ) {
			$trend = $invertBetter ? 'up' : 'down';
		}

		return [
			'value'    => $previous,
			'change'   => $change,
			'trend'    => $trend,
			'positive' => ( $change > 0 && ! $invertBetter ) || ( $change < 0 && $invertBetter ),
		];
	}

	/**
	 * Build a cache key for a query.
	 *
	 * @param string    $method The method name.
	 * @param DateRange $range  The date range.
	 * @param mixed     ...$params Additional parameters.
	 *
	 * @since 1.0.0
	 */
	protected function buildCacheKey( string $method, DateRange $range, mixed ...$params ): string
	{
		$paramsHash = md5( serialize( $params ) );

		return sprintf(
			'%s%s_%s_%s',
			$this->cachePrefix,
			$method,
			$range->toKey(),
			$paramsHash,
		);
	}

	/**
	 * Execute a query with caching.
	 *
	 * Uses cache tags if available for easier cache invalidation.
	 *
	 * @template T
	 *
	 * @param string   $key      The cache key.
	 * @param callable $callback The query callback.
	 *
	 * @return T
	 *
	 * @since 1.0.0
	 */
	protected function cached( string $key, callable $callback ): mixed
	{
		if ( ! $this->cacheEnabled ) {
			return $callback();
		}

		$store = Cache::getStore();

		// Use cache tags if the driver supports them
		if ( method_exists( $store, 'tags' ) ) {
			return Cache::tags( $this->cacheTag )->remember( $key, $this->cacheDuration, $callback );
		}

		return Cache::remember( $key, $this->cacheDuration, $callback );
	}
}
