<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Providers;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Jobs\ProcessEvent;
use ArtisanPackUI\Analytics\Jobs\ProcessPageView;
use ArtisanPackUI\Analytics\Models\Event;
use ArtisanPackUI\Analytics\Models\PageView;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Local database analytics provider.
 *
 * This provider stores all analytics data in your local database,
 * providing complete privacy and control over your data. It implements
 * all query methods for retrieving analytics metrics.
 *
 * @since   1.0.0
 */
class LocalAnalyticsProvider extends AbstractAnalyticsProvider
{
    /**
     * Get the provider's unique name.
     *
     *
     * @since 1.0.0
     */
    public function getName(): string
    {
        return 'local';
    }

    /**
     * Check if this provider is enabled.
     *
     *
     * @since 1.0.0
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? true;
    }

    /**
     * Track a page view.
     *
     * @param  PageViewData  $data  The page view data.
     *
     * @since 1.0.0
     */
    public function trackPageView( PageViewData $data ): void
    {
        $this->safeExecute( function () use ( $data ): void {
            if ( $this->shouldQueue() ) {
                ProcessPageView::dispatch( $data )
                    ->onQueue( $this->getQueueName() );

                return;
            }

            $this->storePageView( $data );
        } );
    }

    /**
     * Track a custom event.
     *
     * @param  EventData  $data  The event data.
     *
     * @since 1.0.0
     */
    public function trackEvent( EventData $data ): void
    {
        $this->safeExecute( function () use ( $data ): void {
            if ( $this->shouldQueue() ) {
                ProcessEvent::dispatch( $data )
                    ->onQueue( $this->getQueueName() );

                return;
            }

            $this->storeEvent( $data );
        } );
    }

    /**
     * Store a page view in the database.
     *
     * Only page-specific data is stored here. Visitor attributes (ipAddress, userAgent,
     * browser, os, deviceType, screenWidth, screenHeight, viewportWidth, viewportHeight)
     * and UTM parameters are intentionally excluded from the page_views table because
     * they are stored in the related analytics_visitors and analytics_sessions tables.
     * This normalized design avoids data duplication and allows efficient querying.
     *
     * @param  PageViewData  $data  The page view data.
     *
     * @since 1.0.0
     */
    public function storePageView( PageViewData $data ): void
    {
        PageView::create( [
            'session_id'  => $data->sessionId,
            'visitor_id'  => $data->visitorId,
            'path'        => $data->path,
            'title'       => $data->title,
            'referrer'    => $data->referrer,
            'load_time'   => $data->loadTime,
            'custom_data' => $data->customData,
            'tenant_id'   => $data->tenantId,
        ] );
    }

    /**
     * Store an event in the database.
     *
     * Only event-specific data is stored here. Visitor attributes (ipAddress, userAgent,
     * browser, os, deviceType) are intentionally excluded from the events table because
     * they are stored in the related analytics_visitors table. This normalized design
     * avoids data duplication and allows efficient querying.
     *
     * @param  EventData  $data  The event data.
     *
     * @since 1.0.0
     */
    public function storeEvent( EventData $data ): void
    {
        Event::create( [
            'session_id' => $data->sessionId,
            'visitor_id' => $data->visitorId,
            'name'       => $data->name,
            'category'   => $data->category,
            'properties' => $data->properties,
            'value'      => $data->value,
            'path'       => $data->path,
            'tenant_id'  => $data->tenantId,
        ] );
    }

    /**
     * Get total page views for a date range.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return int The total page view count.
     *
     * @since 1.0.0
     */
    public function getPageViews( DateRange $range, array $filters = [] ): int
    {
        return $this->safeQuery(
            fn () => $this->applyFilters( PageView::query(), $filters )
                ->whereBetween( 'created_at', [$range->startDate, $range->endDate] )
                ->count(),
            0,
        );
    }

