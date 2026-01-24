<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

use ArtisanPackUI\Analytics\Traits\BelongsToSite;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * PageView model for analytics.
 *
 * Represents a single page view event.
 *
 * @property int             $id
 * @property int|null        $site_id
 * @property string          $session_id
 * @property string          $visitor_id
 * @property string          $path
 * @property string|null     $title
 * @property string|null     $hash
 * @property string|null     $query_string
 * @property string|null     $referrer_path
 * @property int|null        $time_on_page
 * @property int|null        $engaged_time
 * @property int|null        $load_time
 * @property int|null        $dom_ready_time
 * @property int|null        $first_contentful_paint
 * @property int|null        $scroll_depth
 * @property array|null      $custom_data
 * @property int|string|null $tenant_id
 * @property Carbon          $created_at
 *
 * @method static Builder forPath(string $path)
 * @method static Builder forPaths(array $paths)
 * @method static Builder forSite(int $siteId)
 * @method static Builder forTenant(string|int $tenantId)
 * @method static Builder withEngagement()
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Models
 */
class PageView extends Model
{
	use BelongsToSite;
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
	protected $table = 'analytics_page_views';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'site_id',
		'session_id',
		'visitor_id',
		'path',
		'title',
		'hash',
		'query_string',
		'referrer_path',
		'time_on_page',
		'engaged_time',
		'load_time',
		'dom_ready_time',
		'first_contentful_paint',
		'scroll_depth',
		'custom_data',
		'tenant_id',
		'created_at',
	];

	/**
	 * Get the session that owns this page view.
	 *
	 * @return BelongsTo<Session, PageView>
	 *
	 * @since 1.0.0
	 */
	public function session(): BelongsTo
	{
		return $this->belongsTo( Session::class );
	}

	/**
	 * Get the visitor that owns this page view.
	 *
	 * @return BelongsTo<Visitor, PageView>
	 *
	 * @since 1.0.0
	 */
	public function visitor(): BelongsTo
	{
		return $this->belongsTo( Visitor::class );
	}

	/**
	 * Get the events for this page view.
	 *
	 * @return HasMany<Event, PageView>
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
	 * Scope a query to filter by path.
	 *
	 * @param Builder $query The query builder.
	 * @param string  $path  The path to filter by.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeForPath( Builder $query, string $path ): Builder
	{
		return $query->where( 'path', $path );
	}

	/**
	 * Scope a query to filter by multiple paths.
	 *
	 * @param Builder       $query The query builder.
	 * @param array<string> $paths The paths to filter by.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeForPaths( Builder $query, array $paths ): Builder
	{
		return $query->whereIn( 'path', $paths );
	}

	/**
	 * Scope a query to get page views with engagement.
	 *
	 * @param Builder $query The query builder.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeWithEngagement( Builder $query ): Builder
	{
		return $query->whereNotNull( 'engaged_time' )
			->where( 'engaged_time', '>', 0 );
	}

	/**
	 * The "booted" method of the model.
	 *
	 * @return void
	 */
	protected static function booted(): void
	{
		static::creating( function ( PageView $pageView ): void {
			$pageView->created_at = $pageView->created_at ?? now();
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
			'created_at'             => 'datetime',
			'time_on_page'           => 'integer',
			'engaged_time'           => 'integer',
			'load_time'              => 'integer',
			'dom_ready_time'         => 'integer',
			'first_contentful_paint' => 'integer',
			'scroll_depth'           => 'integer',
			'custom_data'            => 'array',
		];
	}
}
