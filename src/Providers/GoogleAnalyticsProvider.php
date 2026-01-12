<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Providers;

use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Exceptions\ProviderException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Google Analytics 4 provider.
 *
 * This provider sends analytics data to Google Analytics 4 using the
 * Measurement Protocol API. It supports tracking page views and custom
 * events, and optionally querying data via the GA4 Data API.
 *
 * @since   1.0.0
 */
class GoogleAnalyticsProvider extends AbstractAnalyticsProvider
{
    /**
     * The GA4 Measurement Protocol endpoint.
     *
     * @var string
     */
    protected const MEASUREMENT_PROTOCOL_URL = 'https://www.google-analytics.com/mp/collect';

    /**
     * The GA4 Measurement Protocol debug endpoint.
     *
     * @var string
     */
    protected const MEASUREMENT_PROTOCOL_DEBUG_URL = 'https://www.google-analytics.com/debug/mp/collect';

    /**
     * The GA4 Data API base URL.
     *
     * @var string
     */
    protected const DATA_API_URL = 'https://analyticsdata.googleapis.com/v1beta';

    /**
     * Get the provider's unique name.
     *
     *
     * @since 1.0.0
     */
    public function getName(): string
    {
        return 'google';
    }

    /**
     * Track a page view via GA4 Measurement Protocol.
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
            $this->sendToMeasurementProtocol( $payload );
        } );
    }

    /**
     * Track a custom event via GA4 Measurement Protocol.
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
            $this->sendToMeasurementProtocol( $payload );
        } );
    }

    /**
     * Check if query methods are supported by this provider.
     *
     * Google Analytics requires additional Data API credentials for querying.
     *
     *
     * @since 1.0.0
     */
    public function supportsQueries(): bool
    {
        // Querying requires GA4 Data API credentials which are more complex
        // This could be enabled in the future with proper authentication setup
        return false;
    }

    /**
     * Get total page views for a date range.
     *
     * Not supported without GA4 Data API credentials.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return int Always returns 0 as querying is not supported.
     *
     * @since 1.0.0
     */
    public function getPageViews( DateRange $range, array $filters = [] ): int
    {
        $this->logUnsupportedQuery( 'getPageViews' );

        return 0;
    }

    /**
     * Get unique visitors for a date range.
     *
     * Not supported without GA4 Data API credentials.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return int Always returns 0 as querying is not supported.
     *
     * @since 1.0.0
     */
    public function getVisitors( DateRange $range, array $filters = [] ): int
    {
        $this->logUnsupportedQuery( 'getVisitors' );

        return 0;
    }

    /**
     * Get total sessions for a date range.
     *
     * Not supported without GA4 Data API credentials.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return int Always returns 0 as querying is not supported.
     *
     * @since 1.0.0
     */
    public function getSessions( DateRange $range, array $filters = [] ): int
    {
        $this->logUnsupportedQuery( 'getSessions' );

        return 0;
    }

    /**
     * Get top pages by page views.
     *
     * Not supported without GA4 Data API credentials.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of pages to return.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{path: string, title: string, views: int, unique_views: int}> Empty collection.
     *
     * @since 1.0.0
     */
    public function getTopPages( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        $this->logUnsupportedQuery( 'getTopPages' );

        return collect();
    }

    /**
     * Get traffic sources breakdown.
     *
     * Not supported without GA4 Data API credentials.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of sources to return.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{source: string, medium: string, sessions: int, visitors: int}> Empty collection.
     *
     * @since 1.0.0
     */
    public function getTrafficSources( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        $this->logUnsupportedQuery( 'getTrafficSources' );

        return collect();
    }

    /**
     * Get bounce rate for a date range.
     *
     * Not supported without GA4 Data API credentials.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return float Always returns 0.0 as querying is not supported.
     *
     * @since 1.0.0
     */
    public function getBounceRate( DateRange $range, array $filters = [] ): float
    {
        $this->logUnsupportedQuery( 'getBounceRate' );

        return 0.0;
    }

