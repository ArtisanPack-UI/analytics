<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Facades;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery as AnalyticsQueryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * Analytics Query facade.
 *
 * Provides a static interface to the AnalyticsQuery service
 * for querying analytics data with caching support.
 *
 * @method static array getStats(DateRange $range, bool $withCompare = true, array $filters = [])
 * @method static Collection getPageViews(DateRange $range, string $granularity = 'day', array $filters = [])
 * @method static int getPageViewCount(DateRange $range, array $filters = [])
 * @method static int getVisitors(DateRange $range, array $filters = [])
 * @method static int getSessions(DateRange $range, array $filters = [])
 * @method static Collection getTopPages(DateRange $range, int $limit = 10, array $filters = [])
 * @method static Collection getTrafficSources(DateRange $range, int $limit = 10, array $filters = [])
 * @method static float getBounceRate(DateRange $range, array $filters = [])
 * @method static int getAverageSessionDuration(DateRange $range, array $filters = [])
 * @method static float getAveragePagesPerSession(DateRange $range, array $filters = [])
 * @method static array getRealtime(int $minutes = 5, array $filters = [])
 * @method static Collection getDeviceBreakdown(DateRange $range, array $filters = [])
 * @method static Collection getBrowserBreakdown(DateRange $range, int $limit = 10, array $filters = [])
 * @method static Collection getCountryBreakdown(DateRange $range, int $limit = 10, array $filters = [])
 * @method static array getPageAnalytics(string $path, DateRange $range, array $filters = [])
 * @method static array getConversionStats(DateRange $range, array $filters = [])
 * @method static Collection getConversionsByGoal(DateRange $range, int $limit = 10, array $filters = [])
 * @method static Collection getConversionsOverTime(DateRange $range, string $granularity = 'day', array $filters = [])
 * @method static array getGoalStats(int $goalId, DateRange $range, array $filters = [])
 * @method static Collection getEventBreakdown(DateRange $range, int $limit = 10, array $filters = [])
 * @method static Collection getEventsOverTime(DateRange $range, string $granularity = 'day', array $filters = [])
 * @method static bool clearCache()
 * @method static AnalyticsQueryService setCacheEnabled(bool $enabled)
 * @method static AnalyticsQueryService setCacheDuration(int $seconds)
 * @method static Collection getReferringHosts(DateRange $range, int $limit = 10, array $filters = [])
 * @method static AnalyticsQueryService includeBots()
 * @method static AnalyticsQueryService withBots()
 * @method static AnalyticsQueryService onlyBots()
 * @method static AnalyticsQueryService excludeBots()
 * @method static Collection getTopBotAgents(DateRange $range, int $limit = 10, array $filters = [])
 * @method static array getBotStats(DateRange $range, int $agentLimit = 10, string $granularity = 'day', array $filters = [])
 * @method static AnalyticsQueryService includeAnonymous()
 * @method static AnalyticsQueryService withAnonymous()
 * @method static AnalyticsQueryService onlyAnonymous()
 * @method static AnalyticsQueryService excludeAnonymous()
 * @method static bool isAnonymousModeEnabled()
 * @method static bool hasAnonymousData(DateRange $range, array $filters = [])
 * @method static int getAnonymousPageViewCount(DateRange $range, array $filters = [])
 * @method static Collection getAnonymousTopPages(DateRange $range, int $limit = 10, array $filters = [])
 * @method static Collection getAnonymousReferringHosts(DateRange $range, int $limit = 10, array $filters = [])
 * @method static Collection getAnonymousDeviceBreakdown(DateRange $range, array $filters = [])
 * @method static Collection getAnonymousPageViewsOverTime(DateRange $range, string $granularity = 'day', array $filters = [])
 * @method static array getAnonymousStats(DateRange $range, int $limit = 10, string $granularity = 'day', array $filters = [])
 *
 * @see AnalyticsQueryService
 * @since   1.0.0
 */
class AnalyticsQuery extends Facade
{
	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	protected static function getFacadeAccessor(): string
	{
		return AnalyticsQueryService::class;
	}
}
