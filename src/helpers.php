<?php

/**
 * ArtisanPack UI Analytics helper functions.
 *
 * Global helper functions for analytics tracking and querying.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics
 */

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Analytics;
use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;
use ArtisanPackUI\Analytics\Models\Site;
use ArtisanPackUI\Analytics\Services\AnalyticsQuery;
use ArtisanPackUI\Analytics\Services\ConsentService;
use ArtisanPackUI\Analytics\Services\TenantManager;
use Illuminate\Support\Collection;

if ( ! function_exists( 'analytics' ) ) {
	/**
	 * Get the Analytics instance.
	 *
	 * @return Analytics
	 *
	 * @since 1.0.0
	 */
	function analytics(): Analytics
	{
		return app( 'analytics' );
	}
}

if ( ! function_exists( 'trackPageView' ) ) {
	/**
	 * Track a page view.
	 *
	 * @param string                    $path       The page path.
	 * @param string|null               $title      The page title.
	 * @param array<string, mixed>|null $customData Optional custom data.
	 *
	 * @since 1.0.0
	 */
	function trackPageView( string $path, ?string $title = null, ?array $customData = null ): void
	{
		$data = new PageViewData(
			path: $path,
			title: $title,
			customData: $customData,
		);

		analytics()->trackPageView( $data );
	}
}

if ( ! function_exists( 'trackEvent' ) ) {
	/**
	 * Track a custom event.
	 *
	 * @param string                    $name       The event name.
	 * @param array<string, mixed>|null $properties Optional event properties.
	 * @param float|null                $value      Optional numeric value.
	 * @param string|null               $category   Optional event category.
	 *
	 * @since 1.0.0
	 */
	function trackEvent( string $name, ?array $properties = null, ?float $value = null, ?string $category = null ): void
	{
		$data = new EventData(
			name: $name,
			properties: $properties,
			value: $value,
			category: $category,
		);

		analytics()->trackEvent( $data );
	}
}

if ( ! function_exists( 'analyticsEnabled' ) ) {
	/**
	 * Check if analytics tracking is enabled.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	function analyticsEnabled(): bool
	{
		return config( 'artisanpack.analytics.enabled', true );
	}
}

if ( ! function_exists( 'dateRangeLastDays' ) ) {
	/**
	 * Create a DateRange for the last N days.
	 *
	 * @param int $days Number of days.
	 *
	 * @return DateRange
	 *
	 * @since 1.0.0
	 */
	function dateRangeLastDays( int $days ): DateRange
	{
		return DateRange::lastDays( $days );
	}
}

if ( ! function_exists( 'dateRangeToday' ) ) {
	/**
	 * Create a DateRange for today.
	 *
	 * @return DateRange
	 *
	 * @since 1.0.0
	 */
	function dateRangeToday(): DateRange
	{
		return DateRange::today();
	}
}

if ( ! function_exists( 'dateRangeThisMonth' ) ) {
	/**
	 * Create a DateRange for this month.
	 *
	 * @return DateRange
	 *
	 * @since 1.0.0
	 */
	function dateRangeThisMonth(): DateRange
	{
		return DateRange::thisMonth();
	}
}

if ( ! function_exists( 'getAnalyticsConfig' ) ) {
	/**
	 * Get an analytics configuration value.
	 *
	 * @param string     $key     The configuration key.
	 * @param mixed|null $default The default value if not found.
	 *
	 * @return mixed
	 *
	 * @since 1.0.0
	 */
	function getAnalyticsConfig( string $key, mixed $default = null ): mixed
	{
		return config( 'artisanpack.analytics.' . $key, $default );
	}
}

if ( ! function_exists( 'country_flag' ) ) {
	/**
	 * Convert a two-letter country code to its flag emoji.
	 *
	 * Uses regional indicator symbols to create flag emojis.
	 * For example, 'US' becomes 🇺🇸, 'GB' becomes 🇬🇧.
	 *
	 * @param string $countryCode The ISO 3166-1 alpha-2 country code.
	 *
	 * @return string The flag emoji, or empty string if invalid code.
	 *
	 * @since 1.0.0
	 */
	function country_flag( string $countryCode ): string
	{
		$countryCode = strtoupper( trim( $countryCode ) );

		if ( 2 !== strlen( $countryCode ) || ! ctype_alpha( $countryCode ) ) {
			return '';
		}

		// Convert each letter to its regional indicator symbol
		// Regional indicator symbols start at U+1F1E6 (🇦)
		$firstChar  = mb_chr( ord( $countryCode[0] ) - ord( 'A' ) + 0x1F1E6 );
		$secondChar = mb_chr( ord( $countryCode[1] ) - ord( 'A' ) + 0x1F1E6 );

		return $firstChar . $secondChar;
	}
}