    /**
     * Get average session duration in seconds.
     *
     * Not supported without GA4 Data API credentials.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return int Always returns 0 as querying is not supported.
     *
     * @since 1.0.0
     */
    public function getAverageSessionDuration( DateRange $range, array $filters = [] ): int
    {
        $this->logUnsupportedQuery( 'getAverageSessionDuration' );

        return 0;
    }

    /**
     * Get page views over time.
     *
     * Not supported without GA4 Data API credentials.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  string  $granularity  The time granularity.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{date: string, pageviews: int, visitors: int}> Empty collection.
     *
     * @since 1.0.0
     */
    public function getPageViewsOverTime( DateRange $range, string $granularity = 'day', array $filters = [] ): Collection
    {
        $this->logUnsupportedQuery( 'getPageViewsOverTime' );

        return collect();
    }

    /**
     * Get device breakdown.
     *
     * Not supported without GA4 Data API credentials.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{device_type: string, sessions: int, percentage: float}> Empty collection.
     *
     * @since 1.0.0
     */
    public function getDeviceBreakdown( DateRange $range, array $filters = [] ): Collection
    {
        $this->logUnsupportedQuery( 'getDeviceBreakdown' );

        return collect();
    }

    /**
     * Get browser breakdown.
     *
     * Not supported without GA4 Data API credentials.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of browsers to return.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{browser: string, version: string, sessions: int, percentage: float}> Empty collection.
     *
     * @since 1.0.0
     */
    public function getBrowserBreakdown( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        $this->logUnsupportedQuery( 'getBrowserBreakdown' );

        return collect();
    }

    /**
     * Get country breakdown.
     *
     * Not supported without GA4 Data API credentials.
     *
     * @param  DateRange  $range  The date range to query.
     * @param  int  $limit  Maximum number of countries to return.
     * @param  array<string, mixed>  $filters  Optional filters to apply.
     *
     * @return Collection<int, array{country: string, country_code: string, sessions: int, percentage: float}> Empty collection.
     *
     * @since 1.0.0
     */
    public function getCountryBreakdown( DateRange $range, int $limit = 10, array $filters = [] ): Collection
    {
        $this->logUnsupportedQuery( 'getCountryBreakdown' );

        return collect();
    }

    /**
     * Get real-time visitor count.
     *
     * Not supported without GA4 Data API credentials.
     *
     * @param  int  $minutes  The number of minutes to consider as "real-time".
     *
     * @return int Always returns 0 as querying is not supported.
     *
     * @since 1.0.0
     */
    public function getRealTimeVisitors( int $minutes = 5 ): int
    {
        $this->logUnsupportedQuery( 'getRealTimeVisitors' );

        return 0;
    }

    /**
     * Get the configuration key for this provider.
     *
     *
     * @since 1.0.0
     */
    protected function getConfigKey(): string
    {
        return 'artisanpack.analytics.providers.google';
    }

