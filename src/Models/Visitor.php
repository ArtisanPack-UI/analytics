<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Visitor model for analytics.
 *
 * Represents a unique visitor identified by fingerprint.
 *
 * @property string               $id
 * @property int|null             $site_id
 * @property string               $fingerprint
 * @property int|null             $user_id
 * @property Carbon               $first_seen_at
 * @property Carbon               $last_seen_at
 * @property string|null          $ip_address
 * @property string|null          $user_agent
 * @property string|null          $country
 * @property string|null          $region
 * @property string|null          $city
 * @property string               $device_type
 * @property string|null          $browser
 * @property string|null          $browser_version
 * @property string|null          $os
 * @property string|null          $os_version
 * @property int|null             $screen_width
 * @property int|null             $screen_height
 * @property int|null             $viewport_width
 * @property int|null             $viewport_height
 * @property string|null          $language
 * @property string|null          $timezone
 * @property int                  $total_sessions
 * @property int                  $total_pageviews
 * @property int                  $total_events
 * @property int|string|null      $tenant_id
 * @property Carbon               $created_at
 * @property Carbon               $updated_at
 *
 * @method static Builder forSite(int $siteId)
 * @method static Builder forTenant(string|int $tenantId)
 * @method static Builder seenBetween(Carbon $start, Carbon $end)
 * @method static Builder newBetween(Carbon $start, Carbon $end)
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Models
 */
class Visitor extends Model
{
	use HasFactory;
	use HasUuids;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'analytics_visitors';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'site_id',
		'fingerprint',
		'user_id',
		'first_seen_at',
		'last_seen_at',
		'ip_address',
		'user_agent',
		'country',
		'region',
		'city',
		'device_type',
		'browser',
		'browser_version',
		'os',
		'os_version',
		'screen_width',
		'screen_height',
		'viewport_width',
		'viewport_height',
		'language',
		'timezone',
		'total_sessions',
		'total_pageviews',
		'total_events',
		'tenant_id',
	];

	/**
	 * Get the site that this visitor belongs to.
	 *
	 * @return BelongsTo<Site, Visitor>
	 *
	 * @since 1.0.0
	 */
	public function site(): BelongsTo
	{
		return $this->belongsTo( Site::class );
	}

	/**
	 * Get the sessions for this visitor.
	 *
	 * @return HasMany<Session, Visitor>
	 *
	 * @since 1.0.0
	 */
	public function sessions(): HasMany
	{
		return $this->hasMany( Session::class );
	}

	/**
	 * Get the page views for this visitor.
	 *
	 * @return HasMany<PageView, Visitor>
	 *
	 * @since 1.0.0
	 */
	public function pageViews(): HasMany
	{
		return $this->hasMany( PageView::class );
	}

	/**
	 * Get the events for this visitor.
	 *
	 * @return HasMany<Event, Visitor>
	 *
	 * @since 1.0.0
	 */
	public function events(): HasMany
	{
		return $this->hasMany( Event::class );
	}

	/**
	 * Get the consents for this visitor.
	 *
	 * @return HasMany<Consent, Visitor>
	 *
	 * @since 1.0.0
	 */
	public function consents(): HasMany
	{
		return $this->hasMany( Consent::class );
	}

	/**
	 * Get the user associated with this visitor.
	 *
	 * @return BelongsTo<Model, Visitor>
	 *
	 * @since 1.0.0
	 */
	public function user(): BelongsTo
	{
		/** @var class-string<Model>|null $userModel */
		$userModel = config( 'auth.providers.users.model' );

		if ( null === $userModel || ! class_exists( $userModel ) ) {
			// Fallback to a generic Model if user model is not configured
			return $this->belongsTo( Model::class, 'user_id' );
		}

		return $this->belongsTo( $userModel, 'user_id' );
	}

	/**
	 * Get the connection name for the model.
	 *
	 * @return string|null
	 */
	public function getConnectionName(): ?string
	{
		return config( 'artisanpack.analytics.local.connection' );
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
	 * Scope a query to filter by tenant.
	 *
	 * @param Builder    $query    The query builder.
	 * @param int|string $tenantId The tenant ID.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeForTenant( Builder $query, string|int $tenantId ): Builder
	{
		if ( config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
			return $query->where( 'tenant_id', $tenantId );
		}

		return $query;
	}

	/**
	 * Scope a query to filter visitors seen between dates.
	 *
	 * @param Builder $query The query builder.
	 * @param Carbon  $start The start date.
	 * @param Carbon  $end   The end date.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeSeenBetween( Builder $query, Carbon $start, Carbon $end ): Builder
	{
		return $query->whereBetween( 'last_seen_at', [ $start, $end ] );
	}

	/**
	 * Scope a query to filter new visitors between dates.
	 *
	 * @param Builder $query The query builder.
	 * @param Carbon  $start The start date.
	 * @param Carbon  $end   The end date.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeNewBetween( Builder $query, Carbon $start, Carbon $end ): Builder
	{
		return $query->whereBetween( 'first_seen_at', [ $start, $end ] );
	}

	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'first_seen_at'    => 'datetime',
			'last_seen_at'     => 'datetime',
			'total_sessions'   => 'integer',
			'total_pageviews'  => 'integer',
			'total_events'     => 'integer',
			'screen_width'     => 'integer',
			'screen_height'    => 'integer',
			'viewport_width'   => 'integer',
			'viewport_height'  => 'integer',
		];
	}
}
