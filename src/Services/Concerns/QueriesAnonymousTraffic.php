<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services\Concerns;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Models\AnonymousPageView;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Anonymous-traffic querying for the AnalyticsQuery service.
 *
 * Reads `analytics_anonymous_page_views`, the table written by anonymous mode
 * for visitors who have not granted consent. Those rows carry no visitor,
 * session, fingerprint, IP or user agent, so they can be counted and nothing
 * else: they contribute to page-view totals, top pages, referring hosts,
 * device-class splits and time series, and can never contribute to unique
 * visitors, sessions, bounce rate or session duration.
 *
 * This lives on the concrete AnalyticsQuery class rather than on
 * AnalyticsQueryInterface. That interface is a published contract and adding
 * methods to it would break every implementor.
 *
 * @since   1.5.0
 *
 * @package ArtisanPackUI\Analytics\Services\Concerns
 */
trait QueriesAnonymousTraffic
{
    /**
     * Pending anonymous-filter mode for the next query.
     *
     * One of 'exclude', 'include', or 'only'. Null falls back to the default
     * ('exclude'). Reset to null after each query so the modifier applies to a
     * single call only.
     */
    protected ?string $pendingAnonymousMode = null;

    /**
     * Include anonymous traffic alongside identified traffic in the next query.
     *
     * @return static The current instance for method chaining.
     *
     * @since 1.5.0
     */
    public function includeAnonymous(): static
    {
        $this->pendingAnonymousMode = 'include';

        return $this;
    }

    /**
     * Include anonymous traffic alongside identified traffic in the next query.
     *
     * Alias of includeAnonymous().
     *
     * @return static The current instance for method chaining.
     *
     * @since 1.5.0
     */
    public function withAnonymous(): static
    {
        return $this->includeAnonymous();
    }

    /**
     * Restrict the next query to anonymous traffic only.
     *
     * @return static The current instance for method chaining.
     *
     * @since 1.5.0
     */
    public function onlyAnonymous(): static
    {
        $this->pendingAnonymousMode = 'only';

        return $this;
    }

    /**
     * Exclude anonymous traffic from the next query (the default behaviour).
     *
     * @return static The current instance for method chaining.
     *
     * @since 1.5.0
     */
    public function excludeAnonymous(): static
    {
        $this->pendingAnonymousMode = 'exclude';

        return $this;
    }

    /**
     * Whether pre-consent anonymous collection is enabled.
     *
     * @return bool True when `privacy.anonymous_mode` is on.
     *
     * @since 1.5.0
     */
    public function isAnonymousModeEnabled(): bool
    {
        return (bool) config( 'artisanpack.analytics.privacy.anonymous_mode', false );
    }

    /**
     * Whether any anonymous rows exist for a date range.
     *
     * Lets a dashboard hide the anonymous surface entirely rather than showing
     * a zeroed panel that reads like a fault.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return bool True when at least one anonymous row was collected.
     *
     * @since 1.5.0
     */
    public function hasAnonymousData( DateRange $range, array $filters = [] ): bool
    {
        return $this->getAnonymousPageViewCount( $range, $filters ) > 0;
    }

    /**
     * Get the anonymous page-view count for a date range.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return int The anonymous page-view count.
     *
     * @since 1.5.0
     */
    public function getAnonymousPageViewCount( DateRange $range, array $filters = [] ): int
    {
        $cacheKey = $this->buildCacheKey( 'anonymous_pageview_count', $range, $this->anonymousCacheScope( $filters ) );

        return $this->cachedAnonymousQuery(
            $cacheKey,
            fn () => $this->anonymousQuery( $range, $filters )->count(),
            0,
        );
    }

    /**
     * Get the top pages by anonymous page views.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of pages to return.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{path: string, title: string, views: int}>
     *
     * @since 1.5.0
     */
    public function getAnonymousTopPages( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        $cacheKey = $this->buildCacheKey( 'anonymous_top_pages', $range, $this->anonymousCacheScope( $filters ), $limit );

        return $this->cachedAnonymousQuery(
            $cacheKey,
            fn () => $this->anonymousQuery( $range, $filters )
                ->select( [
                    'path',
                    DB::raw( 'MAX(title) as title' ),
                    DB::raw( 'COUNT(*) as views' ),
                ] )
                ->groupBy( 'path' )
                ->orderByDesc( 'views' )
                ->limit( $limit )
                ->get()
                ->map( fn ( $row ) => [
                    'path'  => (string) $row->path,
                    'title' => (string) ( $row->title ?? '' ),
                    'views' => (int) $row->views,
                ] ),
            collect(),
        );
    }

