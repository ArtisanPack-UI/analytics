<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Site model for analytics.
 *
 * Represents a tracked site/domain for multi-tenant support.
 *
 * @property int         $id
 * @property string      $uuid
 * @property string|null $tenant_type
 * @property string|null $tenant_id
 * @property string      $name
 * @property string|null $domain
 * @property string      $timezone
 * @property string      $currency
 * @property bool        $is_active
 * @property bool        $tracking_enabled
 * @property bool        $public_dashboard
 * @property array|null  $settings
 * @property string|null $api_key_hash
 * @property Carbon|null $api_key_last_used_at
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 * @property Carbon|null $deleted_at
 *
 * @method static Builder active()
 * @method static Builder forDomain(string $domain)
 * @method static Builder forTenant(Model $tenant)
 * @method static Builder trackingEnabled()
 * @method static Builder publicDashboard()
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Models
 */
class Site extends Model
{
	use HasFactory;
	use SoftDeletes;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'analytics_sites';

	/**
	 * The attributes that are mass assignable.
	 *
	 * Note: api_key_hash is intentionally excluded to prevent
	 * direct assignment. Use generateApiKey() instead.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'uuid',
		'tenant_type',
		'tenant_id',
		'name',
		'domain',
		'timezone',
		'currency',
		'is_active',
		'tracking_enabled',
		'public_dashboard',
		'settings',
	];

	/**
	 * Get the tenant that owns this site (polymorphic).
	 *
	 * @return MorphTo<Model, Site>
	 *
	 * @since 1.0.0
	 */
	public function tenant(): MorphTo
	{
		return $this->morphTo();
	}

	/**
	 * Get the visitors for this site.
	 *
	 * @return HasMany<Visitor, Site>
	 *
	 * @since 1.0.0
	 */
	public function visitors(): HasMany
	{
		return $this->hasMany( Visitor::class );
	}

	/**
	 * Get the sessions for this site.
	 *
	 * @return HasMany<Session, Site>
	 *
	 * @since 1.0.0
	 */
	public function sessions(): HasMany
	{
		return $this->hasMany( Session::class );
	}

	/**
	 * Get the page views for this site.
	 *
	 * @return HasMany<PageView, Site>
	 *
	 * @since 1.0.0
	 */
	public function pageViews(): HasMany
	{
		return $this->hasMany( PageView::class );
	}

	/**
	 * Get the events for this site.
	 *
	 * @return HasMany<Event, Site>
	 *
	 * @since 1.0.0
	 */
	public function events(): HasMany
	{
		return $this->hasMany( Event::class );
	}

	/**
	 * Get the goals for this site.
	 *
	 * @return HasMany<Goal, Site>
	 *
	 * @since 1.0.0
	 */
	public function goals(): HasMany
	{
		return $this->hasMany( Goal::class );
	}

	/**
	 * Get the conversions for this site.
	 *
	 * @return HasMany<Conversion, Site>
	 *
	 * @since 1.0.0
	 */
	public function conversions(): HasMany
	{
		return $this->hasMany( Conversion::class );
	}

	/**
	 * Get the consents for this site.
	 *
	 * @return HasMany<Consent, Site>
	 *
	 * @since 1.0.0
	 */
	public function consents(): HasMany
	{
		return $this->hasMany( Consent::class );
	}

