<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

use ArtisanPackUI\Analytics\Traits\BelongsToSite;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Consent model for analytics.
 *
 * Tracks user consent for analytics tracking.
 *
 * @property int             $id
 * @property int|null        $site_id
 * @property string|null     $visitor_id
 * @property string          $category
 * @property bool            $granted
 * @property string|null     $ip_address
 * @property string|null     $user_agent
 * @property Carbon|null     $granted_at
 * @property Carbon|null     $revoked_at
 * @property Carbon|null     $expires_at
 * @property int|string|null $tenant_id
 * @property Carbon          $created_at
 * @property Carbon          $updated_at
 *
 * @method static Builder active()
 * @method static Builder forCategory(string $category)
 * @method static Builder forSite(int $siteId)
 * @method static Builder forTenant(string|int $tenantId)
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Models
 */
class Consent extends Model
{
	use BelongsToSite;
	use HasFactory;

	/**
	 * Consent category constants.
	 */
	public const CATEGORY_ANALYTICS   = 'analytics';
	public const CATEGORY_MARKETING   = 'marketing';
	public const CATEGORY_FUNCTIONAL  = 'functional';
	public const CATEGORY_PREFERENCES = 'preferences';

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'analytics_consents';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'site_id',
		'visitor_id',
		'category',
		'granted',
		'ip_address',
		'user_agent',
		'granted_at',
		'revoked_at',
		'expires_at',
		'tenant_id',
	];

	/**
	 * Get the visitor that gave this consent.
	 *
	 * @return BelongsTo<Visitor, Consent>
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
	 * Scope a query to get active consents.
	 *
	 * @param Builder $query The query builder.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeActive( Builder $query ): Builder
	{
		return $query->where( 'granted', true )
			->whereNull( 'revoked_at' )
			->where( function ( Builder $q ): void {
				$q->whereNull( 'expires_at' )
					->orWhere( 'expires_at', '>', now() );
			} );
	}

	/**
	 * Scope a query to filter by category.
	 *
	 * @param Builder $query    The query builder.
	 * @param string  $category The consent category.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeForCategory( Builder $query, string $category ): Builder
	{
		return $query->where( 'category', $category );
	}

	/**
	 * Check if the consent is currently active.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function isActive(): bool
	{
		if ( ! $this->granted || null !== $this->revoked_at ) {
			return false;
		}

		return null === $this->expires_at || $this->expires_at->isFuture();
	}

	/**
	 * Revoke this consent.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function revoke(): void
	{
		$this->update( [
			'granted'    => false,
			'revoked_at' => now(),
		] );
	}

	/**
	 * Renew this consent with a new expiration date.
	 *
	 * @param Carbon|null $expiresAt The new expiration date.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function renew( ?Carbon $expiresAt = null ): void
	{
		$this->update( [
			'granted'    => true,
			'granted_at' => now(),
			'revoked_at' => null,
			'expires_at' => $expiresAt,
		] );
	}

	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'granted'    => 'boolean',
			'granted_at' => 'datetime',
			'revoked_at' => 'datetime',
			'expires_at' => 'datetime',
		];
	}
}