    /**
     * Get the top referring hosts by anonymous page views.
     *
     * Rows with no referrer are reported under the `direct` host, matching how
     * the identified traffic-source query labels referrer-less traffic.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of hosts to return.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{host: string, views: int}>
     *
     * @since 1.5.0
     */
    public function getAnonymousReferringHosts( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        $cacheKey = $this->buildCacheKey( 'anonymous_referring_hosts', $range, $this->anonymousCacheScope( $filters ), $limit );

        return $this->cachedAnonymousQuery(
            $cacheKey,
            fn () => $this->anonymousQuery( $range, $filters )
                ->select( [
                    'referrer_host',
                    DB::raw( 'COUNT(*) as views' ),
                ] )
                ->groupBy( 'referrer_host' )
                ->orderByDesc( 'views' )
                ->limit( $limit )
                ->get()
                ->map( fn ( $row ) => [
                    'host'  => ( null === $row->referrer_host || '' === $row->referrer_host )
                        ? 'direct'
                        : (string) $row->referrer_host,
                    'views' => (int) $row->views,
                ] ),
            collect(),
        );
    }

    /**
     * Get the anonymous device-class breakdown.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{device_type: string, views: int, percentage: float}>
     *
     * @since 1.5.0
     */
    public function getAnonymousDeviceBreakdown( DateRange $range, array $filters = [] ): Collection
    {
        $cacheKey = $this->buildCacheKey( 'anonymous_device_breakdown', $range, $this->anonymousCacheScope( $filters ) );

        return $this->cachedAnonymousQuery(
            $cacheKey,
            function () use ( $range, $filters ): Collection {
                $results = $this->anonymousQuery( $range, $filters )
                    ->select( [
                        'device_type',
                        DB::raw( 'COUNT(*) as views' ),
                    ] )
                    ->groupBy( 'device_type' )
                    ->orderByDesc( 'views' )
                    ->get();

                $total = (int) $results->sum( 'views' );

                return $results->map( fn ( $row ) => [
                    'device_type' => (string) ( $row->device_type ?? 'unknown' ),
                    'views'       => (int) $row->views,
                    'percentage'  => $total > 0 ? round( ( (int) $row->views / $total ) * 100, 2 ) : 0.0,
                ] );
            },
            collect(),
        );
    }

    /**
     * Get anonymous page views grouped over time.
     *
     * Uses the same SQL date formatting as the identified time series so the
     * two can be merged bucket for bucket.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  string  $granularity  Grouping granularity ('hour', 'day', 'week', 'month').
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{date: string, pageviews: int}>
     *
     * @since 1.5.0
     */
    public function getAnonymousPageViewsOverTime( DateRange $range, string $granularity = 'day', array $filters = [] ): Collection
    {
        $cacheKey = $this->buildCacheKey( 'anonymous_pageviews_over_time', $range, $this->anonymousCacheScope( $filters ), $granularity );

        return $this->cachedAnonymousQuery(
            $cacheKey,
            function () use ( $range, $granularity, $filters ): Collection {
                return $this->anonymousQuery( $range, $filters )
                    ->select( [
                        DB::raw( $this->anonymousDateExpression( $granularity ) . ' as date' ),
                        DB::raw( 'COUNT(*) as pageviews' ),
                    ] )
                    ->groupBy( 'date' )
                    ->orderBy( 'date' )
                    ->get()
                    ->map( fn ( $row ) => [
                        'date'      => (string) $row->date,
                        'pageviews' => (int) $row->pageviews,
                    ] );
            },
            collect(),
        );
    }

