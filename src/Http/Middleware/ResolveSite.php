<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Middleware;

use ArtisanPackUI\Analytics\Services\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to resolve the current site using TenantManager.
 *
 * Uses the registered site resolvers to determine the current site
 * and sets it in the TenantManager for downstream processing.
 * Also shares the current site with all views.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Middleware
 */
class ResolveSite
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
		// Check if multi-tenant is enabled
		if ( ! config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
			return $next( $request );
		}

		// Resolve the current site
		$site = $this->tenantManager->resolve( $request );

		if ( null !== $site ) {
			// Add site to request attributes
			$request->attributes->set( 'site', $site );
			$request->attributes->set( 'site_id', $site->id );

			// Share site with all views
			View::share( 'currentSite', $site );
			View::share( 'currentSiteId', $site->id );
		}

		return $next( $request );
	}
}