/*
|--------------------------------------------------------------------------
| Tracking Helpers
|--------------------------------------------------------------------------
*/

if ( ! function_exists( 'analyticsTrackForm' ) ) {
	/**
	 * Track a form submission event.
	 *
	 * @param string               $formId The form identifier.
	 * @param array<string, mixed> $data   Optional form data.
	 *
	 * @since 1.0.0
	 */
	function analyticsTrackForm( string $formId, array $data = [] ): void
	{
		trackEvent( 'form_submit', array_merge( [
			'form_id' => $formId,
		], $data ), null, 'forms' );
	}
}

if ( ! function_exists( 'analyticsTrackPurchase' ) ) {
	/**
	 * Track a purchase/ecommerce event.
	 *
	 * @param float                                     $value    The purchase value.
	 * @param string                                    $currency The currency code (e.g., 'USD').
	 * @param array<int, array<string, mixed>>          $items    Optional list of purchased items.
	 * @param array<string, mixed>                      $metadata Optional additional metadata.
	 *
	 * @since 1.0.0
	 */
	function analyticsTrackPurchase( float $value, string $currency = 'USD', array $items = [], array $metadata = [] ): void
	{
		trackEvent( 'purchase', array_merge( [
			'currency' => $currency,
			'items'    => $items,
		], $metadata ), $value, 'ecommerce' );
	}
}

if ( ! function_exists( 'analyticsTrackConversion' ) ) {
	/**
	 * Track a goal conversion manually.
	 *
	 * @param int        $goalId The goal ID to convert.
	 * @param float|null $value  Optional conversion value.
	 *
	 * @since 1.0.0
	 */
	function analyticsTrackConversion( int $goalId, ?float $value = null ): void
	{
		trackEvent( 'goal_conversion', [
			'goal_id' => $goalId,
		], $value, 'conversions' );
	}
}

/*
|--------------------------------------------------------------------------
| Query Helpers
|--------------------------------------------------------------------------
*/

if ( ! function_exists( 'analyticsStats' ) ) {
	/**
	 * Get comprehensive analytics statistics.
	 *
	 * @param DateRange|null       $range       The date range (defaults to last 7 days).
	 * @param bool                 $withCompare Whether to include comparison data.
	 * @param array<string, mixed> $filters     Optional filters.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	function analyticsStats( ?DateRange $range = null, bool $withCompare = true, array $filters = [] ): array
	{
		$range ??= DateRange::last7Days();

		return app( AnalyticsQuery::class )->getStats( $range, $withCompare, $filters );
	}
}

if ( ! function_exists( 'analyticsPageViews' ) ) {
	/**
	 * Get page views over time.
	 *
	 * @param DateRange|null       $range       The date range (defaults to last 7 days).
	 * @param string               $granularity Grouping granularity ('hour', 'day', 'week', 'month').
	 * @param array<string, mixed> $filters     Optional filters.
	 *
	 * @return Collection<int, array<string, mixed>>
	 *
	 * @since 1.0.0
	 */
	function analyticsPageViews( ?DateRange $range = null, string $granularity = 'day', array $filters = [] ): Collection
	{
		$range ??= DateRange::last7Days();

		return app( AnalyticsQuery::class )->getPageViews( $range, $granularity, $filters );
	}
}

if ( ! function_exists( 'analyticsVisitors' ) ) {
	/**
	 * Get the unique visitor count.
	 *
	 * @param DateRange|null       $range   The date range (defaults to last 7 days).
	 * @param array<string, mixed> $filters Optional filters.
	 *
	 * @return int
	 *
	 * @since 1.0.0
	 */
	function analyticsVisitors( ?DateRange $range = null, array $filters = [] ): int
	{
		$range ??= DateRange::last7Days();

		return app( AnalyticsQuery::class )->getVisitors( $range, $filters );
	}
}

if ( ! function_exists( 'analyticsTopPages' ) ) {
	/**
	 * Get the top pages by views.
	 *
	 * @param DateRange|null       $range   The date range (defaults to last 7 days).
	 * @param int                  $limit   Maximum number of results.
	 * @param array<string, mixed> $filters Optional filters.
	 *
	 * @return Collection<int, array<string, mixed>>
	 *
	 * @since 1.0.0
	 */
	function analyticsTopPages( ?DateRange $range = null, int $limit = 10, array $filters = [] ): Collection
	{
		$range ??= DateRange::last7Days();

		return app( AnalyticsQuery::class )->getTopPages( $range, $limit, $filters );
	}
}

