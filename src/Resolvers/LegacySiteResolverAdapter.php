<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Resolvers;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Core\Contracts\SiteResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Puts a resolver written against the 1.4 shape into the shared chain.
 *
 * Before 1.5 a site resolver was any class with `resolve( Request ): ?Site`
 * and `priority(): int`; it did not have to implement anything at all, and the
 * shipped `SiteResolverInterface` did not extend core's contract. The shared
 * chain calls `currentSiteId()` and instantiates whatever the resolver list
 * names, so feeding a 1.4-era class straight into it is a fatal at class load
 * — raised deep inside core, on the first scoped query, with nothing pointing
 * back at analytics.
 *
 * This adapter is what the configuration bridge substitutes for those classes,
 * so an application upgrading with a custom resolver keeps resolving instead
 * of crashing. It is a migration aid: implement {@see SiteResolver}, or extend
 * {@see AbstractSiteResolver}, and the adapter drops out of the picture.
 *
 * @since   1.5.0
 *
 * @package ArtisanPackUI\Analytics\Resolvers
 */
class LegacySiteResolverAdapter extends AbstractSiteResolver
{
	/**
	 * Create an adapter around a legacy resolver class.
	 *
	 * @param class-string $resolverClass The legacy resolver's class name.
	 *
	 * @since 1.5.0
	 */
	public function __construct( protected string $resolverClass )
	{
	}

	/**
	 * Resolve the current site through the wrapped legacy resolver.
	 *
	 * A legacy resolver is application code that predates the shared contract,
	 * so it may return something other than a `Site`, and it may throw. Either
	 * would otherwise surface as an unexplained failure of an unrelated query,
	 * so both are logged against this package and answered as "no site" — the
	 * same answer every shipped resolver gives when it cannot decide.
	 *
	 * @param Request $request The incoming HTTP request.
	 *
	 * @return Site|null The resolved site, or null if not found.
	 *
	 * @since 1.5.0
	 */
	public function resolve( Request $request ): ?Site
	{
		try {
			// build() rather than make(): the bridge binds this adapter against
			// the legacy class name so core's chain picks it up, so make() would
			// hand back the adapter itself and recurse forever. build()
			// constructs the concrete class, still autowiring its dependencies.
			$resolver = app()->build( $this->resolverClass );
			$site     = $resolver->resolve( $request );
		} catch ( Throwable $exception ) {
			Log::error( '[Analytics] A deprecated site resolver failed to resolve.', [
				'resolver' => $this->resolverClass,
				'message'  => $exception->getMessage(),
			] );

			return null;
		}

		if ( null === $site ) {
			return null;
		}

		if ( ! $site instanceof Site ) {
			Log::error( '[Analytics] A deprecated site resolver returned something other than a site.', [
				'resolver' => $this->resolverClass,
				'returned' => get_debug_type( $site ),
			] );

			return null;
		}

		return $site;
	}

	/**
	 * Get the class name of the legacy resolver being adapted.
	 *
	 * @return class-string The wrapped resolver's class name.
	 *
	 * @since 1.5.0
	 */
	public function resolverClass(): string
	{
		return $this->resolverClass;
	}
}
