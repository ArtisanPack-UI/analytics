<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

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
 * @property string         $id
 * @property string         $session_id
 * @property string         $visitor_id
 * @property string|null    $entry_page
 * @property string|null    $exit_page
 * @property string|null    $referrer
 * @property string|null    $utm_source
 * @property string|null    $utm_medium
 * @property string|null    $utm_campaign
 * @property string|null    $utm_term
 * @property string|null    $utm_content
 * @property int            $page_count
 * @property int            $duration
 * @property bool           $is_bounce
 * @property \Carbon\Carbon $started_at
 * @property \Carbon\Carbon|null $ended_at
 * @property \Carbon\Carbon $last_activity_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
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
		'session_id',
		'visitor_id',
		'entry_page',
		'exit_page',
		'referrer',
		'utm_source',
		'utm_medium',
		'utm_campaign',
		'utm_term',
		'utm_content',
		'page_count',
		'duration',
		'is_bounce',
		'started_at',
		'ended_at',
		'last_activity_at',
		'tenant_id',
	];

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
	 * @return HasMany<PageView>
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
	 * @return HasMany<Event>
	 *
	 * @since 1.0.0
	 */
	public function events(): HasMany
	{
		return $this->hasMany( Event::class );
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
	 * Scope a query to filter by tenant.
	 *
	 * @param \Illuminate\Database\Eloquent\Builder $query    The query builder.
	 * @param int|string                            $tenantId The tenant ID.
	 *
	 * @return \Illuminate\Database\Eloquent\Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeForTenant( $query, string|int $tenantId )
	{
		if ( config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
			return $query->where( 'tenant_id', $tenantId );
		}

		return $query;
	}

	/**
	 * Scope a query to get active sessions.
	 *
	 * @param \Illuminate\Database\Eloquent\Builder $query   The query builder.
	 * @param int                                   $minutes The inactivity threshold.
	 *
	 * @return \Illuminate\Database\Eloquent\Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeActive( $query, int $minutes = 30 )
	{
		return $query->where( 'last_activity_at', '>=', now()->subMinutes( $minutes ) )
			->whereNull( 'ended_at' );
	}

	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'page_count'       => 'integer',
			'duration'         => 'integer',
			'is_bounce'        => 'boolean',
			'started_at'       => 'datetime',
			'ended_at'         => 'datetime',
			'last_activity_at' => 'datetime',
		];
	}
}
