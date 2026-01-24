<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Contracts;

use ArtisanPackUI\Analytics\Data\DateRange;
use Illuminate\Support\Collection;

/**
 * Interface for querying analytics data.
 *
 * This interface defines methods for retrieving analytics metrics
 * and dimensions from the storage layer.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Contracts
 */
interface AnalyticsQueryInterface
{
	/**
	 * Get total page views for a date range.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @return int The total page view count.
	 *
	 * @since 1.0.0
	 */
	public function getPageViews( DateRange $range, array $filters = [] ): int;

	/**
	 * Get unique visitors for a date range.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @return int The unique visitor count.
	 *
	 * @since 1.0.0
	 */
	public function getVisitors( DateRange $range, array $filters = [] ): int;

	/**
	 * Get total sessions for a date range.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @return int The total session count.
	 *
	 * @since 1.0.0
	 */
	public function getSessions( DateRange $range, array $filters = [] ): int;

	/**
	 * Get top pages by page views.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param int                  $limit   Maximum number of pages to return.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @return Collection<int, array{path: string, title: string, views: int, unique_views: int}>
	 *
	 * @since 1.0.0
	 */
	public function getTopPages( DateRange $range, int $limit = 10, array $filters = [] ): Collection;

	/**
	 * Get traffic sources breakdown.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param int                  $limit   Maximum number of sources to return.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @return Collection<int, array{source: string, medium: string, sessions: int, visitors: int}>
	 *
	 * @since 1.0.0
	 */
	public function getTrafficSources( DateRange $range, int $limit = 10, array $filters = [] ): Collection;

	/**
	 * Get bounce rate for a date range.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @return float The bounce rate as a percentage (0-100).
	 *
	 * @since 1.0.0
	 */
	public function getBounceRate( DateRange $range, array $filters = [] ): float;

	/**
	 * Get average session duration in seconds.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @return int The average session duration in seconds.
	 *
	 * @since 1.0.0
	 */
	public function getAverageSessionDuration( DateRange $range, array $filters = [] ): int;

	/**
	 * Get page views over time.
	 *
	 * @param DateRange            $range       The date range to query.
	 * @param string               $granularity The time granularity ('hour', 'day', 'week', 'month').
	 * @param array<string, mixed> $filters     Optional filters to apply.
	 *
	 * @return Collection<int, array{date: string, pageviews: int, visitors: int}>
	 *
	 * @since 1.0.0
	 */
	public function getPageViewsOverTime( DateRange $range, string $granularity = 'day', array $filters = [] ): Collection;

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
	public function getDeviceBreakdown( DateRange $range, array $filters = [] ): Collection;

	/**
	 * Get browser breakdown.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param int                  $limit   Maximum number of browsers to return.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @return Collection<int, array{browser: string, version: string, sessions: int, percentage: float}>
	 *
	 * @since 1.0.0
	 */
	public function getBrowserBreakdown( DateRange $range, int $limit = 10, array $filters = [] ): Collection;

	/**
	 * Get country breakdown.
	 *
	 * @param DateRange            $range   The date range to query.
	 * @param int                  $limit   Maximum number of countries to return.
	 * @param array<string, mixed> $filters Optional filters to apply.
	 *
	 * @return Collection<int, array{country: string, country_code: string, sessions: int, percentage: float}>
	 *
	 * @since 1.0.0
	 */
	public function getCountryBreakdown( DateRange $range, int $limit = 10, array $filters = [] ): Collection;

	/**
	 * Get real-time visitor count.
	 *
	 * @param int $minutes The number of minutes to consider as "real-time".
	 *
	 * @return int The number of active visitors.
	 *
	 * @since 1.0.0
	 */
	public function getRealTimeVisitors( int $minutes = 5): int;
}
