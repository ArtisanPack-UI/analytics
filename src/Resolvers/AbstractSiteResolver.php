<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Resolvers;

use ArtisanPackUI\Analytics\Contracts\SiteResolverInterface;
use Illuminate\Http\Request;

/**
 * Base class for request-driven analytics site resolvers.
 *
 * The ecosystem's shared contract asks for an identifier and takes no
 * `Request`, because a resolver that requires one is unusable from a console
 * command or queue worker — which is exactly where per-site iteration happens.
 * Every resolver this package ships, though, genuinely answers from the
 * request: a domain, a header, an API key. This class reconciles the two by
 * reading the request out of the container and returning null when there is no
 * request to read, so the subclasses stay written against `Request` and are
 * simply inert outside an HTTP context.
 *
 * @since   1.5.0
 *
 * @package ArtisanPackUI\Analytics\Resolvers
 */
abstract class AbstractSiteResolver implements SiteResolverInterface
{
	/**
	 * Get the identifier of the site currently in context.
	 *
	 * @return int|string|null The current site identifier, or null when no site
	 *                         is in context.
	 *
	 * @since 1.5.0
	 */
	public function currentSiteId(): int|string|null
	{
		$request = $this->currentRequest();

		if ( null === $request ) {
			return null;
		}

		return $this->resolve( $request )?->id;
	}

	/**
	 * Get the priority of this resolver.
	 *
	 * @return int
	 *
	 * @deprecated 1.5.0 Ordering comes from the order of the
	 *                   `artisanpack.core.multi_tenant.resolvers` list.
	 * @since 1.5.0
	 */
	public function priority(): int
	{
		return 100;
	}

	/**
	 * Get the request being handled, if there is one.
	 *
	 * Laravel binds a `Request` in console too — one synthesised from the
	 * command's argv, carrying whatever host the machine reports and none of
	 * the headers a real request would. Resolving from that would let a
	 * scheduled command attach its work to whichever site happens to match
	 * `localhost`, so console contexts resolve to null and leave a site pinned
	 * with `SiteContext::forSite()` as the only answer there.
	 *
	 * Tests are the exception: they run in console while exercising requests,
	 * and a resolver that always returns null under test is a resolver nothing
	 * can prove works.
	 *
	 * @return Request|null The current request, or null outside an HTTP context.
	 *
	 * @since 1.5.0
	 */
	protected function currentRequest(): ?Request
	{
		$app = app();

		if ( $app->runningInConsole() && ! $app->runningUnitTests() ) {
			return null;
		}

		if ( ! $app->bound( 'request' ) ) {
			return null;
		}

		$request = $app->make( 'request' );

		return $request instanceof Request ? $request : null;
	}
}
