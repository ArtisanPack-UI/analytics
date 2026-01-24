<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics;

use ArtisanPackUI\Analytics\Contracts\AnalyticsProviderInterface;
use ArtisanPackUI\Analytics\Contracts\AnalyticsQueryInterface;
use ArtisanPackUI\Analytics\Contracts\AnalyticsServiceInterface;
use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Data\SessionData;
use ArtisanPackUI\Analytics\Data\VisitorData;
use ArtisanPackUI\Analytics\Exceptions\ProviderException;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use ArtisanPackUI\Analytics\Providers\AbstractAnalyticsProvider;
use BadMethodCallException;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Main Analytics service class.
 *
 * This class serves as the primary interface for tracking analytics data.
 * It manages providers, routes data to all active providers, and supports
 * multi-provider chaining with fallback capabilities.
 *
 * @since   1.0.0
 */
class Analytics implements AnalyticsQueryInterface, AnalyticsServiceInterface
{
    /**
     * Registered analytics providers.
     *
     * @var array<string, AnalyticsProviderInterface>
     */
    protected array $providers = [];

    /**
     * Provider creator callbacks.
     *
     * @var array<string, callable>
     */
    protected array $customCreators = [];

    /**
     * Fallback provider names for when primary providers fail.
     *
     * @var array<string, string>
     */
    protected array $fallbackProviders = [];

    /**
     * The application instance.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    /**
     * Create a new Analytics instance.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app  The application instance.
     *
     * @since 1.0.0
     */
    public function __construct( $app )
    {
        $this->app = $app;
    }

    /**
     * Track a page view.
     *
     * Each provider is called in isolation so that a failure in one provider
     * does not prevent other providers from receiving the data or bubble up
     * to the host application.
     *
     * @param  PageViewData  $data  The page view data.
     *
     * @since 1.0.0
     */
    public function trackPageView( PageViewData $data ): void
    {
        if ( ! $this->canTrack() ) {
            return;
        }

        foreach ( $this->getActiveProviders() as $provider ) {
            $this->executeWithFallback( $provider, function ( AnalyticsProviderInterface $p ) use ( $data ): void {
                $p->trackPageView( $data );
            } );
        }
    }

    /**
     * Track a custom event.
     *
     * Each provider is called in isolation so that a failure in one provider
     * does not prevent other providers from receiving the data or bubble up
     * to the host application.
     *
     * @param  EventData  $data  The event data.
     *
     * @since 1.0.0
     */
    public function trackEvent( EventData $data ): void
    {
        if ( ! $this->canTrack() ) {
            return;
        }

        foreach ( $this->getActiveProviders() as $provider ) {
            $this->executeWithFallback( $provider, function ( AnalyticsProviderInterface $p ) use ( $data ): void {
                $p->trackEvent( $data );
            } );
        }
    }

    /**
     * Track a custom event (convenience method).
     *
     * This is a simplified API for tracking events from PHP code.
     * For package integrations, use the sourcePackage parameter.
     *
     * @param  string  $name  The event name.
     * @param  array<string, mixed>  $properties  The event properties.
     * @param  string|null  $category  The event category (auto-inferred if null).
     * @param  float|null  $value  The event value.
     * @param  string|null  $sourcePackage  The source package name.
     *
     * @since 1.0.0
     */
    public function event(
        string $name,
        array $properties = [],
        ?string $category = null,
        ?float $value = null,
        ?string $sourcePackage = null,
    ): void {
        $data = new EventData(
            name: $name,
            properties: $properties,
            category: $category,
            value: $value,
            sourcePackage: $sourcePackage,
        );

        $this->trackEvent( $data );
    }

    /**
     * Start a new session.
     *
     * @param  SessionData  $data  The session initialization data.
     *
     * @since 1.0.0
     */
    public function startSession( SessionData $data ): Session
    {
        throw new BadMethodCallException( __( 'Session management not yet implemented.' ) );
    }

    /**
     * End an existing session.
     *
     * @param  string  $sessionId  The session ID to end.
     *
     * @since 1.0.0
     */
    public function endSession( string $sessionId ): void
    {
        // This will be implemented by the session manager service
    }

    /**
     * Extend an existing session.
     *
     * @param  string  $sessionId  The session ID to extend.
     *
     * @since 1.0.0
     */
    public function extendSession( string $sessionId ): void
    {
        // This will be implemented by the session manager service
    }

