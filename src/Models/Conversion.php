<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conversion model for analytics.
 *
 * Records goal completions with associated value and metadata.
 *
 * @property int             $id
 * @property int|null        $site_id
 * @property int             $goal_id
 * @property string|null     $session_id
 * @property string|null     $visitor_id
 * @property int|null        $event_id
 * @property int|null        $page_view_id
 * @property float|null      $value
 * @property array|null      $metadata
 * @property int|string|null $tenant_id
 * @property Carbon          $created_at
 *
 * @method static Builder forGoal(int $goalId)
 * @method static Builder withValue()
 * @method static Builder forSite(int $siteId)
 * @method static Builder forTenant(string|int $tenantId)
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Models
 */
class Conversion extends Model
{
	use HasFactory;

	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @var bool
	 */
	public $timestamps = false;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'analytics_conversions';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'site_id',
		'goal_id',
		'session_id',
		'visitor_id',
		'event_id',
		'page_view_id',
		'value',
		'metadata',
		'tenant_id',
		'created_at',
	];

	/**
	 * Get the site that this conversion belongs to.
	 *
	 * @return BelongsTo<Site, Conversion>
	 *
	 * @since 1.0.0
	 */
	public function site(): BelongsTo
	{
		return $this->belongsTo( Site::class );
	}

	/**
	 * Get the goal that this conversion completed.
	 *
	 * @return BelongsTo<Goal, Conversion>
	 *
	 * @since 1.0.0
	 */
	public function goal(): BelongsTo
	{
		return $this->belongsTo( Goal::class );
	}

	/**
	 * Get the session that this conversion occurred in.
	 *
	 * @return BelongsTo<Session, Conversion>
	 *
	 * @since 1.0.0
	 */
	public function session(): BelongsTo
	{
		return $this->belongsTo( Session::class );
	}

	/**
	 * Get the visitor that made this conversion.
	 *
	 * @return BelongsTo<Visitor, Conversion>
	 *
	 * @since 1.0.0
	 */
	public function visitor(): BelongsTo
	{
		return $this->belongsTo( Visitor::class );
	}

	/**
	 * Get the event that triggered this conversion.
	 *
	 * @return BelongsTo<Event, Conversion>
	 *
	 * @since 1.0.0
	 */
	public function event(): BelongsTo
	{
		return $this->belongsTo( Event::class );
	}

	/**
	 * Get the page view that triggered this conversion.
	 *
	 * @return BelongsTo<PageView, Conversion>
	 *
	 * @since 1.0.0
	 */
	public function pageView(): BelongsTo
	{
		return $this->belongsTo( PageView::class );
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
	 * Scope a query to filter by goal.
	 *
	 * @param Builder $query  The query builder.
	 * @param int     $goalId The goal ID.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeForGoal( Builder $query, int $goalId ): Builder
	{
		return $query->where( 'goal_id', $goalId );
	}

	/**
	 * Scope a query to get conversions with value.
	 *
	 * @param Builder $query The query builder.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeWithValue( Builder $query ): Builder
	{
		return $query->whereNotNull( 'value' )
			->where( 'value', '>', 0 );
	}

	/**
	 * The "booted" method of the model.
	 *
	 * @return void
	 */
	protected static function booted(): void
	{
		static::creating( function ( Conversion $conversion ): void {
			$conversion->created_at = $conversion->created_at ?? now();
		} );
	}

	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'created_at' => 'datetime',
			'value'      => 'decimal:4',
			'metadata'   => 'array',
		];
	}
}
