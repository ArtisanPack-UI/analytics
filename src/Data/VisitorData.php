<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Data;

use Illuminate\Http\Request;

/**
 * Data Transfer Object for visitor identification.
 *
 * Encapsulates all data used to identify and fingerprint visitors.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Data
 */
readonly class VisitorData
{
	/**
	 * Create a new VisitorData instance.
	 *
	 * @param string|null     $userAgent        The visitor's user agent.
	 * @param string|null     $ipAddress        The visitor's IP address.
	 * @param string|null     $screenResolution The screen resolution (e.g., '1920x1080').
	 * @param string|null     $timezone         The visitor's timezone.
	 * @param string|null     $language         The visitor's preferred language.
	 * @param string|null     $country          The visitor's country code.
	 * @param string|null     $deviceType       The device type (desktop, mobile, tablet).
	 * @param string|null     $browser          The browser name.
	 * @param string|null     $browserVersion   The browser version.
	 * @param string|null     $os               The operating system.
	 * @param string|null     $osVersion        The OS version.
	 * @param string|null     $existingId       An existing visitor ID from a cookie.
	 * @param int|string|null $tenantId         The tenant identifier for multi-tenant apps.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		public ?string $userAgent = null,
		public ?string $ipAddress = null,
		public ?string $screenResolution = null,
		public ?string $timezone = null,
		public ?string $language = null,
		public ?string $country = null,
		public ?string $deviceType = null,
		public ?string $browser = null,
		public ?string $browserVersion = null,
		public ?string $os = null,
		public ?string $osVersion = null,
		public ?string $existingId = null,
		public string|int|null $tenantId = null,
	) {
	}

	/**
	 * Create from an HTTP request.
	 *
	 * @param Request              $request The HTTP request.
	 * @param array<string, mixed> $data    Additional data from the request body.
	 *
	 * @return static
	 *
	 * @since 1.0.0
	 */
	public static function fromRequest( Request $request, array $data = [] ): static
	{
		return new static(
			userAgent: $request->userAgent(),
			ipAddress: $request->ip(),
			screenResolution: $data['screen_resolution'] ?? null,
			timezone: $data['timezone'] ?? null,
			language: $data['language'] ?? $request->getPreferredLanguage(),
			country: $data['country'] ?? null,
			deviceType: $data['device_type'] ?? null,
			browser: $data['browser'] ?? null,
			browserVersion: $data['browser_version'] ?? null,
			os: $data['os'] ?? null,
			osVersion: $data['os_version'] ?? null,
			existingId: $data['visitor_id'] ?? null,
			tenantId: $data['tenant_id'] ?? null,
		);
	}

	/**
	 * Get the data used for fingerprinting.
	 *
	 * Returns only the data that should be used to generate a
	 * privacy-preserving fingerprint (excludes IP address).
	 *
	 * @return array<string, mixed>
	 *
	 * @since 1.0.0
	 */
	public function getFingerprintData(): array
	{
		return array_filter( [
			'user_agent'        => $this->userAgent,
			'screen_resolution' => $this->screenResolution,
			'timezone'          => $this->timezone,
			'language'          => $this->language,
		], fn ( $value ) => null !== $value );
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
			'user_agent'        => $this->userAgent,
			'ip_address'        => $this->ipAddress,
			'screen_resolution' => $this->screenResolution,
			'timezone'          => $this->timezone,
			'language'          => $this->language,
			'country'           => $this->country,
			'device_type'       => $this->deviceType,
			'browser'           => $this->browser,
			'browser_version'   => $this->browserVersion,
			'os'                => $this->os,
			'os_version'        => $this->osVersion,
			'existing_id'       => $this->existingId,
			'tenant_id'         => $this->tenantId,
		], fn ( $value ) => null !== $value );
	}
}
