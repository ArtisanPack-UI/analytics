<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Providers;

use ArtisanPackUI\Analytics\Contracts\AnalyticsProviderInterface;
use ArtisanPackUI\Analytics\Contracts\AnalyticsQueryInterface;
use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Exceptions\ProviderException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Abstract base class for analytics providers.
 *
 * This abstract class provides shared functionality for all analytics providers
 * including configuration management, error handling, HTTP client setup, and
 * health check capabilities. Concrete providers should extend this class.
 *
 * @since   1.0.0
 */
abstract class AbstractAnalyticsProvider implements AnalyticsProviderInterface, AnalyticsQueryInterface
{
    /**
     * The provider configuration.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Whether the provider has been initialized.
     */
    protected bool $initialized = false;

    /**
     * The last error encountered by the provider.
     */
    protected ?Throwable $lastError = null;

    /**
     * The last health check result.
     */
    protected ?bool $lastHealthCheck = null;

    /**
     * Timestamp of the last health check.
     */
    protected ?int $lastHealthCheckTime = null;

    /**
     * Health check cache duration in seconds.
     */
    protected int $healthCheckCacheDuration = 60;

    /**
     * Create a new provider instance.
     *
     * @since 1.0.0
     */
    public function __construct()
    {
        $this->loadConfiguration();
    }

    /**
     * Get the provider's unique name.
     *
     *
     * @since 1.0.0
     */
    abstract public function getName(): string;

    /**
     * Check if this provider is enabled.
     *
     *
     * @since 1.0.0
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'] ?? false;
    }

    /**
     * Get the provider's configuration.
     *
     * @return array<string, mixed>
     *
     * @since 1.0.0
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Check if the provider is healthy and can accept data.
     *
     * @return bool True if the provider is healthy.
     *
     * @since 1.0.0
     */
    public function isHealthy(): bool
    {
        // Return cached result if still valid
        if ( null !== $this->lastHealthCheck && null !== $this->lastHealthCheckTime ) {
            $cacheExpired = ( time() - $this->lastHealthCheckTime ) >= $this->healthCheckCacheDuration;

            if ( ! $cacheExpired ) {
                return $this->lastHealthCheck;
            }
        }

        // Perform health check
        $this->lastHealthCheck     = $this->performHealthCheck();
        $this->lastHealthCheckTime = time();

        return $this->lastHealthCheck;
    }

    /**
     * Get the last error encountered by the provider.
     *
     *
     * @since 1.0.0
     */
    public function getLastError(): ?Throwable
    {
        return $this->lastError;
    }

    /**
     * Clear the last error.
     *
     * @since 1.0.0
     */
    public function clearLastError(): void
    {
        $this->lastError = null;
    }

    /**
     * Track a page view.
     *
     * @param  PageViewData  $data  The page view data to track.
     *
     * @since 1.0.0
     */
    abstract public function trackPageView( PageViewData $data ): void;

    /**
     * Track a custom event.
     *
     * @param  EventData  $data  The event data to track.
     *
     * @since 1.0.0
     */
    abstract public function trackEvent( EventData $data ): void;

    /**
     * Check if query methods are supported by this provider.
     *
     * External providers like Google Analytics may not support all query methods
     * or may require additional API credentials for querying.
     *
     *
     * @since 1.0.0
     */
    public function supportsQueries(): bool
    {
        return true;
    }

    /**
     * Get total page views for a date range.
     *
     * Default implementation returns 0. Override in subclasses.
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
        return 0;
    }

    /**
     * Get unique visitors for a date range.
     *
     * Default implementation returns 0. Override in subclasses.
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
        return 0;
    }

    /**
     * Get total sessions for a date range.
     *
     * Default implementation returns 0. Override in subclasses.
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
        return 0;
    }

    /**
     * Get top pages by page views.
     *
     * Default implementation returns empty collection. Override in subclasses.
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
        return collect();
    }

    /**
     * Get traffic sources breakdown.
     *
     * Default implementation returns empty collection. Override in subclasses.
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
        return collect();
    }

    /**
     * Get bounce rate for a date range.
     *
     * Default implementation returns 0. Override in subclasses.
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
        return 0.0;
    }

    /**
     * Get average session duration in seconds.
     *
     * Default implementation returns 0. Override in subclasses.
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
        return 0;
    }

    /**
     * Get page views over time.
     *
     * Default implementation returns empty collection. Override in subclasses.
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
        return collect();
    }

    /**
     * Get device breakdown.
     *
     * Default implementation returns empty collection. Override in subclasses.
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
        return collect();
    }

    /**
     * Get browser breakdown.
     *
     * Default implementation returns empty collection. Override in subclasses.
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
        return collect();
    }

    /**
     * Get country breakdown.
     *
     * Default implementation returns empty collection. Override in subclasses.
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
        return collect();
    }

    /**
     * Get real-time visitor count.
     *
     * Default implementation returns 0. Override in subclasses.
     *
     * @param  int  $minutes  The number of minutes to consider as "real-time".
     *
     * @return int The number of active visitors.
     *
     * @since 1.0.0
     */
    public function getRealTimeVisitors( int $minutes = 5 ): int
    {
        return 0;
    }

