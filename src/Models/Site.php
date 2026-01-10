<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Site model for analytics.
 *
 * Represents a tracked site/domain for multi-tenant support.
 *
 * @property int         $id
 * @property string      $name
 * @property string|null $domain
 * @property string      $timezone
 * @property bool        $is_active
 * @property array|null  $settings
 * @property Carbon      $created_at
 * @property Carbon      $updated_at
 *
 * @method static Builder active()
 * @method static Builder forDomain(string $domain)
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Models
 */
class Site extends Model
{
	use HasFactory;

	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'analytics_sites';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array<int, string>
	 */
	protected $fillable = [
		'name',
		'domain',
		'timezone',
		'is_active',
		'settings',
	];

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
	 * @param string $key   The setting key.
	 * @param mixed  $value The setting value.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function setSetting( string $key, mixed $value ): void
	{
		$settings         = $this->settings ?? [];
		$settings[ $key ] = $value;
		$this->settings   = $settings;
	}

	/**
	 * Get the attributes that should be cast.
	 *
	 * @return array<string, string>
	 */
	protected function casts(): array
	{
		return [
			'is_active' => 'boolean',
			'settings'  => 'array',
		];
	}
}