    /**
     * Get unique visitors for a date range.
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
        return $this->safeQuery(
            fn () => $this->applyFilters( PageView::query(), $filters )
                ->whereBetween( 'created_at', [$range->startDate, $range->endDate] )
                ->distinct( 'visitor_id' )
                ->count( 'visitor_id' ),
            0,
        );
    }

    /**
     * Get total sessions for a date range.
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
        return $this->safeQuery(
            fn () => $this->applyFilters( Session::query(), $filters )
                ->whereBetween( 'started_at', [$range->startDate, $range->endDate] )
                ->count(),
            0,
        );
    }

    /**
     * Get top pages by page views.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of pages to return.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{path: string, title: string, views: int, unique_views: int}>
     *
     * @since 1.0.0
     */
    public function getTopPages( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        return $this->safeQuery(
            function () use ( $range, $limit, $filters ) {
                return $this->applyFilters( PageView::query(), $filters )
                    ->select( [
                        'path',
                        DB::raw( 'MAX(title) as title' ),
                        DB::raw( 'COUNT(*) as views' ),
                        DB::raw( 'COUNT(DISTINCT visitor_id) as unique_views' ),
                    ] )
                    ->whereBetween( 'created_at', [$range->startDate, $range->endDate] )
                    ->groupBy( 'path' )
                    ->orderByDesc( 'views' )
                    ->limit( $limit )
                    ->get()
                    ->map( fn ( $row ) => [
                        'path'         => $row->path,
                        'title'        => $row->title ?? '',
                        'views'        => (int) $row->views,
                        'unique_views' => (int) $row->unique_views,
                    ] );
            },
            collect(),
        );
    }

    /**
     * Get traffic sources breakdown.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of sources to return.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{source: string, medium: string, sessions: int, visitors: int}>
     *
     * @since 1.0.0
     */
    public function getTrafficSources( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        return $this->safeQuery(
            function () use ( $range, $limit, $filters ) {
                return $this->applyFilters( Session::query(), $filters )
                    ->select( [
                        DB::raw( 'COALESCE(utm_source, referrer_domain, "direct") as source' ),
                        DB::raw( 'COALESCE(utm_medium, referrer_type, "none") as medium' ),
                        DB::raw( 'COUNT(*) as sessions' ),
                        DB::raw( 'COUNT(DISTINCT visitor_id) as visitors' ),
                    ] )
                    ->whereBetween( 'started_at', [$range->startDate, $range->endDate] )
                    ->groupBy( DB::raw( 'COALESCE(utm_source, referrer_domain, "direct")' ), DB::raw( 'COALESCE(utm_medium, referrer_type, "none")' ) )
                    ->orderByDesc( 'sessions' )
                    ->limit( $limit )
                    ->get()
                    ->map( fn ( $row ) => [
                        'source'   => $row->source ?? 'direct',
                        'medium'   => $row->medium ?? 'none',
                        'sessions' => (int) $row->sessions,
                        'visitors' => (int) $row->visitors,
                    ] );
            },
            collect(),
        );
    }

    /**
     * Get bounce rate for a date range.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return float The bounce rate as a percentage (0-100).
     *
     * @since 1.0.0
     */
    public function getBounceRate( DateRange $range, array $filters = [] ): float
    {
        return $this->safeQuery(
            function () use ( $range, $filters ) {
                $query = $this->applyFilters( Session::query(), $filters )
                    ->whereBetween( 'started_at', [$range->startDate, $range->endDate] );

                $totalSessions   = $query->count();
                $bouncedSessions = ( clone $query )->where( 'is_bounce', true )->count();

                if ( 0 === $totalSessions ) {
                    return 0.0;
                }

                return round( ( $bouncedSessions / $totalSessions ) * 100, 2 );
            },
            0.0,
        );
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
        return $this->safeQuery(
            fn () => (int) $this->applyFilters( Session::query(), $filters )
                ->whereBetween( 'started_at', [$range->startDate, $range->endDate] )
                ->where( 'duration', '>', 0 )
                ->avg( 'duration' ) ?? 0,
            0,
        );
    }

