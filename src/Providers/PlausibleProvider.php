<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Providers;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Exceptions\ProviderException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Plausible Analytics provider.
 *
 * This provider sends analytics data to Plausible Analytics using the
 * Events API and optionally retrieves data via the Stats API.
 *
 * @since   1.0.0
 */
class PlausibleProvider extends AbstractAnalyticsProvider
{
    /**
     * The default Plausible API URL.
     *
     * @var string
     */
    protected const DEFAULT_API_URL = 'https://plausible.io/api';

    /**
     * Get the provider's unique name.
     *
     *
     * @since 1.0.0
     */
    public function getName(): string
    {
        return 'plausible';
    }

    /**
     * Track a page view via Plausible Events API.
     *
     * @param  PageViewData  $data  The page view data.
     *
     * @since 1.0.0
     */
    public function trackPageView( PageViewData $data ): void
    {
        $this->safeExecute( function () use ( $data ): void {
            $this->validateConfiguration();

            $payload = $this->buildPageViewPayload( $data );
            $this->sendToEventsApi( $payload );
        } );
    }

    /**
     * Track a custom event via Plausible Events API.
     *
     * @param  EventData  $data  The event data.
     *
     * @since 1.0.0
     */
    public function trackEvent( EventData $data ): void
    {
        $this->safeExecute( function () use ( $data ): void {
            $this->validateConfiguration();

            $payload = $this->buildEventPayload( $data );
            $this->sendToEventsApi( $payload );
        } );
    }

