<?php

declare( strict_types=1 );

namespace ArtisanPackUI\Analytics\Data;

use Illuminate\Http\Request;

/**
 * Data Transfer Object for page view tracking.
 *
 * Encapsulates all data related to a page view event.
 *
 * @since   1.0.0
 *
 * @package ArtisanPackUI\Analytics\Data
 */
readonly class PageViewData
{
	/**
	 * Create a new PageViewData instance.
	 *
	 * @param string                    $path            The page path.
	 * @param string|null               $title           The page title.
	 * @param string|null               $referrer        The referrer URL.
	 * @param string|null               $sessionId       The session identifier.
	 * @param string|null               $visitorId       The visitor identifier.
	 * @param string|null               $ipAddress       The visitor's IP address.
	 * @param string|null               $userAgent       The visitor's user agent.
	 * @param string|null               $country         The visitor's country code.
	 * @param string|null               $deviceType      The device type (desktop, mobile, tablet).
	 * @param string|null               $browser         The browser name.
	 * @param string|null               $browserVersion  The browser version.
	 * @param string|null               $os              The operating system.
	 * @param string|null               $osVersion       The OS version.
	 * @param string|null               $screenWidth     The screen width in pixels.
	 * @param string|null               $screenHeight    The screen height in pixels.
	 * @param string|null               $viewportWidth   The viewport width in pixels.
	 * @param string|null               $viewportHeight  The viewport height in pixels.
	 * @param string|null               $utmSource       The UTM source parameter.
	 * @param string|null               $utmMedium       The UTM medium parameter.
	 * @param string|null               $utmCampaign     The UTM campaign parameter.
	 * @param string|null               $utmTerm         The UTM term parameter.
	 * @param string|null               $utmContent      The UTM content parameter.
	 * @param float|null                $loadTime        The page load time in milliseconds.
	 * @param array<string, mixed>|null $customData      Custom data to store with the page view.
	 * @param int|string|null           $tenantId        The tenant identifier for multi-tenant apps.
	 * @param int|null                  $siteId          The site identifier.
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		public string $path,
		public ?string $title = null,
		public ?string $referrer = null,
		public ?string $sessionId = null,
		public ?string $visitorId = null,
		public ?string $ipAddress = null,
		public ?string $userAgent = null,
		public ?string $country = null,
		public ?string $deviceType = null,
		public ?string $browser = null,
		public ?string $browserVersion = null,
		public ?string $os = null,
		public ?string $osVersion = null,
		public ?string $screenWidth = null,
		public ?string $screenHeight = null,
		public ?string $viewportWidth = null,
		public ?string $viewportHeight = null,
		public ?string $utmSource = null,
		public ?string $utmMedium = null,
		public ?string $utmCampaign = null,
		public ?string $utmTerm = null,
		public ?string $utmContent = null,
		public ?float $loadTime = null,
		public ?array $customData = null,
		public string|int|null $tenantId = null,
		public ?int $siteId = null,
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
			path: $data['path'] ?? $request->path(),
			title: $data['title'] ?? null,
			referrer: $data['referrer'] ?? $request->header( 'Referer' ),
			sessionId: $data['session_id'] ?? null,
			visitorId: $data['visitor_id'] ?? null,
			ipAddress: $request->ip(),
			userAgent: $request->userAgent(),
			country: $data['country'] ?? null,
			deviceType: $data['device_type'] ?? null,
			browser: $data['browser'] ?? null,
			browserVersion: $data['browser_version'] ?? null,
			os: $data['os'] ?? null,
			osVersion: $data['os_version'] ?? null,
			screenWidth: $data['screen_width'] ?? null,
			screenHeight: $data['screen_height'] ?? null,
			viewportWidth: $data['viewport_width'] ?? null,
			viewportHeight: $data['viewport_height'] ?? null,
			utmSource: $data['utm_source'] ?? $request->query( 'utm_source' ),
			utmMedium: $data['utm_medium'] ?? $request->query( 'utm_medium' ),
			utmCampaign: $data['utm_campaign'] ?? $request->query( 'utm_campaign' ),
			utmTerm: $data['utm_term'] ?? $request->query( 'utm_term' ),
			utmContent: $data['utm_content'] ?? $request->query( 'utm_content' ),
			loadTime: isset( $data['load_time'] ) ? (float) $data['load_time'] : null,
			customData: $data['custom_data'] ?? null,
			tenantId: $data['tenant_id'] ?? null,
		);
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
			'path'            => $this->path,
			'title'           => $this->title,
			'referrer'        => $this->referrer,
			'session_id'      => $this->sessionId,
			'visitor_id'      => $this->visitorId,
			'ip_address'      => $this->ipAddress,
			'user_agent'      => $this->userAgent,
			'country'         => $this->country,
			'device_type'     => $this->deviceType,
			'browser'         => $this->browser,
			'browser_version' => $this->browserVersion,
			'os'              => $this->os,
			'os_version'      => $this->osVersion,
			'screen_width'    => $this->screenWidth,
			'screen_height'   => $this->screenHeight,
			'viewport_width'  => $this->viewportWidth,
			'viewport_height' => $this->viewportHeight,
			'utm_source'      => $this->utmSource,
			'utm_medium'      => $this->utmMedium,
			'utm_campaign'    => $this->utmCampaign,
			'utm_term'        => $this->utmTerm,
			'utm_content'     => $this->utmContent,
			'load_time'       => $this->loadTime,
			'custom_data'     => $this->customData,
			'tenant_id'       => $this->tenantId,
			'site_id'         => $this->siteId,
		], fn ( $value ) => null !== $value );
	}
}