    /**
     * Get an aggregated summary of anonymous traffic for a date range.
     *
     * Mirrors getBotStats(): a single call a dashboard panel can render. Every
     * figure here is a count of page views — there is deliberately no visitor,
     * session or bounce figure, because anonymous rows cannot produce one.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of pages and hosts to return.
     * @param  string  $granularity  Trend grouping granularity.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return array{
     *     enabled: bool,
     *     anonymous_pageviews: int,
     *     identified_pageviews: int,
     *     total_pageviews: int,
     *     anonymous_percentage: float,
     *     top_pages: array<int, array{path: string, title: string, views: int}>,
     *     referring_hosts: array<int, array{host: string, views: int}>,
     *     device_breakdown: array<int, array{device_type: string, views: int, percentage: float}>,
     *     trend: array<int, array{date: string, pageviews: int}>
     * }
     *
     * @since 1.5.0
     */
    public function getAnonymousStats( DateRange $range, int $limit = 10, string $granularity = 'day', array $filters = [] ): array
    {
        // This method controls anonymous scoping itself, so discard any caller
        // mode rather than letting it skew the split.
        unset( $filters['anonymous'] );

        // Every dashboard render calls this, including on sites that never
        // switched anonymous mode on — five queries each against a table they
        // have no rows in. Every surface keys off `enabled` and renders the
        // feature-off message, so the figures behind it are never read.
        if ( ! $this->isAnonymousModeEnabled() ) {
            return [
                'enabled'              => false,
                'anonymous_pageviews'  => 0,
                'identified_pageviews' => $this->getPageViewCount(
                    $range,
                    array_merge( $filters, [ 'anonymous' => 'exclude' ] ),
                ),
                'total_pageviews'      => 0,
                'anonymous_percentage' => 0.0,
                'top_pages'            => [],
                'referring_hosts'      => [],
                'device_breakdown'     => [],
                'trend'                => [],
            ];
        }

        $anonymous  = $this->getAnonymousPageViewCount( $range, $filters );
        $identified = $this->getPageViewCount( $range, array_merge( $filters, [ 'anonymous' => 'exclude' ] ) );
        $total      = $anonymous + $identified;

        return [
            'enabled'              => $this->isAnonymousModeEnabled(),
            'anonymous_pageviews'  => $anonymous,
            'identified_pageviews' => $identified,
            'total_pageviews'      => $total,
            'anonymous_percentage' => $total > 0 ? round( ( $anonymous / $total ) * 100, 1 ) : 0.0,
            'top_pages'            => $this->getAnonymousTopPages( $range, $limit, $filters )->values()->all(),
            'referring_hosts'      => $this->getAnonymousReferringHosts( $range, $limit, $filters )->values()->all(),
            'device_breakdown'     => $this->getAnonymousDeviceBreakdown( $range, $filters )->values()->all(),
            'trend'                => $this->getAnonymousPageViewsOverTime( $range, $granularity, $filters )->values()->all(),
        ];
    }

    /**
     * Resolve the anonymous-filter mode into the filters array.
     *
     * Honours an explicit `anonymous` filter, then the pending modifier, then
     * defaults to excluding anonymous traffic. The pending modifier is consumed
     * so it only affects a single query.
     *
     * When anonymous mode is switched off there is nothing to include, so any
     * requested mode collapses back to 'exclude'.
     *
     * @param  array<string, mixed>  $filters  The filters to resolve.
     *
     * @return array<string, mixed>
     *
     * @since 1.5.0
     */
    protected function resolveAnonymousMode( array $filters ): array
    {
        if ( ! isset( $filters['anonymous'] ) ) {
            $filters['anonymous'] = $this->pendingAnonymousMode ?? 'exclude';
        }

        $this->pendingAnonymousMode = null;

        if ( ! in_array( $filters['anonymous'], [ 'exclude', 'include', 'only' ], true ) ) {
            $filters['anonymous'] = 'exclude';
        }

        if ( ! $this->isAnonymousModeEnabled() ) {
            $filters['anonymous'] = 'exclude';
        }

        return $filters;
    }

    /**
     * Build the base anonymous page-view query for a range and filters.
     *
     * Only the filters an anonymous row can honour are applied. There is no
     * visitor, session or user-agent column here to filter on, and bot scoping
     * is meaningless for the same reason.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  The filters to apply.
     *
     * @return Builder<AnonymousPageView>
     *
     * @since 1.5.0
     */
    protected function anonymousQuery( DateRange $range, array $filters ): Builder
    {
        $query = AnonymousPageView::query()
            ->whereBetween( 'created_at', [ $range->startDate, $range->endDate ] );

        if ( isset( $filters['site_id'] ) ) {
            $query->where( 'site_id', $filters['site_id'] );
        }

        if ( isset( $filters['tenant_id'] ) && analyticsMultiTenancyEnabled() ) {
            $query->where( 'tenant_id', $filters['tenant_id'] );
        }

        if ( isset( $filters['path'] ) ) {
            $query->where( 'path', $filters['path'] );
        }

        return $query;
    }

    /**
     * Reduce filters to the subset anonymous queries actually honour.
     *
     * Keeps cache keys from fragmenting on filters that cannot change an
     * anonymous result, such as bot scoping or a visitor ID.
     *
     * @param  array<string, mixed>  $filters  The filters to reduce.
     *
     * @return array<string, mixed>
     *
     * @since 1.5.0
     */
    protected function anonymousCacheScope( array $filters ): array
    {
        return array_intersect_key( $filters, array_flip( [ 'site_id', 'tenant_id', 'path' ] ) );
    }

