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
 * Resolution itself happens in `artisanpack-ui/core`'s shared site context,
 * from one ecosystem-wide configuration. What this middleware adds is putting
 * the resolved `Site` where the rest of a request expects it: on the request
 * attributes and shared with every view.
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
		if ( ! analyticsMultiTenancyEnabled() ) {
			return $next( $request );
		}

		// Ask the shared site context every ArtisanPack UI package reads from,
		// so this request cannot be site 2 here and site 1 somewhere else.
		$resolvedSiteId = $this->tenantManager->context()->currentSiteId();
		$site           = $this->tenantManager->current();

		if ( null !== $site ) {
			// Pin it for the rest of the request, but only when a shared
			// resolver actually named it. The site a request is for cannot
			// change mid-request, and resolvers are asked afresh on every call
			// by design — without pinning, each scoped query would re-run the
			// whole chain, putting a domain or API-key lookup in front of it.
			//
			// A site that came from `multi_tenant.default_site_id` is not
			// pinned: that key is this package's own fallback, and writing it
			// into the shared context would scope every other package to a
			// default it never opted into.
			if ( null !== $resolvedSiteId ) {
				$this->tenantManager->setCurrent( $site );
			}

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
