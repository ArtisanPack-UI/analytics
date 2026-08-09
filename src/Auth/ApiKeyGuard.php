<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Auth;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\Services\TenantManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

/**
 * Custom authentication guard for API key authentication.
 *
 * Returns the Site model as the "user" when authenticated via API key.
 * This allows using Laravel's authorization features with Site-based permissions.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Auth
 */
class ApiKeyGuard implements Guard
{
	/**
	 * The currently authenticated site.
	 *
	 * @var Site|null
	 */
	protected ?Site $site = null;

	/**
	 * The request the authenticated site was resolved for.
	 *
	 * AuthManager memoises a guard for the life of the process, so this memo
	 * outlives the request that filled it. Remembering which request it belongs
	 * to is what stops one caller's API-key authentication answering for the
	 * next caller on an Octane worker.
	 *
	 * @var Request|null
	 */
	protected ?Request $resolvedFor = null;

	/**
	 * Determine if the current user is authenticated.
	 *
	 * @return bool
	 */
	public function check(): bool
	{
		return null !== $this->user();
	}

	/**
	 * Determine if the current user is a guest.
	 *
	 * @return bool
	 */
	public function guest(): bool
	{
		return ! $this->check();
	}

	/**
	 * Get the currently authenticated user (Site).
	 *
	 * @return Authenticatable|Site|null
	 */
	public function user(): Site|Authenticatable|null
	{
		$this->forgetSiteFromAnotherRequest();

		if ( null !== $this->site ) {
			return $this->site;
		}

		// Check if site was set via middleware
		$site = $this->request()->attributes->get( 'site' );

		if ( $site instanceof Site ) {
			// Verify site is active before accepting it
			if ( ! $site->is_active ) {
				return null;
			}

			$this->site = $site;
			$this->tenantManager()->setCurrent( $this->site );

			return $this->site;
		}

		// Try to resolve from TenantManager
		if ( $this->tenantManager()->hasCurrent() ) {
			$current = $this->tenantManager()->current();

			// Verify site is active
			if ( null !== $current && $current->is_active ) {
				$this->site = $current;

				return $this->site;
			}
		}

		// Try to authenticate from request
		$apiKey = $this->extractApiKey();

		if ( null === $apiKey ) {
			return null;
		}

		$site = Site::findByApiKey( $apiKey );

		// Verify site exists and is active
		if ( null === $site || ! $site->is_active ) {
			return null;
		}

		$this->site = $site;
		$this->site->recordApiKeyUsage();
		$this->tenantManager()->setCurrent( $this->site );

		return $this->site;
	}

	/**
	 * Get the ID for the currently authenticated user.
	 *
	 * @return int|string|null
	 */
	public function id(): int|string|null
	{
		return $this->user()?->id;
	}

	/**
	 * Validate a user's credentials.
	 *
	 * @param array<string, mixed> $credentials The credentials to validate.
	 *
	 * @return bool
	 */
	public function validate( array $credentials = [] ): bool
	{
		$apiKey = $credentials['api_key'] ?? null;

		if ( null === $apiKey ) {
			return false;
		}

		$site = Site::findByApiKey( $apiKey );

		return null !== $site && $site->is_active;
	}

	/**
	 * Determine if the guard has a user instance.
	 *
	 * @return bool
	 */
	public function hasUser(): bool
	{
		$this->forgetSiteFromAnotherRequest();

		return null !== $this->site;
	}

	/**
	 * Set the current user.
	 *
	 * @param Authenticatable $user The user to set.
	 *
	 * @return static
	 */
	public function setUser( Authenticatable $user ): static
	{
		if ( $user instanceof Site ) {
			$this->site        = $user;
			$this->resolvedFor = $this->request();
			$this->tenantManager()->setCurrent( $user );
		}

		return $this;
	}

	/**
	 * Get the currently authenticated site.
	 *
	 * Alias for user() with proper type.
	 *
	 * @return Site|null
	 */
	public function site(): ?Site
	{
		$user = $this->user();

		return $user instanceof Site ? $user : null;
	}

	/**
	 * Get the tenant manager for the work currently in hand.
	 *
	 * Resolved per call rather than captured in the constructor. Laravel's
	 * AuthManager memoises a guard for the life of the process, so a guard
	 * built during one queue job or Octane request would otherwise hold that
	 * job's scoped TenantManager — and its pinned site — for every job after
	 * it, which is precisely the leak scoped bindings exist to prevent.
	 *
	 * @return TenantManager The tenant manager for the current request or job.
	 *
	 * @since 1.5.0
	 */
	protected function tenantManager(): TenantManager
	{
		return app( TenantManager::class );
	}

	/**
	 * Get the request being handled.
	 *
	 * Resolved per call for the same reason as {@see self::tenantManager()}: a
	 * memoised guard holding the first request it ever saw would read another
	 * caller's API key header for the rest of the worker's life.
	 *
	 * @return Request The current request.
	 *
	 * @since 1.5.0
	 */
	protected function request(): Request
	{
		return app( 'request' );
	}

	/**
	 * Drop a site authenticated for a request that is no longer being handled.
	 *
	 * @return void
	 *
	 * @since 1.5.0
	 */
	protected function forgetSiteFromAnotherRequest(): void
	{
		$request = $this->request();

		if ( $this->resolvedFor === $request ) {
			return;
		}

		$this->resolvedFor = $request;
		$this->site        = null;
	}

	/**
	 * Extract the API key from the request.
	 *
	 * Checks headers first (preferred), then optionally query parameters
	 * if enabled in configuration. Query parameter support is disabled by
	 * default because query strings may be logged in server access logs.
	 *
	 * @return string|null
	 */
	protected function extractApiKey(): ?string
	{
		// Check Bearer token first (preferred method)
		$authHeader = $this->request()->header( 'Authorization' );

		if ( null !== $authHeader && str_starts_with( $authHeader, 'Bearer ' ) ) {
			return substr( $authHeader, 7 );
		}

		// Check X-API-Key header
		$apiKeyHeader = $this->request()->header( 'X-API-Key' );

		if ( null !== $apiKeyHeader && '' !== $apiKeyHeader ) {
			return $apiKeyHeader;
		}

		// Check query parameter only if explicitly enabled in config
		// This is disabled by default because query strings may be logged
		if ( config( 'artisanpack.analytics.multi_tenant.allow_query_api_key', false ) ) {
			$queryApiKey = $this->request()->query( 'api_key' );

			if ( is_string( $queryApiKey ) && '' !== $queryApiKey ) {
				return $queryApiKey;
			}
		}

		return null;
	}
}
