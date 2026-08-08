<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Contracts;

/**
 * Interface for resolving the current tenant in multi-tenant deployments.
 *
 * This describes a *tenant*, not a site: it carries its own column name, so an
 * application whose tenants are something other than analytics sites — an
 * account, an organisation — can still identify one. That is why it survived
 * the move to the ecosystem's shared site contract rather than being folded
 * into it.
 *
 * Where a tenant and a site are the same thing, which is the usual case,
 * implement {@see \ArtisanPackUI\Core\Contracts\SiteResolver} and list it in
 * `artisanpack.core.multi_tenant.resolvers` instead. Only that contract feeds
 * the site context every package scopes its queries by; a resolver reachable
 * only from here answers for the tenant middleware and nothing else, which is
 * exactly the split-brain the shared contract exists to remove.
 *
 * @deprecated 1.5.0 Prefer {@see \ArtisanPackUI\Core\Contracts\SiteResolver}
 *                   unless a tenant genuinely is not a site.
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
