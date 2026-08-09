<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Middleware;

use ArtisanPackUI\Analytics\Contracts\TenantResolverInterface;
use ArtisanPackUI\Core\MultiTenancy\SiteContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Tenant resolver middleware for multi-tenant analytics.
 *
 * Adds the current tenant identifier to the request for downstream
 * processing. The identifier comes from the ecosystem's shared site context
 * first, so this middleware agrees with every query scope, dashboard, and
 * sibling package in the same request.
 *
 * A `TenantResolverInterface` configured under
 * `artisanpack.analytics.multi_tenant.resolver` is still consulted when the
 * shared context has no answer. That interface describes a tenant, which is
 * not always a site — it carries its own column name — so it is kept as a
 * fallback rather than folded into site resolution, and is deprecated for the
 * common case where the two are the same thing.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Http\Middleware
 */
class TenantResolver
{
	/**
	 * Handle an incoming request.
	 *
	 * @param Request $request The incoming request.
	 * @param Closure $next    The next middleware.
	 *
	 * @return Response
	 *
	 * @since 1.0.0
	 */
	public function handle( Request $request, Closure $next ): Response
	{
		// Check if multi-tenant is enabled
		if ( ! analyticsMultiTenancyEnabled() ) {
			return $next( $request );
		}

		// The shared context is authoritative; the legacy resolver only fills
		// in when nothing has put a site in context. No bound() guard: core is
		// a hard requirement of this package, so its provider is always
		// registered and SiteContext always resolvable.
		$tenantId = app( SiteContext::class )->currentSiteId();

		if ( null === $tenantId ) {
			$tenantId = $this->getResolver()?->resolve();
		}

		if ( null !== $tenantId ) {
			// Add tenant ID to request for downstream processing
			$request->merge( [ 'tenant_id' => $tenantId ] );
		}

		return $next( $request );
	}

	/**
	 * Get the legacy tenant resolver instance, if one is configured.
	 *
	 * @return TenantResolverInterface|null
	 *
	 * @deprecated 1.5.0 Configure a `ArtisanPackUI\Core\Contracts\SiteResolver`
	 *                   under `artisanpack.core.multi_tenant.resolvers` instead.
	 * @since 1.0.0
	 */
	protected function getResolver(): ?TenantResolverInterface
	{
		$resolverClass = config( 'artisanpack.analytics.multi_tenant.resolver' );

		if ( empty( $resolverClass ) || ! class_exists( $resolverClass ) ) {
			return null;
		}

		try {
			$resolver = app( $resolverClass );
		} catch ( Throwable $e ) {
			logger()->warning( 'Analytics tenant resolver failed to instantiate', [
				'resolver' => $resolverClass,
				'error'    => $e->getMessage(),
			] );

			return null;
		}

		if ( ! $resolver instanceof TenantResolverInterface ) {
			return null;
		}

		return $resolver;
	}
}
