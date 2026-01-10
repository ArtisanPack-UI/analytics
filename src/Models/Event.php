<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Event model for analytics.
 *
 * Represents a custom event tracked by the application.
 *
 * @property string      $id
 * @property string      $session_id
 * @property string      $visitor_id
 * @property string      $name
 * @property string|null $category
 * @property array|null  $properties
 * @property float|null  $value
 * @property string|null $path
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Models
 */
class Event extends Model
{
	use HasFactory;
	use HasUuids;

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
		'session_id',
		'visitor_id',
		'name',
		'category',
		'properties',
		'value',
		'path',
		'tenant_id',
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
	 * Scope a query to filter by event name.
	 *
	 * @param \Illuminate\Database\Eloquent\Builder $query The query builder.
	 * @param string                                $name  The event name.
	 *
	 * @return \Illuminate\Database\Eloquent\Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeNamed( $query, string $name )
	{
		return $query->where( 'name', $name );
	}

	/**
	 * Scope a query to filter by category.
	 *
	 * @param \Illuminate\Database\Eloquent\Builder $query    The query builder.
	 * @param string                                $category The event category.
	 *
	 * @return \Illuminate\Database\Eloquent\Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeInCategory( $query, string $category )
	{
		return $query->where( 'category', $category );
	}

	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'properties' => 'array',
			'value'      => 'float',
		];
	}
}
