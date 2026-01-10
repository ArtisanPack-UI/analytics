<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Goal model for analytics.
 *
 * Defines conversion goals and their matching conditions.
 *
 * @property int             $id
 * @property int|null        $site_id
 * @property string          $name
 * @property string|null     $description
 * @property string          $type
 * @property array           $conditions
 * @property string          $value_type
 * @property float|null      $fixed_value
 * @property string|null     $dynamic_value_path
 * @property bool            $is_active
 * @property array|null      $funnel_steps
 * @property int|string|null $tenant_id
 * @property Carbon          $created_at
 * @property Carbon          $updated_at
 *
 * @method static Builder active()
 * @method static Builder ofType(string $type)
 * @method static Builder forSite(int $siteId)
 * @method static Builder forTenant(string|int $tenantId)
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Models
 */
class Goal extends Model
{
	use HasFactory;

	/**
	 * Goal type constants.
	 */
	public const TYPE_EVENT             = 'event';
	public const TYPE_PAGEVIEW          = 'pageview';
	public const TYPE_DURATION          = 'duration';
	public const TYPE_PAGES_PER_SESSION = 'pages_per_session';

	/**
	 * Value type constants.
	 */
	public const VALUE_TYPE_NONE    = 'none';
	public const VALUE_TYPE_FIXED   = 'fixed';
	public const VALUE_TYPE_DYNAMIC = 'dynamic';

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'analytics_goals';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'site_id',
		'name',
		'description',
		'type',
		'conditions',
		'value_type',
		'fixed_value',
		'dynamic_value_path',
		'is_active',
		'funnel_steps',
		'tenant_id',
	];

	/**
	 * Get the site that this goal belongs to.
	 *
	 * @return BelongsTo<Site, Goal>
	 *
	 * @since 1.0.0
	 */
	public function site(): BelongsTo
	{
		return $this->belongsTo( Site::class );
	}

	/**
	 * Get the conversions for this goal.
	 *
	 * @return HasMany<Conversion, Goal>
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
	 * Scope a query to get active goals.
	 *
	 * @param Builder $query The query builder.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeActive( Builder $query ): Builder
	{
		return $query->where( 'is_active', true );
	}

	/**
	 * Scope a query to filter by goal type.
	 *
	 * @param Builder $query The query builder.
	 * @param string  $type  The goal type.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeOfType( Builder $query, string $type ): Builder
	{
		return $query->where( 'type', $type );
	}

	/**
	 * Check if the goal matches the given subject.
	 *
	 * @param Event|PageView|Session $subject The subject to match against.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function matches( Event|PageView|Session $subject ): bool
	{
		return match ( $this->type ) {
			self::TYPE_EVENT             => $subject instanceof Event && $this->matchesEvent( $subject ),
			self::TYPE_PAGEVIEW          => $subject instanceof PageView && $this->matchesPageView( $subject ),
			self::TYPE_DURATION          => $subject instanceof Session && $this->matchesDuration( $subject ),
			self::TYPE_PAGES_PER_SESSION => $subject instanceof Session && $this->matchesPagesPerSession( $subject ),
			default                      => false,
		};
	}

	/**
	 * Calculate the value for a conversion.
	 *
	 * @param Event|PageView|Session $subject The subject of the conversion.
	 *
	 * @return float|null
	 *
	 * @since 1.0.0
	 */
	public function calculateValue( Event|PageView|Session $subject ): ?float
	{
		return match ( $this->value_type ) {
			self::VALUE_TYPE_FIXED   => $this->fixed_value,
			self::VALUE_TYPE_DYNAMIC => $this->extractDynamicValue( $subject ),
			default                  => null,
		};
	}

	/**
	 * Check if the goal matches an event.
	 *
	 * @param Event $event The event to match.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function matchesEvent( Event $event ): bool
	{
		$conditions = $this->conditions;

		if ( isset( $conditions['event_name'] ) && $event->name !== $conditions['event_name'] ) {
			return false;
		}

		if ( isset( $conditions['event_category'] ) && $event->category !== $conditions['event_category'] ) {
			return false;
		}

		if ( isset( $conditions['property_matches'] ) && is_array( $conditions['property_matches'] ) ) {
			foreach ( $conditions['property_matches'] as $key => $value ) {
				if ( ( $event->properties[ $key ] ?? null ) !== $value ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Check if the goal matches a page view.
	 *
	 * @param PageView $pageView The page view to match.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function matchesPageView( PageView $pageView ): bool
	{
		$conditions = $this->conditions;

		if ( isset( $conditions['path_exact'] ) ) {
			return $pageView->path === $conditions['path_exact'];
		}

		if ( isset( $conditions['path_pattern'] ) ) {
			return Str::is( $conditions['path_pattern'], $pageView->path );
		}

		return false;
	}

	/**
	 * Check if the goal matches a session duration.
	 *
	 * @param Session $session The session to match.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function matchesDuration( Session $session ): bool
	{
		return $session->duration >= ( $this->conditions['min_seconds'] ?? 0 );
	}

	/**
	 * Check if the goal matches pages per session.
	 *
	 * @param Session $session The session to match.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	protected function matchesPagesPerSession( Session $session ): bool
	{
		return $session->page_count >= ( $this->conditions['min_pages'] ?? 0 );
	}

	/**
	 * Extract dynamic value from the subject.
	 *
	 * @param Event|PageView|Session $subject The subject.
	 *
	 * @return float|null
	 *
	 * @since 1.0.0
	 */
	protected function extractDynamicValue( Event|PageView|Session $subject ): ?float
	{
		if ( ! $this->dynamic_value_path || ! $subject instanceof Event ) {
			return null;
		}

		$value = data_get( $subject->properties, $this->dynamic_value_path );

		return is_numeric( $value ) ? (float) $value : null;
	}

	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'conditions'   => 'array',
			'funnel_steps' => 'array',
			'is_active'    => 'boolean',
			'fixed_value'  => 'decimal:4',
		];
	}
}
