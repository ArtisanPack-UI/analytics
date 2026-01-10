<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Visitor model for analytics.
 *
 * Represents a unique visitor identified by fingerprint.
 *
 * @property string      $id
 * @property string      $fingerprint
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $country
 * @property string|null $device_type
 * @property string|null $browser
 * @property string|null $browser_version
 * @property string|null $os
 * @property string|null $os_version
 * @property string|null $language
 * @property \Carbon\Carbon $first_seen_at
 * @property \Carbon\Carbon $last_seen_at
 * @property int|string|null $tenant_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
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
		'fingerprint',
		'ip_address',
		'user_agent',
		'country',
		'device_type',
		'browser',
		'browser_version',
		'os',
		'os_version',
		'language',
		'first_seen_at',
		'last_seen_at',
		'tenant_id',
	];

	/**
	 * Get the sessions for this visitor.
	 *
	 * @return HasMany<Session>
	 *
	 * @since 1.0.0
	 */
	public function sessions(): HasMany
	{
		return $this->hasMany( Session::class );
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
			'first_seen_at' => 'datetime',
			'last_seen_at'  => 'datetime',
		];
	}
}
