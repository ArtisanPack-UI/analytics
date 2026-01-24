<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Resolvers;

use ArtisanPackUI\Analytics\Contracts\SiteResolverInterface;
use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Http\Request;

/**
 * Resolves site by extracting the subdomain from the request.
 *
 * Extracts the subdomain from the request host and matches
 * it against the domain field in the sites table.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Resolvers
 */
class SubdomainResolver implements SiteResolverInterface
{
	/**
	 * The base domain to extract subdomain from.
	 *
	 * @var string|null
	 */
	protected ?string $baseDomain;

	/**
	 * Create a new subdomain resolver.
	 *
	 * @param string|null $baseDomain The base domain (e.g., 'example.com').
	 */
	public function __construct( ?string $baseDomain = null )
	{
		$this->baseDomain = $baseDomain ?? config( 'artisanpack.analytics.multi_tenant.base_domain' );
	}

	/**
	 * Resolve the current site from the request subdomain.
	 *
	 * @param Request $request The incoming HTTP request.
	 *
	 * @return Site|null The resolved site, or null if not found.
	 *
	 * @since 1.0.0
	 */
	public function resolve( Request $request ): ?Site
	{
		$subdomain = $this->extractSubdomain( $request->getHost() );

		if ( null === $subdomain || '' === $subdomain ) {
			return null;
		}

		return Site::query()
			->where( 'domain', $subdomain )
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
		return 90;
	}

	/**
	 * Extract the subdomain from a host.
	 *
	 * @param string $host The full host name.
	 *
	 * @return string|null The subdomain, or null if not found.
	 *
	 * @since 1.0.0
	 */
	protected function extractSubdomain( string $host ): ?string
	{
		if ( null === $this->baseDomain ) {
			return null;
		}

		// Remove port if present
		$host = preg_replace( '/:\d+$/', '', $host );

		// Check if host ends with base domain
		$baseDomainPattern = preg_quote( $this->baseDomain, '/' );

		if ( ! preg_match( '/^(.+)\.' . $baseDomainPattern . '$/i', $host, $matches ) ) {
			return null;
		}

		$subdomain = $matches[1];

		// Ignore common non-tenant subdomains
		$ignoredSubdomains = [ 'www', 'api', 'admin', 'dashboard', 'app' ];

		if ( in_array( strtolower( $subdomain ), $ignoredSubdomains, true ) ) {
			return null;
		}

		return $subdomain;
	}
}