	/**
	 * Get the aggregates for this site.
	 *
	 * @return HasMany<Aggregate, Site>
	 *
	 * @since 1.0.0
	 */
	public function aggregates(): HasMany
	{
		return $this->hasMany( Aggregate::class );
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
	 * Scope a query to get active sites.
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
	 * Scope a query to filter by domain.
	 *
	 * @param Builder $query  The query builder.
	 * @param string  $domain The domain to filter by.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeForDomain( Builder $query, string $domain ): Builder
	{
		return $query->where( 'domain', $domain );
	}

	/**
	 * Scope a query to filter by tenant.
	 *
	 * @param Builder $query  The query builder.
	 * @param Model   $tenant The tenant model.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeForTenant( Builder $query, Model $tenant ): Builder
	{
		return $query
			->where( 'tenant_type', $tenant->getMorphClass() )
			->where( 'tenant_id', $tenant->getKey() );
	}

	/**
	 * Scope a query to get sites with tracking enabled.
	 *
	 * @param Builder $query The query builder.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopeTrackingEnabled( Builder $query ): Builder
	{
		return $query->where( 'tracking_enabled', true );
	}

	/**
	 * Scope a query to get sites with public dashboards.
	 *
	 * @param Builder $query The query builder.
	 *
	 * @return Builder
	 *
	 * @since 1.0.0
	 */
	public function scopePublicDashboard( Builder $query ): Builder
	{
		return $query->where( 'public_dashboard', true );
	}

	/**
	 * Get a setting value from the site settings.
	 *
	 * @param string $key     The setting key.
	 * @param mixed  $default The default value.
	 *
	 * @return mixed
	 *
	 * @since 1.0.0
	 */
	public function getSetting( string $key, mixed $default = null ): mixed
	{
		return data_get( $this->settings, $key, $default );
	}

	/**
	 * Set a setting value in the site settings.
	 *
	 * Supports dot notation for nested keys (e.g., "tracking.enabled").
	 *
	 * @param string $key   The setting key (dot notation supported).
	 * @param mixed  $value The setting value.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function setSetting( string $key, mixed $value ): static
	{
		$settings = $this->settings ?? [];
		data_set( $settings, $key, $value );
		$this->settings = $settings;

		return $this;
	}

	/**
	 * Generate a new API key for this site.
	 *
	 * The plaintext API key is returned once and never stored.
	 * Only a SHA-256 hash is persisted in the database.
	 *
	 * @return string The generated API key (plaintext, only returned once).
	 *
	 * @since 1.0.0
	 */
	public function generateApiKey(): string
	{
		$apiKey = Str::random( 64 );

		$this->api_key_hash         = hash( 'sha256', $apiKey );
		$this->api_key_last_used_at = null;
		$this->save();

		return $apiKey;
	}

	/**
	 * Rotate the API key (generate a new one).
	 *
	 * @return string The new API key (plaintext, only returned once).
	 *
	 * @since 1.0.0
	 */
	public function rotateApiKey(): string
	{
		return $this->generateApiKey();
	}

	/**
	 * Revoke the API key.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function revokeApiKey(): void
	{
		$this->api_key_hash         = null;
		$this->api_key_last_used_at = null;
		$this->save();
	}

	/**
	 * Record API key usage.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function recordApiKeyUsage(): void
	{
		$this->api_key_last_used_at = now();
		$this->saveQuietly();
	}

	/**
	 * Check if the site has a valid API key.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function hasApiKey(): bool
	{
		return null !== $this->api_key_hash;
	}

	/**
	 * Find a site by its API key.
	 *
	 * Compares the hash of the provided plaintext key against stored hashes.
	 *
	 * @param string $apiKey The plaintext API key to search for.
	 *
	 * @return static|null
	 *
	 * @since 1.0.0
	 */
	public static function findByApiKey( string $apiKey ): ?static
	{
		$hash = hash( 'sha256', $apiKey );

		return static::where( 'api_key_hash', $hash )
			->where( 'is_active', true )
			->first();
	}

	/**
	 * Get the tracking script for this site.
	 *
	 * Uses JSON_HEX_* flags to prevent XSS when embedding in HTML.
	 *
	 * @param array<string, mixed> $options Optional script configuration.
	 *
	 * @return string
	 *
	 * @since 1.0.0
	 */
	public function getTrackingScript( array $options = [] ): string
	{
		$scriptPath = config( 'artisanpack.analytics.tracker.script_path', '/js/analytics.js' );
		$endpoint   = config( 'artisanpack.analytics.route_prefix', 'api/analytics' );

		$config = array_merge( [
			'siteId'              => $this->uuid,
			'endpoint'            => url( $endpoint ),
			'trackHashChanges'    => config( 'artisanpack.analytics.tracker.track_hash_changes', false ),
			'trackOutboundLinks'  => config( 'artisanpack.analytics.tracker.track_outbound_links', true ),
			'trackFileDownloads'  => config( 'artisanpack.analytics.tracker.track_file_downloads', true ),
			'respectDoNotTrack'   => config( 'artisanpack.analytics.privacy.respect_dnt', true ),
		], $options );

		// Use JSON_HEX_* flags to escape characters that could break out of script context
		$configJson = json_encode(
			$config,
			JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP,
		);

		// Escape script path for use in HTML attribute
		$escapedScriptPath = htmlspecialchars( $scriptPath, ENT_QUOTES, 'UTF-8' );

		return <<<HTML
<script>
window.apAnalyticsConfig = {$configJson};
</script>
<script src="{$escapedScriptPath}" async defer></script>
HTML;
	}

	/**
	 * Associate this site with a tenant.
	 *
	 * @param Model $tenant The tenant model.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function associateWithTenant( Model $tenant ): static
	{
		$this->tenant_type = $tenant->getMorphClass();
		$this->tenant_id   = (string) $tenant->getKey();

		return $this;
	}

	/**
	 * Boot the model.
	 *
	 * @return void
	 */
	protected static function boot(): void
	{
		parent::boot();

		static::creating( function ( Site $site ): void {
			if ( empty( $site->uuid ) ) {
				$site->uuid = (string) Str::uuid();
			}
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
			'is_active'            => 'boolean',
			'tracking_enabled'     => 'boolean',
			'public_dashboard'     => 'boolean',
			'settings'             => 'array',
			'api_key_last_used_at' => 'datetime',
		];
	}
}
