<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

use ArtisanPackUI\Analytics\Traits\BelongsToSite;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Event model for analytics.
 *
 * Represents a custom event tracked by the application.
 *
 * @property int             $id
 * @property int|null        $site_id
 * @property string|null     $session_id
 * @property string|null     $visitor_id
 * @property int|null        $page_view_id
 * @property string          $name
 * @property string|null     $category
 * @property string|null     $action
 * @property string|null     $label
 * @property array|null      $properties
 * @property float|null      $value
 * @property string|null     $source_package
 * @property string|null     $path
 * @property int|string|null $tenant_id
 * @property Carbon          $created_at
 *
 * @method static Builder named(string $name)
 * @method static Builder inCategory(string $category)
 * @method static Builder forSite(int $siteId)
 * @method static Builder forTenant(string|int $tenantId)
 * @method static Builder fromPackage(string $package)
 * @method static Builder formSubmissions()
 * @method static Builder purchases()
 * @method static Builder bookings()
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Models
 */
class Event extends Model
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
	protected $table = 'analytics_events';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'site_id',
		'session_id',
		'visitor_id',
		'page_view_id',
		'name',
		'category',
		'action',
		'label',
		'properties',
		'value',
		'source_package',
		'path',
		'tenant_id',
		'created_at',
	];

	/**
	 * Get the session that owns this event.
	 *
	 * @return BelongsTo<Session, Event>
	 *
	 * @since 1.0.0
	 */
	public function session(): BelongsTo
	{
		return $this->belongsTo( Session::class );
	}

	/**
	 * Get the visitor that owns this event.
	 *
	 * @return BelongsTo<Visitor, Event>
	 *
	 * @since 1.0.0
	 */
	public function visitor(): BelongsTo
	{
		return $this->belongsTo( Visitor::class );
	}

	/**
	 * Get the page view that this event belongs to.
	 *
	 * @return BelongsTo<PageView, Event>
	 *
	 * @since 1.0.0
	 */
	public function pageView(): BelongsTo
	{
		return $this->belongsTo( PageView::class );
	}

	/**
	 * Get the conversion for this event.
	 *
	 * @return HasOne<Conversion, Event>
	 *
	 * @since 1.0.0
	 */
	public function conversion(): HasOne
	{
		return $this->hasOne( Conversion::class );
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
	 * Scope a query to filter by event name.
	 *
	 * @param Builder $query The query builder.
	 * @param string  $name  The event name.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeNamed( Builder $query, string $name ): Builder
	{
		return $query->where( 'name', $name );
	}

	/**
	 * Scope a query to filter by category.
	 *
	 * @param Builder $query    The query builder.
	 * @param string  $category The event category.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeInCategory( Builder $query, string $category ): Builder
	{
		return $query->where( 'category', $category );
	}

	/**
	 * Scope a query to filter by source package.
	 *
	 * @param Builder $query   The query builder.
	 * @param string  $package The source package name.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeFromPackage( Builder $query, string $package ): Builder
	{
		return $query->where( 'source_package', $package );
	}

	/**
	 * Scope a query to get form submission events.
	 *
	 * @param Builder $query The query builder.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeFormSubmissions( Builder $query ): Builder
	{
		return $query->where( 'name', 'form_submitted' );
	}

	/**
	 * Scope a query to get purchase events.
	 *
	 * @param Builder $query The query builder.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopePurchases( Builder $query ): Builder
	{
		return $query->where( 'name', 'purchase' );
	}

	/**
	 * Scope a query to get booking events.
	 *
	 * @param Builder $query The query builder.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeBookings( Builder $query ): Builder
	{
		return $query->where( 'name', 'booking_created' );
	}

	/**
	 * The "booted" method of the model.
	 *
	 * @return void
	 */
	protected static function booted(): void
	{
		static::creating( function ( Event $event ): void {
			$event->created_at = $event->created_at ?? now();
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
			'properties' => 'array',
			'value'      => 'decimal:4',
		];
	}
}
