<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Http\Middleware;

use ArtisanPackUI\Analytics\Contracts\TenantResolverInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Tenant resolver middleware for multi-tenant analytics.
 *
 * Resolves the current tenant for multi-tenant deployments and
 * adds the tenant ID to the request for downstream processing.
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
		if ( ! config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
			return $next( $request );
		}

		$resolver = $this->getResolver();

		if ( null === $resolver ) {
			return $next( $request );
		}

		// Resolve the tenant
		$tenantId = $resolver->resolve();

		if ( null !== $tenantId ) {
			// Add tenant ID to request for downstream processing
			$request->merge( [ 'tenant_id' => $tenantId ] );
		}

		return $next( $request );
	}

	/**
	 * Get the tenant resolver instance.
	 *
	 * @return TenantResolverInterface|null
	 *
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