    /**
     * Get page views over time.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  string  $granularity  The time granularity ('hour', 'day', 'week', 'month').
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{date: string, pageviews: int, visitors: int}>
     *
     * @since 1.0.0
     */
    public function getPageViewsOverTime( DateRange $range, string $granularity = 'day', array $filters = [] ): Collection
    {
        return $this->safeQuery(
            function () use ( $range, $granularity, $filters ) {
                $dateFormat = $this->getDateFormatForGranularity( $granularity );

                return $this->applyFilters( PageView::query(), $filters )
                    ->select( [
                        DB::raw( "DATE_FORMAT(created_at, '{$dateFormat}') as date" ),
                        DB::raw( 'COUNT(*) as pageviews' ),
                        DB::raw( 'COUNT(DISTINCT visitor_id) as visitors' ),
                    ] )
                    ->whereBetween( 'created_at', [$range->startDate, $range->endDate] )
                    ->groupBy( 'date' )
                    ->orderBy( 'date' )
                    ->get()
                    ->map( fn ( $row ) => [
                        'date'      => $row->date,
                        'pageviews' => (int) $row->pageviews,
                        'visitors'  => (int) $row->visitors,
                    ] );
            },
            collect(),
        );
    }

    /**
     * Get device breakdown.
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
        return $this->safeQuery(
            function () use ( $range, $filters ) {
                $results = $this->applyFilters( Visitor::query(), $filters )
                    ->join( 'analytics_sessions', 'analytics_visitors.id', '=', 'analytics_sessions.visitor_id' )
                    ->select( [
                        'analytics_visitors.device_type',
                        DB::raw( 'COUNT(analytics_sessions.id) as sessions' ),
                    ] )
                    ->whereBetween( 'analytics_sessions.started_at', [$range->startDate, $range->endDate] )
                    ->groupBy( 'analytics_visitors.device_type' )
                    ->orderByDesc( 'sessions' )
                    ->get();

                $total = $results->sum( 'sessions' );

                return $results->map( fn ( $row ) => [
                    'device_type' => $row->device_type ?? 'unknown',
                    'sessions'    => (int) $row->sessions,
                    'percentage'  => $total > 0 ? round( ( $row->sessions / $total ) * 100, 2 ) : 0.0,
                ] );
            },
            collect(),
        );
    }

    /**
     * Get browser breakdown.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of browsers to return.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{browser: string, version: string, sessions: int, percentage: float}>
     *
     * @since 1.0.0
     */
    public function getBrowserBreakdown( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        return $this->safeQuery(
            function () use ( $range, $limit, $filters ) {
                $results = $this->applyFilters( Visitor::query(), $filters )
                    ->join( 'analytics_sessions', 'analytics_visitors.id', '=', 'analytics_sessions.visitor_id' )
                    ->select( [
                        'analytics_visitors.browser',
                        'analytics_visitors.browser_version',
                        DB::raw( 'COUNT(analytics_sessions.id) as sessions' ),
                    ] )
                    ->whereBetween( 'analytics_sessions.started_at', [$range->startDate, $range->endDate] )
                    ->groupBy( 'analytics_visitors.browser', 'analytics_visitors.browser_version' )
                    ->orderByDesc( 'sessions' )
                    ->limit( $limit )
                    ->get();

                $total = $this->applyFilters( Session::query(), $filters )
                    ->whereBetween( 'started_at', [$range->startDate, $range->endDate] )
                    ->count();

                return $results->map( fn ( $row ) => [
                    'browser'    => $row->browser ?? 'Unknown',
                    'version'    => $row->browser_version ?? '',
                    'sessions'   => (int) $row->sessions,
                    'percentage' => $total > 0 ? round( ( $row->sessions / $total ) * 100, 2 ) : 0.0,
                ] );
            },
            collect(),
        );
    }

