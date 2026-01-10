<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics;

use ArtisanPackUI\Analytics\Contracts\AnalyticsProviderInterface;
use ArtisanPackUI\Analytics\Contracts\AnalyticsServiceInterface;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Data\SessionData;
use ArtisanPackUI\Analytics\Data\VisitorData;
use ArtisanPackUI\Analytics\Exceptions\ProviderException;
use ArtisanPackUI\Analytics\Models\Session;
use ArtisanPackUI\Analytics\Models\Visitor;
use BadMethodCallException;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Main Analytics service class.
 *
 * This class serves as the primary interface for tracking analytics data.
 * It manages providers and routes data to all active providers.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics
 */
class Analytics implements AnalyticsServiceInterface
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
	 * The application instance.
	 *
	 * @var \Illuminate\Contracts\Foundation\Application
	 */
	protected $app;

	/**
	 * Create a new Analytics instance.
	 *
	 * @param \Illuminate\Contracts\Foundation\Application $app The application instance.
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
	 * @param PageViewData $data The page view data.
	 *
	 * @since 1.0.0
	 */
	public function trackPageView( PageViewData $data ): void
	{
		if ( ! $this->canTrack() ) {
			return;
		}

		foreach ( $this->getActiveProviders() as $provider ) {
			try {
				$provider->trackPageView( $data );
			} catch ( Throwable $e ) {
				$this->logProviderError( 'trackPageView', $provider->getName(), $e );
			}
		}
	}

	/**
	 * Track a custom event.
	 *
	 * Each provider is called in isolation so that a failure in one provider
	 * does not prevent other providers from receiving the data or bubble up
	 * to the host application.
	 *
	 * @param EventData $data The event data.
	 *
	 * @since 1.0.0
	 */
	public function trackEvent( EventData $data ): void
	{
		if ( ! $this->canTrack() ) {
			return;
		}

		foreach ( $this->getActiveProviders() as $provider ) {
			try {
				$provider->trackEvent( $data );
			} catch ( Throwable $e ) {
				$this->logProviderError( 'trackEvent', $provider->getName(), $e );
			}
		}
	}

	/**
	 * Start a new session.
	 *
	 * @param SessionData $data The session initialization data.
	 *
	 * @return Session
	 *
	 * @since 1.0.0
	 */
	public function startSession( SessionData $data ): Session
	{
		throw new BadMethodCallException( 'Session management not yet implemented.' );
	}

	/**
	 * End an existing session.
	 *
	 * @param string $sessionId The session ID to end.
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
	 * @param string $sessionId The session ID to extend.
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
	 * @param VisitorData $data The visitor data.
	 *
	 * @return Visitor
	 *
	 * @since 1.0.0
	 */
	public function resolveVisitor( VisitorData $data ): Visitor
	{
		// This will be implemented by the visitor resolver service
		return new Visitor();
	}

	/**
	 * Check if tracking is allowed.
	 *
	 * @return bool
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
	 * @param string   $name    The provider name.
	 * @param callable $creator The creator callback.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function extend( string $name, callable $creator ): static
	{
		$this->customCreators[ $name ] = $creator;

		return $this;
	}

	/**
	 * Get a specific provider by name.
	 *
	 * @param string|null $name The provider name. Defaults to the default provider.
	 *
	 * @throws ProviderException If the provider is not found.
	 *
	 * @return AnalyticsProviderInterface
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
		$activeProviderNames = config( 'artisanpack.analytics.active_providers', [ 'local' ] );

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
	 * Get the default provider name.
	 *
	 * @return string
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
	 * @param string $name The provider name.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function setDefaultProvider( string $name ): static
	{
		config( [ 'artisanpack.analytics.default' => $name ] );

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
	 * Log a provider error without rethrowing.
	 *
	 * @param string     $method       The method that failed.
	 * @param string     $providerName The provider that failed.
	 * @param Throwable $exception    The exception that was thrown.
	 *
	 * @since 1.0.0
	 */
	protected function logProviderError( string $method, string $providerName, Throwable $exception ): void
	{
		$message = sprintf(
			'Analytics provider "%s" failed during %s: %s',
			$providerName,
			$method,
			$exception->getMessage(),
		);

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
	 * @param string $name The provider name.
	 *
	 * @throws ProviderException If the provider cannot be created.
	 *
	 * @return AnalyticsProviderInterface
	 *
	 * @since 1.0.0
	 */
	protected function createProvider( string $name ): AnalyticsProviderInterface
	{
		if ( isset( $this->customCreators[ $name ] ) ) {
			return call_user_func( $this->customCreators[ $name ], $this->app );
		}

		throw ProviderException::providerNotFound( $name );
	}
}