    /**
     * Load the provider's configuration.
     *
     * Subclasses can override this method to customize configuration loading.
     *
     * @since 1.0.0
     */
    protected function loadConfiguration(): void
    {
        $this->config = config( $this->getConfigKey(), [] );
    }

    /**
     * Get the configuration key for this provider.
     *
     *
     * @since 1.0.0
     */
    abstract protected function getConfigKey(): string;

    /**
     * Get a specific configuration value.
     *
     * @param  string  $key  The configuration key.
     * @param  mixed  $default  The default value if the key does not exist.
     *
     * @since 1.0.0
     */
    protected function getConfigValue( string $key, mixed $default = null ): mixed
    {
        return $this->config[ $key ] ?? $default;
    }

    /**
     * Check if a required configuration value exists.
     *
     * @param  string  $key  The configuration key to check.
     *
     * @throws ProviderException If the configuration is missing.
     *
     * @since 1.0.0
     */
    protected function requireConfig( string $key ): void
    {
        if ( empty( $this->config[ $key ] ) ) {
            throw ProviderException::missingConfiguration( $this->getName(), $key );
        }
    }

    /**
     * Perform the actual health check for this provider.
     *
     * Override this method in subclasses to implement provider-specific health checks.
     * By default, returns true if the provider is enabled.
     *
     *
     * @since 1.0.0
     */
    protected function performHealthCheck(): bool
    {
        return $this->isEnabled();
    }

    /**
     * Record an error for later inspection.
     *
     * @param  Throwable  $error  The error to record.
     *
     * @since 1.0.0
     */
    protected function recordError( Throwable $error ): void
    {
        $this->lastError = $error;

        $context = [
            'provider'  => $this->getName(),
            'exception' => get_class( $error ),
            'file'      => $error->getFile(),
            'line'      => $error->getLine(),
        ];

        // Only include trace in debug mode to prevent leaking sensitive data in production
        if ( config( 'app.debug' ) || config( 'artisanpack.analytics.log_traces', false ) ) {
            $context['trace'] = $error->getTraceAsString();
        }

        Log::error(
            __( 'Analytics provider ":provider" error: :message', [
                'provider' => $this->getName(),
                'message'  => $error->getMessage(),
            ] ),
            $context,
        );
    }

    /**
     * Execute a tracking operation safely with error handling.
     *
     * @param  callable  $operation  The operation to execute.
     *
     * @since 1.0.0
     */
    protected function safeExecute( callable $operation ): void
    {
        if ( ! $this->isEnabled() ) {
            return;
        }

        try {
            $operation();
        } catch ( Throwable $e ) {
            $this->recordError( $e );
        }
    }

    /**
     * Execute a query operation safely with error handling.
     *
     * @template T
     *
     * @param  callable  $operation  The operation to execute.
     * @param  T  $default  The default value to return on error.
     *
     * @return T
     *
     * @since 1.0.0
     */
    protected function safeQuery( callable $operation, mixed $default ): mixed
    {
        if ( ! $this->isEnabled() ) {
            return $default;
        }

        try {
            return $operation();
        } catch ( Throwable $e ) {
            $this->recordError( $e );

            return $default;
        }
    }

    /**
     * Make an HTTP request to an external API.
     *
     * @param  string  $method  The HTTP method (GET, POST, etc.).
     * @param  string  $url  The URL to request.
     * @param  array<string, mixed>  $options  Request options (headers, body, etc.).
     *
     * @return array{success: bool, data: mixed, status: int, error: string|null}
     *
     * @since 1.0.0
     */
    protected function makeHttpRequest( string $method, string $url, array $options = [] ): array
    {
        try {
            $request = Http::timeout( $options['timeout'] ?? 30 );

            if ( isset( $options['headers'] ) ) {
                $request = $request->withHeaders( $options['headers'] );
            }

            $response = match ( strtoupper( $method ) ) {
                'GET'    => $request->get( $url, $options['query'] ?? [] ),
                'POST'   => $request->post( $url, $options['body'] ?? [] ),
                'PUT'    => $request->put( $url, $options['body'] ?? [] ),
                'DELETE' => $request->delete( $url ),
                default  => $request->send( $method, $url, $options ),
            };

            return [
                'success' => $response->successful(),
                'data'    => $response->json() ?? $response->body(),
                'status'  => $response->status(),
                'error'   => $response->successful() ? null : $response->body(),
            ];
        } catch ( Throwable $e ) {
            $this->recordError( $e );

            return [
                'success' => false,
                'data'    => null,
                'status'  => 0,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
