<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Support;

/**
 * Analytics Hooks Registry.
 *
 * Defines all filter and action hook names used by the analytics package.
 * These hooks integrate with the artisanpack-ui/hooks package to provide
 * extensibility points for tracking, queries, and events.
 *
 * @since 1.0.0
 */
final class AnalyticsHooks
{
	/*
	|--------------------------------------------------------------------------
	| Filter Hooks
	|--------------------------------------------------------------------------
	|
	| Filter hooks allow modification of data as it passes through the system.
	| Use addFilter() to register and applyFilters() to apply.
	|
	*/

	/**
	 * Filter tracking data before it is saved.
	 *
	 * @param array<string, mixed> $data The tracking data.
	 *
	 * @return array<string, mixed>
	 */
	public const BEFORE_TRACK = 'ap.analytics.before_track';

	/**
	 * Filter page view data before processing.
	 *
	 * @param array<string, mixed> $data The page view data.
	 *
	 * @return array<string, mixed>
	 */
	public const PAGE_VIEW_DATA = 'ap.analytics.page_view_data';

	/**
	 * Filter event data before processing.
	 *
	 * @param array<string, mixed> $data The event data.
	 *
	 * @return array<string, mixed>
	 */
	public const EVENT_DATA = 'ap.analytics.event_data';

	/**
	 * Filter the visitor fingerprint generation.
	 *
	 * @param string $fingerprint The generated fingerprint.
	 * @param array<string, mixed> $data The source data.
	 *
	 * @return string
	 */
	public const VISITOR_FINGERPRINT = 'ap.analytics.visitor_fingerprint';

	/**
	 * Filter query parameters before executing.
	 *
	 * @param array<string, mixed> $params The query parameters.
	 *
	 * @return array<string, mixed>
	 */
	public const BEFORE_QUERY = 'ap.analytics.before_query';

	/**
	 * Filter query results after fetching.
	 *
	 * @param mixed $results The query results.
	 * @param array<string, mixed> $params The query parameters.
	 *
	 * @return mixed
	 */
	public const AFTER_QUERY = 'ap.analytics.after_query';

	/**
	 * Filter dashboard statistics before display.
	 *
	 * @param array<string, mixed> $stats The statistics data.
	 *
	 * @return array<string, mixed>
	 */
	public const DASHBOARD_STATS = 'ap.analytics.dashboard_stats';

	/**
	 * Filter consent banner HTML output.
	 *
	 * @param string $html The banner HTML.
	 *
	 * @return string
	 */
	public const CONSENT_BANNER = 'ap.analytics.consent_banner';

	/**
	 * Filter before goal matching.
	 *
	 * @param array<string, mixed> $data The data to match.
	 * @param \ArtisanPackUI\Analytics\Models\Goal $goal The goal being matched.
	 *
	 * @return array<string, mixed>
	 */
	public const BEFORE_GOAL_MATCH = 'ap.analytics.before_goal_match';

	/**
	 * Filter conversion value before recording.
	 *
	 * @param float $value The conversion value.
	 * @param \ArtisanPackUI\Analytics\Models\Goal $goal The goal.
	 * @param mixed $trigger The conversion trigger.
	 *
	 * @return float
	 */
	public const CONVERSION_VALUE = 'ap.analytics.conversion_value';

	/**
	 * Filter provider registration.
	 *
	 * @param array<string, callable> $providers The registered providers.
	 *
	 * @return array<string, callable>
	 */
	public const REGISTER_PROVIDERS = 'ap.analytics.register_providers';

	/**
	 * Filter data before sending to provider.
	 *
	 * @param array<string, mixed> $data The data to send.
	 * @param string $providerName The provider name.
	 *
	 * @return array<string, mixed>
	 */
	public const PROVIDER_DATA = 'ap.analytics.provider_data';

	/**
	 * Filter site resolvers list.
	 *
	 * @param array<string, mixed> $resolvers The resolver configurations.
	 *
	 * @return array<string, mixed>
	 */
	public const SITE_RESOLVERS = 'ap.analytics.site_resolvers';

	/**
	 * Filter tracker script configuration.
	 *
	 * @param array<string, mixed> $config The tracker configuration.
	 *
	 * @return array<string, mixed>
	 */
	public const TRACKER_CONFIG = 'ap.analytics.tracker_config';

	/*
	|--------------------------------------------------------------------------
	| Action Hooks
	|--------------------------------------------------------------------------
	|
	| Action hooks allow executing code at specific points without modifying data.
	| Use addAction() to register and doAction() to trigger.
	|
	*/

