<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Traits;

use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\Services\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Scope;

/**
 * Trait for models that belong to a site.
 *
 * Provides common site relationship, scoping functionality,
 * and automatic global scope for multi-tenant data isolation.
 *
 * @method static Builder forSite(int $siteId)
 * @method static Builder forCurrentSite()
 * @method static Builder withoutSiteScope()
 * @method static Builder allSites()
 *
 * @property int|null $site_id
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Traits
 */
trait BelongsToSite
{
	/**
	 * Whether the site scope is enabled for this model instance.
	 *
	 * @var bool
	 */
	protected static bool $siteScopeEnabled = true;

	/**
	 * Get the site that this model belongs to.
	 *
	 * @return BelongsTo<Site, static>
	 *
	 * @since 1.0.0
	 */
	public function site(): BelongsTo
	{
		return $this->belongsTo( Site::class );
	}

	/**
	 * Scope a query to filter by site.
	 *
	 * @param Builder $query  The query builder.
	 * @param int     $siteId The site ID.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeForSite( Builder $query, int $siteId ): Builder
	{
		return $query->withoutGlobalScope( 'site' )->where( 'site_id', $siteId );
	}

	/**
	 * Scope a query to filter by current site.
	 *
	 * Uses the TenantManager to determine the current site.
	 *
	 * @param Builder $query The query builder.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeForCurrentSite( Builder $query ): Builder
	{
		$siteId = static::getCurrentSiteId();

		if ( null === $siteId ) {
			return $query;
		}

		return $query->withoutGlobalScope( 'site' )->where( 'site_id', $siteId );
	}

	/**
	 * Scope a query to remove the site scope.
	 *
	 * Use this to query across all sites (admin queries).
	 *
	 * @param Builder $query The query builder.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeWithoutSiteScope( Builder $query ): Builder
	{
		return $query->withoutGlobalScope( 'site' );
	}

	/**
	 * Get all records across all sites.
	 *
	 * This is an alias for withoutSiteScope() for semantic clarity.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public static function allSites(): Builder
	{
		return static::query()->withoutGlobalScope( 'site' );
	}

	/**
	 * Check if this model belongs to the given site.
	 *
	 * @param int|Site $site The site ID or Site model.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function belongsToSiteId( int|Site $site ): bool
	{
		$siteId = $site instanceof Site ? $site->id : $site;

		return $this->site_id === $siteId;
	}

	/**
	 * Associate this model with a site.
	 *
	 * @param int|Site|null $site The site ID, Site model, or null.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function associateWithSite( int|Site|null $site ): static
	{
		if ( $site instanceof Site ) {
			$this->site()->associate( $site );
		} else {
			$this->site_id = $site;
		}

		return $this;
	}

	/**
	 * Disable the site scope for a callback.
	 *
	 * @param callable $callback The callback to execute.
	 *
	 * @return mixed The callback return value.
	 *
	 * @since 1.0.0
	 */
	public static function withoutSiteScopeCallback( callable $callback ): mixed
	{
		if ( app()->bound( TenantManager::class ) ) {
			return app( TenantManager::class )->withoutSite( $callback );
		}

		return $callback();
	}

	/**
	 * Boot the trait.
	 *
	 * @return void
	 */
	protected static function bootBelongsToSite(): void
	{
		// Add global scope for automatic site filtering when multi-tenant is enabled
		if ( config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
			static::addGlobalScope( 'site', new class implements Scope {
				/**
				 * Apply the scope to a given Eloquent query builder.
				 *
				 * @param Builder $builder The query builder.
				 * @param Model   $model   The model.
				 *
				 * @return void
				 */
				public function apply( Builder $builder, Model $model ): void
				{
					$tenantManager = app( TenantManager::class );

					if ( $tenantManager->hasCurrent() ) {
						$builder->where( $model->getTable() . '.site_id', $tenantManager->currentId() );
					}
				}
			} );
		}

		// Automatically set site_id when creating new records
		static::creating( function ( $model ): void {
			if ( null === $model->site_id ) {
				$model->site_id = static::getCurrentSiteId();
			}
		} );
	}

	/**
	 * Get the current site ID.
	 *
	 * Uses the TenantManager to determine the current site.
	 * Falls back to configuration if no TenantManager context.
	 *
	 * @return int|null
	 *
	 * @since 1.0.0
	 */
	protected static function getCurrentSiteId(): ?int
	{
		// First, check TenantManager
		if ( app()->bound( TenantManager::class ) ) {
			$tenantManager = app( TenantManager::class );

			if ( $tenantManager->hasCurrent() ) {
				return $tenantManager->currentId();
			}
		}

		// Fall back to legacy resolver configuration
		$resolver = config( 'artisanpack.analytics.multi_site.resolver' );

		if ( null === $resolver ) {
			$defaultSiteId = config( 'artisanpack.analytics.multi_site.default_site_id' );

			return is_int( $defaultSiteId ) ? $defaultSiteId : null;
		}

		$result = null;

		if ( is_callable( $resolver ) ) {
			$result = $resolver();
		} elseif ( is_string( $resolver ) && class_exists( $resolver ) ) {
			$instance = app( $resolver );

			if ( method_exists( $instance, 'resolve' ) ) {
				$result = $instance->resolve();
			} elseif ( method_exists( $instance, '__invoke' ) ) {
				$result = $instance();
			}
		}

		return is_int( $result ) ? $result : null;
	}
}