    /**
     * Perform health check for Google Analytics provider.
     *
     * Validates that required configuration is present and attempts
     * to send a debug validation request.
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

            // Send a validation request to the debug endpoint
            $testPayload = [
                'client_id' => $this->generateClientId(),
                'events'    => [
                    [
                        'name'   => 'health_check',
                        'params' => [
                            'engagement_time_msec' => 1,
                        ],
                    ],
                ],
            ];

            $response = $this->sendToMeasurementProtocol( $testPayload, true );

            return $response['success'];
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
        $this->requireConfig( 'measurement_id' );
        $this->requireConfig( 'api_secret' );
    }

    /**
     * Build the Measurement Protocol payload for a page view.
     *
     * @param  PageViewData  $data  The page view data.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    protected function buildPageViewPayload( PageViewData $data ): array
    {
        $clientId = $data->visitorId ?? $this->generateClientId();

        $params = [
            'page_location' => $data->path,
            'page_title'    => $data->title ?? '',
        ];

        if ( $data->referrer ) {
            $params['page_referrer'] = $data->referrer;
        }

        // Add UTM parameters if present
        if ( $data->utmSource ) {
            $params['traffic_source'] = [
                'source'   => $data->utmSource,
                'medium'   => $data->utmMedium ?? '(not set)',
                'campaign' => $data->utmCampaign ?? '(not set)',
            ];
        }

        // Add engagement time (GA4 requires this for certain metrics)
        $params['engagement_time_msec'] = 100;

        return [
            'client_id' => $clientId,
            'user_id'   => $data->visitorId,
            'events'    => [
                [
                    'name'   => 'page_view',
                    'params' => $params,
                ],
            ],
        ];
    }

    /**
     * Build the Measurement Protocol payload for a custom event.
     *
     * @param  EventData  $data  The event data.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    protected function buildEventPayload( EventData $data ): array
    {
        $clientId = $data->visitorId ?? $this->generateClientId();

        // Build event parameters
        $params = [
            'engagement_time_msec' => 100,
        ];

        // Add category if present
        if ( $data->category ) {
            $params['event_category'] = $data->category;
        }

        // Add value if present
        if ( null !== $data->value ) {
            $params['value'] = $data->value;
        }

        // Add custom properties (limited to 25 parameters per GA4 requirements)
        if ( $data->properties ) {
            $properties = array_slice( $data->properties, 0, 25 );

            foreach ( $properties as $key => $value ) {
                // GA4 parameter names must be alphanumeric with underscores
                $paramKey            = preg_replace( '/[^a-zA-Z0-9_]/', '_', (string) $key );
                $params[ $paramKey ] = $value;
            }
        }

        // Add page path if present
        if ( $data->path ) {
            $params['page_location'] = $data->path;
        }

        return [
            'client_id' => $clientId,
            'user_id'   => $data->visitorId,
            'events'    => [
                [
                    'name'   => $this->normalizeEventName( $data->name ),
                    'params' => $params,
                ],
            ],
        ];
    }

    /**
     * Send payload to the GA4 Measurement Protocol.
     *
     * @param  array<string, mixed>  $payload  The payload to send.
     * @param  bool  $debug  Whether to use the debug endpoint.
     *
     * @return array{success: bool, data: mixed, status: int, error: string|null}
     *
     * @since 1.0.0
     */
    protected function sendToMeasurementProtocol( array $payload, bool $debug = false ): array
    {
        $measurementId = $this->getConfigValue( 'measurement_id' );
        $apiSecret     = $this->getConfigValue( 'api_secret' );

        $baseUrl = $debug ? self::MEASUREMENT_PROTOCOL_DEBUG_URL : self::MEASUREMENT_PROTOCOL_URL;
        $url     = sprintf(
            '%s?measurement_id=%s&api_secret=%s',
            $baseUrl,
            rawurlencode( $measurementId ),
            rawurlencode( $apiSecret ),
        );

        return $this->makeHttpRequest( 'POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body'    => $payload,
            'timeout' => 10,
        ] );
    }

    /**
     * Normalize an event name to meet GA4 requirements.
     *
     * GA4 event names must be alphanumeric with underscores,
     * max 40 characters, and cannot start with a number.
     *
     * @param  string  $name  The event name to normalize.
     *
     * @since 1.0.0
     */
    protected function normalizeEventName( string $name ): string
    {
        // Replace non-alphanumeric characters with underscores
        $normalized = preg_replace( '/[^a-zA-Z0-9_]/', '_', $name );

        // Ensure it doesn't start with a number
        if ( preg_match( '/^[0-9]/', $normalized ) ) {
            $normalized = '_' . $normalized;
        }

        // Truncate to 40 characters
        return substr( $normalized, 0, 40 );
    }

    /**
     * Generate a client ID for GA4.
     *
     *
     * @since 1.0.0
     */
    protected function generateClientId(): string
    {
        return sprintf(
            '%d.%d',
            random_int( 100000000, 999999999 ),
            time(),
        );
    }

    /**
     * Log when an unsupported query method is called.
     *
     * @param  string  $method  The method that was called.
     *
     * @since 1.0.0
     */
    protected function logUnsupportedQuery( string $method ): void
    {
        Log::debug(
            __( 'Google Analytics provider does not support query method ":method". Use the local provider for querying.', [
                'method' => $method,
            ] ),
            [
                'provider' => $this->getName(),
                'method'   => $method,
            ],
        );
    }
}
