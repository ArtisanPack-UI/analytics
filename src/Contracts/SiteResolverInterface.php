<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Contracts;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Core\Contracts\SiteResolver;
use Illuminate\Http\Request;

/**
 * Interface for resolving the current site in multi-site deployments.
 *
 * Site resolution now happens through the ecosystem's shared contract,
 * {@see SiteResolver}, so a resolver written for analytics answers the same
 * question every other ArtisanPack UI package asks. Before that, this package
 * resolved a `Site` model from a `Request` while sibling packages resolved an
 * identifier from nothing at all, and an application installing both could
 * resolve to a different site in each.
 *
 * This interface deliberately does **not** extend `SiteResolver`. Doing so
 * would add `currentSiteId()` to its requirements, and an application class
 * implementing only the 1.4 shape would then be a fatal error the moment PHP
 * loads it — not a deprecation but an outage, raised deep in core's stack with
 * nothing pointing back here. The interface keeps the shape it always had;
 * what changed is that nothing in the resolution path calls it any more.
 *
 * `currentSiteId()` is the only method the shared resolver chain calls.
 * `resolve()` is called by
 * {@see \ArtisanPackUI\Analytics\Resolvers\AbstractSiteResolver}, whose
 * `currentSiteId()` delegates to it, so a resolver already written against the
 * request keeps working through that base class. `priority()` is called by
 * nothing at all: ordering comes from the order of
 * `artisanpack.core.multi_tenant.resolvers`, because a chain assembled from
 * several packages' resolvers cannot be ordered by a priority number only one
 * of those packages knows about.
 *
 * Extending {@see \ArtisanPackUI\Analytics\Resolvers\AbstractSiteResolver} is
 * the shortest migration for a resolver that already returns a `Site` from a
 * `Request`: it implements `currentSiteId()` in terms of `resolve()`. A
 * resolver left exactly as it was in 1.4 keeps resolving too — the
 * configuration bridge wraps it in
 * {@see \ArtisanPackUI\Analytics\Resolvers\LegacySiteResolverAdapter} — but
 * that path goes away in 2.0 along with this interface.
 *
 * @deprecated 1.5.0 Implement {@see SiteResolver} directly, or extend
 *                   {@see \ArtisanPackUI\Analytics\Resolvers\AbstractSiteResolver}.
 *                   Removed in 2.0.
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Contracts
 */
interface SiteResolverInterface
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
