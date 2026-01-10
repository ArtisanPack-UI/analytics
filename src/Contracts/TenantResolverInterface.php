<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Contracts;

/**
 * Interface for resolving the current tenant in multi-tenant deployments.
 *
 * Implement this interface to provide custom tenant resolution logic
 * for your multi-tenant application.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Contracts
 */
interface TenantResolverInterface
{
	/**
	 * Resolve the current tenant identifier.
	 *
	 * @return int|string|null The tenant identifier, or null if not in a tenant context.
	 *
	 * @since 1.0.0
	 */
	public function resolve(): string|int|null;

	/**
	 * Check if we are currently in a tenant context.
	 *
	 * @return bool True if a tenant context is active.
	 *
	 * @since 1.0.0
	 */
	public function hasTenant(): bool;

	/**
	 * Get the tenant column name for database queries.
	 *
	 * @return string The column name used to identify tenants.
	 *
	 * @since 1.0.0
	 */
	public function getTenantColumn(): string;
}
