<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Contracts;

use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Http\Request;

/**
 * Interface for resolving the current site in multi-site deployments.
 *
 * Implement this interface to provide custom site resolution logic
 * for your multi-tenant application.
 *
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
	 * @since 1.0.0
	 */
	public function priority(): int;
}
