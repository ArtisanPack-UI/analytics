<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Contracts\AnalyticsQueryInterface;
use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Models\Conversion;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\Goal;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Services\Concerns\QueriesAnonymousTraffic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Analytics Query Service.
 *
 * Provides a high-level query interface with caching and
 * comparison support for analytics data.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Services
 */
class AnalyticsQuery
{
    use QueriesAnonymousTraffic;

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
     * Pending bot-filter mode for the next query.
     *
     * One of 'exclude', 'include', or 'only'. Null falls back to the default
     * ('exclude'). Reset to null after each query so the modifier applies to a
     * single call only.
     */
    protected ?string $pendingBotMode = null;

    /**
     * Create a new AnalyticsQuery instance.
     *
     * @param  AnalyticsQueryInterface  $provider  The query provider.
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
     * Include bot traffic alongside human traffic in the next query.
     *
     * @return static The current instance for method chaining.
     *
     * @since 1.2.0
     */
    public function includeBots(): static
    {
        $this->pendingBotMode = 'include';

        return $this;
    }

    /**
     * Include bot traffic alongside human traffic in the next query.
     *
     * Alias of includeBots().
     *
     * @return static The current instance for method chaining.
     *
     * @since 1.2.0
     */
    public function withBots(): static
    {
        return $this->includeBots();
    }

    /**
     * Restrict the next query to bot traffic only.
     *
     * @return static The current instance for method chaining.
     *
     * @since 1.2.0
     */
    public function onlyBots(): static
    {
        $this->pendingBotMode = 'only';

        return $this;
    }

    /**
     * Exclude bot traffic from the next query (the default behaviour).
     *
     * @return static The current instance for method chaining.
     *
     * @since 1.2.0
     */
    public function excludeBots(): static
    {
        $this->pendingBotMode = 'exclude';

        return $this;
    }

    /**
     * Get comprehensive statistics for a date range.
     *
     * Returns an array of key metrics with optional comparison to previous period.
     *
     * Only `pageviews` can include anonymous traffic. Visitors, sessions,
     * bounce rate and session duration are derived from identified rows and
     * are reported alongside `identified_only_metrics_available` so a caller
     * can say so rather than presenting a figure that quietly cannot include
     * the anonymous share.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  bool  $withCompare  Whether to include comparison data.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    public function getStats( DateRange $range, bool $withCompare = true, array $filters = [] ): array
    {
        $filters  = $this->resolveAnonymousMode( $this->resolveBotMode( $filters ) );
        $mode     = $filters['anonymous'];
        $cacheKey = $this->buildCacheKey( 'stats', $range, $filters, $withCompare );

        $stats = $this->cached( $cacheKey, function () use ( $range, $withCompare, $filters, $mode ): array {
            // An anonymous-only view has no visitor or session behind it, so
            // those metrics are zero and explicitly marked unavailable.
            $identifiedAvailable = 'only' !== $mode;

            $identifiedPageviews = $identifiedAvailable
                ? $this->provider->getPageViews( $range, $filters )
                : 0;
            $anonymousPageviews = 'exclude' === $mode
                ? 0
                : $this->getAnonymousPageViewCount( $range, $filters );

            $cachedStats = [
                'pageviews'                         => $identifiedPageviews + $anonymousPageviews,
                'identified_pageviews'              => $identifiedPageviews,
                'anonymous_pageviews'               => $anonymousPageviews,
                'anonymous_mode'                    => $mode,
                'identified_only_metrics_available' => $identifiedAvailable,
                'visitors'                          => $identifiedAvailable ? $this->provider->getVisitors( $range, $filters ) : 0,
                'sessions'                          => $identifiedAvailable ? $this->provider->getSessions( $range, $filters ) : 0,
                'bounce_rate'                       => $identifiedAvailable ? $this->provider->getBounceRate( $range, $filters ) : 0.0,
                'avg_session_duration'              => $identifiedAvailable ? $this->provider->getAverageSessionDuration( $range, $filters ) : 0,
            ];

            // Pages per session is a ratio of two identified figures; folding
            // anonymous views into the numerator would inflate it.
            $cachedStats['pages_per_session'] = $cachedStats['sessions'] > 0
                ? round( $identifiedPageviews / $cachedStats['sessions'], 2 )
                : 0.0;

            if ( $withCompare ) {
                $cachedStats['comparison'] = $this->getComparisonStats( $range, $filters );
            }

            return $cachedStats;
        } );

        // Real-time visitors should never be cached
        $stats['realtime_visitors'] = 'only' === $mode
            ? 0
            : $this->provider->getRealTimeVisitors( 5, $filters );

        return $stats;
    }

    /**
     * Get page views with optional grouping.
     *
     * The `pageviews` figure in each bucket can include anonymous traffic;
     * `visitors` never can, so it stays identified-only in every mode.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  string  $granularity  Grouping granularity ('hour', 'day', 'week', 'month').
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{date: string, pageviews: int, visitors: int}>
     *
     * @since 1.0.0
     */
    public function getPageViews( DateRange $range, string $granularity = 'day', array $filters = [] ): Collection
    {
        $filters  = $this->resolveAnonymousMode( $this->resolveBotMode( $filters ) );
        $mode     = $filters['anonymous'];
        $cacheKey = $this->buildCacheKey( 'pageviews', $range, $filters, $granularity );

        return $this->cached( $cacheKey, function () use ( $range, $granularity, $filters, $mode ): Collection {
            $identified = 'only' === $mode
                ? collect()
                : $this->provider->getPageViewsOverTime( $range, $granularity, $filters );

            if ( 'exclude' === $mode ) {
                return $identified;
            }

            return $this->mergeAnonymousTimeSeries(
                $identified,
                $this->getAnonymousPageViewsOverTime( $range, $granularity, $filters ),
            );
        } );
    }

