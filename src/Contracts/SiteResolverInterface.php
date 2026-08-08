<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Contracts;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Core\Contracts\SiteResolver;
use Illuminate\Http\Request;

/**
 * Interface for resolving the current site in multi-site deployments.
 *
 * This now extends the ecosystem's shared contract,
 * {@see SiteResolver}, so a resolver written for
 * analytics answers the same question every other ArtisanPack UI package asks.
 * Before that, this package resolved a `Site` model from a `Request` while
 * sibling packages resolved an identifier from nothing at all, and an
 * application installing both could resolve to a different site in each.
 *
 * Implement `currentSiteId()` — that is the method the shared resolver chain
 * calls. `resolve()` and `priority()` are retained for existing resolvers and
 * are no longer consulted during resolution: ordering now comes from the order
 * of `artisanpack.core.multi_tenant.resolvers`, because a chain assembled from
 * several packages' resolvers cannot be ordered by a priority number only one
 * of those packages knows about.
 *
 * Extending {@see \ArtisanPackUI\Analytics\Resolvers\AbstractSiteResolver} is
 * the shortest migration for a resolver that already returns a `Site` from a
 * `Request`: it implements `currentSiteId()` in terms of `resolve()`.
 *
 * @deprecated 1.5.0 Implement {@see SiteResolver}
 *                   directly. This interface is kept so existing resolvers
 *                   keep type-checking and will be removed in 2.0.
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Contracts
 */
interface SiteResolverInterface extends SiteResolver
{
	/**
	 * Resolve the current site from the request.
	 *
	 * @param Request $request The incoming HTTP request.
	 *
	 * @return Site|null The resolved site, or null if not found.
	 *
	 * @deprecated 1.5.0 Resolution goes through `currentSiteId()`. A resolver
	 *                   that needs the request reads it from the container, so
	 *                   it stays usable from console commands and queue
	 *                   workers.
	 * @since 1.0.0
	 */
	public function resolve( Request $request ): ?Site;

	/**
	 * Get the priority of this resolver.
	 *
	 * Lower numbers run first. Default is 100.
	 *
	 * @return int
	 *
	 * @deprecated 1.5.0 Ordering comes from the order of the
	 *                   `artisanpack.core.multi_tenant.resolvers` list.
	 * @since 1.0.0
	 */
	public function priority(): int;
}
