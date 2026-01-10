<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Traits;

use ArtisanPackUI\Analytics\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait for models that belong to a site.
 *
 * Provides common site relationship and scoping functionality.
 *
 * @method static Builder forSite(int $siteId)
 * @method static Builder forCurrentSite()
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
		return $query->where( 'site_id', $siteId );
	}

	/**
	 * Scope a query to filter by current site.
	 *
	 * Uses the configured site resolver to determine the current site.
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

		return $query->where( 'site_id', $siteId );
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
	 * Boot the trait.
	 *
	 * @return void
	 */
	protected static function bootBelongsToSite(): void
	{
		// Automatically set site_id when creating new records if a current site is set
		static::creating( function ( $model ): void {
			if ( null === $model->site_id && method_exists( static::class, 'getCurrentSiteId' ) ) {
				$model->site_id = static::getCurrentSiteId();
			}
		} );
	}

	/**
	 * Get the current site ID.
	 *
	 * This method checks for a site resolver in the config and uses it
	 * to determine the current site. Falls back to null if no resolver
	 * is configured.
	 *
	 * @return int|null
	 *
	 * @since 1.0.0
	 */
	protected static function getCurrentSiteId(): ?int
	{
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