    /**
     * Get page view count.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return int The total page view count.
     *
     * @since 1.0.0
     */
    public function getPageViewCount( DateRange $range, array $filters = [] ): int
    {
        $filters  = $this->resolveAnonymousMode( $this->resolveBotMode( $filters ) );
        $mode     = $filters['anonymous'];
        $cacheKey = $this->buildCacheKey( 'pageview_count', $range, $filters );

        return $this->cached( $cacheKey, function () use ( $range, $filters, $mode ): int {
            $identified = 'only' === $mode ? 0 : $this->provider->getPageViews( $range, $filters );
            $anonymous  = 'exclude' === $mode ? 0 : $this->getAnonymousPageViewCount( $range, $filters );

            return $identified + $anonymous;
        } );
    }

    /**
     * Get unique visitors.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return int The unique visitor count.
     *
     * @since 1.0.0
     */
    public function getVisitors( DateRange $range, array $filters = [] ): int
    {
        $filters  = $this->resolveBotMode( $filters );
        $cacheKey = $this->buildCacheKey( 'visitors', $range, $filters );

        return $this->cached( $cacheKey, fn () => $this->provider->getVisitors( $range, $filters ) );
    }

    /**
     * Get sessions count.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return int The total session count.
     *
     * @since 1.0.0
     */
    public function getSessions( DateRange $range, array $filters = [] ): int
    {
        $filters  = $this->resolveBotMode( $filters );
        $cacheKey = $this->buildCacheKey( 'sessions', $range, $filters );

        return $this->cached( $cacheKey, fn () => $this->provider->getSessions( $range, $filters ) );
    }

    /**
     * Get top pages.
     *
     * `views` can include anonymous traffic; `unique_views` counts distinct
     * visitors and so is always identified-only. Combined rows also carry
     * `identified_views` and `anonymous_views` so a caller can show the split.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of results.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{path: string, title: string, views: int, unique_views: int}>
     *
     * @since 1.0.0
     */
    public function getTopPages( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        $filters  = $this->resolveAnonymousMode( $this->resolveBotMode( $filters ) );
        $mode     = $filters['anonymous'];
        $cacheKey = $this->buildCacheKey( 'top_pages', $range, $filters, $limit );

        return $this->cached( $cacheKey, function () use ( $range, $limit, $filters, $mode ): Collection {
            if ( 'exclude' === $mode ) {
                return $this->provider->getTopPages( $range, $limit, $filters );
            }

            $candidateLimit = $this->candidateLimit( $limit );

            $anonymous = $this->getAnonymousTopPages( $range, $candidateLimit, $filters );

            if ( 'only' === $mode ) {
                return $anonymous
                    ->take( $limit )
                    ->map( fn ( array $row ) => [
                        'path'             => $row['path'],
                        'title'            => $row['title'],
                        'views'            => $row['views'],
                        'unique_views'     => 0,
                        'identified_views' => 0,
                        'anonymous_views'  => $row['views'],
                    ] )
                    ->values();
            }

            return $this->mergeAnonymousTopPages(
                $this->provider->getTopPages( $range, $candidateLimit, $filters ),
                $anonymous,
                $limit,
            );
        } );
    }

    /**
     * Get the top bot user agents by visit count.
     *
     * Always scoped to bot traffic; any incoming bot mode is ignored.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of user agents to return.
     * @param  array<string, mixed>  $filters  Optional filters to apply (site/tenant scoping).
     *
     * @return Collection<int, array{user_agent: string, visits: int}>
     *
     * @since 1.2.0
     */
    public function getTopBotAgents( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        // Consume any pending one-shot modifier so it cannot leak into a later
        // query, even though this method always forces bot-only scoping.
        $this->resolveBotMode( $filters );
        unset( $filters['bots'] );
        $cacheKey = $this->buildCacheKey( 'top_bot_agents', $range, $filters, $limit );

        return $this->cached( $cacheKey, fn () => $this->provider->getTopBotAgents( $range, $limit, $filters ) );
    }

