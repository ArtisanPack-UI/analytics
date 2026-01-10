<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PageView model for analytics.
 *
 * Represents a single page view event.
 *
 * @property string      $id
 * @property string      $session_id
 * @property string      $visitor_id
 * @property string      $path
 * @property string|null $title
 * @property string|null $referrer
 * @property float|null  $load_time
 * @property array|null  $custom_data
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Models
 */
class PageView extends Model
{
	use HasFactory;
	use HasUuids;

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
		'session_id',
		'visitor_id',
		'path',
		'title',
		'referrer',
		'load_time',
		'custom_data',
		'tenant_id',
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
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'load_time'   => 'float',
			'custom_data' => 'array',
		];
	}
}
