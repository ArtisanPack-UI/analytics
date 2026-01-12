<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Services;

use ArtisanPackUI\Analytics\Models\Site;

/**
 * Service for managing per-site settings with defaults fallback.
 *
 * Provides a centralized way to get and set site-specific settings,
 * falling back to global defaults when site-specific values are not set.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Services
 */
class SiteSettingsService
{
	/**
	 * The tenant manager instance.
	 *
	 * @var TenantManager
	 */
	protected TenantManager $tenantManager;

	/**
	 * Cached default settings.
	 *
	 * @var array<string, mixed>|null
	 */
	protected ?array $defaultSettings = null;

	/**
	 * Create a new site settings service.
	 *
	 * @param TenantManager $tenantManager The tenant manager.
	 */
	public function __construct( TenantManager $tenantManager )
	{
		$this->tenantManager = $tenantManager;
	}

	/**
	 * Get a setting value for the current site.
	 *
	 * Falls back to defaults if no site-specific value is set.
	 *
	 * @param string     $key     The setting key (dot notation supported).
	 * @param mixed|null $default The default value if not found.
	 * @param Site|null  $site    Optional site to get setting from.
	 *
	 * @return mixed
	 *
	 * @since 1.0.0
	 */
	public function get( string $key, mixed $default = null, ?Site $site = null ): mixed
	{
		$site = $site ?? $this->tenantManager->current();

		if ( null !== $site ) {
			$siteValue = $site->getSetting( $key );

			if ( null !== $siteValue ) {
				return $siteValue;
			}
		}

		// Fall back to defaults
		$defaults = $this->getDefaults();

		return data_get( $defaults, $key, $default );
	}

	/**
	 * Set a setting value for the current site.
	 *
	 * @param string    $key   The setting key (dot notation supported).
	 * @param mixed     $value The value to set.
	 * @param Site|null $site  Optional site to set setting for.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function set( string $key, mixed $value, ?Site $site = null ): static
	{
		$site = $site ?? $this->tenantManager->current();

		if ( null === $site ) {
			return $this;
		}

		$site->setSetting( $key, $value );
		$site->save();

		return $this;
	}

	/**
	 * Get all settings for the current site merged with defaults.
	 *
	 * @param Site|null $site Optional site to get settings from.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function all( ?Site $site = null ): array
	{
		$defaults = $this->getDefaults();
		$site     = $site ?? $this->tenantManager->current();

		if ( null === $site ) {
			return $defaults;
		}

		$siteSettings = $site->settings ?? [];

		return array_replace_recursive( $defaults, $siteSettings );
	}

	/**
	 * Check if a feature is enabled for the current site.
	 *
	 * @param string    $feature The feature key.
	 * @param Site|null $site    Optional site to check.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function featureEnabled( string $feature, ?Site $site = null ): bool
	{
		return (bool) $this->get( 'features.' . $feature, false, $site );
	}

	/**
	 * Check if tracking is enabled for the current site.
	 *
	 * @param Site|null $site Optional site to check.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function isTrackingEnabled( ?Site $site = null ): bool
	{
		$site = $site ?? $this->tenantManager->current();

		if ( null === $site ) {
			return true;
		}

		return $site->tracking_enabled && (bool) $this->get( 'tracking.enabled', true, $site );
	}

	/**
	 * Check if the public dashboard is enabled for the current site.
	 *
	 * @param Site|null $site Optional site to check.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function isPublicDashboardEnabled( ?Site $site = null ): bool
	{
		$site = $site ?? $this->tenantManager->current();

		if ( null === $site ) {
			return false;
		}

		return $site->public_dashboard && (bool) $this->get( 'dashboard.public', false, $site );
	}

	/**
	 * Get the default settings from configuration.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function getDefaults(): array
	{
		if ( null === $this->defaultSettings ) {
			$this->defaultSettings = config( 'artisanpack.analytics.site_defaults', $this->getBuiltInDefaults() );
		}

		return $this->defaultSettings;
	}

	/**
	 * Reset a setting to its default value for the current site.
	 *
	 * @param string    $key  The setting key.
	 * @param Site|null $site Optional site to reset setting for.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function reset( string $key, ?Site $site = null ): static
	{
		$site = $site ?? $this->tenantManager->current();

		if ( null === $site ) {
			return $this;
		}

		$settings = $site->settings ?? [];

		data_forget( $settings, $key );

		$site->settings = $settings;
		$site->save();

		return $this;
	}

	/**
	 * Reset all settings to defaults for the current site.
	 *
	 * @param Site|null $site Optional site to reset settings for.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public function resetAll( ?Site $site = null ): static
	{
		$site = $site ?? $this->tenantManager->current();

		if ( null === $site ) {
			return $this;
		}

		$site->settings = [];
		$site->save();

		return $this;
	}

	/**
	 * Get the built-in default settings.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	protected function getBuiltInDefaults(): array
	{
		return [
			'tracking' => [
				'enabled'            => true,
				'respect_dnt'        => true,
				'anonymize_ip'       => true,
				'track_hash_changes' => false,
			],
			'dashboard' => [
				'public'              => false,
				'default_date_range'  => 30,
				'realtime_enabled'    => true,
				'show_conversions'    => true,
				'show_goals'          => true,
			],
			'privacy' => [
				'consent_required'        => false,
				'consent_cookie_lifetime' => 365,
				'excluded_paths'          => [],
				'excluded_ips'            => [],
			],
			'features' => [
				'events'      => true,
				'goals'       => true,
				'conversions' => true,
				'heatmaps'    => false,
				'recordings'  => false,
				'funnels'     => true,
			],
			'providers' => [
				'google' => [
					'enabled'        => false,
					'measurement_id' => null,
				],
				'plausible' => [
					'enabled' => false,
					'domain'  => null,
				],
			],
		];
	}
}