    /**
     * Get an aggregated summary of bot traffic for a date range.
     *
     * Combines bot-only visitor counts, the bot share of total traffic, the
     * busiest bot user agents, and a bot-only visit trend so dashboards can
     * surface filtered traffic in a single call.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $agentLimit  Maximum number of bot user agents to return.
     * @param  string  $granularity  Trend grouping granularity ('hour', 'day', 'week', 'month').
     * @param  array<string, mixed>  $filters  Optional filters to apply (site/tenant scoping).
     *
     * @return array{
     *     bot_visits: int,
     *     total_visits: int,
     *     bot_percentage: float,
     *     top_agents: array<int, array{user_agent: string, visits: int}>,
     *     trend: array<int, array{date: string, visits: int}>
     * }
     *
     * @since 1.2.0
     */
    public function getBotStats( DateRange $range, int $agentLimit = 10, string $granularity = 'day', array $filters = [] ): array
    {
        // This method controls bot scoping itself, so discard any caller mode.
        unset( $filters['bots'] );

        // Anonymous rows are not bots, and carry no visitor to attribute to
        // one. Left in, they would add date buckets to the bot-only trend for
        // days that saw nothing but anonymous traffic.
        $filters['anonymous'] = 'exclude';

        $botVisits   = $this->getVisitors( $range, array_merge( $filters, ['bots' => 'only'] ) );
        $totalVisits = $this->getVisitors( $range, array_merge( $filters, ['bots' => 'include'] ) );

        $percentage = $totalVisits > 0
            ? round( ( $botVisits / $totalVisits ) * 100, 1 )
            : 0.0;

        $trend = $this->getPageViews( $range, $granularity, array_merge( $filters, ['bots' => 'only'] ) )
            ->map( fn ( array $row ) => [
                'date'   => $row['date'],
                'visits' => $row['visitors'],
            ] )
            ->values()
            ->all();

        return [
            'bot_visits'     => $botVisits,
            'total_visits'   => $totalVisits,
            'bot_percentage' => $percentage,
            'top_agents'     => $this->getTopBotAgents( $range, $agentLimit, $filters )->values()->all(),
            'trend'          => $trend,
        ];
    }

    /**
     * Get traffic sources.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of results.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{source: string, medium: string, sessions: int, visitors: int}>
     *
     * @since 1.0.0
     */
    public function getTrafficSources( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        $filters  = $this->resolveBotMode( $filters );
        $cacheKey = $this->buildCacheKey( 'traffic_sources', $range, $filters, $limit );

        return $this->cached( $cacheKey, fn () => $this->provider->getTrafficSources( $range, $limit, $filters ) );
    }

    /**
     * Get bounce rate.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return float The bounce rate as a percentage.
     *
     * @since 1.0.0
     */
    public function getBounceRate( DateRange $range, array $filters = [] ): float
    {
        $filters  = $this->resolveBotMode( $filters );
        $cacheKey = $this->buildCacheKey( 'bounce_rate', $range, $filters );

        return $this->cached( $cacheKey, fn () => $this->provider->getBounceRate( $range, $filters ) );
    }

    /**
     * Get average session duration in seconds.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return int The average session duration in seconds.
     *
     * @since 1.0.0
     */
    public function getAverageSessionDuration( DateRange $range, array $filters = [] ): int
    {
        $filters  = $this->resolveBotMode( $filters );
        $cacheKey = $this->buildCacheKey( 'avg_session_duration', $range, $filters );

        return $this->cached( $cacheKey, fn () => $this->provider->getAverageSessionDuration( $range, $filters ) );
    }

