<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Data;

/**
 * Data Transfer Object for device detection results.
 *
 * Encapsulates device, browser, and operating system information
 * parsed from a User-Agent string.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Data
 */
readonly class DeviceInfo
{
	/**
	 * Create a new DeviceInfo instance.
	 *
	 * @param string      $deviceType     The device type (desktop, mobile, tablet, other).
	 * @param string|null $browser        The browser name.
	 * @param string|null $browserVersion The browser version.
	 * @param string|null $os             The operating system name.
	 * @param string|null $osVersion      The operating system version.
	 * @param bool        $isBot          Whether the user agent is a bot/crawler.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		public string $deviceType = 'desktop',
		public ?string $browser = null,
		public ?string $browserVersion = null,
		public ?string $os = null,
		public ?string $osVersion = null,
		public bool $isBot = false,
	) {
	}

	/**
	 * Create an empty DeviceInfo for unknown user agents.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function unknown(): static
	{
		return new static(
			deviceType: 'other',
			isBot: false,
		);
	}

	/**
	 * Create a DeviceInfo for bot/crawler user agents.
	 *
	 * @param string|null $botName The name of the bot if identified.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function bot( ?string $botName = null ): static
	{
		return new static(
			deviceType: 'other',
			browser: $botName,
			isBot: true,
		);
	}

	/**
	 * Check if this is a mobile device.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function isMobile(): bool
	{
		return 'mobile' === $this->deviceType;
	}

	/**
	 * Check if this is a tablet device.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function isTablet(): bool
	{
		return 'tablet' === $this->deviceType;
	}

	/**
	 * Check if this is a desktop device.
	 *
	 * @return bool
	 *
	 * @since 1.0.0
	 */
	public function isDesktop(): bool
	{
		return 'desktop' === $this->deviceType;
	}

	/**
	 * Convert to array representation.
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function toArray(): array
	{
		return array_filter( [
			'device_type'     => $this->deviceType,
			'browser'         => $this->browser,
			'browser_version' => $this->browserVersion,
			'os'              => $this->os,
			'os_version'      => $this->osVersion,
			'is_bot'          => $this->isBot,
		], fn ( $value ) => null !== $value );
	}
}
