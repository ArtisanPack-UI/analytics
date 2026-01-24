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
	 * The tenant manager instance.
	 *
	 * @var TenantManager
	 */
	protected TenantManager $tenantManager;

	/**
	 * The current request.
	 *
	 * @var Request
	 */
	protected Request $request;

	/**
	 * Create a new guard instance.
	 *
	 * @param TenantManager $tenantManager The tenant manager.
	 * @param Request       $request       The current request.
	 */
	public function __construct( TenantManager $tenantManager, Request $request )
	{
		$this->tenantManager = $tenantManager;
		$this->request       = $request;
	}

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
		if ( null !== $this->site ) {
			return $this->site;
		}

		// Check if site was set via middleware
		$site = $this->request->attributes->get( 'site' );

		if ( $site instanceof Site ) {
			// Verify site is active before accepting it
			if ( ! $site->is_active ) {
				return null;
			}

			$this->site = $site;
			$this->tenantManager->setCurrent( $this->site );

			return $this->site;
		}

		// Try to resolve from TenantManager
		if ( $this->tenantManager->hasCurrent() ) {
			$current = $this->tenantManager->current();

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
		$this->tenantManager->setCurrent( $this->site );

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
			$this->site = $user;
			$this->tenantManager->setCurrent( $user );
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
		$authHeader = $this->request->header( 'Authorization' );

		if ( null !== $authHeader && str_starts_with( $authHeader, 'Bearer ' ) ) {
			return substr( $authHeader, 7 );
		}

		// Check X-API-Key header
		$apiKeyHeader = $this->request->header( 'X-API-Key' );

		if ( null !== $apiKeyHeader && '' !== $apiKeyHeader ) {
			return $apiKeyHeader;
		}

		// Check query parameter only if explicitly enabled in config
		// This is disabled by default because query strings may be logged
		if ( config( 'artisanpack.analytics.multi_tenant.allow_query_api_key', false ) ) {
			$queryApiKey = $this->request->query( 'api_key' );

			if ( is_string( $queryApiKey ) && '' !== $queryApiKey ) {
				return $queryApiKey;
			}
		}

		return null;
	}
}