    /**
     * Resolve or create a visitor.
     *
     * @param  VisitorData  $data  The visitor data.
     *
     * @since 1.0.0
     */
    public function resolveVisitor( VisitorData $data ): Visitor
    {
        // This will be implemented by the visitor resolver service
        return new Visitor;
    }

    /**
     * Check if tracking is allowed.
     *
     *
     * @since 1.0.0
     */
    public function canTrack(): bool
    {
        return config( 'artisanpack.analytics.enabled', true );
    }

    /**
     * Register a custom provider creator.
     *
     * @param  string  $name  The provider name.
     * @param  callable  $creator  The creator callback.
     *
     * @since 1.0.0
     */
    public function extend( string $name, callable $creator ): static
    {
        $this->customCreators[ $name ] = $creator;

        return $this;
    }

    /**
     * Register a fallback provider for another provider.
     *
     * When the primary provider fails or is unhealthy, the fallback
     * provider will be used instead.
     *
     * @param  string  $primaryProvider  The name of the primary provider.
     * @param  string  $fallbackProvider  The name of the fallback provider.
     *
     * @since 1.0.0
     */
    public function registerFallback( string $primaryProvider, string $fallbackProvider ): static
    {
        $this->fallbackProviders[ $primaryProvider ] = $fallbackProvider;

        return $this;
    }

    /**
     * Get a specific provider by name.
     *
     * @param  string|null  $name  The provider name. Defaults to the default provider.
     *
     * @throws ProviderException If the provider is not found.
     *
     * @since 1.0.0
     */
    public function provider( ?string $name = null ): AnalyticsProviderInterface
    {
        $name = $name ?? config( 'artisanpack.analytics.default', 'local' );

        if ( ! isset( $this->providers[ $name ] ) ) {
            $this->providers[ $name ] = $this->createProvider( $name );
        }

        return $this->providers[ $name ];
    }

    /**
     * Get all active providers.
     *
     * @return Collection<int, AnalyticsProviderInterface>
     *
     * @since 1.0.0
     */
    public function getActiveProviders(): Collection
    {
        $activeProviderNames = config( 'artisanpack.analytics.active_providers', ['local'] );

        $providers = collect();

        foreach ( $activeProviderNames as $name ) {
            try {
                $provider = $this->provider( $name );

                if ( $provider->isEnabled() ) {
                    $providers->push( $provider );
                }
            } catch ( ProviderException ) {
                // Provider not found or not configured, skip it
                continue;
            }
        }

        return $providers;
    }

    /**
     * Get all healthy providers.
     *
     * Returns only providers that are enabled and pass their health check.
     *
     * @return Collection<int, AnalyticsProviderInterface>
     *
     * @since 1.0.0
     */
    public function getHealthyProviders(): Collection
    {
        return $this->getActiveProviders()->filter( function ( AnalyticsProviderInterface $provider ): bool {
            if ( $provider instanceof AbstractAnalyticsProvider ) {
                return $provider->isHealthy();
            }

            return $provider->isEnabled();
        } );
    }

    /**
     * Check the health of all active providers.
     *
     * @return array<string, array{healthy: bool, error: string|null}>
     *
     * @since 1.0.0
     */
    public function checkProvidersHealth(): array
    {
        $health = [];

        foreach ( $this->getActiveProviders() as $provider ) {
            $name   = $provider->getName();
            $status = ['healthy' => true, 'error' => null];

            if ( $provider instanceof AbstractAnalyticsProvider ) {
                $status['healthy'] = $provider->isHealthy();

                if ( ! $status['healthy'] && $provider->getLastError() ) {
                    $status['error'] = $provider->getLastError()->getMessage();
                }
            }

            $health[ $name ] = $status;
        }

        return $health;
    }

    /**
     * Get the default provider name.
     *
     *
     * @since 1.0.0
     */
    public function getDefaultProvider(): string
    {
        return config( 'artisanpack.analytics.default', 'local' );
    }

    /**
     * Set the default provider.
     *
     * @param  string  $name  The provider name.
     *
     * @since 1.0.0
     */
    public function setDefaultProvider( string $name ): static
    {
        config( ['artisanpack.analytics.default' => $name] );

        return $this;
    }

