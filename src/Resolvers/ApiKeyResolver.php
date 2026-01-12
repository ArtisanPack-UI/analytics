<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Resolvers;

use ArtisanPackUI\Analytics\Contracts\SiteResolverInterface;
use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Http\Request;

/**
 * Resolves site by API key authentication.
 *
 * Supports multiple authentication methods:
 * - Bearer token in Authorization header
 * - X-API-Key header
 * - api_key query parameter
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Resolvers
 */
class ApiKeyResolver implements SiteResolverInterface
{
	/**
	 * Resolve the current site from the API key.
	 *
	 * @param Request $request The incoming HTTP request.
	 *
	 * @return Site|null The resolved site, or null if not found.
	 *
	 * @since 1.0.0
	 */
	public function resolve( Request $request ): ?Site
	{
		$apiKey = $this->extractApiKey( $request );

		if ( null === $apiKey || '' === $apiKey ) {
			return null;
		}

		$site = Site::findByApiKey( $apiKey );

		if ( null !== $site ) {
			$site->recordApiKeyUsage();
		}

		return $site;
	}

	/**
	 * Get the priority of this resolver.
	 *
	 * Higher priority (lower number) for API key auth.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function priority(): int
	{
		return 10;
	}

	/**
	 * Extract the API key from the request.
	 *
	 * @param Request $request The incoming HTTP request.
	 *
	 * @return string|null The API key, or null if not found.
	 *
	 * @since 1.0.0
	 */
	protected function extractApiKey( Request $request ): ?string
	{
		// Check Bearer token first (Authorization: Bearer <token>)
		$authHeader = $request->header( 'Authorization' );

		if ( null !== $authHeader && str_starts_with( $authHeader, 'Bearer ' ) ) {
			return substr( $authHeader, 7 );
		}

		// Check X-API-Key header
		$apiKeyHeader = $request->header( 'X-API-Key' );

		if ( null !== $apiKeyHeader && '' !== $apiKeyHeader ) {
			return $apiKeyHeader;
		}

		// Check query parameter only if explicitly allowed in config (least preferred for security)
		if ( config( 'artisanpack.analytics.multi_tenant.allow_query_api_key', false ) ) {
			$queryApiKey = $request->query( 'api_key' );

			if ( is_string( $queryApiKey ) && '' !== $queryApiKey ) {
				return $queryApiKey;
			}
		}

		return null;
	}
}