if ( ! function_exists( 'analyticsTrafficSources' ) ) {
	/**
	 * Get traffic sources.
	 *
	 * @param DateRange|null       $range   The date range (defaults to last 7 days).
	 * @param int                  $limit   Maximum number of results.
	 * @param array<string, mixed> $filters Optional filters.
	 *
	 * @return Collection<int, array<string, mixed>>
	 *
	 * @since 1.0.0
	 */
	function analyticsTrafficSources( ?DateRange $range = null, int $limit = 10, array $filters = [] ): Collection
	{
		$range ??= DateRange::last7Days();

		return app( AnalyticsQuery::class )->getTrafficSources( $range, $limit, $filters );
	}
}

if ( ! function_exists( 'analyticsRealtime' ) ) {
	/**
	 * Get real-time visitor data.
	 *
	 * @param int $minutes The number of minutes to consider as "real-time".
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	function analyticsRealtime( int $minutes = 5 ): array
	{
		return app( AnalyticsQuery::class )->getRealtime( $minutes );
	}
}

/*
|--------------------------------------------------------------------------
| Site/Tenant Helpers
|--------------------------------------------------------------------------
*/

if ( ! function_exists( 'analyticsSite' ) ) {
	/**
	 * Get the current analytics site from the TenantManager.
	 *
	 * @return Site|null The current site, or null if not in multi-tenant mode.
	 *
	 * @since 1.0.0
	 */
	function analyticsSite(): ?Site
	{
		if ( ! config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
			return null;
		}

		return app( TenantManager::class )->currentSite();
	}
}

if ( ! function_exists( 'analyticsTenantId' ) ) {
	/**
	 * Get the current tenant ID.
	 *
	 * @return int|string|null The tenant ID, or null if not in multi-tenant mode.
	 *
	 * @since 1.0.0
	 */
	function analyticsTenantId(): int|string|null
	{
		if ( ! config( 'artisanpack.analytics.multi_tenant.enabled', false ) ) {
			return null;
		}

		return app( TenantManager::class )->currentTenantId();
	}
}

/*
|--------------------------------------------------------------------------
| Consent Helpers
|--------------------------------------------------------------------------
*/

if ( ! function_exists( 'analyticsHasConsent' ) ) {
	/**
	 * Check if a visitor has analytics consent.
	 *
	 * @param string|null $fingerprint The visitor's fingerprint (null for server-side checks).
	 * @param string      $category    The consent category.
	 *
	 * @return bool True if consent is granted.
	 *
	 * @since 1.0.0
	 */
	function analyticsHasConsent( ?string $fingerprint, string $category = 'analytics' ): bool
	{
		return app( ConsentService::class )->hasConsent( $fingerprint, $category );
	}
}

if ( ! function_exists( 'analyticsGrantConsent' ) ) {
	/**
	 * Grant analytics consent for specified categories.
	 *
	 * @param string        $fingerprint The visitor's fingerprint.
	 * @param array<string> $categories  The categories to grant consent for.
	 *
	 * @since 1.0.0
	 */
	function analyticsGrantConsent( string $fingerprint, array $categories ): void
	{
		app( ConsentService::class )->grantConsent( $fingerprint, $categories );
	}
}

if ( ! function_exists( 'analyticsRevokeConsent' ) ) {
	/**
	 * Revoke analytics consent for specified categories.
	 *
	 * @param string        $fingerprint The visitor's fingerprint.
	 * @param array<string> $categories  The categories to revoke consent for.
	 *
	 * @since 1.0.0
	 */
	function analyticsRevokeConsent( string $fingerprint, array $categories ): void
	{
		app( ConsentService::class )->revokeConsent( $fingerprint, $categories );
	}
}

if ( ! function_exists( 'analyticsConsentStatus' ) ) {
	/**
	 * Get the consent status for a visitor.
	 *
	 * @param string $fingerprint The visitor's fingerprint.
	 *
	 * @return array<string, array<string, mixed>> The consent status by category.
	 *
	 * @since 1.0.0
	 */
	function analyticsConsentStatus( string $fingerprint ): array
	{
		return app( ConsentService::class )->getConsentStatus( $fingerprint );
	}
}
