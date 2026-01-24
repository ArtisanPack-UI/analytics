<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Contracts\SiteResolverInterface;
use ArtisanPackUI\Analytics\Models\Site;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Manages multi-tenant site resolution and context.
 *
 * Provides a centralized service for resolving the current site
 * from incoming requests using a chain of resolvers.
 *
 * For Laravel Octane compatibility, call flush() after each request
 * to prevent state leakage between requests.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Services
 */
class TenantManager
{
	/**
	 * Registered site resolvers.
	 *
	 * @var array<SiteResolverInterface>
	 */
	protected array $resolvers = [];

	/**
	 * The currently resolved site.
	 *
	 * @var Site|null
	 */
	protected ?Site $currentSite = null;

	/**
	 * Whether resolvers have been sorted.
	 *
	 * @var bool
	 */
	protected bool $resolversSorted = false;

	/**
	 * Register a site resolver.
	 *
	 * @param SiteResolverInterface $resolver The resolver to register.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function registerResolver( SiteResolverInterface $resolver ): static
	{
		$this->resolvers[]     = $resolver;
		$this->resolversSorted = false;

		return $this;
	}

	/**
	 * Register multiple resolvers from configuration.
	 *
	 * @param array<class-string<SiteResolverInterface>> $resolverClasses Array of resolver class names.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function registerResolversFromConfig( array $resolverClasses ): static
	{
		foreach ( $resolverClasses as $resolverClass ) {
			if ( class_exists( $resolverClass ) ) {
				$resolver = app( $resolverClass );

				if ( $resolver instanceof SiteResolverInterface ) {
					$this->registerResolver( $resolver );
				}
			}
		}

		return $this;
	}

	/**
	 * Resolve the current site from the request.
	 *
	 * Iterates through registered resolvers in priority order
	 * until one successfully resolves a site.
	 *
	 * @param Request $request The incoming HTTP request.
	 *
	 * @return Site|null The resolved site, or null if not found.
	 *
	 * @since 1.0.0
	 */
	public function resolve( Request $request ): ?Site
	{
		$this->sortResolvers();

		foreach ( $this->resolvers as $resolver ) {
			try {
				$site = $resolver->resolve( $request );

				if ( null !== $site ) {
					$this->currentSite = $site;

					Log::debug( __( '[Analytics] Site resolved via :resolver', [
						'resolver' => get_class( $resolver ),
					] ) );

					return $site;
				}
			} catch ( Throwable $e ) {
				Log::warning( __( '[Analytics] Resolver :resolver failed: :message', [
					'resolver' => get_class( $resolver ),
					'message'  => $e->getMessage(),
				] ) );
			}
		}

		// Fall back to default site if configured
		$defaultSiteId = config( 'artisanpack.analytics.multi_tenant.default_site_id' );

		if ( null !== $defaultSiteId ) {
			// Only use default site if it exists and is active
			$defaultSite = Site::where( 'id', $defaultSiteId )
				->where( 'is_active', true )
				->first();

			if ( null !== $defaultSite ) {
				$this->currentSite = $defaultSite;

				return $this->currentSite;
			}
		}

		return null;
	}

	/**
	 * Get the current site.
	 *
	 * @return Site|null The current site, or null if not set.
	 *
	 * @since 1.0.0
	 */
	public function current(): ?Site
	{
		return $this->currentSite;
	}

	/**
	 * Set the current site.
	 *
	 * @param Site|null $site The site to set as current.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function setCurrent( ?Site $site ): static
	{
		$this->currentSite = $site;

		return $this;
	}

	/**
	 * Get the current site ID.
	 *
	 * @return int|null The current site ID, or null if not set.
	 *
	 * @since 1.0.0
	 */
	public function currentId(): ?int
	{
		return $this->currentSite?->id;
	}

	/**
	 * Check if a current site is set.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function hasCurrent(): bool
	{
		return null !== $this->currentSite;
	}

	/**
	 * Execute a callback in the context of a specific site.
	 *
	 * The site context is restored after the callback completes.
	 *
	 * @param Site    $site     The site to use as context.
	 * @param Closure $callback The callback to execute.
	 *
	 * @return mixed The callback return value.
	 *
	 * @since 1.0.0
	 */
	public function forSite( Site $site, Closure $callback ): mixed
	{
		$previousSite = $this->currentSite;

		$this->currentSite = $site;

		try {
			return $callback( $site );
		} finally {
			$this->currentSite = $previousSite;
		}
	}

	/**
	 * Execute a callback without any site context.
	 *
	 * The site context is restored after the callback completes.
	 *
	 * @param Closure $callback The callback to execute.
	 *
	 * @return mixed The callback return value.
	 *
	 * @since 1.0.0
	 */
	public function withoutSite( Closure $callback ): mixed
	{
		$previousSite = $this->currentSite;

		$this->currentSite = null;

		try {
			return $callback();
		} finally {
			$this->currentSite = $previousSite;
		}
	}

	/**
	 * Clear the current site context.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function forget(): static
	{
		$this->currentSite = null;

		return $this;
	}

	/**
	 * Flush state for Laravel Octane compatibility.
	 *
	 * Call this method after each request to prevent state leakage
	 * between requests when running under Octane. This can be registered
	 * as a listener for Octane's RequestTerminated event.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function flush(): static
	{
		$this->currentSite = null;

		return $this;
	}

	/**
	 * Get all registered resolvers.
	 *
	 * @return array<SiteResolverInterface>
	 *
	 * @since 1.0.0
	 */
	public function getResolvers(): array
	{
		$this->sortResolvers();

		return $this->resolvers;
	}

	/**
	 * Sort resolvers by priority.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	protected function sortResolvers(): void
	{
		if ( $this->resolversSorted ) {
			return;
		}

		usort( $this->resolvers, fn ( $a, $b ) => $a->priority() <=> $b->priority() );

		$this->resolversSorted = true;
	}
}
