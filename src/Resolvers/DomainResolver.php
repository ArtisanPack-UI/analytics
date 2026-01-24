<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Resolvers;

use ArtisanPackUI\Analytics\Contracts\SiteResolverInterface;
use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Http\Request;

/**
 * Resolves site by matching the request domain.
 *
 * Matches the full domain from the request host against
 * the domain field in the sites table.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Resolvers
 */
class DomainResolver implements SiteResolverInterface
{
	/**
	 * Resolve the current site from the request domain.
	 *
	 * @param Request $request The incoming HTTP request.
	 *
	 * @return Site|null The resolved site, or null if not found.
	 *
	 * @since 1.0.0
	 */
	public function resolve( Request $request ): ?Site
	{
		$host = $request->getHost();

		if ( empty( $host ) ) {
			return null;
		}

		// Remove www. prefix for matching
		$domain = preg_replace( '/^www\./i', '', $host );

		return Site::query()
			->where( function ( $query ) use ( $host, $domain ): void {
				$query->where( 'domain', $host )
					->orWhere( 'domain', $domain )
					->orWhere( 'domain', 'www.' . $domain );
			} )
			->where( 'is_active', true )
			->first();
	}

	/**
	 * Get the priority of this resolver.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function priority(): int
	{
		return 100;
	}
}