    /**
     * Get country breakdown.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of countries to return.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{country: string, country_code: string, sessions: int, percentage: float}>
     *
     * @since 1.0.0
     */
    public function getCountryBreakdown( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        return $this->safeQuery(
            function () use ( $range, $limit, $filters ) {
                $results = $this->applyFilters( Visitor::query(), $filters )
                    ->join( 'analytics_sessions', 'analytics_visitors.id', '=', 'analytics_sessions.visitor_id' )
                    ->select( [
                        'analytics_visitors.country',
                        DB::raw( 'COUNT(analytics_sessions.id) as sessions' ),
                    ] )
                    ->whereBetween( 'analytics_sessions.started_at', [$range->startDate, $range->endDate] )
                    ->whereNotNull( 'analytics_visitors.country' )
                    ->groupBy( 'analytics_visitors.country' )
                    ->orderByDesc( 'sessions' )
                    ->limit( $limit )
                    ->get();

                $total = $this->applyFilters( Session::query(), $filters )
                    ->whereBetween( 'started_at', [$range->startDate, $range->endDate] )
                    ->count();

                return $results->map( fn ( $row ) => [
                    'country'      => $row->country ?? 'Unknown',
                    'country_code' => $row->country ?? 'XX',
                    'sessions'     => (int) $row->sessions,
                    'percentage'   => $total > 0 ? round( ( $row->sessions / $total ) * 100, 2 ) : 0.0,
                ] );
            },
            collect(),
        );
    }

    /**
     * Get real-time visitor count.
     *
     * @param  int  $minutes  The number of minutes to consider as "real-time".
     *
     * @return int The number of active visitors.
     *
     * @since 1.0.0
     */
    public function getRealTimeVisitors( int $minutes = 5 ): int
    {
        return $this->safeQuery(
            fn () => Session::query()
                ->active( $minutes )
                ->distinct( 'visitor_id' )
                ->count( 'visitor_id' ),
            0,
        );
    }

    /**
     * Get statistics summary for a date range.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return array{pageviews: int, visitors: int, sessions: int, bounce_rate: float, avg_session_duration: int}
     *
     * @since 1.0.0
     */
    public function getStats( DateRange $range, array $filters = [] ): array
    {
        return [
            'pageviews'            => $this->getPageViews( $range, $filters ),
            'visitors'             => $this->getVisitors( $range, $filters ),
            'sessions'             => $this->getSessions( $range, $filters ),
            'bounce_rate'          => $this->getBounceRate( $range, $filters ),
            'avg_session_duration' => $this->getAverageSessionDuration( $range, $filters ),
        ];
    }

    /**
     * Get the configuration key for this provider.
     *
     *
     * @since 1.0.0
     */
    protected function getConfigKey(): string
    {
        return 'artisanpack.analytics.local';
    }

    /**
     * Perform health check for the local provider.
     *
     * Checks that the database connection is working and tables exist.
     *
     *
     * @since 1.0.0
     */
    protected function performHealthCheck(): bool
    {
        if ( ! $this->isEnabled() ) {
            return false;
        }

        try {
            // Check if we can query the page_views table
            PageView::query()->limit( 1 )->get();

            return true;
        } catch ( Throwable ) {
            return false;
        }
    }

    /**
     * Check if processing should be queued.
     *
     *
     * @since 1.0.0
     */
    protected function shouldQueue(): bool
    {
        return $this->config['queue_processing'] ?? true;
    }

    /**
     * Get the queue name to use.
     *
     *
     * @since 1.0.0
     */
    protected function getQueueName(): string
    {
        return $this->config['queue_name'] ?? 'analytics';
    }

    /**
     * Apply common filters to a query.
     *
     * @param  Builder  $query  The query builder.
     * @param  array<string, mixed>  $filters  The filters to apply.
     *
     * @since 1.0.0
     */
    protected function applyFilters( Builder $query, array $filters ): Builder
    {
        if ( isset( $filters['site_id'] ) ) {
            $query->where( 'site_id', $filters['site_id'] );
        }

        if ( isset( $filters['tenant_id'] ) && config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
            $query->where( 'tenant_id', $filters['tenant_id']);
        }

        if ( isset( $filters['path'])) {
            $query->where( 'path', $filters['path']);
        }

        if ( isset( $filters['visitor_id'])) {
            $query->where( 'visitor_id', $filters['visitor_id']);
        }

        return $query;
    }

    /**
     * Get the SQL date format string for a given granularity.
     *
     * @param  string  $granularity  The time granularity.
     *
     * @return string The SQL date format string.
     *
     * @since 1.0.0
     */
    protected function getDateFormatForGranularity( string $granularity): string
    {
        return match ( $granularity) {
            'hour'  => '%Y-%m-%d %H:00',
            'week'  => '%Y-%W',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };
    }
}