    /**
     * Cache an anonymous query's result, but never cache its failure.
     *
     * The fallback exists so that a table a 1.5.0 migration has not created
     * yet, or a SQL function this driver lacks, degrades one panel instead of
     * taking the dashboard down. Caching the fallback, though, turns a
     * momentary database blip into zeroes shown for the whole
     * `cache_duration` — long after the database recovered. So a failed query
     * drops its key again and the next caller retries.
     *
     * @template TDefault
     *
     * @param  string  $cacheKey  The cache key for this query.
     * @param  callable  $callback  The query callback.
     * @param  TDefault  $default  The value to return when the query fails.
     *
     * @return mixed|TDefault
     *
     * @since 1.5.0
     */
    protected function cachedAnonymousQuery( string $cacheKey, callable $callback, mixed $default ): mixed
    {
        $failed = false;

        $result = $this->cached( $cacheKey, function () use ( $callback, $default, &$failed ): mixed {
            try {
                return $callback();
            } catch ( Throwable ) {
                $failed = true;

                return $default;
            }
        } );

        if ( $failed ) {
            $this->forgetCachedAnonymousQuery( $cacheKey );
        }

        return $result;
    }

    /**
     * Drop a single cached anonymous query result.
     *
     * @param  string  $cacheKey  The cache key to forget.
     *
     * @return void
     *
     * @since 1.5.0
     */
    protected function forgetCachedAnonymousQuery( string $cacheKey ): void
    {
        $store = Cache::getStore();

        if ( method_exists( $store, 'tags' ) ) {
            Cache::tags( $this->cacheTag )->forget( $cacheKey );

            return;
        }

        Cache::forget( $cacheKey );
    }

    /**
     * Get the SQL date format string for a granularity.
     *
     * Mirrors the identified provider's formats so merged buckets line up.
     *
     * @param  string  $granularity  The time granularity.
     *
     * @return string The SQL date format string.
     *
     * @since 1.5.0
     */
    protected function anonymousDateFormat( string $granularity ): string
    {
        return match ( $granularity ) {
            'hour'  => '%Y-%m-%d %H:00',
            'week'  => '%Y-%W',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };
    }

    /**
     * Build the SQL expression that buckets `created_at` for this driver.
     *
     * `DATE_FORMAT()` is MySQL-only, so on SQLite or Postgres the whole
     * time series came back empty through the failure fallback — while totals
     * stayed correct, which is the worst shape for a bug to have. Worse, the
     * test suite runs on SQLite, so the merge this feeds had no real coverage
     * at all.
     *
     * @param  string  $granularity  The time granularity.
     *
     * @return string The SQL expression producing the bucket label.
     *
     * @since 1.5.0
     */
    protected function anonymousDateExpression( string $granularity ): string
    {
        $driver = AnonymousPageView::query()->getConnection()->getDriverName();
        $format = $this->anonymousDateFormat( $granularity );

        return match ( $driver ) {
            'sqlite' => sprintf( "strftime('%s', created_at)", $this->sqliteDateFormat( $granularity ) ),
            'pgsql'  => sprintf( "to_char(created_at, '%s')", $this->postgresDateFormat( $granularity ) ),
            default  => sprintf( "DATE_FORMAT(created_at, '%s')", $format ),
        };
    }

    /**
     * Get the SQLite strftime format for a granularity.
     *
     * Buckets have to be labelled identically across drivers, because
     * mergeAnonymousTimeSeries() joins the identified and anonymous series on
     * the label. `%W` is MySQL's week-of-year, which SQLite spells `%W` too.
     *
     * @param  string  $granularity  The time granularity.
     *
     * @return string The strftime format string.
     *
     * @since 1.5.0
     */
    protected function sqliteDateFormat( string $granularity ): string
    {
        return match ( $granularity ) {
            'hour'  => '%Y-%m-%d %H:00',
            'week'  => '%Y-%W',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };
    }

    /**
     * Get the Postgres to_char format for a granularity.
     *
     * @param  string  $granularity  The time granularity.
     *
     * @return string The to_char format string.
     *
     * @since 1.5.0
     */
    protected function postgresDateFormat( string $granularity ): string
    {
        return match ( $granularity ) {
            'hour'  => 'YYYY-MM-DD HH24:00',
            'week'  => 'YYYY-WW',
            'month' => 'YYYY-MM',
            default => 'YYYY-MM-DD',
        };
    }
}
