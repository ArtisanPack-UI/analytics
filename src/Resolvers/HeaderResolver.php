<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Resolvers;

use ArtisanPackUI\Analytics\Contracts\SiteResolverInterface;
use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Http\Request;

/**
 * Resolves site by reading a custom header.
 *
 * Reads the site ID or UUID from a custom HTTP header
 * (default: X-Site-ID) and resolves the corresponding site.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Resolvers
 */
class HeaderResolver implements SiteResolverInterface
{
	/**
	 * The header name to read the site ID from.
	 *
	 * @var string
	 */
	protected string $headerName;

	/**
	 * Create a new header resolver.
	 *
	 * @param string|null $headerName The header name to use.
	 */
	public function __construct( ?string $headerName = null )
	{
		$this->headerName = $headerName ?? config( 'artisanpack.analytics.multi_tenant.site_header', 'X-Site-ID' );
	}

	/**
	 * Resolve the current site from the request header.
	 *
	 * @param Request $request The incoming HTTP request.
	 *
	 * @return Site|null The resolved site, or null if not found.
	 *
	 * @since 1.0.0
	 */
	public function resolve( Request $request ): ?Site
	{
		$siteIdentifier = $request->header( $this->headerName );

		if ( null === $siteIdentifier || '' === $siteIdentifier ) {
			return null;
		}

		// Check if it's a UUID or numeric ID
		if ( $this->isUuid( $siteIdentifier ) ) {
			return Site::query()
				->where( 'uuid', $siteIdentifier )
				->where( 'is_active', true )
				->first();
		}

		if ( is_numeric( $siteIdentifier ) ) {
			return Site::query()
				->where( 'id', (int) $siteIdentifier )
				->where( 'is_active', true )
				->first();
		}

		return null;
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
		return 50;
	}

	/**
	 * Check if a string is a valid UUID.
	 *
	 * @param string $value The value to check.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function isUuid( string $value ): bool
	{
		return (bool) preg_match(
			'/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
			$value,
		);
	}
}