    /**
     * Check if query methods are supported by this provider.
     *
     * Plausible requires an API key for querying stats.
     *
     *
     * @since 1.0.0
     */
    public function supportsQueries(): bool
    {
        return ! empty( $this->getConfigValue( 'api_key' ) );
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
        if ( ! $this->supportsQueries() ) {
            $this->logMissingApiKey( 'getPageViews' );

            return 0;
        }

        return $this->safeQuery(
            function () use ( $range, $filters ) {
                $response = $this->queryStatsApi( '/aggregate', [
                    'period'  => 'custom',
                    'date'    => $this->formatDateRange( $range ),
                    'metrics' => 'pageviews',
                    'filters' => $this->buildPlausibleFilters( $filters ),
                ] );

                return (int) ( $response['data']['results']['pageviews']['value'] ?? 0 );
            },
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
        if ( ! $this->supportsQueries() ) {
            $this->logMissingApiKey( 'getVisitors' );

            return 0;
        }

        return $this->safeQuery(
            function () use ( $range, $filters ) {
                $response = $this->queryStatsApi( '/aggregate', [
                    'period'  => 'custom',
                    'date'    => $this->formatDateRange( $range ),
                    'metrics' => 'visitors',
                    'filters' => $this->buildPlausibleFilters( $filters ),
                ] );

                return (int) ( $response['data']['results']['visitors']['value'] ?? 0 );
            },
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
        if ( ! $this->supportsQueries() ) {
            $this->logMissingApiKey( 'getSessions' );

            return 0;
        }

        return $this->safeQuery(
            function () use ( $range, $filters ) {
                $response = $this->queryStatsApi( '/aggregate', [
                    'period'  => 'custom',
                    'date'    => $this->formatDateRange( $range ),
                    'metrics' => 'visits',
                    'filters' => $this->buildPlausibleFilters( $filters ),
                ] );

                return (int) ( $response['data']['results']['visits']['value'] ?? 0 );
            },
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
        if ( ! $this->supportsQueries() ) {
            $this->logMissingApiKey( 'getTopPages' );

            return collect();
        }

        return $this->safeQuery(
            function () use ( $range, $limit, $filters ) {
                $response = $this->queryStatsApi( '/breakdown', [
                    'period'   => 'custom',
                    'date'     => $this->formatDateRange( $range ),
                    'property' => 'event:page',
                    'metrics'  => 'pageviews,visitors',
                    'limit'    => $limit,
                    'filters'  => $this->buildPlausibleFilters( $filters ),
                ] );

                return collect( $response['data']['results'] ?? [] )->map( fn ( $row ) => [
                    'path'         => $row['page'] ?? '',
                    'title'        => '',
                    'views'        => (int) ( $row['pageviews'] ?? 0 ),
                    'unique_views' => (int) ( $row['visitors'] ?? 0 ),
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
        if ( ! $this->supportsQueries() ) {
            $this->logMissingApiKey( 'getTrafficSources' );

            return collect();
        }

        return $this->safeQuery(
            function () use ( $range, $limit, $filters ) {
                $response = $this->queryStatsApi( '/breakdown', [
                    'period'   => 'custom',
                    'date'     => $this->formatDateRange( $range ),
                    'property' => 'visit:source',
                    'metrics'  => 'visits,visitors',
                    'limit'    => $limit,
                    'filters'  => $this->buildPlausibleFilters( $filters ),
                ] );

                return collect( $response['data']['results'] ?? [] )->map( fn ( $row ) => [
                    'source'   => $row['source'] ?? 'Direct / None',
                    'medium'   => '(not set)',
                    'sessions' => (int) ( $row['visits'] ?? 0 ),
                    'visitors' => (int) ( $row['visitors'] ?? 0 ),
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
        if ( ! $this->supportsQueries() ) {
            $this->logMissingApiKey( 'getBounceRate' );

            return 0.0;
        }

        return $this->safeQuery(
            function () use ( $range, $filters ) {
                $response = $this->queryStatsApi( '/aggregate', [
                    'period'  => 'custom',
                    'date'    => $this->formatDateRange( $range ),
                    'metrics' => 'bounce_rate',
                    'filters' => $this->buildPlausibleFilters( $filters ),
                ] );

                return (float) ( $response['data']['results']['bounce_rate']['value'] ?? 0.0 );
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
        if ( ! $this->supportsQueries() ) {
            $this->logMissingApiKey( 'getAverageSessionDuration' );

            return 0;
        }

        return $this->safeQuery(
            function () use ( $range, $filters ) {
                $response = $this->queryStatsApi( '/aggregate', [
                    'period'  => 'custom',
                    'date'    => $this->formatDateRange( $range ),
                    'metrics' => 'visit_duration',
                    'filters' => $this->buildPlausibleFilters( $filters ),
                ] );

                return (int) ( $response['data']['results']['visit_duration']['value'] ?? 0 );
            },
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
        if ( ! $this->supportsQueries() ) {
            $this->logMissingApiKey( 'getPageViewsOverTime' );

            return collect();
        }

        return $this->safeQuery(
            function () use ( $range, $granularity, $filters ) {
                $response = $this->queryStatsApi( '/timeseries', [
                    'period'   => 'custom',
                    'date'     => $this->formatDateRange( $range ),
                    'interval' => $this->mapGranularityToPlausible( $granularity ),
                    'metrics'  => 'pageviews,visitors',
                    'filters'  => $this->buildPlausibleFilters( $filters ),
                ] );

                return collect( $response['data']['results'] ?? [] )->map( fn ( $row ) => [
                    'date'      => $row['date'] ?? '',
                    'pageviews' => (int) ( $row['pageviews'] ?? 0 ),
                    'visitors'  => (int) ( $row['visitors'] ?? 0 ),
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
        if ( ! $this->supportsQueries() ) {
            $this->logMissingApiKey( 'getDeviceBreakdown' );

            return collect();
        }

        return $this->safeQuery(
            function () use ( $range, $filters ) {
                $response = $this->queryStatsApi( '/breakdown', [
                    'period'   => 'custom',
                    'date'     => $this->formatDateRange( $range ),
                    'property' => 'visit:device',
                    'metrics'  => 'visits,visitors',
                    'filters'  => $this->buildPlausibleFilters( $filters ),
                ] );

                $results = collect( $response['data']['results'] ?? [] );
                $total   = $results->sum( 'visits' );

                return $results->map( fn ( $row ) => [
                    'device_type' => $row['device'] ?? 'Unknown',
                    'sessions'    => (int) ( $row['visits'] ?? 0 ),
                    'percentage'  => $total > 0 ? round( ( $row['visits'] / $total ) * 100, 2 ) : 0.0,
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
        if ( ! $this->supportsQueries() ) {
            $this->logMissingApiKey( 'getBrowserBreakdown' );

            return collect();
        }

        return $this->safeQuery(
            function () use ( $range, $limit, $filters ) {
                $response = $this->queryStatsApi( '/breakdown', [
                    'period'   => 'custom',
                    'date'     => $this->formatDateRange( $range ),
                    'property' => 'visit:browser',
                    'metrics'  => 'visits',
                    'limit'    => $limit,
                    'filters'  => $this->buildPlausibleFilters( $filters ),
                ] );

                $results = collect( $response['data']['results'] ?? [] );
                $total   = $results->sum( 'visits' );

                return $results->map( fn ( $row ) => [
                    'browser'    => $row['browser'] ?? 'Unknown',
                    'version'    => '',
                    'sessions'   => (int) ( $row['visits'] ?? 0 ),
                    'percentage' => $total > 0 ? round( ( $row['visits'] / $total ) * 100, 2 ) : 0.0,
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
        if ( ! $this->supportsQueries() ) {
            $this->logMissingApiKey( 'getCountryBreakdown' );

            return collect();
        }

        return $this->safeQuery(
            function () use ( $range, $limit, $filters ) {
                $response = $this->queryStatsApi( '/breakdown', [
                    'period'   => 'custom',
                    'date'     => $this->formatDateRange( $range ),
                    'property' => 'visit:country',
                    'metrics'  => 'visits',
                    'limit'    => $limit,
                    'filters'  => $this->buildPlausibleFilters( $filters ),
                ] );

                $results = collect( $response['data']['results'] ?? [] );
                $total   = $results->sum( 'visits' );

                return $results->map( fn ( $row ) => [
                    'country'      => $row['country'] ?? 'Unknown',
                    'country_code' => $row['country'] ?? 'XX',
                    'sessions'     => (int) ( $row['visits'] ?? 0 ),
                    'percentage'   => $total > 0 ? round( ( $row['visits'] / $total ) * 100, 2 ) : 0.0,
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
        if ( ! $this->supportsQueries() ) {
            $this->logMissingApiKey( 'getRealTimeVisitors' );

            return 0;
        }

        return $this->safeQuery(
            function () {
                $response = $this->queryStatsApi( '/realtime/visitors', [] );

                return (int) ( $response['data'] ?? 0 );
            },
            0,
        );
    }

    /**
     * Get the configuration key for this provider.
     *
     *
     * @since 1.0.0
     */
    protected function getConfigKey(): string
    {
        return 'artisanpack.analytics.providers.plausible';
    }

    /**
     * Perform health check for Plausible provider.
     *
     * Validates that required configuration is present and attempts
     * to send a test event.
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
            $this->validateConfiguration();

            // If we have an API key, test the stats API
            if ( $this->supportsQueries() ) {
                $response = $this->queryStatsApi( '/realtime/visitors', [] );

                return $response['success'];
            }

            // Otherwise just verify configuration is valid
            return true;
        } catch ( Throwable ) {
            return false;
        }
    }

    /**
     * Validate that required configuration values are present.
     *
     * @throws ProviderException If configuration is missing.
     *
     * @since 1.0.0
     */
    protected function validateConfiguration(): void
    {
        $this->requireConfig( 'domain' );
    }

    /**
     * Build the Events API payload for a page view.
     *
     * @param  PageViewData  $data  The page view data.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    protected function buildPageViewPayload( PageViewData $data ): array
    {
        $domain = $this->getConfigValue( 'domain' );

        $payload = [
            'name'   => 'pageview',
            'url'    => $this->buildFullUrl( $data->path ),
            'domain' => $domain,
        ];

        if ( $data->referrer ) {
            $payload['referrer'] = $data->referrer;
        }

        // Add custom properties if present
        $props = [];

        if ( $data->utmSource ) {
            $props['utm_source'] = $data->utmSource;
        }

        if ( $data->utmMedium ) {
            $props['utm_medium'] = $data->utmMedium;
        }

        if ( $data->utmCampaign ) {
            $props['utm_campaign'] = $data->utmCampaign;
        }

        if ( ! empty( $props ) ) {
            $payload['props'] = $props;
        }

        return $payload;
    }

    /**
     * Build the Events API payload for a custom event.
     *
     * @param  EventData  $data  The event data.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    protected function buildEventPayload( EventData $data ): array
    {
        $domain = $this->getConfigValue( 'domain' );

        $payload = [
            'name'   => $data->name,
            'url'    => $this->buildFullUrl( $data->path ?? '/' ),
            'domain' => $domain,
        ];

        // Add custom properties if present
        if ( $data->properties ) {
            $payload['props'] = $data->properties;
        }

        // Add revenue if value is present
        if ( null !== $data->value ) {
            $payload['revenue'] = [
                'currency' => 'USD',
                'amount'   => $data->value,
            ];
        }

        return $payload;
    }

    /**
     * Send payload to the Plausible Events API.
     *
     * @param  array<string, mixed>  $payload  The payload to send.
     *
     * @return array{success: bool, data: mixed, status: int, error: string|null}
     *
     * @since 1.0.0
     */
    protected function sendToEventsApi( array $payload ): array
    {
        $apiUrl = $this->getConfigValue( 'api_url', self::DEFAULT_API_URL );
        $url    = rtrim( $apiUrl, '/' ) . '/event';

        return $this->makeHttpRequest( 'POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent'   => 'ArtisanPack-Analytics/1.0',
            ],
            'body'    => $payload,
            'timeout' => 10,
        ] );
    }

    /**
     * Query the Plausible Stats API.
     *
     * @param  string  $endpoint  The API endpoint.
     * @param  array<string, mixed>  $params  Query parameters.
     *
     * @return array{success: bool, data: mixed, status: int, error: string|null}
     *
     * @since 1.0.0
     */
    protected function queryStatsApi( string $endpoint, array $params ): array
    {
        $apiUrl = $this->getConfigValue( 'api_url', self::DEFAULT_API_URL );
        $apiKey = $this->getConfigValue( 'api_key' );
        $domain = $this->getConfigValue( 'domain' );

        $url = rtrim( $apiUrl, '/' ) . '/v1/stats' . $endpoint;

        // Add site_id to params
        $params['site_id'] = $domain;

        // Remove empty filters
        if ( isset( $params['filters'] ) && empty( $params['filters'] ) ) {
            unset( $params['filters'] );
        }

        return $this->makeHttpRequest( 'GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
            'query'   => $params,
            'timeout' => 30,
        ] );
    }

    /**
     * Build a full URL from a path.
     *
     * @param  string  $path  The path to build a URL for.
     *
     * @since 1.0.0
     */
    protected function buildFullUrl( string $path ): string
    {
        $domain = $this->getConfigValue( 'domain' );

        // If path is already a full URL, return it
        if ( str_starts_with( $path, 'http://' ) || str_starts_with( $path, 'https://' ) ) {
            return $path;
        }

        return 'https://' . $domain . '/' . ltrim( $path, '/' );
    }

    /**
     * Format a DateRange for Plausible API.
     *
     * @param  DateRange  $range  The date range.
     *
     * @return string The formatted date range (YYYY-MM-DD,YYYY-MM-DD).
     *
     * @since 1.0.0
     */
    protected function formatDateRange( DateRange $range ): string
    {
        return $range->startDate->format( 'Y-m-d' ) . ',' . $range->endDate->format( 'Y-m-d' );
    }

    /**
     * Build Plausible filter string from array.
     *
     * @param  array<string, mixed>  $filters  The filters to convert.
     *
     * @return string The filter string for Plausible API.
     *
     * @since 1.0.0
     */
    protected function buildPlausibleFilters( array $filters ): string
    {
        $plausibleFilters = [];

        if ( isset( $filters['path'])) {
            $plausibleFilters[] = 'event:page==' . $filters['path'];
        }

        return implode( ';', $plausibleFilters);
    }

    /**
     * Map granularity to Plausible interval.
     *
     * @param  string  $granularity  The granularity.
     *
     * @return string The Plausible interval.
     *
     * @since 1.0.0
     */
    protected function mapGranularityToPlausible( string $granularity): string
    {
        return match ( $granularity) {
            'hour'  => 'hour',
            'week'  => 'week',
            'month' => 'month',
            default => 'date',
        };
    }

    /**
     * Log when a query method requires an API key.
     *
     * @param  string  $method  The method that was called.
     *
     * @since 1.0.0
     */
    protected function logMissingApiKey( string $method): void
    {
        Log::debug(
            __( 'Plausible provider method ":method" requires an API key. Set ANALYTICS_PLAUSIBLE_API_KEY to enable querying.', [
                'method' => $method,
            ]),
            [
                'provider' => $this->getName(),
                'method'   => $method,
            ],
        );
    }
}
