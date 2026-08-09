<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Resolvers;

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
class ApiKeyResolver extends AbstractSiteResolver
{
	/**
	 * Request attribute marking usage as already recorded this request.
	 *
	 * @var string
	 */
	protected const RECORDED_ATTRIBUTE = 'artisanpack.analytics.api_key_usage_recorded';

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
			$this->recordUsageOncePerRequest( $request, $site );
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
	 * Record this key's usage at most once for the request being handled.
	 *
	 * The shared contract re-resolves on every call, and the site scope resolves
	 * for every scoped query, so an API-key request that skips the pinning
	 * middleware would otherwise write to `sites` once per SELECT it makes.
	 *
	 * @param Request $request The incoming HTTP request.
	 * @param Site    $site    The site the key resolved to.
	 *
	 * @return void
	 *
	 * @since 1.5.0
	 */
	protected function recordUsageOncePerRequest( Request $request, Site $site ): void
	{
		if ( true === $request->attributes->get( self::RECORDED_ATTRIBUTE, false ) ) {
			return;
		}

		$request->attributes->set( self::RECORDED_ATTRIBUTE, true );

		$site->recordApiKeyUsage();
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
