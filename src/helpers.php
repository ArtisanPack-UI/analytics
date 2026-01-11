<?php

declare( strict_types=1 );

use ArtisanPackUI\Analytics\Analytics;
use ArtisanPackUI\Analytics\Data\DateRange;
use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;

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
