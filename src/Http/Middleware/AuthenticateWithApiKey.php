<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Middleware;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\Services\TenantManager;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to authenticate API requests using site API keys.
 *
 * Supports multiple authentication methods:
 * - Bearer token in Authorization header
 * - X-API-Key header
 * - api_key query parameter (least preferred for security)
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Middleware
 */
class AuthenticateWithApiKey
{
	/**
	 * The tenant manager instance.
	 *
	 * @var TenantManager
	 */
	protected TenantManager $tenantManager;

	/**
	 * Create a new middleware instance.
	 *
	 * @param TenantManager $tenantManager The tenant manager.
	 */
	public function __construct( TenantManager $tenantManager )
	{
		$this->tenantManager = $tenantManager;
	}

	/**
	 * Handle an incoming request.
	 *
	 * @param Request $request The incoming request.
	 * @param Closure $next    The next middleware.
	 *
	 * @return Response
	 */
	public function handle( Request $request, Closure $next ): Response
	{
		$apiKey = $this->extractApiKey( $request );

		if ( null === $apiKey || '' === $apiKey ) {
			return $this->unauthorized( __( 'API key is required.' ) );
		}

		$site = Site::findByApiKey( $apiKey );

		if ( null === $site ) {
			return $this->unauthorized( __( 'Invalid API key.' ) );
		}

		if ( ! $site->is_active ) {
			return $this->unauthorized( __( 'Site is not active.' ) );
		}

		// Record API key usage
		$site->recordApiKeyUsage();

		// Set the site in TenantManager
		$this->tenantManager->setCurrent( $site );

		// Add site to request for downstream use
		$request->attributes->set( 'site', $site );
		$request->attributes->set( 'site_id', $site->id );

		return $next( $request );
	}

	/**
	 * Extract the API key from the request.
	 *
	 * @param Request $request The incoming request.
	 *
	 * @return string|null The API key, or null if not found.
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

		// Check query parameter (least preferred for security)
		$queryApiKey = $request->query( 'api_key' );

		if ( is_string( $queryApiKey ) && '' !== $queryApiKey ) {
			return $queryApiKey;
		}

		return null;
	}

	/**
	 * Return an unauthorized response.
	 *
	 * @param string $message The error message.
	 *
	 * @return JsonResponse
	 */
	protected function unauthorized( string $message ): JsonResponse
	{
		return response()->json( [
			'error'   => 'Unauthorized',
			'message' => $message,
		], 401 );
	}
}