	/**
	 * Fired after tracking data is saved.
	 *
	 * @param string $type The tracking type (pageview, event, session).
	 * @param mixed $model The created model.
	 */
	public const AFTER_TRACK = 'ap.analytics.after_track';

	/**
	 * Fired when consent status changes.
	 *
	 * @param string $fingerprint The visitor fingerprint.
	 * @param array<string> $categories The affected categories.
	 * @param bool $granted Whether consent was granted or revoked.
	 */
	public const CONSENT_CHANGED = 'ap.analytics.consent_changed';

	/**
	 * Fired when a goal is converted.
	 *
	 * @param \ArtisanPackUI\Analytics\Models\Goal $goal The converted goal.
	 * @param \ArtisanPackUI\Analytics\Models\Conversion $conversion The conversion record.
	 */
	public const GOAL_CONVERTED = 'ap.analytics.goal_converted';

	/**
	 * Fired when the current site context changes.
	 *
	 * @param \ArtisanPackUI\Analytics\Models\Site|null $site The new site.
	 * @param \ArtisanPackUI\Analytics\Models\Site|null $previousSite The previous site.
	 */
	public const SITE_CHANGED = 'ap.analytics.site_changed';

	/**
	 * Fired when a new site is created.
	 *
	 * @param \ArtisanPackUI\Analytics\Models\Site $site The created site.
	 */
	public const SITE_CREATED = 'ap.analytics.site_created';

	/**
	 * Fired when a site is deleted.
	 *
	 * @param \ArtisanPackUI\Analytics\Models\Site $site The deleted site.
	 */
	public const SITE_DELETED = 'ap.analytics.site_deleted';

	/**
	 * Fired when a goal is created.
	 *
	 * @param \ArtisanPackUI\Analytics\Models\Goal $goal The created goal.
	 */
	public const GOAL_CREATED = 'ap.analytics.goal_created';

	/**
	 * Fired when analytics cache is cleared.
	 */
	public const CACHE_CLEARED = 'ap.analytics.cache_cleared';

	/**
	 * Fired when analytics data is exported.
	 *
	 * @param string $format The export format.
	 * @param array<string, mixed> $options The export options.
	 */
	public const DATA_EXPORTED = 'ap.analytics.data_exported';

	/**
	 * Fired when analytics data is cleaned up.
	 *
	 * @param int $recordsDeleted The number of records deleted.
	 * @param \Carbon\Carbon $cutoffDate The cutoff date used.
	 */
	public const DATA_CLEANED = 'ap.analytics.data_cleaned';

	/*
	|--------------------------------------------------------------------------
	| Helper Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Check if the hooks package is available.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public static function isAvailable(): bool
	{
		return function_exists( 'addFilter' ) && function_exists( 'applyFilters' );
	}

	/**
	 * Apply a filter if hooks package is available.
	 *
	 * @param string $hook The hook name.
	 * @param mixed $value The value to filter.
	 * @param mixed ...$args Additional arguments.
	 *
	 * @return mixed The filtered value.
	 *
	 * @since 1.0.0
	 */
	public static function filter( string $hook, mixed $value, mixed ...$args ): mixed
	{
		if ( ! self::isAvailable() ) {
			return $value;
		}

		return applyFilters( $hook, $value, ...$args );
	}

	/**
	 * Execute an action if hooks package is available.
	 *
	 * @param string $hook The hook name.
	 * @param mixed ...$args Arguments to pass.
	 *
	 * @since 1.0.0
	 */
	public static function action( string $hook, mixed ...$args ): void
	{
		if ( ! self::isAvailable() ) {
			return;
		}

		doAction( $hook, ...$args );
	}

	/**
	 * Register a filter callback if hooks package is available.
	 *
	 * @param string $hook The hook name.
	 * @param callable $callback The callback.
	 * @param int $priority The priority (default 10).
	 *
	 * @since 1.0.0
	 */
	public static function addFilter( string $hook, callable $callback, int $priority = 10 ): void
	{
		if ( ! self::isAvailable() ) {
			return;
		}

		addFilter( $hook, $callback, $priority );
	}

	/**
	 * Register an action callback if hooks package is available.
	 *
	 * @param string $hook The hook name.
	 * @param callable $callback The callback.
	 * @param int $priority The priority (default 10).
	 *
	 * @since 1.0.0
	 */
	public static function addAction( string $hook, callable $callback, int $priority = 10 ): void
	{
		if ( ! self::isAvailable() ) {
			return;
		}

		addAction( $hook, $callback, $priority );
	}
}