    /**
     * Get average pages per session.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return float The average pages per session.
     *
     * @since 1.0.0
     */
    public function getAveragePagesPerSession( DateRange $range, array $filters = [] ): float
    {
        $filters  = $this->resolveBotMode( $filters );
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
     * @param  int  $minutes  The number of minutes to consider as "real-time".
     * @param  array<string, mixed>  $filters  Optional filters to apply (including bot scoping).
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    public function getRealtime( int $minutes = 5, array $filters = [] ): array
    {
        $filters = $this->resolveBotMode( $filters );

        return [
            'active_visitors' => $this->provider->getRealTimeVisitors( $minutes, $filters ),
            'timestamp'       => now()->toIso8601String(),
        ];
    }

    /**
     * Get device breakdown.
     *
     * The identified breakdown counts sessions. Anonymous rows have no session,
     * so their contribution is reported as a separate `anonymous_views` count
     * rather than added to `sessions` — the two are different units and summing
     * them would produce a number that means nothing.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{device_type: string, sessions: int, percentage: float}>
     *
     * @since 1.0.0
     */
    public function getDeviceBreakdown( DateRange $range, array $filters = [] ): Collection
    {
        $filters  = $this->resolveAnonymousMode( $this->resolveBotMode( $filters ) );
        $mode     = $filters['anonymous'];
        $cacheKey = $this->buildCacheKey( 'device_breakdown', $range, $filters );

        return $this->cached( $cacheKey, function () use ( $range, $filters, $mode ): Collection {
            if ( 'exclude' === $mode ) {
                return $this->provider->getDeviceBreakdown( $range, $filters );
            }

            $anonymous = $this->getAnonymousDeviceBreakdown( $range, $filters );

            if ( 'only' === $mode ) {
                return $anonymous->map( fn ( array $row ) => [
                    'device_type'          => $row['device_type'],
                    'sessions'             => 0,
                    'percentage'           => 0.0,
                    'anonymous_views'      => $row['views'],
                    'anonymous_percentage' => $row['percentage'],
                ] )->values();
            }

            return $this->mergeAnonymousDeviceBreakdown(
                $this->provider->getDeviceBreakdown( $range, $filters ),
                $anonymous,
            );
        } );
    }

    /**
     * Get the top referring hosts by page views.
     *
     * Counted in page views for both sources so the two can be added honestly.
     * This is deliberately not the same metric as getTrafficSources(), which
     * counts sessions and cannot include anonymous traffic at all.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of hosts to return.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{host: string, views: int, identified_views: int, anonymous_views: int}>
     *
     * @since 1.5.0
     */
    public function getReferringHosts( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        $filters  = $this->resolveAnonymousMode( $this->resolveBotMode( $filters ) );
        $mode     = $filters['anonymous'];
        $cacheKey = $this->buildCacheKey( 'referring_hosts', $range, $filters, $limit );

        return $this->cached( $cacheKey, function () use ( $range, $limit, $filters, $mode ): Collection {
            $candidateLimit = 'exclude' === $mode ? $limit : $this->candidateLimit( $limit );

            $identified = 'only' === $mode
                ? collect()
                : $this->getIdentifiedReferringHosts( $range, $candidateLimit, $filters );
            $anonymous = 'exclude' === $mode
                ? collect()
                : $this->getAnonymousReferringHosts( $range, $candidateLimit, $filters );

            $totals = [];

            foreach ( $identified as $row ) {
                $totals[ $row['host'] ] = [
                    'host'             => $row['host'],
                    'identified_views' => $row['views'],
                    'anonymous_views'  => 0,
                ];
            }

            foreach ( $anonymous as $row ) {
                $totals[ $row['host'] ] ??= [
                    'host'             => $row['host'],
                    'identified_views' => 0,
                    'anonymous_views'  => 0,
                ];

                $totals[ $row['host'] ]['anonymous_views'] += $row['views'];
            }

            return collect( array_values( $totals ) )
                ->map( fn ( array $row ) => [
                    'host'             => $row['host'],
                    'views'            => $row['identified_views'] + $row['anonymous_views'],
                    'identified_views' => $row['identified_views'],
                    'anonymous_views'  => $row['anonymous_views'],
                ] )
                ->sortByDesc( 'views' )
                ->take( $limit )
                ->values();
        } );
    }

    /**
     * Get browser breakdown.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of results.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{browser: string, version: string, sessions: int, percentage: float}>
     *
     * @since 1.0.0
     */
    public function getBrowserBreakdown( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        $filters  = $this->resolveBotMode( $filters );
        $cacheKey = $this->buildCacheKey( 'browser_breakdown', $range, $filters, $limit );

        return $this->cached( $cacheKey, fn () => $this->provider->getBrowserBreakdown( $range, $limit, $filters ) );
    }

    /**
     * Get country breakdown.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of results.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{country: string, country_code: string, sessions: int, percentage: float}>
     *
     * @since 1.0.0
     */
    public function getCountryBreakdown( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        $filters  = $this->resolveBotMode( $filters );
        $cacheKey = $this->buildCacheKey( 'country_breakdown', $range, $filters, $limit );

        return $this->cached( $cacheKey, fn () => $this->provider->getCountryBreakdown( $range, $limit, $filters ) );
    }

    /**
     * Get page analytics for a specific path.
     *
     * @param  string  $path  The page path.
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Additional filters.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    public function getPageAnalytics( string $path, DateRange $range, array $filters = [] ): array
    {
        $filters['path'] = $path;
        $filters         = $this->resolveBotMode( $filters );
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
     * Get conversion statistics for a date range.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    public function getConversionStats( DateRange $range, array $filters = [] ): array
    {
        $cacheKey = $this->buildCacheKey( 'conversion_stats', $range, $filters );

        return $this->cached( $cacheKey, function () use ( $range, $filters ): array {
            $query = Conversion::query()
                ->whereBetween( 'created_at', [$range->startDate, $range->endDate] );

            if ( isset( $filters['site_id'] ) ) {
                $query->where( 'site_id', $filters['site_id'] );
            }

            if ( isset( $filters['goal_id'] ) ) {
                $query->where( 'goal_id', $filters['goal_id'] );
            }

            // Use a single aggregated query to avoid query builder mutation issues
            $stats = ( clone $query )
                ->selectRaw( 'COUNT(*) as conversions, SUM(value) as total_value' )
                ->first();

            $conversions = (int) ( $stats->conversions ?? 0 );
            $value       = (float) ( $stats->total_value ?? 0 );
            $visitors    = $this->provider->getVisitors( $range, $filters );

            return [
                'total_conversions' => $conversions,
                'total_value'       => round( $value, 2 ),
                'conversion_rate'   => $visitors > 0 ? round( ( $conversions / $visitors ) * 100, 2 ) : 0.0,
                'average_value'     => $conversions > 0 ? round( $value / $conversions, 2 ) : 0.0,
                'unique_visitors'   => $visitors,
            ];
        } );
    }

    /**
     * Get conversions grouped by goal.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of goals to return.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array<string, mixed>>
     *
     * @since 1.0.0
     */
    public function getConversionsByGoal( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        $cacheKey = $this->buildCacheKey( 'conversions_by_goal', $range, $filters, $limit );

        return $this->cached( $cacheKey, function () use ( $range, $filters, $limit ): Collection {
            $query = Conversion::query()
                ->selectRaw( 'goal_id, COUNT(*) as conversions, SUM(value) as total_value' )
                ->whereBetween( 'created_at', [$range->startDate, $range->endDate] )
                ->groupBy( 'goal_id' )
                ->orderByDesc( 'conversions' )
                ->limit( $limit );

            if ( isset( $filters['site_id'] ) ) {
                $query->where( 'site_id', $filters['site_id'] );
            }

            $results          = $query->get();
            $goalIds          = $results->pluck( 'goal_id' )->unique();
            $goals            = Goal::whereIn( 'id', $goalIds )->get()->keyBy( 'id' );
            $totalConversions = $results->sum( 'conversions' );

            return $results->map( function ( $row ) use ( $totalConversions, $goals ) {
                $goal = $goals->get( $row->goal_id );

                return [
                    'goal_id'     => $row->goal_id,
                    'goal_name'   => $goal?->name ?? __( 'Unknown Goal' ),
                    'goal_type'   => $goal?->type ?? 'unknown',
                    'conversions' => (int) $row->conversions,
                    'total_value' => round( (float) $row->total_value, 2 ),
                    'percentage'  => $totalConversions > 0
                        ? round( ( $row->conversions / $totalConversions ) * 100, 2 )
                        : 0.0,
                ];
            } );
        } );
    }

    /**
     * Get conversion rate over time.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  string  $granularity  Grouping granularity ('day', 'week', 'month').
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array<string, mixed>>
     *
     * @since 1.0.0
     */
    public function getConversionsOverTime( DateRange $range, string $granularity = 'day', array $filters = [] ): Collection
    {
        $cacheKey = $this->buildCacheKey( 'conversions_over_time', $range, $filters, $granularity );

        return $this->cached( $cacheKey, function () use ( $range, $granularity, $filters ): Collection {
            $dateFormat = match ( $granularity ) {
                'hour'  => '%Y-%m-%d %H:00:00',
                'week'  => '%Y-%W',
                'month' => '%Y-%m',
                default => '%Y-%m-%d',
            };

            $query = Conversion::query()
                ->selectRaw( "DATE_FORMAT(created_at, '{$dateFormat}') as date, COUNT(*) as conversions, SUM(value) as total_value" )
                ->whereBetween( 'created_at', [$range->startDate, $range->endDate] )
                ->groupBy( 'date' )
                ->orderBy( 'date' );

            if ( isset( $filters['site_id'] ) ) {
                $query->where( 'site_id', $filters['site_id'] );
            }

            if ( isset( $filters['goal_id'] ) ) {
                $query->where( 'goal_id', $filters['goal_id'] );
            }

            return $query->get()->map( fn ( $row ) => [
                'date'        => $row->date,
                'conversions' => (int) $row->conversions,
                'total_value' => round( (float) $row->total_value, 2 ),
            ] );
        } );
    }

    /**
     * Get goal statistics.
     *
     * @param  int  $goalId  The goal ID.
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    public function getGoalStats( int $goalId, DateRange $range, array $filters = [] ): array
    {
        $cacheKey = $this->buildCacheKey( 'goal_stats', $range, $filters, $goalId );

        return $this->cached( $cacheKey, function () use ( $goalId, $range, $filters ): array {
            $goal = Goal::find( $goalId );

            if ( null === $goal ) {
                return [
                    'error' => __( 'Goal not found' ),
                ];
            }

            $baseQuery = Conversion::query()
                ->where( 'goal_id', $goalId )
                ->whereBetween( 'created_at', [$range->startDate, $range->endDate] );

            if ( isset( $filters['site_id'] ) ) {
                $baseQuery->where( 'site_id', $filters['site_id'] );
            }

            // Clone the base query for each aggregate to avoid mutation issues
            $conversions    = ( clone $baseQuery )->count();
            $totalValue     = ( clone $baseQuery )->sum( 'value' );
            $uniqueVisitors = ( clone $baseQuery )->distinct()->count( 'visitor_id' );
            $uniqueSessions = ( clone $baseQuery )->distinct()->count( 'session_id' );
            $totalVisitors  = $this->provider->getVisitors( $range, $filters );

            return [
                'goal_id'         => $goalId,
                'goal_name'       => $goal->name,
                'goal_type'       => $goal->type,
                'conversions'     => $conversions,
                'total_value'     => round( (float) $totalValue, 2 ),
                'unique_visitors' => $uniqueVisitors,
                'unique_sessions' => $uniqueSessions,
                'conversion_rate' => $totalVisitors > 0
                    ? round( ( $uniqueVisitors / $totalVisitors ) * 100, 2 )
                    : 0.0,
                'average_value' => $conversions > 0
                    ? round( (float) $totalValue / $conversions, 2 )
                    : 0.0,
            ];
        } );
    }

    /**
     * Get events by name/category.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of results.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array<string, mixed>>
     *
     * @since 1.0.0
     */
    public function getEventBreakdown( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        $cacheKey = $this->buildCacheKey( 'event_breakdown', $range, $filters, $limit );

        return $this->cached( $cacheKey, function () use ( $range, $filters, $limit ): Collection {
            $query = Event::query()
                ->selectRaw( 'name, category, COUNT(*) as count, SUM(value) as total_value' )
                ->whereBetween( 'created_at', [$range->startDate, $range->endDate] )
                ->groupBy( 'name', 'category' )
                ->orderByDesc( 'count' )
                ->limit( $limit );

            if ( isset( $filters['site_id'] ) ) {
                $query->where( 'site_id', $filters['site_id'] );
            }

            if ( isset( $filters['category'] ) ) {
                $query->where( 'category', $filters['category'] );
            }

            if ( isset( $filters['source_package'] ) ) {
                $query->where( 'source_package', $filters['source_package'] );
            }

            $results = $query->get();
            $total   = $results->sum( 'count' );

            return $results->map( fn ( $row ) => [
                'name'        => $row->name,
                'category'    => $row->category,
                'count'       => (int) $row->count,
                'total_value' => round( (float) $row->total_value, 2 ),
                'percentage'  => $total > 0 ? round( ( $row->count / $total ) * 100, 2 ) : 0.0,
            ] );
        } );
    }

    /**
     * Get events over time.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  string  $granularity  Grouping granularity.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array<string, mixed>>
     *
     * @since 1.0.0
     */
    public function getEventsOverTime( DateRange $range, string $granularity = 'day', array $filters = [] ): Collection
    {
        $cacheKey = $this->buildCacheKey( 'events_over_time', $range, $filters, $granularity );

        return $this->cached( $cacheKey, function () use ( $range, $granularity, $filters ): Collection {
            $dateFormat = match ( $granularity ) {
                'hour'  => '%Y-%m-%d %H:00:00',
                'week'  => '%Y-%W',
                'month' => '%Y-%m',
                default => '%Y-%m-%d',
            };

            $query = Event::query()
                ->selectRaw( "DATE_FORMAT(created_at, '{$dateFormat}') as date, COUNT(*) as count" )
                ->whereBetween( 'created_at', [$range->startDate, $range->endDate] )
                ->groupBy( 'date' )
                ->orderBy( 'date' );

            if ( isset( $filters['site_id'] ) ) {
                $query->where( 'site_id', $filters['site_id'] );
            }

            if ( isset( $filters['name'] ) ) {
                $query->where( 'name', $filters['name'] );
            }

            if ( isset( $filters['category'] ) ) {
                $query->where( 'category', $filters['category'] );
            }

            return $query->get()->map( fn ( $row ) => [
                'date'  => $row->date,
                'count' => (int) $row->count,
            ] );
        } );
    }

    /**
     * Clear all cached analytics query results.
     *
     * Uses cache tags if available (Redis, Memcached), otherwise
     * returns false since we can't selectively clear without tags.
     *
     * @return bool True if cache was cleared, false if driver doesn't support tags.
     *
     * @since 1.0.0
     */
    public function clearCache(): bool
    {
        $store = Cache::getStore();

        // Use cache tags if the driver supports them (Redis, Memcached, etc.)
        if ( method_exists( $store, 'tags' ) ) {
            Cache::tags( $this->cacheTag )->flush();

            return true;
        }

        // For drivers that don't support tags, we can't selectively clear
        // without tracking keys. Return false to indicate cache wasn't cleared.
        // In production, consider using a cache driver that supports tags.
        return false;
    }

    /**
     * Enable or disable caching.
     *
     * @param  bool  $enabled  Whether caching should be enabled.
     *
     * @return static The current instance for method chaining.
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
     * @param  int  $seconds  Cache duration in seconds.
     *
     * @return static The current instance for method chaining.
     *
     * @since 1.0.0
     */
    public function setCacheDuration( int $seconds ): static
    {
        $this->cacheDuration = $seconds;

        return $this;
    }

    /**
     * Resolve the bot-filter mode into the filters array.
     *
     * Honours an explicit `bots` filter, then the pending modifier, then
     * defaults to excluding bots. The pending modifier is consumed so it only
     * affects a single query.
     *
     * @param  array<string, mixed>  $filters  The filters to resolve.
     *
     * @return array<string, mixed>
     *
     * @since 1.2.0
     */
    protected function resolveBotMode( array $filters ): array
    {
        if ( ! isset( $filters['bots'] ) ) {
            $filters['bots'] = $this->pendingBotMode ?? 'exclude';
        }

        $this->pendingBotMode = null;

        return $filters;
    }

    /**
     * Widen a result limit when candidates come from two sources.
     *
     * Combined rankings are built from each source's own top rows, so the pool
     * is widened before merging to reduce the chance of a page that ranks
     * modestly in both sources being dropped before its combined total is
     * known.
     *
     * @param  int  $limit  The requested limit.
     *
     * @return int The candidate limit to query each source with.
     *
     * @since 1.5.0
     */
    protected function candidateLimit( int $limit ): int
    {
        return max( $limit, min( 100, $limit * 3 ) );
    }

    /**
     * Get the top referring hosts from identified page views.
     *
     * Joins each page view to its session so the referrer recorded once per
     * session can be counted per view, keeping the unit the same as the
     * anonymous side.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of hosts to return.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{host: string, views: int}>
     *
     * @since 1.5.0
     */
    protected function getIdentifiedReferringHosts( DateRange $range, int $limit, array $filters ): Collection
    {
        try {
            $query = PageView::query()
                ->leftJoin(
                    'analytics_sessions',
                    'analytics_page_views.session_id',
                    '=',
                    'analytics_sessions.session_id',
                )
                ->select( [
                    DB::raw( "COALESCE(analytics_sessions.referrer_domain, 'direct') as host" ),
                    DB::raw( 'COUNT(*) as views' ),
                ] )
                ->whereBetween( 'analytics_page_views.created_at', [ $range->startDate, $range->endDate ] )
                ->groupBy( 'host' )
                ->orderByDesc( 'views' )
                ->limit( $limit );

            if ( isset( $filters['site_id'] ) ) {
                $query->where( 'analytics_page_views.site_id', $filters['site_id'] );
            }

            if ( isset( $filters['tenant_id'] ) && analyticsMultiTenancyEnabled() ) {
                $query->where( 'analytics_page_views.tenant_id', $filters['tenant_id'] );
            }

            if ( isset( $filters['path'] ) ) {
                $query->where( 'analytics_page_views.path', $filters['path'] );
            }

            $this->applyBotScopeToPageViews( $query, $filters );

            return $query->get()->map( fn ( $row ) => [
                'host'  => (string) ( $row->host ?? 'direct' ),
                'views' => (int) $row->views,
            ] );
        } catch ( Throwable ) {
            return collect();
        }
    }

    /**
     * Apply the bot-filter mode to a page-view query.
     *
     * Mirrors the local provider's scoping: bots are excluded by default, and
     * only page views whose visitor is a confirmed bot are removed, so rows
     * with an unresolved visitor are still counted.
     *
     * @param  Builder<PageView>  $query  The query builder.
     * @param  array<string, mixed>  $filters  The filters to apply.
     *
     * @since 1.5.0
     */
    protected function applyBotScopeToPageViews( Builder $query, array $filters ): void
    {
        $mode = $filters['bots'] ?? 'exclude';

        if ( 'include' === $mode ) {
            return;
        }

        if ( 'only' === $mode ) {
            $query->whereHas( 'visitor', fn ( Builder $visitor ) => $visitor->where( 'is_bot', true ) );

            return;
        }

        $query->whereDoesntHave( 'visitor', fn ( Builder $visitor ) => $visitor->where( 'is_bot', true ) );
    }

    /**
     * Merge an anonymous time series into the identified one.
     *
     * Buckets are keyed by date. `visitors` stays identified-only because an
     * anonymous row has no visitor to count.
     *
     * @param  Collection<int, array{date: string, pageviews: int, visitors: int}>  $identified  The identified series.
     * @param  Collection<int, array{date: string, pageviews: int}>  $anonymous  The anonymous series.
     *
     * @return Collection<int, array{date: string, pageviews: int, visitors: int}>
     *
     * @since 1.5.0
     */
    protected function mergeAnonymousTimeSeries( Collection $identified, Collection $anonymous ): Collection
    {
        $buckets = [];

        foreach ( $identified as $row ) {
            $date             = (string) ( $row['date'] ?? '' );
            $buckets[ $date ] = [
                'date'                 => $date,
                'pageviews'            => (int) ( $row['pageviews'] ?? 0 ),
                'visitors'             => (int) ( $row['visitors'] ?? 0 ),
                'identified_pageviews' => (int) ( $row['pageviews'] ?? 0 ),
                'anonymous_pageviews'  => 0,
            ];
        }

        foreach ( $anonymous as $row ) {
            $date = (string) ( $row['date'] ?? '' );

            $buckets[ $date ] ??= [
                'date'                 => $date,
                'pageviews'            => 0,
                'visitors'             => 0,
                'identified_pageviews' => 0,
                'anonymous_pageviews'  => 0,
            ];

            $buckets[ $date ]['pageviews'] += (int) ( $row['pageviews'] ?? 0 );
            $buckets[ $date ]['anonymous_pageviews'] += (int) ( $row['pageviews'] ?? 0 );
        }

        ksort( $buckets );

        return collect( array_values( $buckets ) );
    }

    /**
     * Merge anonymous page counts into the identified top-pages list.
     *
     * @param  Collection<int, array<string, mixed>>  $identified  The identified top pages.
     * @param  Collection<int, array{path: string, title: string, views: int}>  $anonymous  The anonymous top pages.
     * @param  int  $limit  Maximum number of rows to return.
     *
     * @return Collection<int, array<string, mixed>>
     *
     * @since 1.5.0
     */
    protected function mergeAnonymousTopPages( Collection $identified, Collection $anonymous, int $limit ): Collection
    {
        $pages = [];

        foreach ( $identified as $row ) {
            $path           = (string) ( $row['path'] ?? '' );
            $pages[ $path ] = [
                'path'             => $path,
                'title'            => (string) ( $row['title'] ?? '' ),
                'views'            => (int) ( $row['views'] ?? 0 ),
                'unique_views'     => (int) ( $row['unique_views'] ?? 0 ),
                'identified_views' => (int) ( $row['views'] ?? 0 ),
                'anonymous_views'  => 0,
            ];
        }

        foreach ( $anonymous as $row ) {
            $path = (string) ( $row['path'] ?? '' );

            $pages[ $path ] ??= [
                'path'             => $path,
                'title'            => (string) ( $row['title'] ?? '' ),
                'views'            => 0,
                'unique_views'     => 0,
                'identified_views' => 0,
                'anonymous_views'  => 0,
            ];

            if ( '' === $pages[ $path ]['title'] ) {
                $pages[ $path ]['title'] = (string) ( $row['title'] ?? '' );
            }

            $pages[ $path ]['views'] += (int) ( $row['views'] ?? 0 );
            $pages[ $path ]['anonymous_views'] += (int) ( $row['views'] ?? 0 );
        }

        return collect( array_values( $pages ) )
            ->sortByDesc( 'views' )
            ->take( $limit )
            ->values();
    }

    /**
     * Merge the anonymous device split into the identified breakdown.
     *
     * @param  Collection<int, array<string, mixed>>  $identified  The identified breakdown.
     * @param  Collection<int, array{device_type: string, views: int, percentage: float}>  $anonymous  The anonymous split.
     *
     * @return Collection<int, array<string, mixed>>
     *
     * @since 1.5.0
     */
    protected function mergeAnonymousDeviceBreakdown( Collection $identified, Collection $anonymous ): Collection
    {
        $devices = [];

        foreach ( $identified as $row ) {
            $type             = (string) ( $row['device_type'] ?? 'unknown' );
            $devices[ $type ] = [
                'device_type'          => $type,
                'sessions'             => (int) ( $row['sessions'] ?? 0 ),
                'percentage'           => (float) ( $row['percentage'] ?? 0.0 ),
                'anonymous_views'      => 0,
                'anonymous_percentage' => 0.0,
            ];
        }

        foreach ( $anonymous as $row ) {
            $type = (string) ( $row['device_type'] ?? 'unknown' );

            $devices[ $type ] ??= [
                'device_type'          => $type,
                'sessions'             => 0,
                'percentage'           => 0.0,
                'anonymous_views'      => 0,
                'anonymous_percentage' => 0.0,
            ];

            $devices[ $type ]['anonymous_views'] += (int) ( $row['views'] ?? 0 );
            $devices[ $type ]['anonymous_percentage'] = (float) ( $row['percentage'] ?? 0.0 );
        }

        return collect( array_values( $devices ) )
            ->sortByDesc( fn ( array $row ) => [ $row['sessions'], $row['anonymous_views'] ] )
            ->values();
    }

    /**
     * Get comparison statistics for the previous period.
     *
     * @param  DateRange  $range  The current date range.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return array<string, array{value: float|int, change: float, trend: string}>
     *
     * @since 1.0.0
     */
    protected function getComparisonStats( DateRange $range, array $filters = [] ): array
    {
        $previousRange = $range->getPreviousPeriod();
        $mode          = $filters['anonymous'] ?? 'exclude';

        // Compare like with like: when the current period includes anonymous
        // page views, the previous period has to as well, or the change figure
        // reports the toggle rather than the traffic.
        $currentAnonymous  = 'exclude' === $mode ? 0 : $this->getAnonymousPageViewCount( $range, $filters );
        $previousAnonymous = 'exclude' === $mode ? 0 : $this->getAnonymousPageViewCount( $previousRange, $filters );

        $anonymousOnly = 'only' === $mode;

        $currentPageviews  = ( $anonymousOnly ? 0 : $this->provider->getPageViews( $range, $filters ) ) + $currentAnonymous;
        $previousPageviews = ( $anonymousOnly ? 0 : $this->provider->getPageViews( $previousRange, $filters ) ) + $previousAnonymous;

        $comparison = [
            'pageviews' => $this->calculateChange( $currentPageviews, $previousPageviews ),
        ];

        // In an anonymous-only view the identified metrics are not reported at
        // all, so there is nothing honest to compare them against.
        if ( $anonymousOnly ) {
            return $comparison;
        }

        $currentVisitors = $this->provider->getVisitors( $range, $filters );
        $currentSessions = $this->provider->getSessions( $range, $filters );
        $currentBounce   = $this->provider->getBounceRate( $range, $filters );
        $currentDuration = $this->provider->getAverageSessionDuration( $range, $filters );

        $previousVisitors = $this->provider->getVisitors( $previousRange, $filters );
        $previousSessions = $this->provider->getSessions( $previousRange, $filters );
        $previousBounce   = $this->provider->getBounceRate( $previousRange, $filters );
        $previousDuration = $this->provider->getAverageSessionDuration( $previousRange, $filters );

        return array_merge( $comparison, [
            'visitors'             => $this->calculateChange( $currentVisitors, $previousVisitors ),
            'sessions'             => $this->calculateChange( $currentSessions, $previousSessions ),
            'bounce_rate'          => $this->calculateChange( $currentBounce, $previousBounce, true ),
            'avg_session_duration' => $this->calculateChange( $currentDuration, $previousDuration ),
        ] );
    }

    /**
     * Calculate the percentage change between two values.
     *
     * @param  float|int  $current  The current value.
     * @param  float|int  $previous  The previous value.
     * @param  bool  $invertBetter  Whether a decrease is better (e.g., bounce rate).
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
     * @param  string  $method  The method name.
     * @param  DateRange  $range  The date range.
     * @param  mixed  ...$params  Additional parameters.
     *
     * @return string The generated cache key.
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
     * @param  string  $key  The cache key.
     * @param  callable  $callback  The query callback.
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
            return Cache::tags( $this->cacheTag)->remember( $key, $this->cacheDuration, $callback);
        }

        return Cache::remember( $key, $this->cacheDuration, $callback);
    }
}