    /**
     * Get all registered provider names.
     *
     * @return array<int, string>
     *
     * @since 1.0.0
     */
    public function getProviderNames(): array
    {
        return array_keys( $this->customCreators );
    }

    /**
     * Get total page views for a date range.
     *
     * Uses the first provider that supports queries.
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
        return $this->queryWithProvider( fn ( AnalyticsQueryInterface $p ) => $p->getPageViews( $range, $filters ), 0 );
    }

    /**
     * Get unique visitors for a date range.
     *
     * Uses the first provider that supports queries.
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
        return $this->queryWithProvider( fn ( AnalyticsQueryInterface $p ) => $p->getVisitors( $range, $filters ), 0 );
    }

    /**
     * Get total sessions for a date range.
     *
     * Uses the first provider that supports queries.
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
        return $this->queryWithProvider( fn ( AnalyticsQueryInterface $p ) => $p->getSessions( $range, $filters ), 0 );
    }

    /**
     * Get top pages by page views.
     *
     * Uses the first provider that supports queries.
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
        return $this->queryWithProvider( fn ( AnalyticsQueryInterface $p ) => $p->getTopPages( $range, $limit, $filters ), collect() );
    }

    /**
     * Get traffic sources breakdown.
     *
     * Uses the first provider that supports queries.
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
        return $this->queryWithProvider( fn ( AnalyticsQueryInterface $p ) => $p->getTrafficSources( $range, $limit, $filters ), collect() );
    }

    /**
     * Get bounce rate for a date range.
     *
     * Uses the first provider that supports queries.
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
        return $this->queryWithProvider( fn ( AnalyticsQueryInterface $p ) => $p->getBounceRate( $range, $filters ), 0.0 );
    }

    /**
     * Get average session duration in seconds.
     *
     * Uses the first provider that supports queries.
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
        return $this->queryWithProvider( fn ( AnalyticsQueryInterface $p ) => $p->getAverageSessionDuration( $range, $filters ), 0 );
    }

    /**
     * Get page views over time.
     *
     * Uses the first provider that supports queries.
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
        return $this->queryWithProvider( fn ( AnalyticsQueryInterface $p ) => $p->getPageViewsOverTime( $range, $granularity, $filters ), collect() );
    }

    /**
     * Get device breakdown.
     *
     * Uses the first provider that supports queries.
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
        return $this->queryWithProvider( fn ( AnalyticsQueryInterface $p ) => $p->getDeviceBreakdown( $range, $filters ), collect() );
    }

    /**
     * Get browser breakdown.
     *
     * Uses the first provider that supports queries.
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
        return $this->queryWithProvider( fn ( AnalyticsQueryInterface $p ) => $p->getBrowserBreakdown( $range, $limit, $filters ), collect() );
    }

    /**
     * Get country breakdown.
     *
     * Uses the first provider that supports queries.
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
        return $this->queryWithProvider( fn ( AnalyticsQueryInterface $p ) => $p->getCountryBreakdown( $range, $limit, $filters ), collect() );
    }

    /**
     * Get real-time visitor count.
     *
     * Uses the first provider that supports queries.
     *
     * @param  int  $minutes  The number of minutes to consider as "real-time".
     *
     * @return int The number of active visitors.
     *
     * @since 1.0.0
     */
    public function getRealTimeVisitors( int $minutes = 5 ): int
    {
        return $this->queryWithProvider( fn ( AnalyticsQueryInterface $p ) => $p->getRealTimeVisitors( $minutes ), 0 );
    }

    /**
     * Execute a query with the first provider that supports queries.
     *
     * @template T
     *
     * @param  callable  $query  The query callback.
     * @param  T  $default  The default value if no provider supports queries.
     *
     * @return T
     *
     * @since 1.0.0
     */
    protected function queryWithProvider( callable $query, mixed $default ): mixed
    {
        $provider = $this->getQueryProvider();

        if ( null === $provider ) {
            return $default;
        }

        try {
            return $query( $provider );
        } catch ( Throwable $e ) {
            $this->logProviderError( 'query', $provider->getName(), $e );

            return $default;
        }
    }

