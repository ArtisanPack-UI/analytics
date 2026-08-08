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
	 * The site comes from the shared site context, so this scopes to the same
	 * site every other ArtisanPack UI package is scoping to.
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
			return app( TenantManager::class )->withoutSite( fn () => $callback() );
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
		// The scope is registered unconditionally and decides at query time
		// whether to apply. Booting a model happens once per class per
		// process, and whether tenancy is on is only settled once every
		// provider has booted — a model that booted first would otherwise
		// carry no scope for the life of the process, and run unscoped in an
		// installation whose configuration says it is multi-site.
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
				if ( ! analyticsMultiTenancyEnabled() ) {
					return;
				}

				$siteId = app( TenantManager::class )->currentId();

				if ( null !== $siteId ) {
					$builder->where( $model->getTable() . '.site_id', $siteId );
				}
			}
		} );

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
	 * Reads the ecosystem's shared site context through the TenantManager,
	 * which also applies the configured default site. The old
	 * `artisanpack.analytics.multi_site.resolver` fallback is gone: a second
	 * resolver reachable only from this trait could disagree with the one
	 * every other query used, which is the divergence the shared context
	 * exists to remove.
	 *
	 * @return int|null
	 *
	 * @since 1.0.0
	 */
	protected static function getCurrentSiteId(): ?int
	{
		if ( ! app()->bound( TenantManager::class ) ) {
			return null;
		}

		return app( TenantManager::class )->currentId();
	}
}
