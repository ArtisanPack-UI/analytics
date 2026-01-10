<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Contracts;

use ArtisanPackUI\Analytics\Data\EventData;
use ArtisanPackUI\Analytics\Data\PageViewData;

/**
 * Interface for analytics providers.
 *
 * All analytics providers (local, Google Analytics, Plausible, etc.)
 * must implement this interface to ensure consistent tracking capabilities.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Contracts
 */
interface AnalyticsProviderInterface
{
	/**
	 * Track a page view.
	 *
	 * @param PageViewData $data The page view data to track.
	 *
	 * @since 1.0.0
	 */
	public function trackPageView( PageViewData $data ): void;

	/**
	 * Track a custom event.
	 *
	 * @param EventData $data The event data to track.
	 *
	 * @since 1.0.0
	 */
	public function trackEvent( EventData $data ): void;

	/**
	 * Check if this provider is enabled.
	 *
	 * @return bool True if the provider is enabled and configured.
	 *
	 * @since 1.0.0
	 */
	public function isEnabled(): bool;

	/**
	 * Get the provider's unique name.
	 *
	 * @return string The provider name (e.g., 'local', 'google', 'plausible').
	 *
	 * @since 1.0.0
	 */
	public function getName(): string;

	/**
	 * Get the provider's configuration.
	 *
	 * @return array<string, mixed> The provider configuration array.
	 *
	 * @since 1.0.0
	 */
	public function getConfig(): array;
}