    /**
     * Get the first provider that supports queries.
     *
     * Prioritizes the default provider if it supports queries,
     * otherwise returns the first active provider that does.
     *
     *
     * @since 1.0.0
     */
    protected function getQueryProvider(): ?AnalyticsQueryInterface
    {
        // First try the default provider, but only if it's healthy and supports queries
        try {
            $defaultProvider = $this->provider();

            if ( $defaultProvider instanceof AnalyticsQueryInterface ) {
                if ( $defaultProvider instanceof AbstractAnalyticsProvider ) {
                    // For AbstractAnalyticsProvider, verify it's enabled, healthy, and supports queries
                    if ( $defaultProvider->isEnabled() && $defaultProvider->isHealthy() && $defaultProvider->supportsQueries() ) {
                        return $defaultProvider;
                    }
                } elseif ( $defaultProvider instanceof AnalyticsProviderInterface ) {
                    // For other providers, check if enabled
                    if ( $defaultProvider->isEnabled() ) {
                        return $defaultProvider;
                    }
                }
            }
        } catch ( ProviderException ) {
            // Default provider not available
        }

        // Fall back to the first active provider that supports queries
        foreach ( $this->getActiveProviders() as $provider ) {
            if ( $provider instanceof AnalyticsQueryInterface ) {
                if ( $provider instanceof AbstractAnalyticsProvider ) {
                    if ( $provider->supportsQueries() ) {
                        return $provider;
                    }
                } else {
                    return $provider;
                }
            }
        }

        return null;
    }

    /**
     * Execute a provider operation with fallback support.
     *
     * @param  AnalyticsProviderInterface  $provider  The primary provider.
     * @param  callable  $operation  The operation to execute.
     *
     * @since 1.0.0
     */
    protected function executeWithFallback( AnalyticsProviderInterface $provider, callable $operation ): void
    {
        try {
            // Check if the provider is healthy before executing
            if ( $provider instanceof AbstractAnalyticsProvider && ! $provider->isHealthy() ) {
                $this->tryFallbackProvider( $provider->getName(), $operation );

                return;
            }

            $operation( $provider );
        } catch ( Throwable $e ) {
            $this->logProviderError( 'tracking', $provider->getName(), $e );

            // Try fallback provider if available
            $this->tryFallbackProvider( $provider->getName(), $operation );
        }
    }

    /**
     * Try to execute an operation with a fallback provider.
     *
     * @param  string  $providerName  The name of the failed provider.
     * @param  callable  $operation  The operation to execute.
     *
     * @since 1.0.0
     */
    protected function tryFallbackProvider( string $providerName, callable $operation ): void
    {
        if ( ! isset( $this->fallbackProviders[ $providerName ] ) ) {
            return;
        }

        $fallbackName = $this->fallbackProviders[ $providerName ];

        try {
            $fallbackProvider = $this->provider( $fallbackName );

            if ( $fallbackProvider->isEnabled() ) {
                $operation( $fallbackProvider );
            }
        } catch ( Throwable $e ) {
            $this->logProviderError( 'fallback', $fallbackName, $e );
        }
    }

    /**
     * Log a provider error without rethrowing.
     *
     * @param  string  $method  The method that failed.
     * @param  string  $providerName  The provider that failed.
     * @param  Throwable  $exception  The exception that was thrown.
     *
     * @since 1.0.0
     */
    protected function logProviderError( string $method, string $providerName, Throwable $exception ): void
    {
        $message = __( 'Analytics provider ":provider" failed during :method: :message', [
            'provider' => $providerName,
            'method'   => $method,
            'message'  => $exception->getMessage(),
        ] );

        $context = [
            'provider'  => $providerName,
            'method'    => $method,
            'exception' => get_class( $exception ),
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
        ];

        // Use Laravel's logger if available, fallback to error_log
        if ( function_exists( 'logger' ) ) {
            logger()->error( $message, $context );
        } else {
            error_log( $message . ' ' . json_encode( $context ) );
        }
    }

    /**
     * Create a provider instance.
     *
     * @param  string  $name  The provider name.
     *
     * @throws ProviderException If the provider cannot be created.
     *
     * @since 1.0.0
     */
    protected function createProvider( string $name ): AnalyticsProviderInterface
    {
        if ( isset( $this->customCreators[ $name ])) {
            return call_user_func( $this->customCreators[ $name ], $this->app);
        }

        throw ProviderException::providerNotFound( $name);
    }
}
