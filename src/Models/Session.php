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
 * Session model for analytics.
 *
 * Represents a user session with engagement metrics.
 *
 * @property string              $id
 * @property int|null            $site_id
 * @property string              $visitor_id
 * @property string              $session_id
 * @property Carbon              $started_at
 * @property Carbon|null         $ended_at
 * @property Carbon              $last_activity_at
 * @property int                 $duration
 * @property string              $entry_page
 * @property string|null         $exit_page
 * @property int                 $page_count
 * @property bool                $is_bounce
 * @property string|null         $referrer
 * @property string|null         $referrer_domain
 * @property string              $referrer_type
 * @property string|null         $utm_source
 * @property string|null         $utm_medium
 * @property string|null         $utm_campaign
 * @property string|null         $utm_term
 * @property string|null         $utm_content
 * @property string|null         $landing_page_title
 * @property int|string|null     $tenant_id
 * @property Carbon              $created_at
 * @property Carbon              $updated_at
 *
 * @method static Builder active(int $minutes = 30)
 * @method static Builder bounced()
 * @method static Builder notBounced()
 * @method static Builder forSite(int $siteId)
 * @method static Builder forTenant(string|int $tenantId)
 * @method static Builder fromSource(string $source)
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Models
 */
class Session extends Model
{
	use HasFactory;
	use HasUuids;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'analytics_sessions';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'site_id',
		'visitor_id',
		'session_id',
		'started_at',
		'ended_at',
		'last_activity_at',
		'duration',
		'entry_page',
		'exit_page',
		'page_count',
		'is_bounce',
		'referrer',
		'referrer_domain',
		'referrer_type',
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
		'landing_page_title',
		'tenant_id',
	];

	/**
	 * Get the site that this session belongs to.
	 *
	 * @return BelongsTo<Site, Session>
	 *
	 * @since 1.0.0
	 */
	public function site(): BelongsTo
	{
		return $this->belongsTo( Site::class );
	}

	/**
	 * Get the visitor that owns this session.
	 *
	 * @return BelongsTo<Visitor, Session>
	 *
	 * @since 1.0.0
	 */
	public function visitor(): BelongsTo
	{
		return $this->belongsTo( Visitor::class );
	}

	/**
	 * Get the page views for this session.
	 *
	 * @return HasMany<PageView, Session>
	 *
	 * @since 1.0.0
	 */
	public function pageViews(): HasMany
	{
		return $this->hasMany( PageView::class );
	}

	/**
	 * Get the events for this session.
	 *
	 * @return HasMany<Event, Session>
	 *
	 * @since 1.0.0
	 */
	public function events(): HasMany
	{
		return $this->hasMany( Event::class );
	}

	/**
	 * Get the conversions for this session.
	 *
	 * @return HasMany<Conversion, Session>
	 *
	 * @since 1.0.0
	 */
	public function conversions(): HasMany
	{
		return $this->hasMany( Conversion::class );
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
	 * Scope a query to get active sessions.
	 *
	 * @param Builder $query   The query builder.
	 * @param int     $minutes The inactivity threshold.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeActive( Builder $query, int $minutes = 30 ): Builder
	{
		return $query->where( 'last_activity_at', '>=', now()->subMinutes( $minutes ) )
			->whereNull( 'ended_at' );
	}

	/**
	 * Scope a query to get bounced sessions.
	 *
	 * @param Builder $query The query builder.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeBounced( Builder $query ): Builder
	{
		return $query->where( 'is_bounce', true );
	}

	/**
	 * Scope a query to get non-bounced sessions.
	 *
	 * @param Builder $query The query builder.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeNotBounced( Builder $query ): Builder
	{
		return $query->where( 'is_bounce', false );
	}

	/**
	 * Scope a query to filter by UTM source.
	 *
	 * @param Builder $query  The query builder.
	 * @param string  $source The UTM source.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeFromSource( Builder $query, string $source ): Builder
	{
		return $query->where( 'utm_source', $source );
	}

	/**
	 * Check if the session is currently active.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function isActive(): bool
	{
		if ( null === $this->last_activity_at ) {
			return false;
		}

		$sessionLifetime = config( 'artisanpack.analytics.local.session_lifetime', 30 );

		return null === $this->ended_at &&
			$this->last_activity_at->gte( now()->subMinutes( $sessionLifetime ) );
	}

	/**
	 * Calculate the session duration in seconds.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	public function calculateDuration(): int
	{
		if ( null === $this->started_at ) {
			return 0;
		}

		$end = $this->ended_at ?? $this->last_activity_at ?? $this->started_at;

		return $end->diffInSeconds( $this->started_at );
	}

	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'started_at'       => 'datetime',
			'ended_at'         => 'datetime',
			'last_activity_at' => 'datetime',
			'duration'         => 'integer',
			'page_count'       => 'integer',
			'is_bounce'        => 'boolean',
		];
	}
}
